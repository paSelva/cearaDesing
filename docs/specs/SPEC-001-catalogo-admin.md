# SPEC-001 — Catálogo do prêmio e fundação administrativa

Status: implementação DEV em `44b0d335f55d1d1f4a2509498de19911d324b5d0`, aguardando revisão independente do QA. Base: `09659c8a2ace8b43d6f7e7855674b6a28a092614` (`origin/develop`). Escopo exclusivo desta branch: `feature/spec-001-catalogo-admin`.

## Objetivo e escopo

Preparar o domínio do catálogo oficial 2026 e um painel administrativo utilizável para consultar categorias/critérios e alterar os parâmetros da edição. Esta SPEC não cria inscrições, votos, notas, atribuições de jurados ou publicação de resultados.

### Critérios verificáveis

1. Migrações criam usuários com papel tipado e catálogo relacional com integridade/índices.
2. `db:seed` cria uma edição, nove categorias do §3 do edital e quatro critérios por categoria, somente 3.7 marcada como exclusiva para estudantes.
3. Repetir o seed não duplica registros nem substitui configurações editadas pela organização.
4. Login/logout do Filament funciona em `/admin`; só `admin` acessa o painel. As roles `jurado`, `participante` e `eleitor` são recusadas.
5. Categorias e critérios podem ser consultados, mas não criados/alterados/excluídos; configuração da edição pode ser editada e persiste.
6. Datas fora de ordem, quantidade de jurados não positiva e faixas fora de ordem/acima do máximo são recusadas pela validação de domínio no servidor.
7. Não existe cadastro público de administradores nem atribuição de papel por preenchimento público.

## Regras, decisões e perguntas

- **Edital:** nome/ano da edição (§1); elegibilidade de projetos e categorias oficiais (§2–§3); avaliação por critérios e escala (quatro critérios, nota de 1 a 5, máximo de 60 com três jurados) (§5); faixas (§6); períodos e datas informativas (§7). Categorias/descrições reproduzidas de §3 do PDF fornecido localmente; textos originais estão no seeder. O edital local não foi incluído no Git.
- **Decisão confirmada:** uma edição 2026 (P32); campo `role` em `users` (T2), papéis admin/jurado/participante/eleitor; 3.7 é exclusiva para estudantes (P9); 3 jurados e faixas iniciais correspondentes ao edital/modelo.
- **Convenção técnica desta SPEC:** campos datetime de inscrição, avaliação e votação usam `America/Fortaleza`, início em 00:00:00 e término em 23:59:59 da data. Datas informativas/elegibilidade são `date`. A convenção foi escolhida para consistência da interface e não cria regra de negócio adicional no edital.
- **Recomendação técnica aplicada:** Laravel convencional; Actions para alterações; Policies no servidor; enum PHP para papéis; índices/FKs no MySQL; seed com `firstOrCreate`, de modo a preservar alterações existentes.
- **Em aberto, não alterado:** o edital não prevê voto popular. A decisão organizacional sobre voto popular e seus parâmetros permanece pendente (P34–P41); nada de votação foi implementado. P9 define o vínculo da categoria 3.7 a estudantes, mas outras consequências de elegibilidade (bloqueio de estudantes fora da 3.7, prova de vínculo e operação de validação) devem ser formalizadas antes do fluxo de inscrição. `is_student_only` é dado informativo do catálogo, não validação de inscrição.

## Dados e operações

- Migração `add_role_to_users_table`: role MySQL enum, default participante; role não é mass-assignable.
- Migração `create_prize_catalog_tables`: `editions`, `categories`, `criteria`, FKs restritivas, unicidade do ano/código/slug e da posição dentro da categoria.
- Seeder `EditionSeeder`: uma edição; períodos 28/09–28/10 inscrição, 29/10–20/11 avaliação/votação; datas informativas 21/11 e 26/11; projetos elegíveis 01/01/2022–28/10/2026; 3 jurados; faixas 54/48/42/36.
- `CreateAdminUser` e CLI `app:create-admin`: prompt de senha oculto, mínimo de 12 caracteres, hash Laravel, sem senha default.
- `UpdateEditionSettings`: validação reutilizável; mantém ano e `results_published_at` fora de entrada editável.

## Acesso

| Recurso | Visitante | Admin | Jurado/participante/eleitor |
|---|---|---|---|
| `/admin` | redireciona para login | permite | 403 |
| Configuração da edição | login obrigatório | consultar/editar somente a edição 2026 | bloqueado |
| Categorias/critérios | login obrigatório | consultar | bloqueado |
| Criar/alterar/excluir catálogo | — | negado por Policy | negado |
| Criar admin | somente operador via CLI | não há cadastro web | não há cadastro web |

## Evidência DEV

Handoff com comandos, resultados, ambiente isolado e lacunas: [`docs/qa/SPEC-001-handoff.md`](../qa/SPEC-001-handoff.md). A execução local não equivale a aprovação independente nem prova a compatibilidade com HostGator.
