# Referência: Secrets Protection

## .gitignore completo — Laravel (Prêmio Ceará Tem Design)

> Partir do `.gitignore` gerado pelo `laravel new` e **manter** as entradas que o
> projeto já tem (`.DS_Store`, `.idea/`, `anexos/` — edital fora do repositório).

```gitignore
# ─── Secrets e variáveis de ambiente ────────────────────────────────────────
.env
.env.local
.env.development
.env.production
.env.staging
.env.*.local
.env.test

# Chaves e certificados
*.key
*.pem
*.p12
*.pfx
*.crt
*.cer
secrets/
credentials/
private/

# ─── Laravel ────────────────────────────────────────────────────────────────
/vendor
/node_modules
/public/build
/public/hot
/public/storage
/storage/*.key
/storage/pail
.phpunit.result.cache
/.phpunit.cache
Homestead.json
Homestead.yaml
npm-debug.log
# storage/app, storage/logs e storage/framework já vêm com .gitignore próprio
# no esqueleto do Laravel — NÃO apagar esses arquivos internos.
# NÃO ignorar database/migrations — elas vão pro git.

# ─── Dados pessoais (LGPD, edital §8) ───────────────────────────────────────
*.sql
*.sql.gz
backups/

# ─── Edital (fora do repositório, decisão do responsável) ──────────────────
anexos/

# ─── Logs ───────────────────────────────────────────────────────────────────
*.log

# ─── Sistema operacional ────────────────────────────────────────────────────
.DS_Store
.DS_Store?
._*
.Spotlight-V100
.Trashes
Thumbs.db
ehthumbs.db
Desktop.ini

# ─── IDEs ──────────────────────────────────────────────────────────────────
.idea/
*.iml
.vscode/settings.json
.vscode/launch.json
# Manter .vscode/extensions.json — útil para colaboradores

# ─── Testes e cobertura ────────────────────────────────────────────────────
coverage/
.nyc_output/

# ─── Misc ──────────────────────────────────────────────────────────────────
*.swp
*.swo
.tmp/
tmp/
```

---

## Gitleaks — setup completo

### O que é
Gitleaks escaneia o repositório (incluindo histórico completo) em busca de secrets hardcoded. Funciona como:
1. **Pre-commit hook** — bloqueia o commit antes que o secret chegue ao git
2. **GitHub Actions** — escaneia em todo push/PR como segunda barreira

### Instalação (Ubuntu/Debian ou WSL — no macOS: `brew install gitleaks`)
```bash
# Baixar binary mais recente
wget https://github.com/gitleaks/gitleaks/releases/download/v8.18.1/gitleaks_8.18.1_linux_x64.tar.gz
tar -xzf gitleaks_8.18.1_linux_x64.tar.gz
sudo mv gitleaks /usr/local/bin/
gitleaks version
```

### Instalação (macOS)
```bash
brew install gitleaks
```

### Instalação do pre-commit framework
```bash
pip install pre-commit
# ou
brew install pre-commit
```

### Arquivo `.pre-commit-config.yaml` (raiz do projeto)
```yaml
repos:
  - repo: https://github.com/gitleaks/gitleaks
    rev: v8.18.1
    hooks:
      - id: gitleaks
        name: Detect hardcoded secrets
        exclude: '^(vendor/|node_modules/|public/build/|coverage/)'
```

### Ativar hooks no repositório
```bash
# Na raiz do projeto
pre-commit install

# Testar manualmente em todos os arquivos
pre-commit run --all-files

# Testar só o gitleaks
pre-commit run gitleaks --all-files
```

### Escanear histórico completo (auditoria pontual)
```bash
gitleaks git --source . --verbose --report-format json --report-path gitleaks-report.json
# Remover o report depois — não commitar
```

### Pular o hook em emergência (usar com cuidado)
```bash
SKIP=gitleaks git commit -m "commit urgente sem scan"
```

### Configuração personalizada `.gitleaks.toml` (opcional)
Use quando o scanner der falso positivo em algo do projeto:
```toml
[allowlist]
  description = "Allowlist de falsos positivos"
  paths = [
    '''^\.env\.example$''',
    '''^tests/fixtures/''',
  ]
  regexes = [
    '''EXAMPLE_TOKEN_HERE''',
    '''your-secret-here''',
  ]
```

---

## Protocolo de incidente — secret vazou

### Cenário: identificou um secret no histórico ou em código commitado

**Passo 0 — Não entre em pânico, mas aja rápido**

Mesmo em repositório privado, trate como comprometido. Crawlers e scrapers indexam repos rapidamente.

---

**Passo 1 — Revogar a credencial (AGORA, antes de qualquer outra coisa)**

| Provedor | Onde revogar |
|----------|-------------|
| Senha do MySQL (`DB_PASSWORD`) | cPanel → Bancos de dados MySQL → alterar senha do usuário → atualizar `.env` no servidor |
| Conta FTP / cPanel | cPanel → Contas FTP (ou senha do cPanel) → trocar senha |
| `APP_KEY` | `php artisan key:generate` e subir o novo `.env`. ⚠️ Invalida sessões e **tudo que foi criptografado** com a chave antiga (casts `encrypted`) — avaliar antes; usar `APP_PREVIOUS_KEYS` para rotação sem perda |
| Turnstile (P52) | Painel Cloudflare → Turnstile → rotacionar secret key |
| E-mail transacional (⚠️ P31) | Painel do provedor escolhido → gerar nova senha/chave |
| GitHub Token | Settings → Developer settings → Personal access tokens |

---

**Passo 2 — Remover do código atual**
```bash
# Mover para .env
# Remover do arquivo de código
git add .
git commit -m "chore: remove credencial exposta do código"
```

---

**Passo 3 — Limpar o histórico com git filter-repo**

```bash
# Instalar git-filter-repo (NÃO usar git filter-branch — obsoleto e perigoso)
pip install git-filter-repo

# Remover o arquivo inteiro do histórico (se .env foi commitado)
git filter-repo --path .env --invert-paths

# OU — substituir um valor específico no histórico
git filter-repo --replace-text <(echo 'sk-ant-XXXX==>REDACTED')

# Verificar que sumiu
git log --all --full-history -- .env
gitleaks git --source . --verbose
```

---

**Passo 4 — Force push (único caso onde é aceitável)**
```bash
# Usar --force-with-lease, não --force puro
git push origin main --force-with-lease

# Se tiver outras branches afetadas
git push origin --all --force-with-lease
```

---

**Passo 5 — Confirmar que está limpo**
```bash
gitleaks git --source . --verbose
# Deve retornar: "No leaks found"
```

---

**Passo 6 — Ativar proteções para não repetir**
1. Instalar gitleaks como pre-commit hook (ver seção acima)
2. Ativar Push Protection no GitHub (Settings → Code security → Enable push protection)
3. Adicionar ao vault Obsidian: `04-erros-e-solucoes.md` com o que aconteceu e como foi resolvido

---

## Verificações rápidas de diagnóstico

```bash
# .env está rastreado?
git ls-files | grep -E "\.env$|\.env\."

# Algum arquivo sensível no staging?
git diff --cached --name-only | grep -E "\.(env|key|pem|p12)$"

# Secret em arquivos rastreados?
git ls-files | xargs grep -lE "APP_KEY=base64|DB_PASSWORD=.+|MAIL_PASSWORD=.+|api_key" 2>/dev/null | grep -v ".example"

# Tamanho do histórico (commits com blobs grandes = suspeito)
git log --all --pretty=format:"%H %s" | head -20
```
