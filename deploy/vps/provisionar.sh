#!/usr/bin/env bash
# Prepara a VPS (Ubuntu 24.04, nginx nativo, MariaDB 10.11, compartilhada com outros sites).
# Uso, como root, uma etapa por vez e nesta ordem:
#   provisionar.sh inventario   só leitura
#   provisionar.sh php          PHP 8.4 (packages.sury.org), sem atualizar nem remover pacotes
#   provisionar.sh base         usuário, pastas, banco, shared/.env e pool do PHP-FPM
#   provisionar.sh http         vhost HTTP para o desafio do certificado
#   provisionar.sh certificado  Let's Encrypt por webroot (exige o DNS já apontado)
#   provisionar.sh https        vhost definitivo (exige a primeira release instalada)
#   provisionar.sh backup       backup diário em /etc/cron.d
#   provisionar.sh vizinhos     só leitura: código HTTP de cada site deste nginx
# Segredos são gerados aqui, gravados direto no .env e nunca impressos. Nada de outro site é
# editado; o nginx só recebe reload, depois de nginx -t, e volta atrás se algum vizinho mudar.
set -euo pipefail
umask 022
export LC_ALL=C.UTF-8 DEBIAN_FRONTEND=noninteractive
# Sem isto, o needrestart do Ubuntu 24.04 pode reiniciar nginx/MariaDB durante um apt install.
export NEEDRESTART_SUSPEND=1 NEEDRESTART_MODE=l

DOMAIN=premiocearatemdesign.com.br
APP_USER=premioceara
BASE=/var/www/premio-ceara
ACME=/var/www/premio-ceara-acme
DB=premio_ceara
POOL=/etc/php/8.4/fpm/pool.d/premio-ceara.conf
VHOST=/etc/nginx/sites-available/$DOMAIN
VHOST_LINK=/etc/nginx/sites-enabled/$DOMAIN
HERE="$(cd "$(dirname "$0")" && pwd)"
PACKAGES=(php8.4-fpm php8.4-cli php8.4-common php8.4-curl php8.4-intl php8.4-mbstring
    php8.4-mysql php8.4-opcache php8.4-xml php8.4-zip)

fail() { printf 'ERRO: %s\n' "$1" >&2; exit 1; }
[ "$(id -u)" -eq 0 ] || fail "execute como root"

# Um por linha: "código_http código_https domínio", batendo no próprio servidor.
vizinhos() {
    local name http https
    nginx -T 2>/dev/null | grep -vE '^[[:space:]]*#' | grep -oE 'server_name[[:space:]]+[^;]+;' \
        | sed -E 's/^server_name[[:space:]]+//; s/;$//' | tr -s '[:space:]' '\n' | grep -E '^[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)+$' \
        | grep -vE "(^|\.)${DOMAIN//./\\.}$" | sort -u | while read -r name; do
            http=$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 --resolve "$name:80:127.0.0.1" "http://$name/" || true)
            https=$(curl -sk -o /dev/null -w '%{http_code}' --max-time 10 --resolve "$name:443:127.0.0.1" "https://$name/" || true)
            printf '%s %s %s\n' "$http" "$https" "$name"
        done || true
}

inventario() {
    printf '== Sistema\n'; grep PRETTY_NAME /etc/os-release; uptime; free -h; df -h /
    printf '== PHP existente\n'
    ls /etc/php /run/php 2>/dev/null || echo 'nenhum'
    dpkg -l 'php*-fpm' 'libapache2-mod-php*' apache2 2>/dev/null | grep '^ii' || true
    update-alternatives --query php 2>/dev/null | grep -E '^(Status|Value)' || true
    grep -rlE 'ondrej/php|packages.sury.org' /etc/apt/sources.list.d 2>/dev/null || echo 'sem PPA ondrej/php nem packages.sury.org'
    printf '== Banco\n'
    mariadb -NBe 'SELECT VERSION(), @@version_comment, @@default_storage_engine, @@bind_address, @@skip_name_resolve' \
        || echo 'root sem acesso por socket ao MariaDB: verificar antes de continuar'
    printf '== nginx\n'; nginx -v; nginx -t || true; ls -l /etc/nginx/sites-enabled
    nginx -T 2>/dev/null | grep -E '^[[:space:]]*(listen|include)[[:space:]]' | sort | uniq -c || true
    printf '== Portas 80/443\n'; ss -ltnp '( sport = :80 or sport = :443 )'
    printf '== Conflitos com este sistema\n'
    ls -d "$BASE" "$ACME" "$VHOST" "$POOL" 2>/dev/null || echo 'nenhum outro caminho em uso'
    getent passwd "$APP_USER" || echo "usuário $APP_USER livre"
    printf '== SSH (um AllowUsers exigiria incluir %s)\n' "$APP_USER"
    sshd -T 2>/dev/null | grep -iE '^(allowusers|allowgroups|passwordauthentication|permitrootlogin)' || true
    printf '== Ferramentas\n'; command -v certbot tar openssl curl mariadb-dump || true
    printf '== Escutas IPv6 do nginx\n'
    nginx -T 2>/dev/null | grep -E '^[[:space:]]*listen[[:space:]]+\[::\]' | sort | uniq -c || echo 'nenhuma'
    printf '== Vizinhos (http https domínio)\n'; vizinhos
}

