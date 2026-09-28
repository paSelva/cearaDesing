#!/usr/bin/env bash
# Gera o pacote de deploy para a HostGator (sem SSH) a partir de um commit exato.
# Uso: bash scripts/build-release.sh [ref]   (padrão: HEAD)
# Saída: dist/votacao-<sha>.zip — enviar por FTPS ou pelo Gerenciador de Arquivos do cPanel.
# Nunca usa a pasta de trabalho: .env, uploads locais e builds locais não entram no pacote.
set -euo pipefail

REF="${1:-HEAD}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SHA="$(git -C "$ROOT" rev-parse --short=12 "$REF")"
IMAGE="ceara-spec-composer:2.8.12-php84-intl"
WORK="$(mktemp -d)"
APP="$WORK/app"
OUT="$ROOT/dist/votacao-$SHA.zip"

trap 'rm -rf "$WORK"' EXIT

echo "== Pacote do commit $SHA ($REF)"
mkdir -p "$APP" "$ROOT/dist"
git -C "$ROOT" archive "$REF" | tar -x -C "$APP"

echo "== Composer (sem dependências de desenvolvimento, PHP 8.4 + exif)"
docker build --quiet --file "$ROOT/.docker/composer/Dockerfile" --tag "$IMAGE" "$ROOT/.docker/composer" >/dev/null
docker run --rm --user "$(id -u):$(id -g)" --env COMPOSER_HOME=/tmp/composer \
    --volume "$APP:/app" --workdir /app "$IMAGE" \
    composer install --no-dev --classmap-authoritative --no-interaction --prefer-dist --no-progress

echo "== Assets (Vite)"
(cd "$APP" && npm ci --ignore-scripts --no-audit --no-fund >/dev/null && npm run build >/dev/null)

echo "== Limpeza do que não vai para o servidor"
rm -rf "$APP/node_modules" "$APP/tests" "$APP/.github" "$APP/skills" "$APP/.docker" \
    "$APP/phpunit.xml" "$APP/compose.yaml" "$APP/.claude" "$APP/public/hot"
echo "$SHA" > "$APP/VERSION"

echo "== Verificações"
fail() { echo "ERRO: $1" >&2; exit 1; }
[ -f "$APP/vendor/autoload.php" ] || fail "vendor/autoload.php ausente"
[ -f "$APP/public/build/manifest.json" ] || fail "assets do Vite ausentes"
[ -d "$APP/public/js/filament" ] && [ -d "$APP/public/css/filament" ] || fail "assets do Filament ausentes"
[ ! -e "$APP/.env" ] || fail ".env dentro do pacote"
[ ! -e "$APP/public/hot" ] || fail "public/hot dentro do pacote"
[ ! -e "$APP/bootstrap/cache/config.php" ] || fail "cache de configuração gerado fora do servidor"
ls "$APP"/bootstrap/cache/routes*.php >/dev/null 2>&1 && fail "cache de rotas gerado fora do servidor"
grep -qiE 'sail|pail|collision' "$APP/bootstrap/cache/packages.php" && fail "provedores de desenvolvimento no manifesto"
find "$APP" -name '*.sql' -o -name '*.sql.gz' | grep -q . && fail "dump SQL no pacote"
find "$APP/storage/app" -type f ! -name '.gitignore' | grep -q . && fail "arquivos de usuário em storage/app"

echo "== Compactando"
rm -f "$OUT"
(cd "$APP" && zip -qr "$OUT" .)
echo "Pacote: $OUT ($(du -h "$OUT" | cut -f1))"
