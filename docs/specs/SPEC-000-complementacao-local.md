# SPEC-000 — Complementação local das seções 4, 8 e 9

Execução: 27/09/2026. Branch: `chore/spec-000-fundacao-local`. Base do diff: `a3dd6df` (`develop`). Commits de implementação: `20fa999` e `f2d8066`. O workflow preexistente não rastreado `docs/workflow-ceara-design.md` foi preservado fora do diff.

Ambiente comprovado: macOS, Docker 29.3.1, container PHP 8.4.26, Composer 2.8.12, Node 26.8.2/npm 11.19.1 no host, MySQL 8.0.46 no projeto Compose `ceara_spec000`, porta HTTP isolada 8081 e bancos `ceara_spec000_local`/`ceara_spec000_testing`. O MySQL 8.0 é uma prova local; P65 (versão HostGator) permanece aberta.

## Skills aplicadas

| Skill | Aplicação | Evidência |
|---|---|---|
| `code-best-practices` | fundação Laravel sem módulos, configuração por ambiente e diff atômico | `composer.json`, `config/app.php`, commits `20fa999`/`f2d8066`; Pint escopado passou |
| `github-security` | actions fixadas por SHA, permissões mínimas, lockfiles e secret scan | `.github/workflows/foundation.yml`, `scripts/validate-foundation.sh` |
| `security-audit` | headers, debug apenas local, raiz pública e runbook sem SSH | `AddSecurityHeaders`, `compose.yaml`, runbook; smoke HTTP |
| `security-backend` | endpoint de saúde sem configuração, sem rota Artisan, middleware global | `bootstrap/app.php`, `routes/web.php`, `tests/Feature/FoundationTest.php` |
| `security-database` | MySQL isolado, migrations existentes, nenhum reset compartilhado | `compose.yaml`, `phpunit.xml`; `migrate`/`migrate:status` passaram |
| `security-frontend` | Blade escapado, Vite com somente `VITE_APP_NAME`, sem secrets no bundle | `welcome.blade.php`, `.env.example`; build e carregamento CSS 200 |
| `testing` | Pest 5/Laravel 5, MySQL em Feature tests, storage privado e headers testados | `tests/Pest.php`, `tests/Feature/FoundationTest.php`, `tests/ArchTest.php`; 6 testes/19 assertions passaram |

## Matriz de evidências — seções 4, 8 e 9

