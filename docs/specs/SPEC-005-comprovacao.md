# SPEC-005 — Comprovação

Status: implementação DEV concluída, aguardando revisão independente do QA. Base: `feature/spec-004-triagem` (PR #7, sem merge). Branch: `feature/spec-005-comprovacao`.

## Objetivo e escopo

A organização pede, dentro do sistema, uma comprovação de uma inscrição (§2, §4, P12), com mensagem e prazo (P56). O participante vê o pedido na área dele e envia 1 PDF de até 10 MB (P54). Envio depois do prazo é aceito e marcado como atrasado (P79). A organização aceita ou recusa; qualquer consequência (ex.: desclassificar) é uma ação separada (P56). O status da inscrição não muda em nenhum momento (P72).

**Fora do escopo:** aviso por e-mail (⚠️ P25 — o pedido aparece no painel do participante e na página da inscrição); cancelar ou editar um pedido; o participante substituir o PDF depois de enviado (não previstos em `docs/`).

### Critérios verificáveis

1. Na página da inscrição em `/admin/inscricoes/{id}`, a seção "Comprovações" lista os pedidos (data, mensagem, prazo, status, data de envio, atrasada) e tem a ação "Pedir comprovação" (mensagem + prazo em horário de Fortaleza).
2. Só inscrições `enviada` ou `aprovada` recebem pedido; mensagem obrigatória e prazo futuro (Recomendação).
3. O participante vê o aviso "A organização pediu uma comprovação" no painel e, na página da inscrição, a mensagem, o prazo e o formulário de envio — mesmo com a inscrição aprovada ou fora do prazo de inscrições.
4. O envio aceita só PDF de até 10 MB; muda o pedido para "respondida" e marca `is_late` se passou do prazo. Um segundo envio no mesmo pedido é recusado.
5. O admin abre o PDF e escolhe "Aceitar" ou "Recusar" (só em pedido respondido). A inscrição não muda de status.
6. Pedido e análise ficam no activity log (`comprovacao`) com o admin como autor.
7. Só o dono e o admin abrem o PDF (rotas separadas); outro participante e o jurado recebem 403.

## Regras, decisões e perguntas

- **Edital:** a organização pode pedir comprovações depois da inscrição (§2, §4).
- **Decisões confirmadas:** P12, P54, P56, P72, P79.
- **Recomendações aplicadas:** prazo precisa ser futuro ao criar o pedido; estados `aceita`/`recusada` como análise da organização (docs/04); mais de um pedido por inscrição é permitido.
- **Não previsto (perguntar se necessário):** cancelar/editar pedido; substituir o PDF depois de enviado.

## Dados e operações

- Migration `create_proof_requests_table` conforme `docs/04`.
- Enum `ProofRequestStatus`; model `ProofRequest` (Media Library, coleção `proof`, disco privado, arquivo único; nenhum campo por atribuição em massa).
- Actions `App\Actions\Proofs\RequestProof`, `SubmitProof`, `ReviewProof` (transação + trava de linha).
- `ProofRequestPolicy`; relation manager `ProofRequestsRelationManager` no `EntryResource`.
- Rotas: participante `POST /participante/comprovacoes/{id}` e `GET .../arquivo`; organização `GET /organizacao/comprovacoes/{id}/arquivo`.
- `PrivateMediaResponse` (antes `EntryMediaResponse`) serve arquivos privados de qualquer dono.

## Pendências e riscos

- **Nada está no ar:** P2 sem resposta e deploy na HostGator nunca testado (P62, P65, P68).
- **P25:** sem e-mail, o participante só descobre o pedido se entrar na área dele. A organização deve avisar por fora até haver e-mail.
- Depende dos PRs #5, #6 e #7, ainda sem QA.

## Evidência DEV

[`docs/qa/SPEC-005-handoff.md`](../qa/SPEC-005-handoff.md).
