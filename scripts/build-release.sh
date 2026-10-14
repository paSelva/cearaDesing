#!/usr/bin/env bash
# Gera um ZIP de produção a partir de um commit exato, sem usar nem alterar o checkout.
# Uso: bash scripts/build-release.sh <sha-completo-de-40-caracteres>
# Requer: Git, Node/npm, zip e Docker; alternativamente PHP/Composer compatíveis.
# Composer/npm precisam de DNS/HTTPS; o build Vite também acessa fonts.bunny.net.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
REF="${1:-}"
IMAGE="ceara-release-composer:2.8.12-php84"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"

fail() { printf 'ERRO: %s\n' "$1" >&2; exit 1; }

[[ "$REF" =~ ^[0-9a-f]{40}$ ]] \
    || fail "informe o SHA completo (40 caracteres); branches, tags e HEAD não são aceitos"
SHA="$(git -C "$ROOT" rev-parse --verify "${REF}^{commit}")" \
    || fail "commit não encontrado: $REF"
[[ "$SHA" == "$REF" ]] || fail "o SHA informado não identifica exatamente o commit resolvido"

SHORT_SHA="${SHA:0:12}"
WORK="$(mktemp -d)"
APP="$WORK/app"
OUT_DIR="$ROOT/dist"
OUT="$OUT_DIR/ceara-tem-design-$SHORT_SHA.zip"
CHECKSUM="$OUT.sha256"

cleanup() { rm -rf "$WORK"; }
trap cleanup EXIT

for command in git node npm zip shasum tar; do
    command -v "$command" >/dev/null 2>&1 || fail "comando obrigatório ausente no build: $command"
done
node -e 'const [major, minor] = process.versions.node.split(".").map(Number); process.exit((major === 20 && minor >= 19) || major >= 22 ? 0 : 1)' \
    || fail "Node incompatível com package-lock.json/Vite: use 20.19+ ou 22.12+"

printf '== Pacote do commit %s\n' "$SHA"
mkdir -p "$APP" "$OUT_DIR"
git -C "$ROOT" archive --format=tar "$SHA" | tar -xf - -C "$APP"

printf '== Dependências PHP travadas (produção, PHP 8.4)\n'
if command -v docker >/dev/null 2>&1 && docker info >/dev/null 2>&1; then
    docker build --quiet --file "$ROOT/.docker/composer/Dockerfile" --tag "$IMAGE" "$ROOT/.docker/composer" >/dev/null
    docker run --rm --user "$(id -u):$(id -g)" --env COMPOSER_HOME=/tmp/composer \
        --volume "$APP:/app" --workdir /app "$IMAGE" \
        composer install --no-dev --classmap-authoritative --no-interaction --prefer-dist --no-progress
    docker run --rm --user "$(id -u):$(id -g)" \
        --volume "$APP:/app:ro" --workdir /app "$IMAGE" \
        composer check-platform-reqs --no-dev
else
    [ -x "$PHP_BIN" ] || command -v "$PHP_BIN" >/dev/null 2>&1 \
        || fail "Docker indisponível e PHP_BIN não executável: $PHP_BIN"
    [ -f "$COMPOSER_BIN" ] || command -v "$COMPOSER_BIN" >/dev/null 2>&1 \
        || fail "Docker indisponível e COMPOSER_BIN não encontrado: $COMPOSER_BIN"
    "$PHP_BIN" -r 'exit(version_compare(PHP_VERSION, "8.4.1", ">=") ? 0 : 1);' \
        || fail "PHP nativo incompatível: use PHP 8.4.1+"
    (
        cd "$APP"
        "$PHP_BIN" "$COMPOSER_BIN" install --no-dev --classmap-authoritative \
            --no-interaction --prefer-dist --no-progress
        "$PHP_BIN" "$COMPOSER_BIN" check-platform-reqs --no-dev
    )
fi

printf '== Assets Vite (npm ci; requer DNS/HTTPS de saída para fonts.bunny.net)\n'
(
    cd "$APP"
    npm ci --ignore-scripts --no-audit --no-fund
    npm run build
)

printf '== Limpeza e metadados da release\n'
rm -rf \
    "$APP/node_modules" "$APP/tests" "$APP/database/factories" "$APP/docs" "$APP/scripts" \
    "$APP/.github" "$APP/skills" "$APP/.docker" "$APP/.claude" \
    "$APP/phpunit.xml" "$APP/pint.json" "$APP/compose.yaml" \
    "$APP/package.json" "$APP/package-lock.json" "$APP/vite.config.js" \
    "$APP/.env.example" "$APP/public/hot"
printf '%s\n' "$SHA" > "$APP/VERSION"

# O Git não materializa diretórios vazios. Estes caminhos são necessários ao Laravel.
mkdir -p \
    "$APP/bootstrap/cache" \
    "$APP/storage/app/private" "$APP/storage/app/public" \
    "$APP/storage/framework/cache/data" "$APP/storage/framework/sessions" \
    "$APP/storage/framework/testing" "$APP/storage/framework/views" \
    "$APP/storage/logs"

printf '== Verificações do conteúdo\n'
[ -f "$APP/vendor/autoload.php" ] || fail "vendor/autoload.php ausente"
[ -f "$APP/public/build/manifest.json" ] || fail "assets do Vite ausentes"
[ -d "$APP/public/js/filament" ] && [ -d "$APP/public/css/filament" ] \
    || fail "assets publicados do Filament ausentes"
[ -f "$APP/public/.htaccess" ] || fail "public/.htaccess ausente"
[ -f "$APP/database/seeders/EditionSeeder.php" ] || fail "seeder oficial ausente"
[ -f "$APP/deploy/cron-deploy.sh" ] || fail "script de deploy ausente"
[ ! -e "$APP/.env" ] || fail ".env dentro do pacote"
[ ! -e "$APP/public/hot" ] || fail "public/hot dentro do pacote"
[ ! -e "$APP/bootstrap/cache/config.php" ] || fail "cache de configuração gerado no build"
find "$APP/bootstrap/cache" -maxdepth 1 -type f \( -name 'routes*.php' -o -name 'events.php' \) -print -quit | grep -q . \
    && fail "cache de aplicação gerado no build"
find "$APP" -type f \( -name '*.sql' -o -name '*.sql.gz' -o -name '*.bak' -o -name '*.log' \) -print -quit | grep -q . \
    && fail "dump, backup ou log no pacote"
find "$APP/storage/app" -type f ! -name '.gitignore' -print -quit | grep -q . \
    && fail "upload local no pacote"
find "$APP" -type f -name '.env*' -print -quit | grep -q . \
    && fail "arquivo de ambiente no pacote"
node -e '
    const packages = require(process.argv[1]).packages ?? [];
    const devOnly = packages.find(({ name = "" }) => /^(laravel\/(sail|pail|pao)|pestphp\/|fakerphp\/|nunomaduro\/collision$)/i.test(name));
    if (devOnly) {
        console.error(`ERRO: dependência de desenvolvimento no pacote: ${devOnly.name}`);
        process.exit(1);
    }
' "$APP/vendor/composer/installed.json" || fail "dependência de desenvolvimento no pacote"

printf '== Compactação e checksum\n'
rm -f "$OUT" "$CHECKSUM"
(cd "$APP" && zip -qry "$OUT" .)
(
    cd "$OUT_DIR"
    shasum -a 256 "$(basename "$OUT")" > "$(basename "$CHECKSUM")"
)
printf 'Pacote: %s\nChecksum: %s\n' "$OUT" "$CHECKSUM"
