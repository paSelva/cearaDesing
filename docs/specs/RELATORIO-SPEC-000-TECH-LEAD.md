# Relatório técnico — SPEC-000

## Resumo executivo

A SPEC-000 — Diagnóstico, preparação técnica e viabilidade operacional — foi complementada com a implementação e validação da fundação local do projeto Ceará Tem Design 2026.

A aplicação Laravel mínima foi criada, as dependências foram travadas, o ambiente local foi executado com PHP 8.4 e MySQL 8.0 descartável, os assets foram compilados, migrations foram executadas, testes foram adicionados e o CI foi ampliado.

A viabilidade remota na HostGator ainda não foi comprovada. Não houve alteração em produção, DNS, cPanel ou serviços pagos.

## Identificação

| Item | Valor |
|---|---|
| Repositório | `paSelva/cearaDesing` |
| Branch de integração | `develop` |
| Branch da SPEC | `chore/spec-000-fundacao-local` |
| Base do diff | `a3dd6df` |
| Último SHA de implementação | `14c43be6bda6f8296a4f877bf2dfc4967ca9f23c` |
| SHA final do relatório/PR | Confirmado no PR após este commit documental |
| Pull Request | [PR #2](https://github.com/paSelva/cearaDesing/pull/2) |
| Estado do PR | Aberto, sem merge |
| Checks remotos | 3/3 aprovados |
| QA independente | Ainda não executado |

## Estado inicial

O PR #1 da SPEC-000 anterior já estava mergeado em `develop`. O repositório continha documentação, skills, CI documental e runbook, mas não possuía aplicação Laravel, `artisan`, manifests, lockfiles, migrations ou testes de aplicação.

Foi identificado também o arquivo local preexistente `docs/workflow-ceara-design.md`. Ele foi preservado e não foi incluído no diff.

## Skills aplicadas

- `code-best-practices`: fundação Laravel mínima, commits atômicos e separação de responsabilidades.
- `github-security`: permissões mínimas, Actions fixadas por SHA e secret detection.
- `security-audit`: headers, superfície pública, debug local e deploy sem SSH.
- `security-backend`: middleware global, rota de saúde e ausência de rotas secretas para Artisan.
- `security-database`: MySQL isolado, migrations existentes e proteção contra uso de banco compartilhado.
- `security-frontend`: Blade escapado, Vite sem secrets e storage privado.
- `testing`: Pest, testes de feature, teste arquitetural, MySQL e serviços externos isolados.

Detalhes e evidências estão em `docs/specs/SPEC-000-complementacao-local.md`.

## Implementação realizada

- Laravel `13.33.0` com requisito PHP `^8.4`.
- Pest `5.2.1` e plugin Laravel `5.0.1`.
- Composer 2.8.12 no ambiente de instalação.
- Node/npm com `package-lock.json`.
- Compose local com PHP 8.4 mínimo e MySQL 8.0.
- Middleware `AddSecurityHeaders` com:
  - `X-Content-Type-Options`;
  - `X-Frame-Options`;
  - `Referrer-Policy`;
  - `Permissions-Policy`;
  - HSTS somente em requisições HTTPS.
- Página pública mínima do prêmio.
- Endpoint de saúde `/up` do Laravel.
- Configuração de timezone `America/Fortaleza` e locale `pt_BR`.
- Discos Laravel separados, com o disco local apontando para `storage/app/private`.
- Nenhum módulo de inscrição, votação, júri ou premiação foi implementado.

## Evidências locais

Ambiente utilizado:

- macOS;
- Docker 29.3.1;
- PHP 8.4.26 no container da aplicação;
- MySQL 8.0.46;
- Node 26.8.2 e npm 11.19.1 no host;
- Projeto Compose isolado `ceara_spec000`;
- Porta HTTP local 8081;
- Bancos descartáveis `ceara_spec000_local` e `ceara_spec000_testing`.

Resultados observados:

| Verificação | Resultado |
|---|---|
| `composer validate --strict` | Passou |
| `composer audit` | Sem advisories |
| `npm ci --ignore-scripts` | Passou |
| `npm audit --audit-level=high` | 0 vulnerabilidades |
| `npm run build` | Passou; manifest e assets gerados |
| Pint escopado à aplicação | 21 arquivos aprovados |
| Pest | 6 testes, 19 assertions aprovadas |
| Migrations | 3 migrations executadas |
| Página `/` | HTTP 200 |
| Endpoint `/up` | HTTP 200 |
| CSS compilado | HTTP 200 |
| `/.env` | HTTP 404 |
| `/composer.json` | HTTP 404 |
| `/.git/HEAD` | HTTP 404 |
| Arquivo privado em `/storage` | HTTP 403 |
| Secret detection no GitHub | Aprovado |
| Repository foundation no GitHub | Aprovado |
| Laravel application no GitHub | Aprovado |

## Testes implementados

`tests/Feature/FoundationTest.php` valida:

1. resposta da página inicial e do endpoint de saúde;
2. headers básicos de segurança;
3. ausência de exposição de `.env`, Composer e Git;
4. separação do storage privado.

`tests/ArchTest.php` valida presets PHP/security e ausência de funções de debug na aplicação.

## Segurança

- `.env` permanece ignorado e não foi versionado.
- `.env.example` contém apenas valores vazios ou fictícios.
- Nenhum secret foi colocado em `VITE_*`, frontend, HTML, estado público ou logs versionados.
- Nenhuma credencial foi incluída no relatório, commit ou PR.
- O storage privado não é servido pela superfície pública.
- Não foi criada rota HTTP para executar Artisan, migrations, shell ou `phpinfo`.
- A raiz pública da aplicação é `public/`.
- O pacote de release não contém `.env`, `.git`, dumps, backups ou logs.

## CI e Git

Commits da complementação:

- `20fa999` — `feat(fundacao): inicializa Laravel 13 com prova local`
- `f2d8066` — `ci(fundacao): valida Laravel MySQL e assets`
- `6dc1d72` — `docs(spec): registra evidencias da prova local`
- `14c43be` — `test(fundacao): preserva suite unit vazia`

O workflow agora executa PHP 8.4, MySQL 8.0, Composer, npm, auditorias, build, Pint, migrations e Pest. As Actions foram fixadas por SHA.

## Deploy e viabilidade remota

O deploy remoto não foi executado por ausência de ambiente QA HostGator autorizado. Permanecem pendentes:

- hostname/subdomínio — P62;
- versão real do MySQL — P65;
- document root apontando para `public/` — P68;
- PHP web e PHP CLI reais;
- cron e limite mínimo de 15 minutos;
- HTTPS e cookies seguros;
- e-mail transacional;
- transporte FTPS ou cPanel HTTPS;
- backup e restauração;
- storage remoto e quotas;
- configuração real do Turnstile.

O runbook atualizado está em `docs/runbooks/ambiente-deploy-sem-ssh.md`.

## Handoff para QA

O QA deve revisar em worktree separado usando o SHA final informado no PR #2 após este commit documental. O último SHA de implementação antes deste relatório é:

```text
14c43be6bda6f8296a4f877bf2dfc4967ca9f23c
```

Base do diff:

```text
a3dd6df
```

Comandos principais:

```bash
git fetch origin
# substitua <SHA_FINAL_DO_PR> pelo SHA confirmado no PR #2
git worktree add --detach ../ceara-qa-spec-000 <SHA_FINAL_DO_PR>
cd ../ceara-qa-spec-000
cp .env.example .env
docker compose -p ceara_qa_spec000 up -d --build
docker compose -p ceara_qa_spec000 exec -T laravel.test php artisan key:generate --force
docker compose -p ceara_qa_spec000 exec -T laravel.test php artisan migrate --force
docker compose -p ceara_qa_spec000 exec -T laravel.test php artisan test
```

O QA deve usar banco, storage, fila, e-mail e porta exclusivos. O parecer do QA deve ser independente; esta execução não representa aprovação.

## Parecer final

**Seções 4, 8 e 9 concluídas no escopo autorizado local.**

A preparação técnica local está concluída e comprovada. A viabilidade remota e a publicação em produção permanecem pendentes das confirmações operacionais da HostGator e da organização.

Não iniciar a SPEC-001 antes da revisão independente do QA e da resolução das pendências remotas que afetem o próximo marco.
