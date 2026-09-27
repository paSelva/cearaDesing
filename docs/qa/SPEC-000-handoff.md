# Handoff QA — SPEC-000

O SHA alvo imutável será publicado no PR #1 após o commit final. A base do diff é `e05177f` (`develop` inicial) e a branch é `chore/spec-000-viabilidade`.

## Preparação isolada

```bash
git fetch origin
git worktree add --detach ../ceara-qa-spec-000 <SHA_ALVO>
cd ../ceara-qa-spec-000
bash scripts/validate-foundation.sh
```

Não executar `<SHA_ALVO>` literalmente. O QA deve substituir pelo SHA entregue, usar dados sintéticos e não acessar produção.

Confirme o snapshot antes dos testes:

```bash
test "$(git rev-parse HEAD)" = "<SHA_ALVO>"
git status --short --branch
```

## Isolamento do ambiente

- Criar `.env` próprio do worktree, nunca copiar o de DEV ou produção.
- Quando a aplicação existir, usar banco exclusivo como `ceara_qa_spec000`; confirmar a conexão antes de qualquer reset.
- Manter o `storage/` do worktree e uploads sintéticos separados; não apontar para storage compartilhado.
- Usar cache/fila próprios ou drivers efêmeros; nenhum worker DEV/produção pode consumir a fila de QA.
- Usar porta local exclusiva (por exemplo, `8081`) e `APP_URL` correspondente.
- Usar transporte de e-mail falso/local (`array`/`log`) e nunca remetentes ou destinatários reais.
- Turnstile e demais integrações devem usar mocks/chaves oficiais de teste, nunca credenciais de produção.

## Casos mínimos

1. Confirmar que o diff parte de `e05177f` e não inclui `docs/workflow-ceara-design.md`.
2. Executar `bash -n scripts/validate-foundation.sh` e o script.
3. Confirmar que `.env`, chaves, dumps, logs e backups não estão rastreados nem no artefato.
4. Revisar se `.env.example` possui somente vazios/fictícios e nenhum segredo `VITE_*`.
5. Validar sintaxe do workflow e conferir os checks do PR, especialmente Gitleaks.
6. Conferir que requisitos documentados não foram classificados como implementados.
7. Conferir a tensão §2/P9/P41 e a natureza extraedital do voto popular P1/P34/P41.
8. Conferir que cron por minuto, rota de artisan/migration e publicação da raiz do projeto não são prescritos.
9. Marcar provas Laravel, MySQL, storage, e-mail, Turnstile, backup/restore e HostGator como bloqueadas, não aprovadas.

O QA deve registrar parecer para o SHA exato. Mudança posterior exige novo teste e novo parecer.
