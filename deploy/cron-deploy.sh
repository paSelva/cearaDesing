#!/usr/bin/env bash
# Tarefas de deploy sem SSH, executadas por um cron TEMPORÁRIO do cPanel (HostGator).
# Roda uma única vez por versão do pacote (arquivo VERSION) e depois não faz mais nada.
#
# Uso no cron (a cada 15 minutos; remover depois da primeira execução bem-sucedida):
#   /bin/bash /home/USUARIO/votacao/deploy/cron-deploy.sh /CAMINHO/DO/PHP84
#
# O que faz, nesta ordem:
#   1. php artisan migrate --force
#   2. só na primeira instalação: semeia a edição 2026 (EditionSeeder)
#   3. se existir o arquivo ../admin-inicial.ini (fora da pasta do app): cria o admin e apaga o arquivo
# Nunca roda migrate:fresh, db:wipe nem seeders de demonstração.
set -euo pipefail

PHP_BIN="${1:?Informe o caminho do PHP 8.4 (veja docs/runbooks/deploy-hostgator.md)}"
APP="$(cd "$(dirname "$0")/.." && pwd)"
VERSION="$(cat "$APP/VERSION" 2>/dev/null || echo desconhecida)"
MARKER="$APP/storage/app/.deploy-$VERSION"
INSTALLED="$APP/storage/app/.instalado"
ADMIN_FILE="$(dirname "$APP")/admin-inicial.ini"
LOG="$APP/storage/logs/deploy-$VERSION.log"

exec 9>"$APP/storage/app/.deploy.lock"
flock -n 9 || exit 0          # outra execução em andamento
[ -f "$MARKER" ] && exit 0    # esta versão já foi aplicada

{
    echo "== $(date '+%Y-%m-%d %H:%M:%S') deploy da versão $VERSION"
    "$PHP_BIN" -v | head -1
    "$PHP_BIN" -r 'exit(version_compare(PHP_VERSION, "8.4.0", ">=") ? 0 : 1);' \
        || { echo "ERRO: o PHP informado não é 8.4"; exit 1; }

    cd "$APP"
    "$PHP_BIN" artisan migrate --force --no-interaction

    if [ ! -f "$INSTALLED" ]; then
        "$PHP_BIN" artisan db:seed --class=EditionSeeder --force --no-interaction
        touch "$INSTALLED"
    fi

    if [ -f "$ADMIN_FILE" ]; then
        "$PHP_BIN" artisan app:create-admin --from-file="$ADMIN_FILE" --no-interaction
    fi

    # Sem cache de configuração: mudanças no .env (e-mail, Turnstile) valem na hora.
    "$PHP_BIN" artisan optimize:clear --no-interaction

    touch "$MARKER"
    echo "== concluído"
} >>"$LOG" 2>&1
