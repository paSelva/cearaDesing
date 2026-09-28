# SPEC-003 — Inscrição de projetos

Status: implementação DEV concluída, aguardando revisão independente do QA. Base: `feature/spec-002-cadastro-participante` (PR #5, ainda sem merge). Branch: `feature/spec-003-inscricao`.

## Objetivo e escopo

Permitir que o participante inscreva um projeto por categoria, preencha os campos do §4, declare o uso de IA (§8), envie imagens, links e um PDF complementar, e envie a inscrição com as declarações do §8, dentro do prazo do §7. Até o fim do prazo, a inscrição enviada pode ser editada (P8), exceto se já tiver sido aprovada na triagem (P70).

**Fora do escopo:** listagem e triagem pela organização (SPEC-004: `ApproveEntry`, `DisqualifyEntry`); comprovação (P12, P54, P56); e-mail de confirmação (⚠️ P25); galeria pública (P24, P55); excluir rascunho ou trocar a categoria de uma inscrição (não previstos em `docs/` — se a tela precisar, vira pergunta nova).

### Critérios verificáveis

1. O participante vê as categorias da edição 2026 e cria um rascunho em uma delas. Estudante vê e inscreve só na categoria exclusiva; os demais, só nas outras (P9).
2. Uma inscrição por categoria por participante (§4), garantida também pelo índice único, inclusive em corrida entre requisições. O mesmo projeto pode ir para outra categoria (P10).
3. Criar, editar, enviar e mexer em arquivos só dentro do período: de 28/09 00:00:00 a 28/10 23:59:59, horário de Fortaleza (§7, P77), lido da tabela `editions`.
4. O rascunho aceita campos vazios. Para enviar, exige: os 5 textos do §4, resposta sim/não sobre IA e a descrição quando "sim" (§8), pelo menos 1 imagem (§4) e os 6 aceites do §8.
5. Os 6 aceites são gravados em `entry_consents`, com data/hora e IP (P11, P53). Os textos são os do §8 do edital.
6. Inscrição enviada: ao salvar os textos, tudo continua obrigatório e os aceites são pedidos de novo (novo conjunto, mantendo o histórico). Não pode ficar sem imagem.
7. Aprovada e desclassificada não podem ser editadas (P70, §8).
8. Imagens: de 1 a 10, JPG/PNG/WebP, até 5 MB cada (P6). PDF complementar opcional, 1 arquivo de até 10 MB, substituído a cada novo envio (P7, P45). Textos até 5.000 caracteres e até 5 links `http(s)` (P75).
9. Arquivos ficam no disco privado e só o dono os vê, por rota com Policy. Outro participante recebe 403 em todas as rotas da inscrição (IDOR).
10. Arquivo acima do limite do PHP volta para a página com mensagem que cita o limite, sem erro 500.
11. O envio acontece no mesmo formulário dos textos ("Salvar e enviar"): se falhar, o que foi digitado já está salvo. Arquivos são enviados em formulários próprios, acima dos textos, com aviso de que recarregam a página.

## Regras, decisões e perguntas

- **Edital:** campos (§4); um projeto por categoria (§4); declaração de IA (§8); declarações e autorização de uso (§8); período (§7).
- **Decisões confirmadas usadas:** P7, P8, P9, P10, P11, P53, P70, P75, P77.
- **Novas decisões (28/09):** P6 (imagens: 1 a 10, JPG/PNG/WebP, 5 MB), P45 (PDF opcional, 1 arquivo, 10 MB), P10 (sem bloqueio entre categorias). Registradas em `docs/06`, `docs/04` e `docs/07`; os limites ficam em `config/premio.php`.
- **P9 × P41:** a regra "estudante só na 3.7" está confirmada (P9) e foi implementada nas duas direções. P41 (publicar um adendo com essa regra) continua com a organização.
- **Recomendações técnicas aplicadas:**
  - status `rascunho` antes de enviar (docs/04);
  - um arquivo por requisição, para caber nos limites de upload da hospedagem compartilhada (com trava da inscrição para dois envios simultâneos não passarem de 10 imagens);
  - `ai_used` como resposta explícita sim/não;
  - arquivos com nome gerado (UUID), nome original guardado só como rótulo;
  - sem conversões de imagem (miniaturas), para não depender de GD/Imagick no servidor;
  - envio ou remoção de arquivo em inscrição enviada **não** pede novo aceite (os aceites já cobrem "imagens e materiais enviados"); só salvar os textos pede.
- **Divergência documentada:** como na SPEC-002, erros de regra viram `ValidationException` por campo (e não `BusinessRuleException`).

## Dados e operações

- Migration `create_media_table` (Media Library 11.23.8).
- Migration `create_entries_tables`: `entries` completa conforme `docs/04` (inclusive as colunas de triagem e resultado, para evitar migrations extras em produção), `entry_links` e `entry_consents` (sem `updated_at`).
- Enums `EntryStatus` e `ConsentType` (textos do §8).
- Actions em `app/Actions/Entries/`: `CreateEntryDraft`, `UpdateEntry`, `SubmitEntry`, `AddEntryImage`, `RemoveEntryImage`, `SetSupplementaryPdf`, `RemoveSupplementaryPdf`, com `EntryGuard` (prazo e status) e `RecordConsents`.
- `EntryPolicy` (dono participante); controllers `Participant\DashboardController` e `Participant\EntryController`.
- Coleções da Media Library no disco `local` (`storage/app/private`): `images` e `supplementary_pdf` (arquivo único).
- Ambiente: `ext-exif` adicionada às imagens Docker e ao CI; `.docker/php/uploads.ini` com `upload_max_filesize=10M`, `post_max_size=12M` e `display_errors=Off`.

## Acesso

| Rota | Visitante | Dono (participante) | Outro participante | Admin/jurado/eleitor |
|---|---|---|---|---|
| `/participante` (categorias e inscrições) | login | permite | — | 403 |
| `POST /participante/inscricoes` | login | permite (regras acima) | — | 403 |
| `/participante/inscricoes/{id}` e edição/envio/arquivos | login | permite | 403 | 403 |
| `/participante/inscricoes/{id}/arquivos/{media}` | login | permite; arquivo de outra inscrição → 404 | 403 | 403 |

## Pendências e riscos

- **P2:** as inscrições abrem hoje (28/09) e o sistema **não está publicado**: o deploy na HostGator nunca foi testado (P62, P65, P68). Terminar esta SPEC não coloca nada no ar.
- **Limites de upload na HostGator não verificados:** `upload_max_filesize` ≥ 10M, `post_max_size` ≥ 12M, `display_errors = Off` e extensão `exif`.
- **SPEC-002 ainda sem QA:** esta branch parte dela (PR #5).
- **P25:** sem e-mail de confirmação do envio.
- **Fotos de iPhone (HEIC):** o iPhone salva fotos em HEIC por padrão, e a P6 aceita só JPG/PNG/WebP; essas fotos serão recusadas. A organização pode querer orientar os participantes ("exporte como JPG").
- **IP dos aceites (P53):** vem de `$request->ip()`. Se houver proxy ou Cloudflare na frente do servidor, será o IP do proxy — conferir no deploy.
- **Espaço em disco:** até 10 imagens de 5 MB + 1 PDF de 10 MB por inscrição (até 60 MB). Acompanhar a cota da hospedagem.

## Evidência DEV

[`docs/qa/SPEC-003-handoff.md`](../qa/SPEC-003-handoff.md).
