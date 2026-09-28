# Handoff QA — SPEC-003

**Status:** pronta para revisão QA independente; a validação DEV abaixo não é aprovação QA.

**Branch:** `feature/spec-003-inscricao` (parte de `feature/spec-002-cadastro-participante`, PR #5).

## Ambiente

Mesmo ambiente isolado da SPEC-002 (`docs/qa/SPEC-002-handoff.md`): projeto Compose `ceara_spec002_dev`, `localhost:18082`, MySQL 8.0 na porta `33361`, inclusive o contorno do banco de teste `ceara_spec001_testing`.

**Mudanças de ambiente nesta SPEC — é preciso reconstruir as imagens:**
- `ext-exif` nas duas imagens (`.docker/php` e `.docker/composer`) e no CI: exigida pela Media Library 11.23.8.
- `.docker/php/uploads.ini`: `upload_max_filesize=10M`, `post_max_size=12M`, `display_errors=Off`.

```bash
bash scripts/bootstrap-local.sh                         # reconstrói a imagem do Composer (exif)
docker compose -p ceara_spec002_dev up -d --build       # reconstrói a imagem da aplicação
docker compose -p ceara_spec002_dev exec -T laravel.test php artisan migrate --force
npm run build
docker compose -p ceara_spec002_dev exec -T laravel.test php artisan test
```

## Roteiro manual

O seeder cria as inscrições de 28/09 00:00:00 a 28/10 23:59:59 (Fortaleza). Fora dessa janela, ajuste as datas em `/admin` (Configuração da edição) para testar.

1. Cadastrar participante profissional; em `/participante`, conferir que a 3.7 não aparece; clicar em "Inscrever projeto" numa categoria.
2. Salvar rascunho só com o nome; conferir a lista "Antes de enviar, falta".
3. Enviar uma imagem JPG de ~5 MB (aceita) e uma de 5 MB + alguns bytes (recusada com "Cada imagem pode ter no máximo 5 MB.").
4. Enviar PDF de 10 MB (aceito), de 11 MB (recusado) e de 13 MB (volta com "O arquivo é maior que o limite permitido…").
5. Preencher os textos e clicar em "Salvar e enviar inscrição" **sem imagem** → os textos ficam salvos, aparece "Rascunho salvo, mas a inscrição ainda não foi enviada" e a lista do que falta. Enviar uma imagem (os textos continuam lá), marcar os 6 aceites e "Salvar e enviar" → status "Enviada".
6. Editar um texto da inscrição enviada sem marcar os aceites → erro; marcando → salva. Conferir em `entry_consents` 12 registros com data/hora e IP.
7. Tentar remover a única imagem da inscrição enviada → recusado.
8. Cadastrar participante estudante: só a 3.7 aparece.
9. Com outro participante logado, abrir a URL da inscrição e a URL de um arquivo dela → 403.
10. Tentar criar segunda inscrição na mesma categoria (reenviar o POST) → "Você já tem uma inscrição nesta categoria."

## Evidência executada pelo DEV

| Verificação | Resultado |
|---|---|
| `php artisan test --compact` | **172 testes, 371 assertions, todos passaram** (117 até a SPEC-002 + 55 novos) |
| `vendor/bin/pint --test` | passou |
| `scripts/validate-foundation.sh`, `npm run build` | passaram |
| `composer require spatie/laravel-medialibrary` | 11.23.8; `composer audit` sem advisories |
| `migrate` | `create_media_table` e `create_entries_tables` aplicadas |
| Fluxo HTTP real (arquivos de verdade, passando pelos limites do PHP) | imagem 5 MB aceita; 5 MB+10 B recusada ("no máximo 5 MB"); foto de 11 MB recusada ("A imagem não foi recebida. Cada imagem pode ter no máximo 5 MB."); PDF 10 MB aceito; PDF 11 MB recusado ("…no máximo 10 MB"); PDF 13 MB volta com mensagem (302); "Salvar e enviar" sem imagem guarda os textos e mantém rascunho; com imagem → "Enviada" |
| Navegador | **não executado** (extensão indisponível); QA deve conferir o visual |

Testes novos em `tests/Feature/Entries/`: `CreateEntryDraftTest`, `UpdateAndSubmitEntryTest`, `EntryFilesTest`, `EntryHttpTest` (+ `EntryFixtures.php`). As imagens falsas usam bytes reais de JPG/PNG/WebP/GIF para não depender da extensão GD.

## Segurança

- Todos os arquivos no disco privado; download só pelo dono, com `X-Content-Type-Options: nosniff` e `Cache-Control: private, no-store`. `/storage/...` responde 403.
- Nome do arquivo gerado (UUID); tipo validado pelo conteúdo (Media Library `acceptsMimeTypes`) e pela regra de validação.
- `EntryPolicy` em todas as rotas; a Action confere que a imagem removida pertence à inscrição.
- Status, datas, notas e distinção fora do `$fillable` de `Entry`.
- Links só `http`/`https`; Blade só com `{{ }}`.

## Limites e pendências

- P2 e deploy (P62, P65, P68): nada está publicado.
- Limites de PHP e `exif` na HostGator não verificados.
- Depende do PR #5 (SPEC-002), ainda sem QA.
