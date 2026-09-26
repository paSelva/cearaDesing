---
name: github-security
description: >
  Auditoria e configuração segura do repositório GitHub do Prêmio Ceará Tem Design
  (Laravel 13, MySQL, deploy por FTP na HostGator sem SSH). Verifica secrets expostos
  (.env, APP_KEY, senha do MySQL, FTP/cPanel), gera .gitignore Laravel, .env.example,
  gitleaks e GitHub Actions com composer audit + Pest, e mantém o estado em docs/ (e vault
  Obsidian, se houver). Use SEMPRE que o usuário mencionar: "setup github", "segurança do
  repo", "branch protection", "commit seguro", "gitleaks", "pre-commit hook", "gitignore",
  "secret vazou", "APP_KEY vazou", "auditoria git", "GitHub Actions", "CI seguro",
  "composer audit", "secrets no git", "env no git", "antes de subir pro github", "deploy
  por FTP", "credencial do cPanel", "como organizar meu git", ou variações. Acione também
  quando o usuário mostrar um .gitignore ou mencionar .env commitado.
---

# github-security — Boas Práticas GitHub (Prêmio Ceará Tem Design)

> Stack e hospedagem: `docs/02-stack.md` (Laravel 13, PHP 8.4, MySQL, HostGator
> compartilhada, **sem SSH** — P66). Repositório: `paSelva/cearaDesing`.
> Não inventar decisões: o que não estiver em `docs/` ou não for dito pelo usuário
> vira pergunta (ver `docs/06-perguntas-em-aberto.md`).

## Protocolo de início de sessão

Antes de qualquer ação, execute SEMPRE nessa ordem:

```
1. Ler README.md e docs/02-stack.md do repositório
2. Se existir vault Obsidian do projeto: ler 00-CONTEXTO.md e github-status.md
3. Verificar estrutura atual do repositório (ls -la, cat .gitignore)
4. Só então auditar e agir
```

Se houver vault e `github-status.md` não existir → criar ao final da sessão.

**Hierarquia de verdade:**
- `docs/` do repositório (e vault, se houver) → estado esperado, decisões tomadas
- Repositório real → estado atual do código

Divergência entre os dois → apontar ao usuário antes de agir.

---

## Fase 1 — Audit: o que verificar

### 1.1 Secrets e dados sensíveis

```bash
# Verificar se .env está sendo rastreado (CRÍTICO)
git ls-files | grep -E "\.env$|\.env\."

# Listar arquivos ignorados corretamente
cat .gitignore

# Escanear histórico completo por secrets (requer gitleaks instalado)
gitleaks git --source . --verbose

# Verificar se há credenciais hardcoded no código
grep -rnE "APP_KEY=base64|DB_PASSWORD=.+|MAIL_PASSWORD=.+|password|secret|api_key|TURNSTILE" \
  --include="*.php" --include="*.js" --include="*.env*" --include="*.yml" . 2>/dev/null \
  | grep -v vendor/ | grep -v node_modules/ | grep -v ".example"
```

**Problemas críticos (parar tudo):**
- [ ] `.env` aparece em `git ls-files` → já está no histórico → ver Fase 3 (Incidente)
- [ ] Credentials hardcoded em código commitado
- [ ] `APP_KEY`, `DB_PASSWORD`, `MAIL_PASSWORD`, chave secreta do Turnstile (P52) ou credenciais de FTP/cPanel no código
- [ ] Dump do MySQL (`*.sql`) ou `storage/` com uploads de participantes commitado (dados pessoais — §8, LGPD)
- [ ] Edital em `anexos/` commitado (decisão: fica fora do repositório — ver `.gitignore`)

**Problemas altos:**
- [ ] `.gitignore` ausente ou incompleto
- [ ] `.env.example` não existe
- [ ] Arquivos `*.key`, `*.pem`, `*.p12` no repositório

### 1.2 Estrutura do repositório

```bash
# Estado atual das branches
git branch -a
git log --oneline -10

# Verificar remote configurado
git remote -v

# Ver tamanho de commits recentes (commits gigantes = suspeito)
git log --stat --oneline -5
```

**Verificar:**
- [ ] Branch `main` existe e é a principal
- [ ] Commits seguem Conventional Commits (ou alguma convenção)
- [ ] Sem commits de "teste" ou com mensagens vazias (".", "fix", "wip" sem contexto)
- [ ] `.gitignore` cobre `vendor/`, `node_modules/`, `public/build/`, `public/hot`, `public/storage`, `.env`, `storage/*.key`, logs

### 1.3 Configuração GitHub (verificar manualmente no navegador)

- [ ] Branch `main` tem Branch Protection ativado
- [ ] Secret Scanning ativado (Settings → Code security)
- [ ] Push Protection ativado (bloqueia push com secrets conhecidos)
- [ ] Dependabot Alerts ativado (vulnerabilidades em dependências)
- [ ] Visibilidade do repo correta (público/privado conforme necessário)

---

## Fase 2 — Configurar: arquivos que a skill gera

### 2.1 .gitignore (gerar/atualizar)

> Leia `references/secrets-protection.md` → seção `.gitignore completo` para o template

Gera/atualiza o `.gitignore` partindo do padrão do Laravel, **mantendo** as entradas já existentes do projeto (`.idea/`, `anexos/`).
Sempre inclui:
```
.env
.env.local
.env.*.local
*.key
*.pem
*.p12
*.pfx
secrets/
```

### 2.2 .env.example (criar se não existir)

Analisa o `.env` real (se acessível localmente) e gera um `.env.example` com:
- Todas as chaves listadas
- Valores substituídos por placeholders descritivos
- Comentários explicando cada variável

