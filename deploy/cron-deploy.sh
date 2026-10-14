#!/usr/bin/env bash
# Instalador/atualizador temporário para Cron Jobs do cPanel.
# Uso: /bin/bash /.../releases/SHA/deploy/cron-deploy.sh /CAMINHO/PHP /.../shared
# É concluído uma única vez por VERSION. Remova o Cron depois de confirmar o log.
set -euo pipefail
umask 077

PHP_BIN="${1:-}"
SHARED_DIR="${2:-}"
APP="$(cd "$(dirname "$0")/.." && pwd)"
VERSION_FILE="$APP/VERSION"

fail() { printf 'ERRO: %s\n' "$1" >&2; exit 1; }

[[ "$PHP_BIN" = /* ]] || fail "informe o caminho absoluto do PHP CLI"
[ -x "$PHP_BIN" ] || fail "PHP CLI ausente ou não executável: $PHP_BIN"
[[ "$SHARED_DIR" = /* ]] || fail "informe o caminho absoluto do diretório shared"
[ -f "$VERSION_FILE" ] || fail "arquivo VERSION ausente"
VERSION="$(tr -d '[:space:]' < "$VERSION_FILE")"
[[ "$VERSION" =~ ^[0-9a-f]{40}$ ]] || fail "VERSION deve conter um SHA completo"

STATE_DIR="$SHARED_DIR/storage/app"
LOG_DIR="$SHARED_DIR/storage/logs"
mkdir -p "$STATE_DIR" "$LOG_DIR"
[ -w "$STATE_DIR" ] || fail "storage/app não é gravável"
[ -w "$LOG_DIR" ] || fail "storage/logs não é gravável"

LOG="$LOG_DIR/deploy-$VERSION.log"
MARKER="$STATE_DIR/.deploy-$VERSION.complete"
LOCK_DIR="$STATE_DIR/.deploy.lock"
touch "$LOG"
chmod 600 "$LOG" 2>/dev/null || true

if [ -f "$MARKER" ]; then
    printf 'Release %s já concluída. Nenhuma ação executada.\n' "$VERSION" >> "$LOG"
    exit 0
fi

if ! mkdir "$LOCK_DIR" 2>/dev/null; then
    printf 'Outra execução está ativa (ou existe trava obsoleta): %s\n' "$LOCK_DIR" >> "$LOG"
    exit 75
fi
release_lock() { rmdir "$LOCK_DIR" 2>/dev/null || true; }
trap release_lock EXIT
trap 'exit 130' HUP INT TERM

{
    printf '== %s início do deploy %s\n' "$(date '+%Y-%m-%d %H:%M:%S %z')" "$VERSION"

    # .env e storage são persistentes e ficam fora da release. O ZIP contém apenas um
    # esqueleto vazio de storage, movido sem apagar dados. A trava protege esta preparação.
    [ -f "$SHARED_DIR/.env" ] || fail "crie $SHARED_DIR/.env antes do Cron"
    [ ! -e "$APP/.env" ] || [ -L "$APP/.env" ] || fail "a release não pode conter .env regular"
    if [ ! -L "$APP/.env" ]; then
        ln -s "$SHARED_DIR/.env" "$APP/.env"
    fi
    [ "$(realpath "$APP/.env")" = "$(realpath "$SHARED_DIR/.env")" ] || fail "link .env aponta para local inesperado"

    if [ -d "$APP/storage" ] && [ ! -L "$APP/storage" ]; then
        find "$APP/storage" -type f ! -name '.gitignore' -print -quit | grep -q . \
            && fail "storage da release contém dados; migração automática recusada"
        cp -R "$APP/storage/." "$SHARED_DIR/storage/"
        mv "$APP/storage" "$APP/storage.release-skeleton"
        ln -s "$SHARED_DIR/storage" "$APP/storage"
    fi
    [ -L "$APP/storage" ] || fail "storage da release deve ser link para shared/storage"
    [ "$(realpath "$APP/storage")" = "$(realpath "$SHARED_DIR/storage")" ] || fail "link storage aponta para local inesperado"

    # Preflight de plataforma e arquivos: nada no banco foi alterado até este ponto.
    "$PHP_BIN" -r 'exit(version_compare(PHP_VERSION, "8.4.1", ">=") ? 0 : 1);' \
        || fail "PHP CLI 8.4.1 ou superior é obrigatório"
    printf 'PHP CLI: '
    "$PHP_BIN" -r 'echo PHP_VERSION, PHP_EOL;'

    REQUIRED_EXTENSIONS='ctype date dom exif fileinfo filter hash iconv intl json libxml mbstring openssl pcre pdo pdo_mysql session tokenizer xmlreader zip zlib'
    "$PHP_BIN" -r '
        $missing = [];
        foreach (explode(" ", $argv[1]) as $extension) {
            if (! extension_loaded($extension)) {
                $missing[] = $extension;
            }
        }
        if ($missing !== []) {
            fwrite(STDERR, "ERRO: extensões PHP ausentes: ".implode(", ", $missing).PHP_EOL);
            exit(1);
        }
    ' "$REQUIRED_EXTENSIONS"

    for required in \
        "$APP/.env" "$APP/artisan" "$APP/vendor/autoload.php" \
        "$APP/public/index.php" "$APP/public/.htaccess" "$APP/public/build/manifest.json"; do
        [ -f "$required" ] || fail "arquivo obrigatório ausente: $required"
    done
    [ ! -e "$APP/public/hot" ] || fail "public/hot não pode existir em produção"
    [ -w "$APP/bootstrap/cache" ] || fail "bootstrap/cache não é gravável"
    [ -w "$APP/storage/framework" ] || fail "storage/framework não é gravável"

    "$PHP_BIN" -r '
        $mode = fileperms($argv[1]);
        exit($mode !== false && ($mode & 0007) === 0 && ($mode & 0020) === 0 ? 0 : 1);
    ' "$APP/.env" || fail ".env deve usar permissão 600 ou 640"

    cd "$APP"
    "$PHP_BIN" artisan config:clear --no-interaction
    "$PHP_BIN" artisan app:deploy-preflight --no-interaction

    # Todas as migrations desta release foram revisadas como aditivas. Nunca use fresh/wipe.
    "$PHP_BIN" artisan migrate --force --no-interaction

    # Seeder oficial idempotente: cria apenas a edição/categorias/critério ausentes.
    "$PHP_BIN" artisan db:seed --class=EditionSeeder --force --no-interaction

    # Opcional e somente na primeira instalação. O comando valida conteúdo/local/permissões
    # e remove o arquivo após criar a conta; atualizações não devem recriar esse arquivo.
    ADMIN_FILE="$SHARED_DIR/admin-inicial.ini"
    if [ -f "$ADMIN_FILE" ]; then
        "$PHP_BIN" artisan app:create-admin --from-file="$ADMIN_FILE" --no-interaction
    fi

    "$PHP_BIN" artisan optimize:clear --no-interaction
    printf 'release=%s\ncompleted_at=%s\n' "$VERSION" "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" > "$MARKER"
    chmod 600 "$MARKER" 2>/dev/null || true
    printf '== concluído\n'
} >> "$LOG" 2>&1
