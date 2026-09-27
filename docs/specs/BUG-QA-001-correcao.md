# BUG-QA-001 — Correção do bootstrap em checkout limpo

Data DEV: 27/09/2026. Branch: `fix/spec-000-bootstrap-limpo`. Base:
`9b2b26b` (`develop`, merge do PR #2). Commit de implementação validado:
`65b62b49f47a8579469896f04d937e5bfab6fdf4`.

O parecer independente reprovado foi preservado em
`docs/qa/SPEC-000-14c43be.md`. Esta validação do DEV não substitui o reteste do QA.

## Causa e correção

O `compose.yaml` montava
`vendor/laravel/sail/database/mysql/create-testing-database.sh` antes de o Composer criar
`vendor/`. Docker convertia a origem ausente em diretório e o MySQL encerrava com código
126. Além disso, o container Laravel também depende de `vendor/autoload.php` em runtime.

A correção:

- versiona o init do banco em `.docker/mysql/create-testing-database.sh`;
- cria e concede acesso ao banco indicado por `DB_TEST_DATABASE`;
- remove todo bind de `vendor/` do Compose;
- instala o `composer.lock` por `composer:2.8.12`, independente dos containers da aplicação;
- rejeita pré-requisitos ausentes ou que não sejam arquivos regulares;
- valida o Compose antes da primeira subida;
- mantém recuperação sem `down -v`, remoção de diretórios ou limpeza de banco.

## Validação DEV em checkout limpo

Ambiente isolado: worktree destacado em `/tmp`, SHA `65b62b4`, inicialmente sem `.env`,
`vendor/` e `node_modules/`; projeto Compose `ceara_bugqa001_dev`; volume dedicado; portas
8081/33060 livres; dados sintéticos. Nenhum `migrate:fresh`, `db:wipe` ou `down -v` foi
executado.

| Critério | Comando/evidência | Resultado |
|---|---|---|
| Dependências PHP travadas | `cp .env.example .env`; `bash scripts/bootstrap-local.sh` | 129 installs, 0 updates/removals; `vendor/autoload.php` criado; Compose válido |
| Runtime do bootstrap | imagem `composer:2.8.12` | PHP 8.4.14; `mbstring` e `zip` carregadas |
| Runtime da aplicação | `php -r` em `laravel.test` | PHP 8.4.26; `pdo_mysql`, `mbstring` e `zip` carregadas |
| Compose/MySQL | `docker compose ... up -d --build`; `docker compose ... ps` | MySQL 8.0.46 saudável; aplicação ativa |
| Banco de teste | consulta como usuário configurado | `ceara_spec000_testing` existe; acesso por `sail@%` confirmado |
| APP_KEY isolada | `php artisan key:generate --force` | concluído somente no `.env` ignorado do worktree |
| Migrations | `php artisan migrate --force`; `migrate:status` | 3 migrations `Ran` |
| Composer | `composer validate --strict`; `composer audit` | manifesto válido; nenhum advisory |
| Node/assets | `npm ci --ignore-scripts`; `npm audit --audit-level=high`; `npm run build` | 97 pacotes; 0 vulnerabilidades; manifest, CSS, JS e fontes gerados |
| Pint | `vendor/bin/pint --test app config routes tests bootstrap/app.php` | 21 arquivos passaram |
| Pest | `php artisan test` | 6 testes, 19 assertions passaram |
| HTTP | `curl` em `/` e `/up` | 200 e 200 |
| Assets | `curl` no CSS indicado pelo manifest | 200 |
| Headers | `curl -I /` | nosniff, SAMEORIGIN, Referrer-Policy e Permissions-Policy presentes |
| Superfície pública | `curl` em `/.env`, `/composer.json`, `/.git/HEAD` | 404, 404 e 404 |
| Storage privado | arquivo sintético em `storage/app/private`; `curl /storage/...` | 403 |
| Segredos | diff, rastreamento Git e `public/build` examinados | nenhum secret real encontrado; `.env` permaneceu ignorado |

O primeiro `npm audit` no sandbox falhou por DNS. A execução foi repetida com rede
autorizada e passou. O build acessou `fonts.bunny.net`, conforme o contrato documentado.

## Limites

HostGator, P62, P65, P68, PHP remoto, cPanel, DNS, HTTPS público, e-mail, cron, FTPS,
backup/restore e storage remoto não foram executados nem alterados. A viabilidade remota
continua pendente. O BUG-QA-001 está corrigido pelo DEV e aguarda reteste independente no
SHA final do PR complementar.
