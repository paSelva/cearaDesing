# Prêmio Ceará Tem Design — Plataforma de Inscrição e Avaliação

Sistema web do **1º Prêmio Ceará Tem Design 2026** (Associação Ceará Design).

> **Escopo:** o edital (`anexos/EDITAL FINAL_PREMIO CEARA TEM DESIGN.pdf`) prevê **avaliação por jurados**: 3 jurados por projeto, 4 critérios, notas de 1 a 5, máximo de 60 pontos (§5). Além disso, haverá **voto popular** (confirmado em 26/09, P1). Ele não está no edital, e as regras dele ainda estão em aberto (P34–P41).

## Stack

- **Definido:** PHP + Laravel.
- **Definido:** PHP ≥ 8.4.1 · Percona Server 5.7.44-48 (validado) ou MySQL 8 (histórico local) · auth do participante e do eleitor em páginas próprias (Fortify).
- **Recomendado:** Laravel 13 + Filament 5 (admin e jurado) + Blade/Livewire 4/Tailwind 4 + Pest.
- **Hospedagem:** cPanel; banco confirmado como Percona Server 5.7.44-48. PHP Web/CLI,
  Document Root, Cron e Terminal/SSH precisam ser confirmados na conta real.
- **A definir:** domínio final, HTTPS e SMTP transacional.

Os detalhes estão em [docs/02-stack.md](docs/02-stack.md).

## Painel da organização (SPEC-001)

O painel Filament fica em `/admin`; somente usuários com o papel `admin` podem
autenticar. Crie o primeiro administrador pelo CLI local, com senha em prompt
oculto (não passe credenciais como argumentos nem as registre em logs):

```bash
docker compose -p ceara_spec001_dev exec laravel.test php artisan app:create-admin
```

Depois, acesse `http://localhost:18081/admin`. O catálogo e a configuração são
semeados com `php artisan db:seed`; a repetição preserva alterações feitas pela
organização. Consulte o [handoff da SPEC-001](docs/qa/SPEC-001-handoff.md) antes
de executar comandos que alterem bancos ou volumes.

## Área do participante (SPEC-002)

Cadastro em `/register`, login em `/login`, recuperação de senha em `/forgot-password` e
área em `/participante` (Fortify, páginas próprias — T1). O login do site aceita só o papel
`participante`; admin usa `/admin`. Localmente o e-mail de recuperação vai para o log
(`MAIL_MAILER=log`); em produção depende do provedor (⚠️ P31).

## Bootstrap local

Pré-requisitos no host: Docker com Compose e Node/npm compatível com o `package-lock.json`.
PHP e Composer nativos não são necessários: o bootstrap usa a imagem
`ceara-spec-composer:2.8.12-php84-intl`, independente dos containers da aplicação.
Ela contém PHP 8.4, Composer 2.8.12 e as extensões intl, mbstring, pdo_mysql e zip.

```bash
cp .env.example .env
# Ajuste APP_URL/APP_PORT, DB_DATABASE/DB_TEST_DATABASE e FORWARD_DB_PORT
# para valores livres e exclusivos antes de iniciar o projeto Compose.
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

O script de bootstrap instala as dependências do `composer.lock`, verifica os arquivos
necessários e valida a configuração Compose antes da primeira subida. O build baixa a
fonte Instrument Sans de `fonts.bunny.net` e, portanto, requer conectividade com esse host.
Use somente banco, volume, portas e `.env` isolados; nunca execute `migrate:fresh` ou
`db:wipe` em ambiente compartilhado ou de produção.

Variáveis locais relevantes: `APP_URL`, `APP_PORT`, `APP_KEY`, `DB_CONNECTION`,
`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_TEST_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`,
`FORWARD_DB_PORT`, `SESSION_DRIVER` e `MAIL_MAILER`. O fuso é configurado em
`config/app.php` (`America/Fortaleza`), não por variável de cliente. Nunca coloque
segredos em `VITE_*` nem versione `.env`.

## Documentação

1. [Escopo e regras do edital](docs/01-escopo-edital.md)
2. [Stack e tecnologias](docs/02-stack.md)
3. [Arquitetura](docs/03-arquitetura.md)
4. [Modelo de dados](docs/04-modelo-dados.md)
5. [Roadmap e cronograma](docs/05-roadmap.md)
6. [Perguntas em aberto](docs/06-perguntas-em-aberto.md) ← **o que ainda precisa ser respondido**
7. [Actions (casos de uso)](docs/07-actions.md)
8. [SPEC-001 — catálogo e painel administrativo](docs/specs/SPEC-001-catalogo-admin.md)
9. [Handoff reproduzível para QA](docs/qa/SPEC-001-handoff.md)
10. [SPEC-002 — cadastro e login do participante](docs/specs/SPEC-002-cadastro-participante.md) · [handoff QA](docs/qa/SPEC-002-handoff.md)
11. [SPEC-003 — inscrição de projetos](docs/specs/SPEC-003-inscricao.md) · [handoff QA](docs/qa/SPEC-003-handoff.md)
12. [SPEC-004 — triagem das inscrições](docs/specs/SPEC-004-triagem.md) · [handoff QA](docs/qa/SPEC-004-handoff.md)
13. [SPEC-005 — comprovação](docs/specs/SPEC-005-comprovacao.md) · [handoff QA](docs/qa/SPEC-005-handoff.md)
14. [SPEC-006 — júri: jurados da categoria, atribuição e substituição](docs/specs/SPEC-006-juri.md) · [handoff QA](docs/qa/SPEC-006-handoff.md)
15. [SPEC-007 — avaliação: painel do jurado, notas, resultado e correção](docs/specs/SPEC-007-avaliacao.md) · [handoff QA](docs/qa/SPEC-007-handoff.md)
16. [SPEC-008 — site público com o conteúdo do edital](docs/specs/SPEC-008-site-publico.md)
17. [SPEC-009 — cadastro de eleitor](docs/specs/SPEC-009-eleitor.md) · [handoff QA](docs/qa/SPEC-009-handoff.md)
18. [SPEC-010 — galeria, voto popular, parcial e apuração](docs/specs/SPEC-010-votacao.md)
19. [SPEC-011 — publicação do resultado](docs/specs/SPEC-011-resultado.md)

Convenção: **(§x)** = regra do edital · **(P#, confirmado)** = decisão do responsável que não está no edital · **Recomendação** = proposta técnica · **⚠️ A definir (P#)** = pergunta em aberto.

## Referência

- Site de referência: <https://www.premiobornancini.com.br/>, com o portal de inscrição separado em <https://www.inscricaopremiobornancini.com.br/>
- Edital fornecido pelo responsável: `docs/EDITAL FINAL_PREMIO CEARA TEM DESIGN.pdf` (arquivo local não versionado; disponibilizar ao QA por canal autorizado se necessário)

## Skills do Claude Code

A pasta [`skills/`](skills/) tem skills adaptadas a este projeto (Laravel, MySQL, HostGator): `code-best-practices`, `testing`, `github-security`, `security-audit`, `security-backend`, `security-database` e `security-frontend`. O link `.claude/skills → ../skills` faz o Claude Code carregá-las ao abrir o repositório.
