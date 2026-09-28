# SPEC-004 — Triagem das inscrições pela organização

Status: implementação DEV concluída, aguardando revisão independente do QA. Base: `feature/spec-003-inscricao` (PR #6, sem merge). Branch: `feature/spec-004-triagem`.

## Objetivo e escopo

Dar à organização, no painel `/admin`, a consulta das inscrições e a triagem: aprovar (P43, P70) ou desclassificar com motivo (§8). A aprovação trava a edição pelo participante e é o que, nas próximas SPECs, libera o projeto para a galeria e a votação (P37, P55) e para os jurados (P76).

**Fora do escopo:** desfazer aprovação ou desclassificação (não previsto em `docs/`; se a organização precisar, vira pergunta); mostrar o motivo da desclassificação ao participante (não definido — pergunta); e-mails de aprovação/desclassificação (⚠️ P25); comprovação (P12, P54, P56); atribuição de jurados (P76); galeria e votos (P55, P78). A SPEC-004 só muda o status; quem lê o status (galeria, votação, avaliação, resultado) trata o efeito quando for construído.

### Critérios verificáveis

1. `/admin/inscricoes` lista as inscrições da edição 2026 com categoria, projeto, participante, status e data de envio; filtros por status e categoria. Por padrão, rascunhos ficam ocultos (escolha de interface: não foram enviados).
2. A página de uma inscrição mostra o participante (nome, e-mail, telefone, tipo de pessoa, CPF/CNPJ, tipo de participante, cidade/UF), os textos do §4, a resposta sobre IA, os links, as imagens e o PDF, e o histórico de aceites do §8 com data/hora e IP.
3. "Aprovar" só a partir de `enviada`; grava `approved_at` e `approved_by`. Pode acontecer durante o período de inscrições (P70).
4. "Desclassificar" a partir de `enviada` ou `aprovada`, com motivo obrigatório; grava `disqualified_at`, `disqualified_by` e o motivo.
5. Cada aprovação/desclassificação gera um registro no activity log (`triagem`) com o admin, o status anterior e o novo, e o motivo.
6. Depois de aprovada, o participante não edita textos nem arquivos (P70), mesmo que a aprovação aconteça no meio de uma requisição dele (a edição trava a linha e reconfere o status).
7. Só o admin acessa o painel de inscrições e a rota de arquivos da organização (`/organizacao/inscricoes/{id}/arquivos/{arquivo}`); jurado, participante (inclusive o dono) e eleitor recebem 403. Arquivo de outra inscrição → 404.
8. O admin **não** ganha permissão de editar o conteúdo da inscrição (`update` continua só do dono).

## Regras, decisões e perguntas

- **Edital:** desclassificação (§8).
- **Decisões confirmadas:** a triagem é a mesma aprovação que libera a votação (P37, P43); triagem durante o período e aprovada travada (P70); comprovação não muda o status (P72).
- **Recomendações aplicadas:** motivo obrigatório para desclassificar (docs/07); activity log para mudanças de status (docs/04); rascunhos ocultos por padrão na lista.
- **Perguntas novas para a organização:** (a) é preciso desfazer uma aprovação ou uma desclassificação? (b) o participante deve ver o motivo da desclassificação na área dele? Não implementado até a resposta.

## Dados e operações

- Migration `create_activity_log_table` (`spatie/laravel-activitylog` 5.1.1).
- Actions `App\Actions\Triage\ApproveEntry` e `DisqualifyEntry` (transação + `lockForUpdate`).
- `EntryGuard::lockEditable()`: as Actions de edição do participante (textos, envio, imagens, PDF) travam a linha e reconferem o status dentro da transação.
- `EntryPolicy`: `viewAny`/`view` para admin; `approve`/`disqualify` por status; `update` só do dono; ninguém cria ou exclui.
- Filament `EntryResource` (`/admin/inscricoes`): listagem e visualização, sem criar/editar; ações "Aprovar" (com confirmação) e "Desclassificar" (com motivo) na tabela e na página.
- `EntryMediaResponse` (renomeado para `PrivateMediaResponse` na SPEC-005) compartilhado entre a rota do participante e a da organização (disco privado, `nosniff`, `private, no-store`).

## Pendências e riscos

- **Nada está no ar:** P2 sem resposta e deploy na HostGator nunca testado (P62, P65, P68).
- Depende dos PRs #5 e #6, ainda sem QA.
- O painel mostra CPF/CNPJ e telefone ao admin (uso pela organização, §8). ⚠️ Política de privacidade: P28.

## Evidência DEV

[`docs/qa/SPEC-004-handoff.md`](../qa/SPEC-004-handoff.md).