instalar_php() {
    local sim current_php codename tmp
    # O PPA ondrej/php está congelado (sem correções de segurança); o mesmo mantenedor publica
    # agora em packages.sury.org. Do pacote do chaveiro usa-se só o arquivo da chave.
    if grep -rqE 'ondrej/php' /etc/apt/sources.list.d 2>/dev/null; then
        fail "o PPA congelado ondrej/php está ativo e pode servir outro site; migre-o antes (runbook)"
    fi
    if ! grep -rq 'packages.sury.org/php' /etc/apt/sources.list.d 2>/dev/null; then
        codename="$(. /etc/os-release && printf '%s' "$VERSION_CODENAME")"
        tmp="$(mktemp -d)"
        curl -fsSL --proto '=https' -o "$tmp/keyring.deb" https://packages.sury.org/debsuryorg-archive-keyring.deb
        dpkg-deb -x "$tmp/keyring.deb" "$tmp/x"
        install -m 644 "$(find "$tmp/x" -name 'debsuryorg-archive-keyring.gpg' -print -quit)" /usr/share/keyrings/debsuryorg-archive-keyring.gpg
        printf 'deb [signed-by=/usr/share/keyrings/debsuryorg-archive-keyring.gpg] https://packages.sury.org/php/ %s main\n' \
            "$codename" > /etc/apt/sources.list.d/sury-php.list
    fi
    # Deste repositório, só o PHP 8.4 (e o php-common de que ele depende): um "apt upgrade"
    # futuro não troca o PHP de outros sites pelo pacote do sury.org.
    cat > /etc/apt/preferences.d/sury-php <<'PIN'
Package: *
Pin: origin packages.sury.org
Pin-Priority: 100

Package: php8.4-* php-common
Pin: origin packages.sury.org
Pin-Priority: 500
PIN
    apt-get update -q

    # Simula antes: instalar o PHP 8.4 não pode atualizar nem remover nada dos outros sites.
    sim="$(apt-get -s install --no-install-recommends "${PACKAGES[@]}")"
    if grep -qE '^Remv ' <<<"$sim"; then
        grep -E '^Remv ' <<<"$sim"; fail "a instalação removeria pacotes; nada foi instalado"
    fi
    if grep -qE '^Inst (apache2|libapache2)' <<<"$sim"; then
        fail "a instalação traria o Apache, que disputa as portas 80/443; nada foi instalado"
    fi
    if grep -qE '^Inst [^ ]+ \[' <<<"$sim" && [ "${ACEITO_ATUALIZACOES:-}" != sim ]; then
        grep -E '^Inst [^ ]+ \[' <<<"$sim"
        fail "a instalação atualizaria os pacotes acima; avalie e, se aceitar, repita com ACEITO_ATUALIZACOES=sim"
    fi

    # O comando "php" dos outros sites continua na versão que já usam.
    current_php="$(command -v php >/dev/null && readlink -f "$(command -v php)" || true)"
    apt-get install -y -q --no-install-recommends "${PACKAGES[@]}"
    if [ -n "$current_php" ] && [ "$(readlink -f "$(command -v php)")" != "$current_php" ]; then
        update-alternatives --set php "$current_php"
        printf 'Comando "php" mantido em %s\n' "$current_php"
    fi
    /usr/bin/php8.4 -r 'exit(version_compare(PHP_VERSION, "8.4.1", ">=") ? 0 : 1);' || fail "PHP 8.4.1+ não instalado"
    /usr/bin/php8.4 -v
}

