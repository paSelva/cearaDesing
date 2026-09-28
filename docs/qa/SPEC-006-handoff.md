# Handoff QA — SPEC-006

**Status:** pronta para revisão QA independente; a validação DEV abaixo não é aprovação QA.

**Branch:** `feature/spec-006-juri` (parte de `feature/spec-005-comprovacao`, PR #8).

## Ambiente

O mesmo da SPEC-003 em diante. Uma migration nova (`create_jury_tables`):

```bash
docker compose -p ceara_spec002_dev exec -T laravel.test php artisan migrate --force
docker compose -p ceara_spec002_dev exec -T laravel.test php artisan test
```

## Roteiro manual

1. Criar 4 jurados em `/admin/jurados`.
2. Em Categorias, "Definir jurados" numa categoria com 3 deles; tentar 4 → bloqueado pelo campo.
3. Aprovar duas inscrições dessa categoria (SPEC-004). Na lista de inscrições, "Atribuir jurados aos aprovados" → mensagem com 2 atribuídas; coluna "Avaliações" = 0/3.
4. Abrir uma delas, aba "Jurados": 3 pendentes. "Substituir" um deles pelo 4º jurado, sem motivo → erro; com motivo → o antigo aparece "Substituído" e o novo "Avaliação pendente". A coluna continua 0/3.
5. Tentar "Atribuir jurados da categoria" de novo → "A inscrição já tem os 3 jurados."
6. Numa categoria sem jurados, atribuir → erro explicando que faltam jurados.
7. Conferir `activity_log` com `log_name = juri`.

## Evidência executada pelo DEV

| Verificação | Resultado |
|---|---|
| `php artisan test --compact` | **246 testes, 591 assertions, todos passaram** (224 até a SPEC-005 + 22 novos em `tests/Feature/Jury/JurySetupTest.php`) |
| Pint (escopo do CI), `validate-foundation.sh`, `npm run build` | passaram |
| Navegador | **não executado** (extensão indisponível) |

**CI no GitHub:** só roda com base `main`/`develop`.
