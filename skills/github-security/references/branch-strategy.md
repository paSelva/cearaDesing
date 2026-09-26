# Referência: Branch Strategy — Dev Solo

## Estratégia: GitHub Flow Simplificado

Para dev solo, GitFlow (com develop, release, hotfix em paralelo) é overhead desnecessário.
A regra central é simples: **`main` é sempre deployável.**

### Estrutura de branches

```
main                   → produção — NUNCA commitar feature direto aqui
│
├── feat/nome-curto    → nova funcionalidade
├── fix/nome-curto     → correção de bug
└── hotfix/nome-curto  → emergência em produção (raramente)
```

### Ciclo de trabalho

```bash
# 1. Criar branch a partir de main atualizado
git checkout main
git pull origin main
git checkout -b feat/area-do-jurado

# 2. Trabalhar normalmente com commits atômicos
git add .
git commit -m "feat(jurado): lista de projetos atribuídos"
git commit -m "feat: adiciona validação de conflito de horários"
git commit -m "test: cobertura para conflito de horários"

# 3. Quando pronto — rebase antes de mergear (histórico limpo)
git fetch origin
git rebase origin/main

# 4. Merge na main (--no-ff mantém o contexto da feature no log)
git checkout main
git merge feat/area-do-jurado --no-ff -m "feat(jurado): área do jurado completa"
git push origin main

# 5. Limpar branch local e remota
git branch -d feat/area-do-jurado
git push origin --delete feat/area-do-jurado
```

### Quando fazer hotfix

```bash
# Problema crítico em produção
git checkout main
git pull origin main
git checkout -b hotfix/login-quebrado

# Corrigir
git commit -m "fix: corrige validação de token expirado no login"

# Mergear direto na main
git checkout main
git merge hotfix/login-quebrado --no-ff
git push origin main
git branch -d hotfix/login-quebrado
```

---

## Conventional Commits — referência completa

### Formato

```
<tipo>(<escopo opcional>): <descrição curta em minúsculas>

[corpo opcional — explica o "por quê", não o "o quê"]

[rodapé opcional — BREAKING CHANGE, closes #issue]
```

### Tipos

| Tipo | Quando usar |
|------|-------------|
| `feat` | Nova funcionalidade para o usuário |
| `fix` | Correção de bug |
| `refactor` | Refatoração sem mudança de comportamento externo |
| `docs` | Mudanças em documentação apenas |
| `chore` | Atualização de deps, configs, build — sem impacto no usuário |
| `test` | Adicionar ou corrigir testes |
| `perf` | Mudança que melhora performance |
| `style` | Formatação, espaço, ponto-e-vírgula — sem mudança de lógica |
| `ci` | Mudanças em arquivos de CI/CD |
| `revert` | Reverter commit anterior |

### Exemplos (Prêmio Ceará Tem Design)

```bash
# Features
feat(inscricao): permite 1 projeto por categoria por participante (§4)
feat(jurado): trava notas após envio (P19)
feat(admin): botão Publicar resultado (P69)

# Fixes
fix: prazo de inscrição ignorava fuso America/Fortaleza (P77)
fix(voto): eleitor conseguia votar fora do período (P38)
fix(migration): faltava índice único em entries (edition_id, category_id, user_id)

# Refactors
refactor: extrai cálculo da nota final para Action
refactor(jurado): move regra de atribuição para AssignJudgesToEntry

# Infra / chore
chore: atualiza dependências críticas (filament 5.x → 5.y)
chore: adiciona gitleaks como pre-commit hook
ci: adiciona workflow de security scan no GitHub Actions

# Docs
docs: atualiza README com variáveis de ambiente necessárias
docs: adiciona .env.example com todos os campos

# Breaking changes
feat!: renomeia coluna de status das inscrições (exige migration de dados)
# ou no rodapé:
feat(inscricao): separa links do projeto em tabela própria

BREAKING CHANGE: coluna entries.links removida; dados migrados para entry_links
```

### Boas práticas de mensagem

```bash
# ❌ Mensagens inúteis
git commit -m "fix"
git commit -m "wip"
git commit -m "ajustes"
git commit -m "."
git commit -m "update"

# ✅ Mensagens que contam uma história
git commit -m "fix(prazo): encerramento das inscrições comparava datas em UTC"
git commit -m "feat(jurado): adiciona tela de avaliação com as 4 notas (§5)"
git commit -m "chore: remove console.log esquecido em userService"
```

**Regra de ouro:** quem ler o log em 6 meses (provavelmente você mesmo) deve entender o que foi feito sem abrir o diff.

---

## Branch Protection Rules (configurar no GitHub)

Para repositório solo, a proteção serve para evitar acidentes e manter histórico limpo.

**Settings → Branches → Add branch protection rule → `main`**

### Configurações recomendadas para dev solo

```
Branch name pattern: main

☑ Require a pull request before merging
  - Required approvals: 0 (ou desativar reviews se preferir)
  
☑ Require status checks to pass before merging
  - Adicionar: "Secret Detection" (do workflow de CI)
  
☑ Require branches to be up to date before merging

☑ Do not allow bypassing the above settings
  → Isso evita push acidental direto na main (mesmo sendo admin)

☐ Require signed commits (opcional — recomendado em projetos críticos)
```

**Por que proteger main como dev solo?**
Sem proteção, um `git push origin main` por acidente derruba o guarda-chuva inteiro. A proteção força o ritual de branch → merge, que também gera um histórico mais legível.

### Verificar proteção via GitHub CLI (opcional)

```bash
# Instalar gh cli
gh auth login

# Verificar proteções atuais
gh api repos/{owner}/{repo}/branches/main/protection

# Ver branch rules
gh api repos/{owner}/{repo}/branches
```

---

## CODEOWNERS (quando aplicável)

Para dev solo puro, CODEOWNERS é opcional. Criar quando:
- Vai onboarding um colaborador temporário (freelancer, estagiário)
- O cliente tem acesso ao repositório e pode abrir PRs

```
# .github/CODEOWNERS

# Arquivos críticos — requer review do dono do repo
*.env.example          @seu-usuario-github
.github/workflows/     @seu-usuario-github
app/Policies/          @seu-usuario-github
database/migrations/   @seu-usuario-github
config/premio.php      @seu-usuario-github
```

---

## Aliases git úteis para dev solo

Adicionar ao `~/.gitconfig`:

```ini
[alias]
  # Status resumido
  s = status -s

  # Log visual com grafo
  lg = log --oneline --graph --decorate --all

  # Último commit
  last = log -1 HEAD --stat

  # Desfazer último commit (mantém alterações)
  undo = reset HEAD~1 --mixed

  # Branch com data de último commit
  recent = branch --sort=-committerdate --format='%(refname:short) %(committerdate:relative)'

  # Verificar o que vai para staging
  diff-staged = diff --cached

  # Limpar branches locais já mergeadas
  cleanup = "!git branch --merged main | grep -v main | xargs git branch -d"
```

Usar:
```bash
git lg          # visualizar histórico
git recent      # ver branches mais recentes
git cleanup     # limpar branches já mergeadas
```