criar_env() {
    local env="$BASE/shared/.env" template="$HERE/../env.producao.example" app_key cpf_key db_pass line host
    if [ -f "$env" ]; then
        printf '%s já existe; mantido sem alterações.\n' "$env"
        return
    fi
    [ -f "$template" ] || fail "modelo ausente: $template"
    [ "$(mariadb -NBe "SELECT COUNT(*) FROM mysql.user WHERE user = '$DB'")" = 0 ] \
        || fail "usuário de banco $DB já existe sem $env; resolva à mão (nada foi alterado)"
    [ "$(mariadb -NBe "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = '$DB'")" = 0 ] \
        || fail "banco $DB já existe sem $env; resolva à mão (nada foi alterado)"

    app_key="$(openssl rand -base64 32)"
    cpf_key="$(openssl rand -hex 32)"
    db_pass="$(openssl rand -hex 24)"

    # O .env é gravado antes do banco: se algo falhar no meio, a senha não se perde.
    # printf é interno do bash e a senha vai por stdin: nada aparece na lista de processos.
    ( umask 077
      while IFS= read -r line || [ -n "$line" ]; do
        case "$line" in
            APP_KEY=*) printf 'APP_KEY=base64:%s\n' "$app_key" ;;
            APP_URL=*) printf 'APP_URL=https://%s\n' "$DOMAIN" ;;
            CPF_HMAC_KEY=*) printf 'CPF_HMAC_KEY=%s\n' "$cpf_key" ;;
            DB_HOST=*) printf 'DB_HOST=127.0.0.1\n' ;;
            DB_DATABASE=*) printf 'DB_DATABASE=%s\n' "$DB" ;;
            DB_USERNAME=*) printf 'DB_USERNAME=%s\n' "$DB" ;;
            DB_PASSWORD=*) printf 'DB_PASSWORD=%s\n' "$db_pass" ;;
            *) printf '%s\n' "$line" ;;
        esac
      done < "$template" > "$env" )
    chown "$APP_USER:$APP_USER" "$env"
    chmod 600 "$env"

    {
        printf 'CREATE DATABASE `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n' "$DB"
        for host in localhost 127.0.0.1; do
            printf "CREATE USER '%s'@'%s' IDENTIFIED BY '%s';\n" "$DB" "$host" "$db_pass"
            printf "GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES ON \`%s\`.* TO '%s'@'%s';\n" "$DB" "$DB" "$host"
        done
    } | mariadb
    printf 'Criado %s (600). Preencha TURNSTILE_* e MAIL_* e guarde APP_KEY e CPF_HMAC_KEY no cofre.\n' "$env"
}

base() {
    [ -x /usr/sbin/php-fpm8.4 ] || fail "rode antes: provisionar.sh php"
    mariadb -NBe 'SELECT VERSION()' | grep -qE '^10\.11\.[0-9]+-MariaDB' || fail "o servidor não é MariaDB 10.11 (única série ensaiada)"
    # Usuário próprio, sem senha e sem sudo: dono do código, do deploy (SSH por chave) e do PHP-FPM.
    getent passwd "$APP_USER" >/dev/null || useradd --create-home --shell /bin/bash "$APP_USER"
    install -d -o "$APP_USER" -g "$APP_USER" -m 755 "$BASE" "$BASE/releases" "$BASE/incoming"
    install -d -o "$APP_USER" -g "$APP_USER" -m 700 "$BASE/shared" "$BASE/shared/storage" "$BASE/shared/storage/logs"
    install -d -m 755 "$ACME"
    criar_env

    install -m 644 "$HERE/php-fpm-pool.conf" "$POOL"
    if ! php-fpm8.4 -t; then
        mv "$POOL" "$POOL.recusado"
        fail "pool recusado pelo php-fpm; renomeado para $POOL.recusado"
    fi
    systemctl reload-or-restart php8.4-fpm   # só o 8.4; outras versões não são tocadas
    sleep 1
    [ -S /run/php/premio-ceara.sock ] || fail "socket do pool não foi criado"
    printf '== base pronta\n'
}

