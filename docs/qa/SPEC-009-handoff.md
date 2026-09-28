# Handoff QA — SPEC-009

**Branch:** `feature/spec-009-eleitor` (parte de `feature/site-publico`).

## Ambiente

Uma migration nova (`create_voter_profiles_table`). No `.env` local:

```dotenv
CPF_HMAC_KEY=<gerar com: openssl rand -hex 32>
# Chaves de TESTE da Cloudflare (sempre passam) — só desenvolvimento, nunca produção:
TURNSTILE_SITE_KEY=1x00000000000000000000AA
TURNSTILE_SECRET_KEY=1x0000000000000000000000000000000AA
```

Chaves de teste que sempre falham (para testar a recusa): site `2x00000000000000000000AB`, secret `2x0000000000000000000000000000000AA`. Fonte: documentação do Cloudflare Turnstile ("Testing").

```bash
docker compose -p ceara_spec002_dev exec -T laravel.test php artisan migrate --force
docker compose -p ceara_spec002_dev exec -T laravel.test php artisan test
```

## Roteiro manual

1. `/eleitor/cadastro`: cadastrar com CPF `529.982.247-25` → vai para `/eleitor`.
2. Sair; cadastrar de novo com o mesmo CPF sem máscara → "Este CPF já tem cadastro de eleitor."
3. Tentar com o e-mail de uma conta de participante → mensagem pedindo outro e-mail.
4. Sair; entrar em `/login` com o eleitor → `/eleitor`; abrir `/participante` → 403.
5. Trocar as chaves do Turnstile pelas de falha → cadastro recusado; apagar as chaves → aviso de cadastro indisponível.
6. Fazer 5 cadastros seguidos do mesmo computador → o 6º é bloqueado por 1 hora.

## Evidência DEV

| Verificação | Resultado |
|---|---|
| `php artisan test --compact` | **305 testes, 767 assertions** (16 novos em `tests/Feature/Voting/RegisterVoterTest.php`) |
| Pint (escopo do CI) | passou |
| Navegador | não executado (o widget do Turnstile precisa das chaves de teste no `.env`) |
