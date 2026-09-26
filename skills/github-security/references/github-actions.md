# Referência: GitHub Actions Security (Laravel)

> Projeto: Prêmio Ceará Tem Design — Laravel 13 · PHP 8.4 · MySQL · Pest.
> Stack e hospedagem: `docs/02-stack.md`.

## Workflow de Security Scan + Testes

Arquivo: `.github/workflows/security.yml`

Escaneia secrets e dependências em todo push para `main` e em todo PR contra `main`.
Roda a suíte Pest. Bloqueia o merge se algo falhar.

```yaml
name: Security Scan

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

permissions:
  contents: read

jobs:
  secret-detection:
    name: Detect secrets
    runs-on: ubuntu-latest
    steps:
      - name: Checkout com histórico completo
        uses: actions/checkout@v4
        with:
          fetch-depth: 0  # necessário para escanear histórico inteiro

      - name: Run Gitleaks
        uses: gitleaks/gitleaks-action@v2
        env:
          GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}

  dependency-audit:
    name: Audit dependencies
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'   # docs/02-stack.md (T3)
          tools: composer

      - name: Composer install
        run: composer install --no-interaction --prefer-dist --no-progress

      - name: Composer audit
        run: composer audit --no-interaction

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '24'
          cache: 'npm'

      - name: npm audit (assets do Vite/Tailwind)
        run: |
          npm ci
          npm audit --audit-level=high

  tests:
    name: Pest
    runs-on: ubuntu-latest
    needs: [secret-detection]
    services:
      mysql:
        # Usar a MESMA versão do servidor HostGator (⚠️ P65: 5.7 ou 8.0)
        image: mysql:8.0
        env:
          MYSQL_DATABASE: testing
          MYSQL_ROOT_PASSWORD: root   # só existe dentro do runner efêmero
        ports: ['3306:3306']
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=5
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          extensions: mbstring, pdo_mysql, gd, exif
          coverage: none
      - run: composer install --no-interaction --prefer-dist --no-progress
      - run: cp .env.example .env && php artisan key:generate
      - name: Build de assets (views compiladas usam o manifest do Vite)
        run: npm ci && npm run build
      - name: Rodar testes
        env:
          DB_CONNECTION: mysql
          DB_HOST: 127.0.0.1
          DB_PORT: 3306
          DB_DATABASE: testing
          DB_USERNAME: root
          DB_PASSWORD: root
        run: php artisan test
```

---

## Boas práticas para GitHub Actions

### Least privilege — permissões mínimas

```yaml
permissions:
  contents: read          # ler código
  # security-events: write  # só se usar CodeQL
```

No nível do repositório (Settings → Actions → General):
- Default workflow permissions → **Read repository contents and packages permissions**

### Usar secrets — nunca hardcode em workflows

```yaml
# ❌ NUNCA
- name: Deploy
  env:
    DB_PASSWORD: "senha123"

# ✅ Correto — Settings → Secrets and variables → Actions
- name: Deploy
  env:
    FTP_PASSWORD: ${{ secrets.FTP_PASSWORD }}
```

> A senha do MySQL no job `tests` acima é aceitável: o banco só existe dentro do runner
> e é destruído no fim do job. Nunca use essa ideia para credenciais reais.

### Nunca expor secrets em logs

```yaml
# ❌ Isso vaza o secret nos logs
- run: echo "APP_KEY=${{ secrets.APP_KEY }}"

# ✅ Verificar existência sem expor valor
- run: |
    if [ -z "$FTP_PASSWORD" ]; then
      echo "❌ FTP_PASSWORD não configurado"
      exit 1
    fi
    echo "✅ FTP_PASSWORD configurado"
  env:
    FTP_PASSWORD: ${{ secrets.FTP_PASSWORD }}
```

### Fixar versões de actions com SHA (supply chain)

```yaml
# ❌ @v4 pode mudar
- uses: actions/checkout@v4

# ✅ Fixado por SHA — imutável (conferir o SHA atual no repositório da action)
- uses: actions/checkout@<SHA_COMPLETO> # v4.x.y
```

Principalmente para actions de terceiros que recebem secrets (ex.: a de FTP abaixo).

---

## Deploy (HostGator compartilhada, sem SSH)

O plano **não tem SSH** (P66) e o SSH **não será solicitado** (P67). O processo recomendado
em `docs/02-stack.md` é: build local → envio por FTP/Gerenciador de Arquivos do cPanel →
migrations por **cron temporário** no cPanel.

Automatizar o envio por FTP é **opcional** e só entra se o responsável decidir
(⚠️ não está definido nos docs — perguntar antes de gerar). Se for adotado:

```yaml
name: Deploy (FTP)

on:
  workflow_dispatch:   # manual — nunca automático em push

permissions:
  contents: read

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.4', tools: composer }
      - run: composer install --no-dev --optimize-autoloader --no-interaction
      - run: npm ci && npm run build
      - name: Enviar por FTPS
        uses: SamKirkland/FTP-Deploy-Action@v4.3.5   # fixar por SHA
        with:
          server: ${{ secrets.FTP_SERVER }}
          username: ${{ secrets.FTP_USERNAME }}
          password: ${{ secrets.FTP_PASSWORD }}
          protocol: ftps
          exclude: |
            **/.git*
            **/.git*/**
            **/node_modules/**
            .env
            tests/**
            storage/**
```

Regras:
- **Nunca** enviar `.env` pelo pipeline — ele é criado uma vez no servidor, fora do repositório.
- **Nunca** sobrescrever `storage/` (uploads dos participantes e logs ficam lá).
- Usar **FTPS**, nunca FTP puro (credencial trafega em texto claro).
- Migrations continuam pelo cron temporário (ver `security-audit/references/hostgator-shared.md`).

---

## Configurações do GitHub a ativar manualmente

**Settings → Code security and analysis:**

| Configuração | Ativar | Por quê |
|---|---|---|
| Dependency graph | ✅ | Base para Dependabot |
| Dependabot alerts | ✅ | Avisa de vulnerabilidades em dependências |
| Dependabot security updates | ✅ | Abre PRs automáticos com fix |
| Secret scanning | ✅ | Escaneia histórico por secrets |
| Push protection | ✅ | Bloqueia push com secrets conhecidos |

**Push Protection** é o mais importante — bloqueia na fonte, antes de chegar ao GitHub.

---

## Dependabot

Arquivo: `.github/dependabot.yml`

```yaml
version: 2
updates:
  - package-ecosystem: "composer"
    directory: "/"
    schedule:
      interval: "weekly"
      day: "monday"
    open-pull-requests-limit: 5
    ignore:
      # Major de Laravel/Filament/Livewire: testar manualmente
      - dependency-name: "*"
        update-types: ["version-update:semver-major"]

  - package-ecosystem: "npm"
    directory: "/"
    schedule:
      interval: "weekly"

  - package-ecosystem: "github-actions"
    directory: "/"
    schedule:
      interval: "monthly"
```

O sistema é só para a edição 2026 (P32): priorizar **patches de segurança** até o fim do
prêmio e evitar upgrades grandes perto de prazos do edital (§7).
