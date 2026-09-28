# SPEC-007 — Avaliação: painel do jurado, notas, resultado e correção

Status: implementação DEV concluída, aguardando revisão independente do QA. Base: `feature/spec-006-juri`. Branch: `feature/spec-007-avaliacao`.

## Objetivo e escopo

O jurado entra no painel `/jurado`, vê os projetos atribuídos a ele e dá 4 notas de 1 a 5 (§5), sem comentário (P18). Depois de enviar, as notas travam (P19). Com as 3 avaliações enviadas, o sistema calcula a nota final e a distinção (§5, §6, docs/04 §9). A organização acompanha as notas e corrige em caso de erro, com histórico e justificativa (P19, P46).

**Fora do escopo:** publicar o resultado e a página de premiados (P69, P21, P44 — próxima SPEC); mostrar a nota ao participante (P22); pedidos de revisão (⚠️ P20); e-mail aos jurados (⚠️ P25); mini bio e foto (P59).

### Critérios verificáveis

1. `/jurado` aceita só usuários `jurado` (admin, participante e eleitor → 403; jurado em `/admin` → 403). Sem recuperação de senha (P82).
2. "Minhas avaliações" lista só as atribuições do jurado logado que não foram substituídas, de inscrições aprovadas. Uma atribuição substituída ou de inscrição desclassificada some da lista. Abrir a de outro jurado → 404.
3. A tela "Avaliar" mostra o projeto com autoria e créditos (P13), IA, imagens, PDF e links, e um grupo de 5 opções (1 Insuficiente … 5 Excelente) por critério da categoria. Não mostra e-mail, telefone, CPF/CNPJ nem aceites do participante.
4. "Salvar notas" guarda notas parciais. "Salvar e enviar avaliação" (com confirmação) salva e depois envia; se faltar nota, o que foi marcado fica salvo e aparece o aviso.
5. Notas só inteiras de 1 a 5, só de critérios da categoria da inscrição, só dentro do período de avaliação (29/10 00:00:00 a 20/11 23:59:59, horário de Fortaleza, lido de `editions`).
6. Depois do envio, a tela de avaliação responde 403 e a página mostra só as notas do próprio jurado.
7. Nota final = soma das notas das avaliações enviadas não substituídas, só quando há `judges_per_entry` (3) delas; distinção pelas faixas de `editions` (54/48/42/36). Senão, resultado vazio. Recalcula ao enviar, ao substituir jurado e ao corrigir nota.
8. O jurado não vê notas de outros jurados nem a nota final (leitura do §8: "atuar de forma independente").
9. Na aba "Jurados" da inscrição, a organização vê as notas de cada jurado (ex.: 4 + 5 + 3 + 2 = 14) e usa "Corrigir nota" (critério, nova nota, justificativa obrigatória); fica um registro em `score_revisions` e no activity log, e o resultado é recalculado.
10. A nota final e a distinção aparecem só no painel da organização. O participante não vê nada até a publicação (P69).

## Regras, decisões e perguntas

- **Edital:** 4 critérios, notas de 1 a 5, soma de 3 jurados, máximo 60 (§5); faixas (§6); período de avaliação (§7); jurado independente e correção de erros pela organização (§8).
- **Decisões confirmadas:** P13, P18, P19, P46, P69, P76, P80, P82.
- **Leituras adotadas (a confirmar se a organização discordar):** o jurado não vê notas dos colegas nem a nota final (§8); o jurado pode ver os projetos atribuídos antes de 29/10, mas só dá notas dentro do período.
- **Perguntas em aberto relacionadas:** P20 (canal dos pedidos de revisão); o jurado deve ver as comprovações? (hoje não vê).

## Dados e operações

- Enum `Distinction`; `Entry` passa a ter cast de `distinction` e `final_score`.
- Actions `SaveScores`, `SubmitEvaluation`, `CalculateEntryResult`, `CorrectScore` (+ `EvaluationGuard`); `ReplaceJudgeOnEntry` agora recalcula o resultado.
- Painel Filament `jurado` (`JuradoPanelProvider`, recursos em `app/Filament/Jurado`); `User::canAccessPanel()` separa os dois painéis.
- `JudgeAssignmentPolicy`: jurado vê e edita só a própria atribuição ativa (edição só pendente).
- Rota de arquivos do jurado `/avaliacao/atribuicoes/{id}/arquivos/{arquivo}` (só imagens e PDF complementar; comprovações → 404).

## Pendências e riscos

- **Nada está no ar:** P2 e deploy (P62, P65, P68). A avaliação começa em 29/10.
- A pilha de PRs sem revisão chega a 6 (#5 a #10), se esta e a SPEC-006 forem abertas.

## Evidência DEV

[`docs/qa/SPEC-007-handoff.md`](../qa/SPEC-007-handoff.md).
