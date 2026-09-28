#!/usr/bin/env bash
# Teste de fumaça depois do deploy. Só faz requisições GET/HEAD públicas; não envia dados.
# Uso: bash scripts/smoke-producao.sh [https://votacao.cearadesign.org.br]
set -uo pipefail

BASE="${1:-https://votacao.cearadesign.org.br}"
HOST="${BASE#https://}"
FAIL=0

check() { # caminho, códigos aceitos
    local code
    code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "$BASE$1")
    if [[ " $2 " == *" $code "* ]]; then echo "ok   $code $1"; else echo "FALHA $code $1 (esperado: $2)"; FAIL=1; fi
}

echo "== Páginas que devem abrir"
for p in /up / /login /register /eleitor/cadastro /galeria /premiados /admin/login /jurado/login; do check "$p" "200"; done

echo "== Arquivos que NÃO podem ser públicos"
for p in /.env /composer.json /composer.lock /.git/HEAD /storage/logs/laravel.log /vendor/autoload.php /artisan /VERSION /deploy/cron-deploy.sh; do check "$p" "403 404"; done

echo "== HTTP redireciona para HTTPS"
code=$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' --max-time 20 "http://$HOST/")
[[ "$code" == 30[178]\ https://* ]] && echo "ok   $code" || { echo "FALHA $code"; FAIL=1; }

echo "== Cabeçalhos de segurança"
headers=$(curl -sI --max-time 20 "$BASE/")
for h in 'x-content-type-options: nosniff' 'x-frame-options: sameorigin' 'referrer-policy' 'strict-transport-security'; do
    echo "$headers" | grep -qi "$h" && echo "ok   $h" || { echo "FALHA sem $h"; FAIL=1; }
done

echo "== Proxy na frente? (afeta o limite por IP, P52, e o IP dos aceites, P53)"
echo "$headers" | grep -iE '^(cf-ray|via|x-cache|server):' || echo "nenhum indício de proxy"

exit $FAIL