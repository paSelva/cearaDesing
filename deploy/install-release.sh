#!/usr/bin/env bash
# Instala e ativa uma release no servidor. Três modos:
#   install-dir PHP_CLI BASE SHA ORIGEM  cópia de um diretório já com vendor/build, usado pelo
#                                        Git Version Control do cPanel (.cpanel.yml do branch
#                                        "producao"; ORIGEM = clone em ~/repositories/...)
#   install-zip PHP_CLI BASE SHA         ZIP + .sha256 enviados para BASE/incoming (via SSH,
#                                        workflow deploy.yml; dormente enquanto não houver SSH)
#   rollback    PHP_CLI BASE SHA         aponta "current" para uma release já instalada
# BASE é a pasta da aplicação, ex.: $HOME/ceara-design (com shared/.env criado à mão).
# A instalação extrai/copia em releases/SHA, roda cron-deploy.sh (preflight, migrations,
# seeder, admin inicial) e só então troca o link "current". O banco nunca é revertido.
# Não usa unzip/rsync/git (podem faltar no ambiente da hospedagem).
set -euo pipefail
umask 022

ACTION="${1:-}"
PHP_BIN="${2:-}"
BASE_DIR="${3:-}"
SHA="${4:-}"
SOURCE_DIR="${5:-}"
KEEP_RELEASES="${KEEP_RELEASES:-2}"

fail() { printf 'ERRO: %s\n' "$1" >&2; exit 1; }

# Log fixo e fácil de achar (Gerenciador de Arquivos → pasta pessoal), aberto antes de
# qualquer verificação: o cPanel não mostra a saída das tarefas e a base pode nem existir.
EARLY_LOG="${HOME:?HOME não definido}/ceara-design-install.log"
touch "$EARLY_LOG" && chmod 600 "$EARLY_LOG"
exec > >(tee -a "$EARLY_LOG") 2>&1
printf '\n== %s %s %s\n' "$(date '+%Y-%m-%d %H:%M:%S %z')" "${1:-?}" "${4:-?}"

