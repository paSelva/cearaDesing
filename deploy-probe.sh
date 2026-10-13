#!/bin/bash
# Relatório do ambiente em que o cPanel executa as tarefas do .cpanel.yml. Sem segredos.
OUT="$HOME/ceara-design-probe.txt"
umask 077
{
    echo "== sonda $(date '+%Y-%m-%d %H:%M:%S %z')"
    echo "id: $(id)"
    echo "pwd: $(pwd)"
    echo "HOME: $HOME"
    echo "PATH: $PATH"
    echo "shell: $BASH_VERSION"
    echo "export entre tarefas: ${CEARA_PROBE_EXPORT:-NÃO persistiu}"
    echo "umask: $(umask)"
    for c in bash tar php git find mv realpath readlink awk sort tee unzip; do
        printf '%-9s %s\n' "$c" "$(command -v "$c" || echo AUSENTE)"
    done
    echo "GNU find: $(find --version 2>/dev/null | head -1 | grep . || echo não)"
    echo "GNU mv: $(mv --version 2>/dev/null | head -1 | grep . || echo não)"
    t="$(mktemp -d "$HOME/.probe-XXXXXX")" && ln -s /tmp "$t/l.tmp" && mv -T "$t/l.tmp" "$t/l" \
        && echo "mv -T com link: ok" || echo "mv -T com link: FALHOU"
    rm -rf "$t"
    REQ='ctype date dom exif fileinfo filter hash iconv intl json libxml mbstring openssl pcre pdo pdo_mysql session tokenizer xmlreader zip zlib'
    for p in /opt/cpanel/ea-php8*/root/usr/bin/php /usr/local/bin/php /usr/bin/php "$(command -v php)"; do
        [ -x "$p" ] || continue
        echo "-- $p"
        "$p" -r 'echo "versão: ", PHP_VERSION, PHP_EOL;' 2>&1 | head -2
        "$p" -r '$m=[]; foreach (explode(" ", $argv[1]) as $e) { if (!extension_loaded($e)) $m[]=$e; } echo "extensões faltando: ", ($m ? implode(", ", $m) : "nenhuma"), PHP_EOL;' "$REQ" 2>&1 | head -2
    done
    echo "disco: $(df -h "$HOME" 2>/dev/null | tail -1)"
    echo "== fim"
} > "$OUT" 2>&1