| Requisito | Situação | Arquivo/commit | Comando e resultado | Ambiente | Pendência |
|---|---|---|---|---|---|
| Diagnóstico e base Git atualizados | Executado e verificado. | branch `chore/spec-000-fundacao-local`, base `a3dd6df`; `20fa999` | `git fetch`, `git switch develop`, `git pull --ff-only`, `git switch -c`; sucesso; workflow não rastreado preservado | macOS/GitHub acessível | Nenhuma local |
| Fundação Laravel mínima | Executado e verificado. | `app/`, `bootstrap/`, `config/`, `routes/`, `public/`; `20fa999` | `composer create-project laravel/laravel:^13.0`; Laravel 13.33.0 criado | PHP 8.4.14 no container de criação | Módulos de negócio fora do escopo |
| Dependências reproduzíveis | Executado e verificado. | `composer.lock`, `package-lock.json`; `20fa999` | `composer install` no container; `npm ci --ignore-scripts`; ambos concluíram | Composer 2.8.12/PHP 8.4; npm 11.19.1 | Host sem PHP/Composer nativo; usar container ou instalar runtime |
| Manifests | Executado e verificado. | `composer.json`, `package.json`; `20fa999` | `composer validate --strict`: válido; `npm ci`: 98 pacotes, 0 vulnerabilidades | container Composer + Node 26 | Nenhuma local |
| Aplicação local e health check | Executado e verificado. | `routes/web.php`, `bootstrap/app.php`; `20fa999` | Compose + `curl http://127.0.0.1:8081/` → 200; `/up` → 200; HTML contém “Ceará Tem Design” | Compose `ceara_spec000`, porta 8081 | QA remoto não executado |
| Assets | Executado e verificado. | `resources/`, `vite.config.js`; `20fa999` | `npm run build`: Vite 8.3.1, manifest/assets gerados; curl do CSS → 200 | Node 26.8.2/npm 11.19.1 | Aviso não bloqueante: pacote opcional `fontaine` ausente |
| Banco descartável | Executado e verificado. | `compose.yaml`, `phpunit.xml`; `20fa999` | `docker compose -p ceara_spec000`; healthcheck MySQL saudável; `SELECT VERSION()` → 8.0.46 | container MySQL 8.0.46, volume Compose dedicado | P65 HostGator não confirmada |
| Migrations existentes | Executado e verificado. | `database/migrations/`; `20fa999` | `php artisan migrate --force`: 3 migrations DONE; `migrate:status`: 3 Ran | banco local `ceara_spec000_local` | Não executar `migrate:fresh` fora de DB descartável |
| Testes em banco isolado | Executado e verificado. | `tests/Feature/FoundationTest.php`, `phpunit.xml`; `20fa999` | criou `ceara_spec000_testing`; `php artisan test`: 6 passed, 19 assertions | MySQL 8.0.46, usuário sintético | QA deve criar banco próprio |
| Separação público/privado | Executado e verificado. | `config/filesystems.php`, `.gitignore`, teste de storage; `20fa999` | teste grava em `storage/app/private`; HTTP `/storage/...` → 403; filesystem começa em `storage/app/private` | app local | Downloads autorizados de módulos ainda não existem |
| Raiz pública e arquivos sensíveis | Executado e verificado. | `public/`, `public/.htaccess`; `20fa999` | curl `/.env`, `/composer.json`, `/.git/HEAD` → 404 | servidor Artisan em container | Confirmar document root cPanel P68 |
| Headers e HTTPS | Executado e verificado. | `app/Http/Middleware/AddSecurityHeaders.php`, teste; `20fa999` | Pest verificou nosniff, frame, referrer, permissions; HSTS só em request HTTPS | Laravel 13 local | Rever CSP quando Livewire/Filament entrarem |
| Estilo | Executado e verificado. | app/config/routes/tests/bootstrap; `20fa999` | `vendor/bin/pint --test app config routes tests bootstrap/app.php`: 21 files PASS | container PHP 8.4 | Pint global ainda encontra 2 exemplos preexistentes em `skills/` |
| Auditoria Composer/npm | Executado e verificado. | lockfiles; `20fa999` | `composer audit`: sem advisories; `npm audit --audit-level=high`: 0 vulnerabilidades | container Composer/Node host | Repetir em cada mudança de dependência |
| Gate documental/higiene | Executado e verificado. | `scripts/validate-foundation.sh`; `f2d8066` | `bash -n` + script: “Fundação documental e higiene ... validadas”; fallback Docker usado para Composer | macOS + Docker | CI remoto ainda precisa concluir no PR |
| Secrets no diff e artefato | Executado e verificado. | `.env.example`, `.gitignore`; `20fa999` | `git diff --check`; nomes proibidos ausentes; pacote `/tmp/ceara-spec000-release-20260927.tgz` sem `.env`, `.git`, dumps/backups/logs | local; artefato não enviado | Gitleaks do PR deve passar |
| Pacote de deploy | Executado e verificado. | pacote derivado de `f2d8066` (não versionado) | `git archive`; `composer install --no-dev`; `npm ci && npm run build`; tar 5.339.247 bytes; `vendor/`, `public/build/`, `artisan` presentes | `/tmp`, PHP 8.4/Node 26 | FTP/cPanel não executado |
| Runbook sem SSH | Já existia e foi verificado. | `docs/runbooks/ambiente-deploy-sem-ssh.md`; base `a3dd6df` | revisão de seções de pacote, raiz `public/`, cron mínimo 15 min, lock/rollback | documentação local | hostname/PHP CLI/cron reais pendentes |
| Deploy remoto | Não aplicável, com justificativa. | nenhum arquivo remoto; nenhum commit de produção | não há QA HostGator autorizado, hostname ou credencial; nenhuma chamada cPanel/DNS | fora do ambiente | comprovar somente em QA autorizado |
| Handoff QA | Executado e verificado. | `docs/qa/SPEC-000-handoff.md` será atualizado neste complemento | QA deve usar SHA final, worktree separado, Compose/banco/storage/porta/e-mail próprios | segundo terminal do QA | parecer independente ainda não emitido |

## Bloqueios delimitados

- HostGator: P62 (hostname), P65 (MySQL real), P68 (document root), PHP CLI/web, cron do plano, HTTPS, e-mail, FTPS/cPanel e restore continuam sem evidência remota. Isso bloqueia somente viabilidade remota/publicação.
- Host local: PHP/Composer não estão instalados no PATH; a prova foi concluída com Docker. Isso não bloqueia continuar, mas é pré-requisito para execução nativa.
- O Pint global falha em dois exemplos preexistentes dentro de `skills/testing/references/examples/`; o Pint escopado aos caminhos da aplicação passou. Nenhuma skill foi alterada.

## Parecer

As seções 4, 8 e 9 estão concluídas no escopo técnico local autorizado. A preparação local, execução Laravel, build, MySQL descartável, migrations, testes, segurança e pacote sanitizado foram verificados. A viabilidade remota permanece não comprovada e a publicação em produção segue NO-GO até as pendências HostGator/organização serem evidenciadas. A SPEC-001 não foi iniciada.
