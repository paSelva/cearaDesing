# Handoff QA — SPEC-005

**Status:** pronta para revisão QA independente; a validação DEV abaixo não é aprovação QA.

**Branch:** `feature/spec-005-comprovacao` (parte de `feature/spec-004-triagem`, PR #7 → #6 → #5).

## Ambiente

O mesmo da SPEC-003/004 (`docs/qa/SPEC-003-handoff.md`). Uma migration nova:

```bash
docker compose -p ceara_spec002_dev exec -T laravel.test php artisan migrate --force
docker compose -p ceara_spec002_dev exec -T laravel.test php artisan test
```

## Roteiro manual

1. Com uma inscrição enviada (SPEC-003), entrar como admin em `/admin/inscricoes/{id}`; na seção "Comprovações", "Pedir comprovação" com mensagem e prazo. Testar prazo no passado → erro.
2. Como o participante dono: o painel mostra "A organização pediu uma comprovação"; a página da inscrição mostra mensagem, prazo e o formulário.
3. Enviar um PDF de ~2 MB → "Comprovação enviada"; o status vira "Enviada, em análise" e aparece "Ver o PDF enviado". Tentar enviar de novo → não há mais formulário.
4. Como admin, "Abrir PDF" e depois "Aceitar" (ou "Recusar"). Conferir que o status da inscrição não mudou.
5. Criar outro pedido com prazo curto, esperar passar (ou ajustar o relógio do teste) e enviar → marcado como atrasado.
6. Como outro participante, abrir `/participante/comprovacoes/{id}/arquivo` → 403.

## Evidência executada pelo DEV

| Verificação | Resultado |
|---|---|
| `php artisan test --compact` | **224 testes, 528 assertions, todos passaram** (200 até a SPEC-004 + 24 novos em `tests/Feature/Proofs/ProofRequestTest.php`) |
| `vendor/bin/pint --test app config routes tests bootstrap/app.php` (escopo do CI) | passou |
| `scripts/validate-foundation.sh`, `npm run build` | passaram |
| Fluxo HTTP real | pedido criado; participante logado vê o aviso no painel, envia PDF real de 2 MB → "Enviada, em análise"; download do próprio PDF → 200 `application/pdf` |
| Navegador | **não executado** (extensão indisponível); QA deve conferir o visual |

Observação: para o teste HTTP, o `tinker` criou um admin sintético no banco local descartável (`ceara_spec002_local`).

**CI no GitHub:** só roda em PRs para `main`/`develop`; este PR terá base na branch da SPEC-004.

## Limites e pendências

- Nada publicado (P2, P62, P65, P68).
- Sem e-mail de aviso (P25).
