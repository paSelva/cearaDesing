# SPEC-006 — Júri: jurados da categoria, atribuição e substituição

Status: implementação DEV concluída, aguardando revisão independente do QA. Base: `feature/spec-005-comprovacao` (PR #8, sem merge). Branch: `feature/spec-006-juri`.

## Objetivo e escopo

Preparar a avaliação pela organização: definir os jurados fixos de cada categoria (P14), atribuí-los às inscrições aprovadas (P15, P76) e substituir um jurado numa inscrição em caso de conflito (§8, P16, P47). Acompanhar o andamento na lista de inscrições.

**Fora do escopo (SPEC-007):** painel `/jurado`, notas, envio da avaliação, cálculo do resultado, correção de nota. **Fora do escopo (sem decisão):** remover uma atribuição sem substituir; mini bio e foto do jurado (P59).

### Critérios verificáveis

1. Em Catálogo → Categorias, a ação "Definir jurados" escolhe até `judges_per_entry` jurados (3 pelo §5). A coluna "Jurados fixos" mostra a lista. Os dados da categoria continuam somente leitura.
2. Na página da inscrição (`/admin/inscricoes/{id}`), a seção "Jurados" lista as atribuições e tem "Atribuir jurados da categoria" (só para aprovada).
3. A atribuição completa até `judges_per_entry` atribuições não substituídas, pulando quem já está ou esteve na inscrição; se faltarem jurados na categoria, nada é criado e aparece o motivo.
4. "Atribuir jurados aos aprovados" (lista de inscrições) aplica isso a todas as aprovadas incompletas de uma categoria e informa quantas receberam, quantas já estavam completas e quais falharam.
5. "Substituir" (por atribuição) exige motivo e um jurado que ainda não esteve na inscrição; a antiga vira "Substituído" (notas guardadas para histórico) e a nova fica pendente.
6. A coluna "Avaliações" da lista mostra enviadas/jurados ativos das aprovadas (ex.: 0/3).
7. Jurados fixos, atribuições e substituições ficam no activity log (`juri`).
8. Só o admin gerencia o júri; jurado, participante e eleitor recebem 403.

## Regras, decisões e perguntas

- **Edital:** 3 jurados por projeto (§5); substituição em caso de conflito (§8).
- **Decisões confirmadas:** P14, P15, P16, P47, P76.
- **Recomendações aplicadas:** limite de jurados lido de `editions.judges_per_entry` (editável no admin, §5); motivo obrigatório na substituição (docs/04); o jurado substituído não pode voltar à mesma inscrição (índice único `(entry_id, judge_id)`).
- **Leitura adotada:** mudar os jurados fixos da categoria não altera atribuições já feitas.

## Dados e operações

- Migration única `create_jury_tables`: `category_judges`, `judge_assignments`, `scores` (com `CHECK 1–5` como reforço no MySQL ≥ 8.0.16; ⚠️ P65) e `score_revisions`. As duas últimas são usadas na SPEC-007.
- Enum `AssignmentStatus`; models `JudgeAssignment`, `Score`, `ScoreRevision` (nenhum campo por atribuição em massa); `Category::judges()`.
- Actions `SetCategoryJudges`, `AssignJudgesToEntry`, `AssignJudgesToCategory`, `ReplaceJudgeOnEntry` (+ `JuryGuard`), com trava de linha.
- `JudgeAssignmentPolicy` (admin); relation manager `JudgeAssignmentsRelationManager`; com duas seções, a página da inscrição passa a mostrar abas (Jurados, Comprovações).

## Pendências e riscos

- **Nada está no ar:** P2 e deploy (P62, P65, P68). A avaliação começa em 29/10 (§7).
- A pilha de PRs sem revisão chega a 6 (#5 a #10).

## Evidência DEV

[`docs/qa/SPEC-006-handoff.md`](../qa/SPEC-006-handoff.md).
