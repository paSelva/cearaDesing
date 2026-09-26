# 07 — Actions (casos de uso)

Cada **Action** é uma classe em `app/Actions/<Área>/` com um único método público `handle(...)`. Ela concentra **toda** a regra de negócio daquele caso de uso. Controllers, componentes Livewire e Resources do Filament só validam o formato da entrada e chamam a Action (Recomendação, ver [03-arquitetura](03-arquitetura.md)).

**Convenções (Recomendação):**
- Toda Action que grava em mais de uma tabela roda dentro de `DB::transaction()`.
- Violação de regra de negócio → lança `App\Exceptions\BusinessRuleException` com uma mensagem em português para o usuário.
- Permissão (quem pode chamar) → verificada por **Policy** antes de chamar a Action.
- "Agora" = `now()` no fuso `America/Fortaleza`. Prazos são sempre comparados com os campos de `editions` (§7).
- Cada Action tem **testes Pest** cobrindo as regras listadas.

Legenda: **(§x)** edital · **(P#)** decisão confirmada · **Recomendação** escolha técnica · **⚠️** em aberto.

---

## Visão geral

| Área | Action | Quem chama | Superfície |
|---|---|---|---|
| Conta | `RegisterParticipant` | visitante | site (Fortify) |
| Conta | `UpdateParticipantProfile` | participante | área do participante |
| Conta | `RegisterVoter` | visitante | site (Fortify) |
| Conta | `CreateJudge` | admin | Filament `/admin` |
| Inscrição | `CreateEntryDraft` | participante | área do participante |
| Inscrição | `UpdateEntry` | participante | área do participante |
| Inscrição | `SubmitEntry` | participante | área do participante |
| Triagem | `ApproveEntry` | admin | Filament `/admin` |
| Triagem | `DisqualifyEntry` | admin | Filament `/admin` |
| Comprovação | `RequestProof` | admin | Filament `/admin` |
| Comprovação | `SubmitProof` | participante | área do participante |
| Comprovação | `ReviewProof` | admin | Filament `/admin` |
| Júri | `SetCategoryJudges` | admin | Filament `/admin` |
| Júri | `AssignJudgesToEntry` | admin | Filament `/admin` |
| Júri | `ReplaceJudgeOnEntry` | admin | Filament `/admin` |
| Júri | `SaveScores` | jurado | Filament `/jurado` |
| Júri | `SubmitEvaluation` | jurado | Filament `/jurado` |
| Júri | `CorrectScore` | admin | Filament `/admin` |
| Voto popular | `CastPopularVote` | eleitor | galeria (site) |
| Voto popular | `TallyPopularVotes` | admin / sistema | Filament `/admin` |
| Voto popular | `DecidePopularVoteTie` | admin | Filament `/admin` |
| Resultado | `CalculateEntryResult` | sistema | chamada por outras Actions |
| Resultado | `PublishResults` | admin | Filament `/admin` |
| Importação | `ImportEntries` | admin | ⚠️ só se P2 confirmar |

Apoio (não são Actions): regras de validação `Cpf` e `Cnpj` (dígito verificador, P4, P42) e `Turnstile` (P52).

---

## 1. Conta

### `RegisterParticipant`
Cria `users` (role `participante`) + `participant_profiles`.
- **Entrada:** nome, e-mail, senha, `person_type`, documento, telefone, cidade, UF, `participant_type` (P3, P4).
- **Regras:**
  - e-mail único (`users.email`);
  - documento válido pelo dígito verificador: CPF se PF, CNPJ se PJ (P4);
  - documento **único** entre participantes (P74);
  - `participant_type` dentro da lista do §2.
- **Efeitos:** o participante entra logado. **Não** é exigida a confirmação de e-mail (P73).

### `UpdateParticipantProfile`
Atualiza o perfil do participante.
- **Regra:** se ele tiver inscrição em categoria `is_student_only`, o `participant_type` não pode deixar de ser `estudante`, e vice-versa (P9). Recomendação: bloquear a troca nesse caso.

### `RegisterVoter`
Cria `users` (role `eleitor`) + `voter_profiles`.
- **Entrada:** nome, e-mail, senha, CPF, token do Turnstile.
- **Regras:**
  - Turnstile válido (P52);
  - limite de **5 cadastros por IP por hora** (P52), via `RateLimiter`;
  - CPF válido **só pelo dígito verificador** (P42);
  - `cpf_hash` único, ou seja, 1 cadastro por CPF (P35);
  - e-mail único em `users`: quem já é participante ou jurado usa outro e-mail (T4, P60);
  - sem confirmação de e-mail (P42).
- **Efeitos:** grava `cpf_hash` (HMAC) + `cpf_encrypted` + `registration_ip` (Recomendação).

### `CreateJudge`
O admin cria o jurado (P17).
- **Entrada:** nome, e-mail, senha, mini bio, foto (P59).
- **Efeitos:** `users` (role `jurado`) + `judge_profiles` + mídia `photo`. A senha é repassada pela organização (P17).

---

## 2. Inscrição

### `CreateEntryDraft`
- **Entrada:** categoria.
- **Regras:**
  - dentro do período de inscrições (`registration_starts_at` ≤ agora ≤ `registration_ends_at`, §7);
  - não existe outra inscrição do participante na mesma categoria (§4). Isso também é garantido pelo índice único;
  - categoria 3.7 só para `estudante`, e `estudante` só na 3.7 (P9).
- **Efeitos:** `entries` com status `rascunho` (Recomendação).

### `UpdateEntry`
- **Entrada:** campos do §4, declaração de IA (§8), links, imagens, PDF complementar.
- **Regras:**
  - dentro do período de inscrições (P8);
  - status `rascunho` ou `enviada`: **aprovada não pode ser editada** (P70);
  - textos com até 5.000 caracteres, e até 5 links (P75);
  - ⚠️ limites de imagens (P6) e do PDF complementar (P45).
- **Efeitos:** atualiza os campos.
  - Se a inscrição já estava `enviada`, grava um novo conjunto de `entry_consents` (Recomendação). Isso exige os aceites de novo na tela de edição.

### `SubmitEntry`
- **Entrada:** inscrição + os 6 aceites marcados (P11).
- **Regras:**
  - dentro do período de inscrições (§7);
  - status `rascunho`;
  - todos os campos do §4 preenchidos, com **pelo menos 1 imagem** (§4);
  - se `ai_used = true`, `ai_usage_description` é obrigatório (§8);
  - os 6 aceites marcados (§8, P11).
- **Efeitos:**
  - status → `enviada`, `submitted_at`;
  - 6 registros em `entry_consents` com data/hora e IP (P53);
  - ⚠️ e-mail de confirmação depende de P25.

---

## 3. Triagem

### `ApproveEntry`
- **Regras:**
  - status `enviada`;
  - pode ser feita **durante** o período de inscrições (P70).
- **Efeitos:**
  - status → `aprovada`, `approved_at`, `approved_by`;
  - a inscrição **trava para edição** (P70);
  - passa a aparecer na **galeria e na votação** a partir de `voting_starts_at` (P37, P43, P55);
  - fica **disponível para atribuição de jurados** (P76);
  - registro em `activity_log`.

### `DisqualifyEntry`
- **Entrada:** motivo (obrigatório, Recomendação).
- **Regras:** status `enviada` ou `aprovada` (§8).
- **Efeitos:**
  - status → `desclassificada`;
  - sai da galeria, da votação e do cálculo de resultados;
  - atribuições de jurados pendentes deixam de aparecer para os jurados;
  - registro em `activity_log`.
- Votos populares já recebidos ficam no banco, mas são **desconsiderados na apuração**, e o eleitor **não vota de novo** naquela categoria (P78).

---

## 4. Comprovação (P12)

### `RequestProof`
- **Entrada:** inscrição, mensagem, prazo (P56).
- **Regras:** status `enviada` ou `aprovada`.
- **Efeitos:**
  - cria `proof_requests` com status `pendente`;
  - **não altera** o status da inscrição (P72);
  - ⚠️ aviso ao participante por e-mail depende de P25. Recomendação: mostrar o pedido na área logada do participante, independente do e-mail.

### `SubmitProof`
- **Entrada:** 1 PDF de até 10 MB (P54).
- **Regras:** a solicitação está `pendente` e pertence ao participante.
- Envio depois do prazo é **aceito** e marcado com `is_late = true`. A organização decide (P79).
- **Efeitos:** status → `respondida`, `responded_at`.

### `ReviewProof`
- **Entrada:** decisão (`aceita` / `recusada`).
- **Efeitos:** registra `reviewed_at` e `reviewed_by`. Qualquer consequência (ex.: desclassificar) é uma ação separada da organização (P56).

---

## 5. Júri

### `SetCategoryJudges`
- **Entrada:** categoria + até 3 jurados.
- **Regras:** no máximo 3 por categoria (P14), e só usuários com role `jurado`.
- **Efeitos:** sincroniza `category_judges`.

### `AssignJudgesToEntry`
- **Entrada:** inscrição (opcional: lista de jurados; por padrão, os 3 fixos da categoria).
- **Regras:**
  - inscrição `aprovada` (P76);
  - ao final, exatamente `judges_per_entry` (3) atribuições não substituídas (§5);
  - atribuição feita pelo admin (P15).
- **Efeitos:** cria `judge_assignments` com status `pendente` e `assigned_by`.
- Recomendação: um botão "atribuir os jurados da categoria a todos os aprovados" que chama esta Action em lote. Continua sendo uma ação manual do admin (P15).

### `ReplaceJudgeOnEntry`
- **Entrada:** atribuição, novo jurado (**qualquer** jurado, P47), motivo.
- **Regras:**
  - conflito identificado pela organização (P16);
  - o novo jurado ainda não está atribuído à mesma inscrição.
- **Efeitos:**
  - atribuição antiga → `substituida`, com `replaced_at`, `replaced_by` e `replacement_reason`;
  - cria uma atribuição nova `pendente`;
  - se a inscrição já tinha resultado calculado, chama `CalculateEntryResult`.

### `SaveScores`
O jurado salva notas parciais antes de enviar (Recomendação: o P19 trava as notas só **depois** do envio).
- **Regras:**
  - a atribuição é do jurado logado e está `pendente`;
  - dentro do período de avaliação (`judging_*`, §7);
  - cada nota é inteiro de 1 a 5 (§5);
  - o critério pertence à categoria da inscrição.
- **Efeitos:** upsert em `scores`.

### `SubmitEvaluation`
- **Regras:**
  - as mesmas do `SaveScores`;
  - as **4 notas** estão preenchidas (§5).
- **Efeitos:**
  - status → `enviada`, `submitted_at`, e as notas **travam** (P19);
  - chama `CalculateEntryResult`.

### `CorrectScore`
Correção pela organização em caso de erro (§8, P19).
- **Entrada:** nota, novo valor (1–5), **justificativa obrigatória** (P46).
- **Efeitos:**
  - grava `score_revisions` (valor anterior, valor novo, motivo, admin, data) (P46);
  - atualiza `scores.value`;
  - chama `CalculateEntryResult`.
- Se os resultados já foram publicados, a página pública reflete a correção imediatamente. O sistema não envia aviso: a organização comunica por fora, se quiser (P80).

---

## 6. Voto popular

### `CastPopularVote`
- **Entrada:** inscrição.
- **Regras:**
  - usuário logado com role `eleitor` (P60);
  - dentro de `voting_starts_at`–`voting_ends_at` (P38);
  - inscrição `aprovada` (P37);
  - o eleitor ainda não votou nessa categoria (P36). Isso também é garantido pelo índice único;
  - voto final, sem troca (P71);
  - pode votar no próprio projeto (P58).
- **Efeitos:** `popular_votes` com IP.

### `TallyPopularVotes`
- **Efeitos:**
  - conta os votos por categoria, só de inscrições `aprovada`: votos em desclassificadas não contam (P78);
  - o admin vê a parcial a qualquer momento (P48), e o público não vê (P39);
  - ao final da votação, grava `popular_vote_winners` com `was_tie` quando houver empate.

### `DecidePopularVoteTie`
- **Entrada:** categoria, inscrição escolhida, observação.
- **Regras:** só quando `was_tie = true` (P49).
- **Efeitos:** grava `decided_by` e `decision_note`.

---

## 7. Resultado

### `CalculateEntryResult`
Algoritmo descrito em [04-modelo-dados §9](04-modelo-dados.md).
- **Efeitos:**
  - grava `entries.final_score` e `entries.distinction` quando há 3 avaliações `enviada` (§5, §6);
  - com menos de 3, limpa os dois campos (avaliação incompleta).

### `PublishResults`
Botão do admin (P69).
- **Regras (Recomendação):**
  - todas as inscrições `aprovada` têm resultado calculado;
  - as categorias com empate no voto popular já têm decisão registrada (P49);
  - caso contrário, o botão mostra o que está pendente.
- **Efeitos:**
  - `editions.results_published_at = agora`;
  - a **página de premiados** fica visível, com nome, categoria, distinção, nota final, autoria e créditos, imagens, textos e o resultado do voto popular (P21, P44, P51);
  - a **galeria** passa a mostrar a distinção e a nota final (P57);
  - o **participante** vê a nota final na área logada (P22);
  - ⚠️ e-mail avisando o resultado depende de P25.
- As notas por jurado e por critério **nunca** são exibidas (P22).

---

## 8. Importação (condicional)

### `ImportEntries`
Só existe se P2 confirmar o uso de um formulário provisório. ⚠️ O formato de entrada (colunas, imagens) depende da ferramenta que a organização escolher.

---

## 9. Leituras (Queries)

Não alteram dados, então não são Actions. Ficam como *query scopes* nos Models (Recomendação):

| Consulta | Regra |
|---|---|
| Galeria pública | inscrições `aprovada`, visíveis a partir de `voting_starts_at` (P55). Mostra nome, categoria e imagens. Depois da publicação, mostra também a distinção e a nota (P57) |
| Página de premiados | só depois de `results_published_at` (P69). Inscrições com distinção ≠ `sem_distincao` + vencedores do voto popular (P44, P51) |
| Fila do jurado | atribuições do jurado logado, não substituídas, de inscrições `aprovada` (P76). Mostra a inscrição completa, com autoria (P13) |
| Área do participante | inscrições próprias. Nota final visível ao participante (P22) depois da publicação do resultado (P69) |
| Seção Avaliadores | jurados com nome, foto e mini bio, desde o lançamento (P59, P61) |
