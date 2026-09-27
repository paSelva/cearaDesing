# Handoff QA — SPEC-000 complementar

Branch: `chore/spec-000-fundacao-local`
Base do diff: `a3dd6df` (`develop`)
SHA alvo: preencher após o commit final desta complementação
PR: complementar ao PR #1 mergeado; não fazer merge durante a revisão

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

- Docker Desktop ativo; PHP/Composer nativos não são necessários.
- Node/npm compatíveis com `package-lock.json` (a prova local usou Node 26.8.2/npm 11.19.1).
- Nenhum `.env` de DEV/produção deve ser copiado.
- Use somente dados sintéticos, e-mail `log`/`array`, storage local e banco novo.

```bash
cp .env.example .env
docker compose -p ceara_qa_spec000 up -d --build
docker compose -p ceara_qa_spec000 exec -T laravel.test php artisan key:generate --force
docker compose -p ceara_qa_spec000 exec -T laravel.test php artisan migrate --force
```

Se a porta 8081 estiver ocupada, escolha outra porta local e ajuste `APP_URL`/`APP_PORT` somente no `.env` ignorado. O banco padrão do Compose é isolado no volume/projeto `ceara_qa_spec000`; confirme o nome antes de qualquer reset. Não usar `migrate:fresh` ou `db:wipe`.

## Comandos de validação

```bash
bash -n scripts/validate-foundation.sh
bash scripts/validate-foundation.sh
npm ci --ignore-scripts
npm audit --audit-level=high
npm run build
docker compose -p ceara_qa_spec000 exec -T laravel.test vendor/bin/pint --test app config routes tests bootstrap/app.php
docker compose -p ceara_qa_spec000 exec -T laravel.test php artisan test
curl -I http://127.0.0.1:8081/
curl -I http://127.0.0.1:8081/up
```

Critérios mínimos: `/` e `/up` 200; `/.env`, `/composer.json` e `/.git/HEAD` 404; arquivo privado não é servido; headers de segurança presentes; migrations concluídas; assets do manifest respondem 200; sem secrets no diff/artefato.

## Variáveis por nome

`APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_URL`, `APP_PORT`, `APP_DEBUG`, `APP_LOCALE`, `APP_FALLBACK_LOCALE`, `APP_FAKER_LOCALE`, `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `FORWARD_DB_PORT`, `SESSION_DRIVER`, `SESSION_SECURE_COOKIE`, `SESSION_ENCRYPT`, `CACHE_STORE`, `QUEUE_CONNECTION`, `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY`, `CPF_HMAC_KEY`.

Não colar valores no PR ou relatório. `TURNSTILE_SECRET_KEY`, `CPF_HMAC_KEY`, `APP_KEY` e credenciais de banco são somente backend.

## Escopo e pendências

Esta prova não implementa inscrição, júri, votação, premiação, Fortify, Livewire ou Filament. O QA não deve aprovar produção: HostGator/P62/P65/P68, transporte, e-mail, cron real, backup/restore e storage remoto continuam sem prova. Registrar o parecer para o SHA exato; qualquer mudança exige novo SHA e novo teste.