Exemplo gerado:
```bash
APP_NAME="Prêmio Ceará Tem Design"
APP_ENV=local
# Gerar com: php artisan key:generate (NUNCA commitar o valor real)
APP_KEY=
APP_DEBUG=true            # produção: false (obrigatório)
# Fuso America/Fortaleza fica em config/app.php (docs/04-modelo-dados.md)
APP_URL=http://localhost  # produção: subdomínio (⚠️ nome: P62)

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cearadesign
DB_USERNAME=
DB_PASSWORD=

# Sem Redis garantido na HostGator (docs/02-stack.md)
SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

# ⚠️ Provedor de e-mail transacional: P31
MAIL_MAILER=log
MAIL_HOST=
MAIL_USERNAME=
MAIL_PASSWORD=

# Cloudflare Turnstile (P52) — nomes das chaves dependem do pacote escolhido
TURNSTILE_SITE_KEY=
TURNSTILE_SECRET_KEY=
```

### 2.3 Pre-commit hooks com Gitleaks

> Leia `references/secrets-protection.md` → seção `Gitleaks setup` para instruções completas

Gera `.pre-commit-config.yaml`:
```yaml
repos:
  - repo: https://github.com/gitleaks/gitleaks
    rev: v8.18.1
    hooks:
      - id: gitleaks
        exclude: '^(vendor/|node_modules/|public/build/)'
```

Comandos de instalação a passar ao usuário:
```bash
pip install pre-commit
pre-commit install
```

### 2.4 GitHub Actions — security scan

> Leia `references/github-actions.md` para o workflow completo

Gera `.github/workflows/security.yml` — gitleaks + `composer audit` + `npm audit` + Pest em todo push e PR.

### 2.5 PR Template (opcional mas recomendado)

Gera `.github/pull_request_template.md`:
```markdown
## O que esse PR faz?
<!-- 2-3 frases descrevendo a mudança -->

## Tipo de mudança
- [ ] feat: nova funcionalidade
- [ ] fix: correção de bug
- [ ] refactor: refatoração sem mudança de comportamento
- [ ] chore: atualização de deps/config

## Checklist
- [ ] Sem credenciais ou dados sensíveis no código
- [ ] .env.example atualizado (se adicionei variáveis)
- [ ] Migrations novas revisadas (rodam por cron na HostGator — sem SSH)
- [ ] `php artisan test` passando
- [ ] Regra do edital citada (§x / P#) quando a mudança implementa regra de negócio
```

---

## Fase 3 — Branch Strategy (para dev solo)

> Leia `references/branch-strategy.md` para detalhes completos

**Estratégia recomendada: GitHub Flow simplificado**

Para dev solo, GitFlow é overhead desnecessário. Use:

```
main          → sempre deployável, código em produção
feat/nome     → funcionalidades (ex: feat/area-do-jurado)
fix/nome      → correções (ex: fix/prazo-inscricao-fuso)
hotfix/nome   → emergências em produção
```

**Conventional Commits (obrigatório):**
```
feat: adiciona inscrição de projeto por categoria
fix: impede segunda inscrição na mesma categoria (§4)
refactor: extrai cálculo da nota final para Action
docs: atualiza perguntas em aberto (P65)
chore: atualiza filament para 5.x.y
test: adiciona cobertura para SubmitEvaluation
```

**Regra de ouro para dev solo:** `main` nunca recebe commit direto de feature.
Mesmo sem PR de revisão, use branches para isolar trabalho. Merge quando estável.

---

## Fase 4 — Incidente: secret vazou

> Leia `references/secrets-protection.md` → seção `Protocolo de incidente` para o passo a passo completo

**Regra #1: Assumir comprometido.** Qualquer secret que chegou ao histórico git — mesmo em repo privado — deve ser considerado comprometido. Agir imediatamente.

**Sequência mínima:**
1. **Revogar/trocar** a credencial — AGORA, antes de qualquer outra coisa (senha do MySQL e do FTP no cPanel, `APP_KEY`, chave do Turnstile, senha do e-mail)
2. **Remover** do código atual e commitar a remoção
3. **Limpar histórico** com `git filter-repo`
4. **Force push** nas branches afetadas
5. **Rodar** `gitleaks` para confirmar que o histórico está limpo
6. **Ativar** push protection no GitHub para evitar reincidência

---

## Encerramento de sessão — atualizar vault

Ao final de toda sessão que envolva GitHub:

```
1. Decisão nova sobre repositório/CI/deploy → registrar em docs/02-stack.md
   (ou como pergunta em docs/06-perguntas-em-aberto.md, se ainda não decidida)

2. Se houver vault Obsidian: atualizar github-status.md e o diário
   → branches ativas, última auditoria, configs aplicadas, pendências
```

---

## Checklist de saída

Antes de encerrar, confirmar com o usuário:

- [ ] `.gitignore` completo e correto
- [ ] `.env` fora do git (`git ls-files | grep .env` retorna vazio)
- [ ] `.env.example` existe e está no repositório
- [ ] Gitleaks instalado como pre-commit hook
- [ ] GitHub Actions de security scan configurado
- [ ] Branch `main` com proteção ativada no GitHub
- [ ] Secret Scanning + Push Protection ativados no GitHub
- [ ] `docs/` (e vault, se houver) atualizado com o estado atual

---

## Referências

- `references/secrets-protection.md` — gitleaks completo, pre-commit, protocolo de incidente, .gitignore template
- `references/branch-strategy.md` — GitHub Flow, naming, Conventional Commits detalhado
- `references/github-actions.md` — workflow CI security, boas práticas Actions
- `references/obsidian-github.md` — template `github-status.md`, protocolo de sessão
