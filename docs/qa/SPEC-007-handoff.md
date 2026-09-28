# Handoff QA — SPEC-007

**Status:** pronta para revisão QA independente; a validação DEV abaixo não é aprovação QA.

**Branch:** `feature/spec-007-avaliacao` (parte de `feature/spec-006-juri`).

## Ambiente

O mesmo da SPEC-003 em diante. Sem migration nova (as tabelas vieram na SPEC-006).

## Roteiro manual

Pré-requisito: uma inscrição aprovada com 3 jurados atribuídos (SPEC-004 e SPEC-006) e o relógio/período de avaliação aberto (ajustar as datas de avaliação em `/admin` → Configuração da edição, se necessário).

1. Entrar como um jurado em `/jurado/login`. Conferir que não há "esqueci a senha". Abrir `/admin` → 403.
2. "Minhas avaliações" mostra só as dele. Abrir "Avaliar": conferir projeto, autoria, imagens (abrem), PDF, links; conferir que não aparecem e-mail/telefone/CPF do participante.
3. Marcar 2 notas e "Salvar e enviar avaliação" → aviso de que faltam notas; recarregar: as 2 notas continuam.
4. Completar as 4 e enviar (confirmar no modal). A tela de avaliação passa a dar 403; a página mostra só "Minhas notas".
5. Repetir com os outros 2 jurados. Como admin, na inscrição: "Resultado da avaliação" com nota final e distinção; aba "Jurados" com as somas; coluna "Nota final" na lista.
6. "Corrigir nota" sem justificativa → erro; com justificativa → nota e resultado atualizados; conferir `score_revisions`.
7. Como admin, "Substituir" um jurado que já enviou → resultado volta a vazio até o substituto enviar.
8. Como participante, abrir a inscrição → nada de nota ou distinção.
9. Mudar o período de avaliação para o passado → salvar notas dá "Fora do período de avaliação".

## Evidência executada pelo DEV

| Verificação | Resultado |
|---|---|
| `php artisan test --compact` | **284 testes, 706 assertions, todos passaram** (246 até a SPEC-006 + 38 novos em `tests/Feature/Evaluation/`) |
| Fronteiras obrigatórias do docs/04 §9 | 60, 54, 53, 48, 47, 42, 41, 36, 35 e 12, avaliação incompleta, substituição e correção — cobertas |
| Pint (escopo do CI), `validate-foundation.sh`, `composer validate --strict`, `composer audit`, `npm audit`, `npm run build` | passaram |
| Navegador | **não executado** (extensão indisponível); QA deve conferir o visual do painel `/jurado` |

**CI no GitHub:** só roda com base `main`/`develop`.