[[ "$ACTION" =~ ^(install-dir|install-zip|rollback)$ ]] || fail "ação deve ser install-dir, install-zip ou rollback"
[[ "$PHP_BIN" = /* && -x "$PHP_BIN" ]] || fail "informe o caminho absoluto e executável do PHP CLI: $PHP_BIN"
[[ "$BASE_DIR" = /* && -d "$BASE_DIR" ]] || fail "pasta base não encontrada: $BASE_DIR (crie \$HOME/ceara-design/shared com o .env)"
[[ "$SHA" =~ ^[0-9a-f]{40}$ ]] || fail "informe o SHA completo (40 caracteres)"
[[ "$KEEP_RELEASES" =~ ^[0-9]+$ && "$KEEP_RELEASES" -ge 2 ]] || fail "KEEP_RELEASES deve ser 2 ou mais"

SHARED_DIR="$BASE_DIR/shared"
RELEASES_DIR="$BASE_DIR/releases"
INCOMING_DIR="$BASE_DIR/incoming"
RELEASE="$RELEASES_DIR/$SHA"
CURRENT="$BASE_DIR/current"
MARKER="$SHARED_DIR/storage/app/.deploy-$SHA.complete"
ZIP="$INCOMING_DIR/ceara-tem-design-${SHA:0:12}.zip"

[ -f "$SHARED_DIR/.env" ] || fail "crie $SHARED_DIR/.env antes do primeiro deploy (manual, nunca pelo GitHub)"
mkdir -p "$RELEASES_DIR" "$SHARED_DIR/storage/app" "$SHARED_DIR/storage/logs"

# Saída também num log privado: o cPanel pode não mostrar o resultado das tarefas de deploy.
INSTALL_LOG="$SHARED_DIR/storage/logs/install-$SHA.log"
touch "$INSTALL_LOG" && chmod 600 "$INSTALL_LOG"
exec > >(tee -a "$INSTALL_LOG") 2>&1

# Ativa só uma release concluída e ligada ao .env/storage compartilhados.
# Troca atômica: link temporário renomeado sobre "current" (mv -T, GNU coreutils).
activate() {
    local target="$1" link
    [ -f "$target/VERSION" ] || fail "release sem VERSION: $target"
    [ -f "$MARKER" ] || fail "release $SHA não foi concluída pelo cron-deploy.sh; ativação recusada"
    for link in .env storage; do
        [ -L "$target/$link" ] && [ "$(realpath "$target/$link")" == "$(realpath "$SHARED_DIR/$link")" ] \
            || fail "$link da release não aponta para shared/; ativação recusada"
    done
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

# 1. Monta a release numa pasta temporária e só então renomeia: nunca sobre uma existente.
if [ -d "$RELEASE" ]; then
    printf 'Release %s já existe; reaproveitando (o cron-deploy.sh é idempotente).\n' "$SHA"
else
    STAGING="$RELEASES_DIR/.staging-$SHA-$$"
    trap 'rm -rf "$STAGING"' EXIT
    mkdir -p "$STAGING"
    if [ "$ACTION" == install-zip ]; then
        [ -f "$ZIP" ] && [ -f "$ZIP.sha256" ] || fail "ZIP ou .sha256 ausente em $INCOMING_DIR"
        EXPECTED="$(awk '{print $1}' "$ZIP.sha256")"
        ACTUAL="$("$PHP_BIN" -r 'echo hash_file("sha256", $argv[1]);' "$ZIP")"
        [[ "$EXPECTED" =~ ^[0-9a-f]{64}$ && "$EXPECTED" == "$ACTUAL" ]] || fail "checksum do ZIP não confere"
        "$PHP_BIN" -r '
            $zip = new ZipArchive();
            if ($zip->open($argv[1]) !== true || ! $zip->extractTo($argv[2])) {
                fwrite(STDERR, "ERRO: falha ao extrair o ZIP\n");
                exit(1);
            }
            $zip->close();
        ' "$ZIP" "$STAGING"
    else
        # O clone do cPanel não pode ficar sujo (o deploy seria desabilitado): só leitura dele.
        [[ "$SOURCE_DIR" = /* && -f "$SOURCE_DIR/VERSION" ]] || fail "informe o diretório de origem com VERSION"
        (cd "$SOURCE_DIR" && tar --exclude=./.git --exclude=./.cpanel.yml -cf - .) | (cd "$STAGING" && tar -xf -)
    fi
    [ "$(tr -d '[:space:]' < "$STAGING/VERSION")" == "$SHA" ] || fail "VERSION do pacote difere do SHA pedido"
    [ -f "$STAGING/vendor/autoload.php" ] || fail "pacote sem vendor/; use o branch de release, não a main"

    # O Git não guarda diretórios vazios; o Laravel precisa destes.
    mkdir -p \
        "$STAGING/bootstrap/cache" \
        "$STAGING/storage/app/private" "$STAGING/storage/app/public" \
        "$STAGING/storage/framework/cache/data" "$STAGING/storage/framework/sessions" \
        "$STAGING/storage/framework/testing" "$STAGING/storage/framework/views" \
        "$STAGING/storage/logs"
    mv "$STAGING" "$RELEASE"
    trap - EXIT

    # Release nova (inclusive a republicação de um SHA já removido): o marcador antigo não
    # vale para esta pasta, que ainda não tem os links. Migrations e seeder são idempotentes.
    rm -f "$MARKER"
fi

# 2. Instalação pelo mesmo script do caminho via Cron, que grava o próprio log.
LOG="$SHARED_DIR/storage/logs/deploy-$SHA.log"
set +e
/bin/bash "$RELEASE/deploy/cron-deploy.sh" "$PHP_BIN" "$SHARED_DIR"
STATUS=$?
set -e
[ -f "$LOG" ] && tail -n 60 "$LOG"
[ "$STATUS" -ne 75 ] || fail "outra instalação está em andamento (trava em shared/storage/app/.deploy.lock)"
[ "$STATUS" -eq 0 ] || fail "cron-deploy.sh terminou com código $STATUS; current não foi alterado"

# 3. Ativação. A release que estava ativa fica guardada para rollback.
PREVIOUS="$(basename "$(readlink "$CURRENT" 2>/dev/null || true)")"
activate "$RELEASE"
[ "$ACTION" != install-zip ] || rm -f "$ZIP" "$ZIP.sha256"

# 4. Retenção: mantém as KEEP_RELEASES mais recentes, sem nunca apagar a ativa nem a anterior.
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
