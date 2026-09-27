#!/usr/bin/env bash
set -euo pipefail

test_database="${MYSQL_TEST_DATABASE:-ceara_spec000_testing}"

if [[ ! "$test_database" =~ ^[A-Za-z0-9_]+$ ]]; then
    printf 'Nome inválido para o banco de testes.\n' >&2
    exit 1
fi

if [ -n "${MYSQL_USER:-}" ] && [[ ! "$MYSQL_USER" =~ ^[A-Za-z0-9_]+$ ]]; then
    printf 'Nome inválido para o usuário do banco.\n' >&2
    exit 1
fi

MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --user=root <<-EOSQL
    CREATE DATABASE IF NOT EXISTS \`$test_database\`;
EOSQL

if [ -n "${MYSQL_USER:-}" ]; then
    MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --user=root <<-EOSQL
        GRANT ALL PRIVILEGES ON \`$test_database\`.* TO '$MYSQL_USER'@'%';
EOSQL
fi
