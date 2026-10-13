#!/usr/bin/env bash
# Instala e ativa uma release pelo SSH da hospedagem (HostGator compartilhada: porta 2222).
# Executado no servidor, normalmente pelo workflow .github/workflows/deploy.yml via stdin:
#   ssh -p 2222 USUARIO@HOST 'bash -s' -- install /PHP_CLI /home/USUARIO/ceara-design SHA < deploy/ssh-deploy.sh
#   ssh -p 2222 USUARIO@HOST 'bash -s' -- rollback /PHP_CLI /home/USUARIO/ceara-design SHA_ANTERIOR < deploy/ssh-deploy.sh
# install: confere o ZIP enviado em incoming/, extrai em releases/SHA, roda cron-deploy.sh
# (preflight, migrations, seeder, admin inicial) e só então troca o link "current".
# rollback: aponta "current" para uma release já instalada; não mexe no banco.
# Não usa unzip/rsync (podem faltar no shell da hospedagem): extrai com ZipArchive do PHP CLI.
set -euo pipefail
umask 022

ACTION="${1:-}"
PHP_BIN="${2:-}"
BASE_DIR="${3:-}"
SHA="${4:-}"
KEEP_RELEASES="${KEEP_RELEASES:-3}"

fail() { printf 'ERRO: %s\n' "$1" >&2; exit 1; }

[[ "$ACTION" == install || "$ACTION" == rollback ]] || fail "ação deve ser install ou rollback"
[[ "$PHP_BIN" = /* && -x "$PHP_BIN" ]] || fail "informe o caminho absoluto e executável do PHP CLI"
[[ "$BASE_DIR" = /* && -d "$BASE_DIR" ]] || fail "informe o caminho absoluto existente da base (ex.: /home/USUARIO/ceara-design)"
[[ "$SHA" =~ ^[0-9a-f]{40}$ ]] || fail "informe o SHA completo (40 caracteres)"
[[ "$KEEP_RELEASES" =~ ^[0-9]+$ && "$KEEP_RELEASES" -ge 2 ]] || fail "KEEP_RELEASES deve ser 2 ou mais"

SHARED_DIR="$BASE_DIR/shared"
RELEASES_DIR="$BASE_DIR/releases"
INCOMING_DIR="$BASE_DIR/incoming"
RELEASE="$RELEASES_DIR/$SHA"
CURRENT="$BASE_DIR/current"
SHORT_SHA="${SHA:0:12}"
ZIP="$INCOMING_DIR/ceara-tem-design-$SHORT_SHA.zip"

[ -f "$SHARED_DIR/.env" ] || fail "crie $SHARED_DIR/.env antes do primeiro deploy (manual, nunca pelo GitHub)"
mkdir -p "$RELEASES_DIR" "$INCOMING_DIR"

# Troca atômica do link: um link temporário renomeado sobre "current" (mv -T, GNU coreutils).
activate() {
    local target="$1"
    [ -f "$target/VERSION" ] || fail "release sem VERSION: $target"
    [ -f "$SHARED_DIR/storage/app/.deploy-$SHA.complete" ] \
        || fail "release $SHA não foi concluída pelo cron-deploy.sh; ativação recusada"
    ln -s "$target" "$CURRENT.tmp-$$"
    mv -T "$CURRENT.tmp-$$" "$CURRENT"
    printf 'current -> %s\n' "$(readlink "$CURRENT")"
}

if [ "$ACTION" == rollback ]; then
    [ -d "$RELEASE" ] || fail "release não instalada: $RELEASE"
    printf 'Rollback de código para %s. Confirme antes a compatibilidade com o schema atual.\n' "$SHA"
    activate "$RELEASE"
    "$PHP_BIN" "$CURRENT/artisan" optimize:clear --no-interaction
    exit 0
fi

# 1. Pacote enviado pelo workflow e checksum calculado no build.
[ -f "$ZIP" ] && [ -f "$ZIP.sha256" ] || fail "ZIP ou .sha256 ausente em $INCOMING_DIR"
EXPECTED="$(awk '{print $1}' "$ZIP.sha256")"
ACTUAL="$("$PHP_BIN" -r 'echo hash_file("sha256", $argv[1]);' "$ZIP")"
[[ "$EXPECTED" =~ ^[0-9a-f]{64}$ && "$EXPECTED" == "$ACTUAL" ]] || fail "checksum do ZIP não confere"

# 2. Extração em pasta temporária e renomeação: nunca sobre uma release existente.
if [ -d "$RELEASE" ]; then
    printf 'Release %s já extraída; reaproveitando (o cron-deploy.sh é idempotente).\n' "$SHA"
else
    STAGING="$RELEASES_DIR/.staging-$SHA-$$"
    trap 'rm -rf "$STAGING"' EXIT
    "$PHP_BIN" -r '
        $zip = new ZipArchive();
        if ($zip->open($argv[1]) !== true || ! $zip->extractTo($argv[2])) {
            fwrite(STDERR, "ERRO: falha ao extrair o ZIP\n");
            exit(1);
        }
        $zip->close();
    ' "$ZIP" "$STAGING"
    [ "$(tr -d '[:space:]' < "$STAGING/VERSION")" == "$SHA" ] || fail "VERSION do pacote difere do SHA pedido"
    mv "$STAGING" "$RELEASE"
    trap - EXIT
fi

# 3. Instalação pelo mesmo script do caminho via Cron. Ele grava tudo no log privado;
# o conteúdo é repetido aqui para aparecer no job do GitHub (não contém segredos).
LOG="$SHARED_DIR/storage/logs/deploy-$SHA.log"
set +e
/bin/bash "$RELEASE/deploy/cron-deploy.sh" "$PHP_BIN" "$SHARED_DIR"
STATUS=$?
set -e
[ -f "$LOG" ] && tail -n 60 "$LOG"
if [ "$STATUS" -eq 75 ]; then
    fail "outra instalação está em andamento (trava em shared/storage/app/.deploy.lock)"
fi
[ "$STATUS" -eq 0 ] || fail "cron-deploy.sh terminou com código $STATUS; current não foi alterado"

# 4. Ativação e limpeza do pacote enviado. A release que estava ativa fica guardada para rollback.
PREVIOUS="$(basename "$(readlink "$CURRENT" 2>/dev/null || true)")"
activate "$RELEASE"
rm -f "$ZIP" "$ZIP.sha256"

# 5. Retenção: mantém as KEEP_RELEASES mais recentes, sem nunca apagar a ativa nem a anterior.
ACTIVE="$(basename "$(readlink "$CURRENT")")"
find "$RELEASES_DIR" -mindepth 1 -maxdepth 1 -type d -regextype posix-extended -regex '.*/[0-9a-f]{40}' \
    -printf '%T@ %f\n' | sort -rn | awk -v keep="$KEEP_RELEASES" 'NR > keep {print $2}' \
    | while read -r old; do
        if [ "$old" != "$ACTIVE" ] && [ "$old" != "$PREVIOUS" ]; then
            rm -rf "${RELEASES_DIR:?}/$old"
            printf 'Release antiga removida: %s\n' "$old"
        fi
    done
printf '== Deploy %s ativo\n' "$SHA"
