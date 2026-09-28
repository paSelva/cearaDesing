# Handoff QA — SPEC-004

**Status:** pronta para revisão QA independente; a validação DEV abaixo não é aprovação QA.

**Branch:** `feature/spec-004-triagem` (parte de `feature/spec-003-inscricao`, PR #6, que parte de `feature/spec-002-cadastro-participante`, PR #5).

## Ambiente

O mesmo da SPEC-003 (`docs/qa/SPEC-003-handoff.md`), com as imagens Docker já reconstruídas (exif e `uploads.ini`). Nesta SPEC entra só uma migration nova:

```bash
docker compose -p ceara_spec002_dev exec -T laravel.test php artisan migrate --force
docker compose -p ceara_spec002_dev exec -T laravel.test php artisan test
```

## Roteiro manual

1. Como participante, criar e enviar duas inscrições (SPEC-003) e deixar uma terceira em rascunho.
2. Criar admin (`php artisan app:create-admin`) e abrir `/admin/inscricoes`: aparecem as duas enviadas; o rascunho só aparece marcando o status "Rascunho" no filtro.
3. Abrir uma inscrição: conferir participante, textos, IA, links, imagens (clicar abre o arquivo), PDF e o histórico de aceites (seção recolhida) com data/hora e IP.
4. "Aprovar" a primeira. Voltar como participante: a página mostra "Aprovada na triagem" e não há mais campos editáveis nem botões de arquivo.
5. "Desclassificar" a segunda sem motivo → erro no formulário; com motivo → status "Desclassificada".
6. Conferir na tabela `activity_log` dois registros com `log_name = triagem`, o admin como `causer`, e `properties` com status anterior, novo e motivo.
7. Como participante, jurado e eleitor, abrir `/admin/inscricoes` → 403; abrir a URL `/organizacao/inscricoes/{id}/arquivos/{arquivo}` → 403.

## Evidência executada pelo DEV

| Verificação | Resultado |
|---|---|
| `php artisan test --compact` | **200 testes, 459 assertions, todos passaram** (172 até a SPEC-003 + 28 novos em `tests/Feature/Triage/TriageTest.php`) |
| `vendor/bin/pint --test app config routes tests bootstrap/app.php` (escopo do CI) | passou |
| `scripts/validate-foundation.sh` | passou |
| `composer validate --strict`, `composer audit` | válido; sem advisories |
| `npm audit --audit-level=high`, `npm run build` | 0 vulnerabilidades; build ok |
| Render HTTP do painel | `/admin/inscricoes` e `/admin/inscricoes/{id}` com imagem, PDF e link renderizam (teste de feature) |
| Navegador | **não executado** (extensão indisponível); QA deve conferir o visual do painel |

**CI no GitHub:** o workflow só roda em PRs para `main`/`develop`. Como este PR tem base na branch da SPEC-003, o CI só vai rodar depois que a base for trocada para `develop`.

## Segurança

- Admin tem `view` e as ações de triagem, mas não `update`: não edita o conteúdo do participante (testado nas rotas do participante → 403).
- Rota de arquivos da organização separada (`auth` + `role:admin` + Policy + checagem de que o arquivo é da inscrição).
- Aprovação e desclassificação travam a linha; dois admins ao mesmo tempo → o segundo recebe erro.
- Activity log sem dados pessoais (só status e motivo).

## Limites e pendências

- Nada publicado (P2, P62, P65, P68).
- Perguntas novas: desfazer triagem; motivo visível ao participante.
