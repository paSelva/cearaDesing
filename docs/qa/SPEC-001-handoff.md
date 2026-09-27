# Handoff QA — SPEC-001

**Status:** pronta para revisão QA independente; a validação DEV abaixo não é aprovação QA.

**Branch:** `feature/spec-001-catalogo-admin`

**Base planejada:** `09659c8a2ace8b43d6f7e7855674b6a28a092614` (`origin/develop`, inclui BUG-QA-001).

**SHA da implementação exercitada:** `f3352053b47cbd6af008639087e64236cc0103f4` (o commit seguinte desta entrega altera somente documentação/handoff).

**QA deve revisar:** o HEAD remoto mais recente desta branch, informado no relatório final.
**Worktree DEV:** isolado em `/private/tmp/ceara-spec001-worktree`; o worktree original e seus arquivos alheios não foram alterados.

## Ambiente isolado usado pelo DEV

- Docker Compose project `ceara_spec001_dev`, aplicação em `localhost:18081`, MySQL 8.0 em porta host `33360`, volume próprio `ceara_spec001_dev_sail-mysql`, DB local `ceara_spec001_local` e DB Pest `ceara_spec001_testing`.
- Runtime PHP 8.4 e extensões `intl`, `mbstring`, `pdo_mysql`, `zip`; Composer 2.8.12. Resolução instalou Laravel 13.33.0, Filament 5.9.0, Livewire 4.4.6, Pest 5 (versões travadas no lockfile). Node v24.12.0/npm 11.6.2; Vite 8.3.1.
- `APP_KEY` foi gerada somente no `.env` temporário deste worktree. `MAIL_MAILER=log`; credenciais de DB são exemplos sintéticos de desenvolvimento e não devem ser reutilizadas.
- Banco inteiramente descartável e exclusivo do projeto Compose acima. Não executar comandos destrutivos contra qualquer outro destino. Não rodar `migrate:fresh`, `db:wipe` ou `down -v`.

## Reprodução sugerida pelo QA

Use checkout/worktree limpo no SHA deste handoff. Confirme branch e SHA antes de começar; configure portas próprias caso estejam ocupadas.

```bash
cp .env.example .env
# No .env local (nunca versionar): APP_URL=http://localhost:18081; APP_PORT=18081;
# DB_DATABASE=ceara_spec001_local; DB_TEST_DATABASE=ceara_spec001_testing;
# DB_HOST=mysql; DB_USERNAME=sail; DB_PASSWORD=<somente valor sintético local>;
# FORWARD_DB_PORT=33360; MAIL_MAILER=log
bash scripts/bootstrap-local.sh
docker compose -p ceara_spec001_dev up -d --build
docker compose -p ceara_spec001_dev exec -T laravel.test php artisan key:generate --force
docker compose -p ceara_spec001_dev exec -T laravel.test php artisan migrate --force
docker compose -p ceara_spec001_dev exec -T laravel.test php artisan db:seed --force
npm ci --ignore-scripts
npm run build
docker compose -p ceara_spec001_dev exec -T laravel.test php artisan test
docker compose -p ceara_spec001_dev exec -T laravel.test vendor/bin/pint --test app bootstrap database tests
```

Node/npm executam no host; Composer e comandos PHP dentro do ambiente Docker descrito pelo bootstrap/Compose. Composer instala exatamente `composer.lock`; npm usa `package-lock.json`. O build busca Instrument Sans de `fonts.bunny.net` (é necessário acesso à rede; falha de rede pode bloquear somente o build de assets).

Para validar a UI, crie conta sintética usando o CLI (senha oculta, nunca enviar a senha para QA/chat):

```bash
docker compose -p ceara_spec001_dev exec laravel.test php artisan app:create-admin
```

Entre em `http://localhost:18081/admin`. Confira dashboard com 9 categorias/36 critérios; tabelas de categorias e critérios; abra a edição e ajuste um campo inocuo, salve, recarregue para comprovar persistência e restaure o valor. Faça logout. Confira no navegador que cadastro `/register` inexiste. Repita requests ao painel autenticando usuários sintéticos com cada uma das três roles não admin para confirmar 403. Os testes automatizados exercitam as quatro roles e as Policies.

