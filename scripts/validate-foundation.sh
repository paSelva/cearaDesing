#!/usr/bin/env bash
set -euo pipefail

required_files=(
  "README.md"
  "docs/01-escopo-edital.md"
  "docs/02-stack.md"
  "docs/03-arquitetura.md"
  "docs/04-modelo-dados.md"
  "docs/05-roadmap.md"
  "docs/06-perguntas-em-aberto.md"
  "docs/07-actions.md"
  "docs/specs/SPEC-000-auditoria-execucao.md"
  "docs/specs/SPEC-000-viabilidade-servicos.md"
  "docs/specs/SPEC-000-matriz-aderencia.md"
  "docs/runbooks/ambiente-deploy-sem-ssh.md"
  "docs/qa/SPEC-000-handoff.md"
)

for path in "${required_files[@]}"; do
  test -f "$path" || { printf 'Arquivo obrigatório ausente: %s\n' "$path" >&2; exit 1; }
done

grep -Eq '^[[:space:]]+pull-requests:[[:space:]]+read$' .github/workflows/foundation.yml \
  || { printf 'Permissão mínima de leitura de PR ausente no workflow.\n' >&2; exit 1; }

required_ignores=(
  '.env'
  '/vendor/'
  '/node_modules/'
  '*.sql'
  'anexos/'
)

for pattern in "${required_ignores[@]}"; do
  grep -Fqx "$pattern" .gitignore || { printf 'Regra ausente no .gitignore: %s\n' "$pattern" >&2; exit 1; }
done

if git ls-files \
  | grep -Ev '(^|/)\.env\.example$' \
  | grep -Eq '(^|/)(\.env($|\.)|[^/]+\.(key|pem|p12|pfx|sql|sql\.gz)$)'; then
  printf 'Arquivo potencialmente sensível está rastreado.\n' >&2
  exit 1
fi

if grep -Eq '^VITE_.*(SECRET|PASSWORD|TOKEN|KEY)=' .env.example; then
  printf 'Variável potencialmente secreta destinada ao bundle foi encontrada.\n' >&2
  exit 1
fi

if test -f composer.json; then
  command -v composer >/dev/null || { printf 'composer.json existe, mas Composer não está disponível.\n' >&2; exit 1; }
  test -f composer.lock || { printf 'composer.lock ausente.\n' >&2; exit 1; }
  composer validate --strict --no-check-publish
fi

if test -f package.json; then
  test -f package-lock.json || { printf 'package-lock.json ausente.\n' >&2; exit 1; }
  npm pkg get name >/dev/null
fi

printf 'Fundação documental e higiene do repositório validadas.\n'
