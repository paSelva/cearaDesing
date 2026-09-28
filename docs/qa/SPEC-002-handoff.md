# Handoff QA — SPEC-002

**Status:** pronta para revisão QA independente; a validação DEV abaixo não é aprovação QA.

**Branch:** `feature/spec-002-cadastro-participante`

**Base:** `aba3f29` (`develop`, inclui SPEC-001).

**QA deve revisar:** o HEAD remoto mais recente desta branch, informado no PR.

## Ambiente isolado usado pelo DEV

- Docker Compose project `ceara_spec002_dev`, aplicação em `localhost:18082`, MySQL 8.0 na porta host `33361`, banco local `ceara_spec002_local`.
- PHP 8.4 (imagem do projeto), Composer 2.8.12 (imagem do bootstrap), Node v24.14.1 no host.
- Fortify **1.40.0** adicionado pelo Composer do bootstrap (`composer.lock` atualizado).
- `MAIL_MAILER=log`: o link de recuperação de senha aparece em `storage/logs/laravel.log`, sem envio real.
- Banco descartável. Não rodar `migrate:fresh`, `db:wipe` ou `down -v` fora deste projeto Compose.

## Atenção: banco de testes

O `phpunit.xml` fixa `DB_DATABASE=ceara_spec001_testing`. O script de inicialização do MySQL cria o banco com o nome de `DB_TEST_DATABASE` do `.env`. Se esse nome for diferente, os testes falham com `Access denied ... ceara_spec001_testing`. Contorno usado pelo DEV (não altera arquivos versionados):

```bash
docker compose -p ceara_spec002_dev exec -T -e MYSQL_PWD=<senha local> mysql \
  mysql -uroot -e "CREATE DATABASE IF NOT EXISTS ceara_spec001_testing; GRANT ALL PRIVILEGES ON ceara_spec001_testing.* TO 'sail'@'%';"
```

Sugestão para uma SPEC futura: ler o nome do banco de teste de uma única fonte.

## Reprodução sugerida

```bash
cp .env.example .env
# .env local: APP_URL/APP_PORT=18082, DB_DATABASE=ceara_spec002_local,
# DB_TEST_DATABASE=ceara_spec002_testing, FORWARD_DB_PORT=33361, WWWUSER/WWWGROUP = id -u / id -g
bash scripts/bootstrap-local.sh
docker compose -p ceara_spec002_dev up -d --build
docker compose -p ceara_spec002_dev exec -T laravel.test php artisan key:generate --force
docker compose -p ceara_spec002_dev exec -T laravel.test php artisan migrate --force
docker compose -p ceara_spec002_dev exec -T laravel.test php artisan db:seed --force
npm ci --ignore-scripts && npm run build
# criar o banco de teste (seção acima), depois:
docker compose -p ceara_spec002_dev exec -T laravel.test php artisan test
docker compose -p ceara_spec002_dev exec -T laravel.test vendor/bin/pint --test
```

Roteiro manual (dados sintéticos):
1. `/register`: criar PF com CPF `529.982.247-25`; verificar redirecionamento para `/participante` sem confirmação de e-mail.
2. Sair; criar PJ com CNPJ alfanumérico `12.ABC.345/01DE-35` e tipo "Empresa".
3. Tentar repetir o documento sem máscara (`52998224725`) com outro e-mail → erro "Já existe uma conta de participante com este documento."
4. Criar admin por `php artisan app:create-admin` e tentar entrar com ele em `/login` → "E-mail ou senha inválidos."; `/admin` continua funcionando.
5. `/forgot-password` com e-mail de participante, e-mail inexistente e e-mail do admin → mesma mensagem (só o participante recebe link); link no `storage/logs/laravel.log` para o cadastrado; redefinir e entrar com a nova senha.
6. Seis tentativas erradas de login seguidas → a sexta retorna 429.
7. Conferir que `/participante` não mostra CPF/CNPJ nem telefone.
8. Como admin, em `/admin/jurados`: criar um jurado; usar "Alterar senha" na linha dele; confirmar que senha com menos de 12 caracteres é recusada. Entrar como participante e abrir `/admin/jurados` → 403.

## Evidência executada pelo DEV

| Verificação | Resultado |
|---|---|
| `bash scripts/bootstrap-local.sh` | exit 0 |
| `up -d --build`, `key:generate`, `migrate`, `db:seed` | exit 0; migration `create_participant_profiles_table` aplicada |
| `php artisan test --compact` | **117 testes, 245 assertions, todos passaram** (29 da SPEC-001 + 88 novos) |
| `vendor/bin/pint --test` e o comando do CI | passaram (`skills/` excluída por `pint.json`) |
| `scripts/validate-foundation.sh`, `composer validate --strict`, `composer audit`, `npm audit --audit-level=high` | passaram (0 advisories, 0 vulnerabilidades) |
| `migrate:status` | nenhuma tabela de 2FA/passkeys (migrations publicadas pelo `fortify:install` foram removidas) |
| `npm run build` | passou |
| HTTP local | `/`, `/login`, `/register`, `/forgot-password` → 200; `/participante` sem login → 302 para `/login` |
| Fluxo real com cookie/CSRF | cadastro PJ com CNPJ alfanumérico → 302 para `/participante`; página mostra nome, "Pessoa jurídica (CNPJ)", "Empresa", "Fortaleza/CE" |
| Navegador | **não executado** (extensão de navegador indisponível); QA deve conferir o visual das páginas |

Testes novos: `tests/Unit/DocumentRulesTest.php`, `tests/Feature/Account/RegisterParticipantTest.php`, `tests/Feature/Account/ParticipantAuthTest.php`, `tests/Feature/Admin/JudgeManagementTest.php`. Alterado: `tests/Feature/Admin/AdminAccessTest.php` (ver SPEC, seção "Alteração de teste da SPEC-001").

## Segurança e escopo QA

- Papel definido no servidor; `role` fora do `$fillable` (inalterado desde a SPEC-001).
- Login do site restrito a participante com mensagem genérica; throttle 5/min.
- Recuperação de senha neutra quanto à existência do e-mail.
- `participant_profiles.document` e `phone` ocultos na serialização do model.
- CSRF ativo em todos os formulários (`@csrf`); Blade só com `{{ }}`.
- Nenhum HostGator, cPanel, DNS, e-mail real ou serviço pago foi acessado.

## Limites e pendências

- P2 (inscrições abrem em 28/09) sem resposta.
- P31 (e-mail) bloqueia a recuperação de senha em produção.
- Deploy remoto (P62, P65, P68) não verificado.