## Evidência executada pelo DEV

| Verificação | Resultado observado | Ambiente/critério |
|---|---|---|
| `bash scripts/bootstrap-local.sh` | Exit 0; Composer reportou dependências travadas válidas e Compose válido | Docker local; instalação reproduzível |
| `docker compose -p ceara_spec001_dev up -d --build` | exit 0; containers app/MySQL iniciados; MySQL healthy | Imagem PHP 8.4 construída e rede/volume isolados |
| `php artisan key:generate --force`, `migrate --force`, `db:seed --force`, `migrate:status` | todos exit 0; cinco migrations `[1] Ran`, incluindo role e catálogo; seeder concluiu | Banco local descartável MySQL 8.0.46 |
| Query `information_schema.schemata` | versão 8.0.46 e banco `ceara_spec001_testing` presente (contagem 1) | MySQL isolado; database de testes criado pelo script existente |
| `npm ci --ignore-scripts` | exit 0, 97 pacotes adicionados | Host Node v24.12.0/npm 11.6.2 |
| `npm run build` | exit 0, `public/build/manifest.json` e assets emitidos; fonte Instrument Sans baixada | Host, conectividade Bunny disponível; aviso opcional de `fontaine` |
| `php artisan test --compact` (após build; execução final) | 29 testes, 80 assertions, todos passaram | Pest contra MySQL 8.0.46, DB de teste isolado |
| `vendor/bin/pint --test app bootstrap database tests` | passou após formatter `pint app bootstrap database tests` | Container PHP; formatter restrito ao código da aplicação/testes, sem modificar skills |
| navegador local | login e logout exercitados; painel mostra contagens 9/36; tabelas mostram 9 e 36 registros; configuração foi alterada, recarregada (persistiu) e restaurada | Navegador Playwright em `localhost:18081`; conta somente sintética neste banco local |
| HTTP `GET /`, `GET /up` | antes do build: 500 em `/` por falta do manifest; após `npm run build`, suíte HTTP passou para `/` e `/up`; `/up` respondeu 200 no servidor local | App no Docker; falha foi corrigida pela etapa de build esperada |
| Policies/roles/CLI | suíte cobre visitante redirect, admin 200, outras roles 403, Policy de catálogo read-only, ausência de `/register`, papel não mass-assignable, senha hasheada/validação | Teste Pest no DB de teste; administrador de UI criado por prompt oculto |

O primeiro `pint --test` sem escopo apresentou 8 avisos: 6 em arquivos da aplicação corrigidos pelo Pint e 2 em exemplos dentro de `skills/testing/references/examples/`, que são instruções versionadas não pertencentes a esta SPEC. A verificação final excluiu skills e passou.

## Segurança e escopo QA

- `.env`, `vendor/`, `node_modules/`, `public/build/`, `.git` e caches locais são ignorados/não fazem parte da entrega. Revisar diff e artefatos antes de compartilhar.
- `APP_KEY` local é somente de desenvolvimento; nunca copiar para QA/prod. Não use `.env.example` como configuração pronta para deploy.
- Não foi usado envio de email real; cadastro público de usuários/admin permanece fora do escopo. Role é definida no servidor e excluída do fillable.
- Categorias/critério só são consulta; não existe ação de criação/edição/remoção no painel e Policies negam diretamente essas operações.
- Verifique configuração das sessões, CSRF e autorização por Policy na revisão; não considere menus ocultos como autorização.

## Limites e pendências

- HostGator, cPanel, cron, domínio/DNS/HTTPS e deploy não foram acessados nem alterados. Viabilidade remota permanece não verificada; P62/P65/P68 (hostname, MySQL do plano e raiz pública) seguem bloqueadores de deploy, não do desenvolvimento local.
- O ambiente DEV confirmou MySQL 8.0.46; não comprova que a versão real de produção é compatível (P65).
- A prova de interface usou Chromium/Playwright local. O QA independente deve repetir a revisão em worktree e banco isolados, sem assumir validade do resultado DEV.
- Após confirmar SHA, preencher o SHA alvo no topo e preservar a base registrada.
