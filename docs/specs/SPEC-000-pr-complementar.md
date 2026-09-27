# PR — SPEC-000: fundação local e prova técnica

## Problema e objetivo

O PR #1 documentou a viabilidade, mas não havia aplicação, lockfiles, banco ou prova executável. Esta complementação cria somente a fundação Laravel 13/PHP 8.4 e verifica localmente dependências, HTTP, assets, MySQL, migrations, storage e higiene de release.

## Escopo

- Laravel 13 mínimo, Pest 5, Sail disponível e Compose PHP 8.4/MySQL 8.0 isolado.
- Middleware de headers de segurança e rota `/up` do framework.
- Página técnica mínima e build Vite/Tailwind.
- Testes de saúde, headers, arquivos sensíveis e storage privado.
- CI com PHP 8.4, MySQL 8.0, Composer/npm audit, build, Pint e Pest.
- Runbook/handoff/matriz com evidências e limitações.

Não há inscrição, júri, votação, premiação, Fortify, Livewire ou Filament nesta SPEC.

## Validações

- `composer validate --strict` e `composer audit`: passaram, sem advisories.
- `npm ci`, `npm audit --audit-level=high` e `npm run build`: passaram; 0 vulnerabilidades.
- `vendor/bin/pint --test app config routes tests bootstrap/app.php`: passou (21 arquivos).
- `php artisan test`: 6 testes, 19 assertions, todos passaram em MySQL 8.0.46 descartável.
- `/` e `/up`: HTTP 200; assets CSS: HTTP 200.
- `/.env`, `/composer.json`, `/.git/HEAD`: HTTP 404; arquivo privado: HTTP 403.
- Pacote em `/tmp` derivado do SHA sem `.env`, `.git`, dumps, backups ou logs; contém vendor/assets.

O Pint global ainda encontra dois exemplos PHP preexistentes em `skills/testing/references/examples/`; o escopo da aplicação passou e as skills não foram alteradas.

## Segurança, risco e rollback

Secrets permanecem fora do Git e do bundle; `.env.example` tem somente valores fictícios/vazios. A raiz pública é `public/`, storage local é privado, e nenhuma rota executa Artisan. Rollback local: parar o projeto Compose; rollback de código: restaurar o commit anterior. Não houve alteração remota/produção.

## Limitações e revisão

A HostGator não foi acessada. P62/P65/P68, PHP web/CLI, cron, HTTPS, e-mail, FTPS/cPanel, Turnstile, backup/restore e storage remoto ainda exigem QA autorizado. O QA deve revisar em worktree separado sobre o SHA final deste PR; não considerar este PR aprovado pelo teste do autor.
