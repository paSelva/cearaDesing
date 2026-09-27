# Handoff QA — SPEC-000 complementar

Branch: `fix/spec-000-bootstrap-limpo`
Base do diff: `9b2b26b` (`develop`, merge do PR #2)
SHA alvo: preencher com o SHA final informado no PR complementar
Histórico: PR #2 mergeado; SHA `14c43be` reprovado no BUG-QA-001
PR: complementar ao PR #2; não fazer merge durante a revisão

## Worktree isolado

```bash
git fetch origin
git worktree add --detach ../ceara-qa-spec-000 <SHA_ALVO>
cd ../ceara-qa-spec-000
test "$(git rev-parse HEAD)" = "<SHA_ALVO>"
git status --short --branch
```

O arquivo preexistente `docs/workflow-ceara-design.md` não faz parte do diff e deve permanecer fora da revisão.

## Pré-requisitos e ambiente

- Docker Desktop ativo; PHP/Composer nativos não são necessários. A instalação inicial usa `composer:2.8.12`, antes dos containers da aplicação.
- Node/npm compatíveis com `package-lock.json` (a prova local usou Node 26.8.2/npm 11.19.1).
- Nenhum `.env` de DEV/produção deve ser copiado.
- Use somente dados sintéticos, e-mail `log`/`array`, storage local e banco novo.

```bash
cp .env.example .env
bash scripts/bootstrap-local.sh
docker compose -p ceara_qa_spec000 up -d --build
docker compose -p ceara_qa_spec000 exec -T laravel.test php artisan key:generate --force
docker compose -p ceara_qa_spec000 exec -T laravel.test php artisan migrate --force
```

O bootstrap instala exatamente o `composer.lock` por uma imagem independente, confirma arquivos regulares obrigatórios e executa `docker compose config --quiet`. O script MySQL está versionado em `.docker/mysql/`; nenhum bind do Compose depende de `vendor/`.

Se a porta 8081 ou 33060 estiver ocupada, escolha portas locais exclusivas e ajuste `APP_URL`, `APP_PORT` e `FORWARD_DB_PORT` somente no `.env` ignorado. Ajuste também `DB_DATABASE` e `DB_TEST_DATABASE` para nomes exclusivos do reteste. O volume/projeto `ceara_qa_spec000` deve ser novo. Não usar `migrate:fresh`, `db:wipe`, `down -v` ou banco compartilhado.

Para recuperar uma tentativa anterior que criou o caminho do Sail como diretório, siga a seção “Recuperação do BUG-QA-001” do runbook. O caminho legado e o volume devem ser preservados; a configuração corrigida apenas recria os containers sem `-v`.

## Comandos de validação

```bash
bash -n scripts/validate-foundation.sh
bash scripts/validate-foundation.sh
docker compose -p ceara_qa_spec000 config --quiet
docker compose -p ceara_qa_spec000 ps
docker compose -p ceara_qa_spec000 exec -T mysql sh -lc 'MYSQL_PWD="$MYSQL_PASSWORD" mysql -u"$MYSQL_USER" -e "USE \`$MYSQL_TEST_DATABASE\`; SELECT DATABASE();"'
docker compose -p ceara_qa_spec000 exec -T laravel.test php artisan migrate:status
npm ci --ignore-scripts
npm audit --audit-level=high
npm run build
docker compose -p ceara_qa_spec000 exec -T laravel.test vendor/bin/pint --test app config routes tests bootstrap/app.php
docker compose -p ceara_qa_spec000 exec -T laravel.test php artisan test
curl -I http://127.0.0.1:8081/
curl -I http://127.0.0.1:8081/up
curl -I http://127.0.0.1:8081/.env
curl -I http://127.0.0.1:8081/composer.json
curl -I http://127.0.0.1:8081/.git/HEAD
```

Critérios mínimos: MySQL saudável; usuário configurado acessa `DB_TEST_DATABASE`; `/` e `/up` 200; `/.env`, `/composer.json` e `/.git/HEAD` 404; arquivo privado não é servido; headers de segurança presentes; migrations concluídas; assets do manifest respondem 200; sem secrets no diff/artefato.

`npm run build` acessa `fonts.bunny.net` para baixar Instrument Sans e requer conectividade HTTPS de saída. Registrar eventual bloqueio de rede separadamente; não trocar fontes ou atualizar dependências neste reteste.

## Variáveis por nome

`APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_URL`, `APP_PORT`, `APP_DEBUG`, `APP_LOCALE`, `APP_FALLBACK_LOCALE`, `APP_FAKER_LOCALE`, `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_TEST_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `FORWARD_DB_PORT`, `SESSION_DRIVER`, `SESSION_SECURE_COOKIE`, `SESSION_ENCRYPT`, `CACHE_STORE`, `QUEUE_CONNECTION`, `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY`, `CPF_HMAC_KEY`.

Não colar valores no PR ou relatório. `TURNSTILE_SECRET_KEY`, `CPF_HMAC_KEY`, `APP_KEY` e credenciais de banco são somente backend.

## Escopo e pendências

Esta prova não implementa inscrição, júri, votação, premiação, Fortify, Livewire ou Filament. O QA não deve aprovar produção: HostGator/P62/P65/P68, transporte, e-mail, cron real, backup/restore e storage remoto continuam sem prova. Registrar o parecer para o SHA exato; qualquer mudança exige novo SHA e novo teste.

O DEV corrigiu o BUG-QA-001, mas não o aprova. O parecer reprovado de `14c43be` permanece em `docs/qa/SPEC-000-14c43be.md`; este SHA aguarda reteste independente do bootstrap limpo e da regressão relevante.
