# Relatório executivo — SPEC-001

**Projeto:** Ceará Tem Design 2026

**SPEC:** Catálogo do prêmio e fundação administrativa

**Estado:** implementação DEV concluída; aguardando revisão independente do QA
**PR:** [#4 — feat(admin): catálogo e painel da edição 2026](https://github.com/paSelva/cearaDesing/pull/4) (aberto para `develop`, sem merge)

## Resumo

A SPEC-001 implementa o domínio do catálogo oficial da edição 2026 e o painel organizacional em `/admin`. O painel permite consultar edição, categorias e critérios, visualizar contagens reais e editar parâmetros da edição. Inscrições, avaliação operacional, voto popular, publicação de resultados e premiação permanecem fora do escopo.

## Implementado

- Enum PHP `Role` e papel `role` na tabela de usuários, com `admin`, `jurado`, `participante` e `eleitor`.
- Models, relacionamentos e migrations de `editions`, `categories` e `criteria`, com integridade referencial e índices únicos.
- Seeder repetível para uma edição 2026, nove categorias do edital e 36 critérios; a repetição não duplica nem sobrescreve configurações existentes.
- Filament 5 no caminho `/admin`, com login/logout e acesso exclusivo a `admin`.
- Dashboard com contagens reais, consulta de categorias/critérios e edição persistente da configuração da edição.
- Policies bloqueiam mutações de catálogo, restringem recursos ao papel admin e validam objetos da edição 2026.
- Ações reutilizáveis para criação do administrador via CLI e alteração validada dos parâmetros.

O primeiro admin é criado pelo comando `php artisan app:create-admin`: a senha é digitada em prompt oculto e armazenada com hash. Não há cadastro público de administradores nem campo público para promoção de papel.

## Decisões e limites

- Categorias/descrições foram transcritas do §3 do edital fornecido localmente. O arquivo do edital não foi adicionado ao Git por ser um artefato local preexistente; deve ser disponibilizado separadamente ao QA para conferência da fonte.
- A edição atende somente 2026. Somente a categoria 3.7 tem `is_student_only = true` conforme P9. O fluxo e outras consequências de elegibilidade estudantil não foram implementados nem inferidos.
- Os campos de data/hora dos períodos usam `America/Fortaleza`, com início às 00:00:00 e término às 23:59:59. Isso está documentado como convenção técnica, não como nova regra do edital.
- Voto popular não consta no edital; a decisão e as regras seguem pendentes (P34–P41). Nenhuma funcionalidade de voto foi criada.
- Nenhuma alteração foi feita em HostGator, cPanel, DNS, produção ou serviços pagos. A compatibilidade com o MySQL real da hospedagem e a viabilidade do deploy permanecem não verificadas (P62/P65/P68).
- As inscrições do edital iniciam em 28/09/2026. A pendência organizacional P2 é urgente para definir o procedimento durante a conclusão do sistema.

## Validação DEV

Ambiente isolado `ceara_spec001_dev`, PHP 8.4, Composer 2.8.12, MySQL 8.0.46, Node 24.12.0 e npm 11.6.2. Dependências instaladas pelos lockfiles: Laravel 13.33.0, Filament 5.9.0, Livewire 4.4.6 e Vite 8.3.1.

- `composer validate --strict`: passou.
- `bash scripts/bootstrap-local.sh`: passou; dependências travadas verificadas e Compose validado.
- `docker compose ... up -d --build`: containers locais iniciados, MySQL saudável.
- Migrations, `db:seed` e `migrate:status`: passaram no banco descartável exclusivo.
- `npm ci --ignore-scripts` e `npm run build`: passaram; os assets compilados foram servidos. O build requer acesso a `fonts.bunny.net`.
- `php artisan test --compact`: 29 testes e 82 assertions passaram.
- `vendor/bin/pint --test app bootstrap database tests`: passou.
- Navegador local: login/logout, dashboard, consultas de 9 categorias e 36 critérios e persistência da edição foram verificados.
- Endpoints locais `/` e `/up`: HTTP 200 após a compilação dos assets.
- Verificados controle de acesso, restrições de mutação, ausência de cadastro público, proteção de arquivos privados herdada da fundação e ausência de arquivos locais `.env`/artefatos gerados no diff.

Esses resultados são evidência DEV e não substituem a aprovação independente do QA nem comprovam operação em produção.

## Git e handoff

- **Branch:** `feature/spec-001-catalogo-admin`
- **Base do diff:** `09659c8a2ace8b43d6f7e7855674b6a28a092614` (`develop`, inclui a correção do BUG-QA-001)
- **SHA funcional exercitado:** `44b0d335f55d1d1f4a2509498de19911d324b5d0`
- **PR:** https://github.com/paSelva/cearaDesing/pull/4
- **Instruções reproduzíveis para QA:** [SPEC-001-handoff.md](../qa/SPEC-001-handoff.md)

Este relatório é uma atualização documental posterior ao SHA funcional exercitado; para reteste, use o HEAD remoto atual do PR #4, confirmado no GitHub, e confira o SHA no relatório de entrega associado.

## Próxima etapa

Submeter o HEAD do PR #4 à revisão QA independente. Após o parecer, a próxima etapa funcional planejada é cadastro/login de participante e, em seguida, o fluxo de inscrições. Não iniciar outra feature antes do reteste/decisão do responsável sobre P2 e das pendências explícitas de escopo.
