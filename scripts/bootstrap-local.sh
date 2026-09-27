#!/usr/bin/env bash
set -euo pipefail

required_files=(
    '.env'
    '.docker/php/Dockerfile'
    '.docker/mysql/create-testing-database.sh'
    'artisan'
    'composer.json'
    'composer.lock'
    'compose.yaml'
)

for path in "${required_files[@]}"; do
    if [ ! -f "$path" ]; then
        printf 'Pré-requisito ausente ou não é arquivo regular: %s\n' "$path" >&2
        exit 1
    fi
done

command -v docker >/dev/null 2>&1 || {
    printf 'Docker não está disponível.\n' >&2
    exit 1
}

docker info >/dev/null
docker compose version >/dev/null

docker run --rm \
    --user "$(id -u):$(id -g)" \
    --env COMPOSER_HOME=/tmp/composer \
    --volume "$PWD:/app" \
    --workdir /app \
    composer:2.8.12 \
    composer install --no-interaction --prefer-dist --no-progress

if [ ! -f vendor/autoload.php ]; then
    printf 'Composer terminou sem gerar vendor/autoload.php como arquivo regular.\n' >&2
    exit 1
fi

docker compose config --quiet

printf 'Dependências PHP instaladas e configuração Compose válida.\n'