# Instala o vhost deste domínio com backup, validação e retorno automático.
instalar_vhost() {
    local source="$1" before after previous="" had_link=0
    nginx -t || fail "o nginx já estava com erro antes desta alteração; nada foi feito"
    tar -C / -czf "/root/nginx-backup-$(date +%Y%m%d-%H%M%S).tgz" etc/nginx
    before="$(vizinhos)"
    [ -n "$before" ] || [ "${SEM_VIZINHOS:-}" = sim ] || fail "nenhum site vizinho encontrado para comparar; nada foi feito (SEM_VIZINHOS=sim se for isso mesmo)"
    if [ -f "$VHOST" ]; then previous="/root/premio-ceara-vhost-anterior.conf"; cp -p "$VHOST" "$previous"; fi
    [ ! -L "$VHOST_LINK" ] || had_link=1

    voltar() {
        [ "$had_link" -eq 1 ] || unlink "$VHOST_LINK"
        if [ -n "$previous" ]; then cp -p "$previous" "$VHOST"; else unlink "$VHOST"; fi
        nginx -t
    }

    install -m 644 "$source" "$VHOST"
    ln -sfn "$VHOST" "$VHOST_LINK"
    if ! nginx -t; then voltar; fail "vhost recusado pelo nginx -t; alteração desfeita, sem reload"; fi
    systemctl reload nginx
    sleep 3
    after="$(vizinhos)"
    if [ "$before" != "$after" ]; then
        diff <(printf '%s\n' "$before") <(printf '%s\n' "$after") || true
        voltar && systemctl reload nginx
        fail "um site vizinho mudou de resposta; alteração desfeita"
    fi
    printf 'Vhost ativo; vizinhos inalterados:\n%s\n' "$after"
}

certificado() {
    local name addr ips
    command -v certbot >/dev/null || fail "certbot ausente"
    ips=" $(hostname -I) "
    for name in "$DOMAIN" "www.$DOMAIN"; do
        # Consulta o DNS público, não o /etc/hosts nem o cache local.
        addr="$(curl -s --max-time 10 -H 'accept: application/dns-json' "https://cloudflare-dns.com/dns-query?name=$name&type=A" \
            | grep -oE '"data": ?"[0-9.]+"' | grep -oE '[0-9.]+' | head -1 || true)"
        [ -n "$addr" ] && [[ "$ips" == *" $addr "* ]] \
            || fail "$name resolve para '${addr:-nada}', que não é um IP deste servidor; ajuste o DNS e aguarde"
    done
    certbot certonly --webroot -w "$ACME" --cert-name "$DOMAIN" -d "$DOMAIN" -d "www.$DOMAIN" \
        --non-interactive --agree-tos --keep-until-expiring --deploy-hook 'systemctl reload nginx' "$@"
}

https() {
    [ -f "/etc/letsencrypt/live/$DOMAIN/fullchain.pem" ] || fail "rode antes: provisionar.sh certificado"
    [ -f "$BASE/current/public/index.php" ] || fail "instale a primeira release antes"
    instalar_vhost "$HERE/nginx-https.conf"
}

backup() {
    install -m 700 "$HERE/backup.sh" /usr/local/sbin/premio-ceara-backup
    printf '17 3 * * * root /usr/local/sbin/premio-ceara-backup\n' > /etc/cron.d/premio-ceara-backup
    chmod 644 /etc/cron.d/premio-ceara-backup
    /usr/local/sbin/premio-ceara-backup
    ls -lh /var/backups/premio-ceara
}

ACTION="${1:-}"
[ $# -eq 0 ] || shift
case "$ACTION" in
    inventario) inventario ;;
    php) instalar_php ;;
    base) base ;;
    http)
        [ ! -f "/etc/letsencrypt/live/$DOMAIN/fullchain.pem" ] || fail "já há certificado; use https"
        instalar_vhost "$HERE/nginx-http.conf" ;;
    certificado) certificado "$@" ;;
    https) https ;;
    backup) backup ;;
    vizinhos) vizinhos ;;
    *) sed -n '2,14p' "$0"; exit 64 ;;
esac
