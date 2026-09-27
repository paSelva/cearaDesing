# Runbook — ambiente e deploy sem SSH

Este runbook prepara a prova em QA. Não autoriza produção, DNS, compra de serviços nem uso de dados reais.

## 1. Inventário de serviços

| Recurso | Finalidade | Obrigatório | Configuração | Responsável | Dependência | Teste de aceite | Situação |
|---|---|---:|---|---|---|---|---|
| GitHub + CI | código, PR e checks | sim | proteção de `main`/`develop`, Actions, secret scanning | mantenedor do repo | permissões GitHub | PR exige checks e bloqueia push direto | CI adicionada; proteção não verificada |
| DEV local | desenvolvimento | sim | PHP 8.4, Composer, Node travado, MySQL compatível | desenvolvimento | major Laravel e P65 | instalação limpa, testes e build | verificado em Docker; host sem PHP/Composer nativos |
| QA isolado | prova e revisão | sim | banco/storage/e-mail/portas próprios | DEV + QA | acesso e credenciais QA | fluxo reproduzível sem dados reais | não disponível |
| HostGator/cPanel | hospedagem | sim | plano, quotas, PHP web/CLI, cron, FTPS | organização/operador | acesso cPanel | app mínima servida com raiz segura | plano genérico confirmado; conta não verificada |
| MySQL | persistência | sim | P65, InnoDB, utf8mb4, banco/usuário por ambiente | operador | cPanel | migration/rollback em QA | local 8.0 verificado; P65 HostGator pendente |
| Domínio/DNS/HTTPS | acesso público | sim | P62, P68, certificado, redirecionamento | organização/operador | DNS/cPanel | HTTPS e arquivos sensíveis 403/404 | bloqueado P62/P68 |
| E-mail transacional | reset e avisos aprovados | sim para reset | P31, remetente e autenticação DNS | organização | provedor | reset chega e link funciona | bloqueado P31 |
| Turnstile | antifraude de eleitor | sim para votação | sitekey pública; secret backend; hosts QA/prod | organização + DEV | P62 | sucesso/falha/reuso/hostname testados | não implementado |
| Cron/filas | CLI, filas e scheduler | sim | PHP CLI e mínimo do plano | operador | cPanel/PHP CLI | job idempotente sem sobreposição | limite público 15 min; plano não verificado |
| Storage | mídia pública e documentos privados | sim | discos, quota, permissões, backup | operador + DEV | P6/P45/P68 | usuário B não baixa arquivo de A | separação local verificada; módulos/QA pendentes |
| Backup/restore | recuperação | sim | banco, storage e custódia das chaves | organização/operador | storage seguro | restore completo em QA | não executado |
| Logs/monitoramento | diagnóstico operacional | sim | retenção, redação, alerta, contato | operador | P28/responsável | falha detectada sem PII/secrets | bloqueado |

Cloudflare Tunnel, R2, Workers, migração de DNS, Redis e VPS não são requisitos desta fundação.

## 2. Variáveis por nome e custódia

| Grupo | Nomes | Regra |
|---|---|---|
| aplicação | `APP_NAME`, `APP_ENV`, `APP_URL`, `APP_DEBUG`, `APP_KEY` | `APP_KEY` é secret; produção usa debug falso |
| banco | `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_TEST_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | credenciais só no backend/cPanel; banco de teste exclusivo por ambiente |
| sessão/cache/fila | `SESSION_DRIVER`, `SESSION_SECURE_COOKIE`, `SESSION_ENCRYPT`, `CACHE_STORE`, `QUEUE_CONNECTION` | drivers precisam ser comprovados na hospedagem |
| e-mail | `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | credenciais privadas; P31 |
| Turnstile | `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY` | sitekey pública; secret nunca no bundle/resposta |
| documento | `CPF_HMAC_KEY` | secret independente, com plano de rotação |

Não criar variável secreta `VITE_*`. `.env.example` contém somente vazios e exemplos fictícios. O `.env` de cada ambiente é criado fora do pacote e do Git.

## 3. Preparação local reproduzível

1. Confirmar por decisão registrada a major do Laravel e obter P65.
2. No host, instalar Docker com Compose e Node/npm compatível com o lockfile. PHP e Composer nativos são opcionais: `scripts/bootstrap-local.sh` usa `composer:2.8.12` (validado com PHP 8.4.14, `mbstring` e `zip`) e instala o `composer.lock` antes de iniciar os containers da aplicação. O container `laravel.test` foi validado com PHP 8.4.26 e `pdo_mysql`, `mbstring` e `zip`.
3. Em checkout limpo, executar literalmente:

   ```bash
   cp .env.example .env
   bash scripts/bootstrap-local.sh
   docker compose -p ceara_local up -d --build
   docker compose -p ceara_local exec -T laravel.test php artisan key:generate --force
   docker compose -p ceara_local exec -T laravel.test php artisan migrate --force
   npm ci --ignore-scripts
   npm run build
   ```

4. O preflight exige `.env`, lockfile, Dockerfile, `artisan`, o script MySQL versionado em `.docker/mysql/` e, depois do Composer, `vendor/autoload.php` como arquivos regulares. Ele também executa `docker compose config --quiet`. O Compose não monta arquivos de `vendor/` durante a inicialização do MySQL.
5. Criar bancos exclusivamente descartáveis em `DB_DATABASE` e `DB_TEST_DATABASE`, nomeados para a SPEC/QA, e conferir projeto Compose, volume e portas antes de qualquer operação. Não usar `migrate:fresh` ou `db:wipe`.
6. Executar `composer validate --strict`, auditorias de dependência, estilo adotado, testes, migrations e `npm run build`.
7. Demonstrar discos separados: mídia elegível pública; rascunhos, PDFs e comprovações privados e servidos com Policy.

### Recuperação do BUG-QA-001

Uma tentativa anterior podia criar
`vendor/laravel/sail/database/mysql/create-testing-database.sh` como diretório. A correção
não depende mais desse caminho. Preserve o volume e quaisquer arquivos existentes:

```bash
docker compose -p <PROJETO_ANTERIOR> down
test -d vendor/laravel/sail/database/mysql/create-testing-database.sh \
  && printf 'Diretório legado preservado; ele não é mais montado pelo Compose.\n'
bash scripts/bootstrap-local.sh
docker compose -p <PROJETO_ANTERIOR> up -d --build --force-recreate
```

Não use `down -v`, não remova o diretório legado e não apague `vendor/`. O comando `down`
sem `-v` recria containers e rede, mas preserva o volume MySQL. Se o Composer acusar o
diretório legado, interrompa e preserve-o no storage privado antes de repetir:

```bash
mkdir -p storage/app/private/bootstrap-recovery
test ! -e storage/app/private/bootstrap-recovery/create-testing-database.sh.bind-mount-directory
mv vendor/laravel/sail/database/mysql/create-testing-database.sh \
  storage/app/private/bootstrap-recovery/create-testing-database.sh.bind-mount-directory
bash scripts/bootstrap-local.sh
```

Não force a instalação com `--ignore-platform-reqs`. A custódia acima fica fora da
superfície pública e pode ser inspecionada antes de ser descartada por decisão explícita.

### Contrato de rede do build

`npm run build` usa o plugin de fontes do Vite para obter Instrument Sans em
`fonts.bunny.net`. O build requer DNS e HTTPS de saída para esse host. Essa dependência
remota é uma limitação conhecida, não uma autorização para trocar fontes ou dependências
nesta SPEC.

## 4. Pacote de deploy

Gerar em ambiente compatível com PHP 8.4 e o servidor:

1. checkout do SHA aprovado;
2. `composer install --no-dev --classmap-authoritative --no-interaction`;
3. `npm ci && npm run build`;
4. validar a aplicação e construir arquivo de release;
5. inspecionar o conteúdo antes do envio.

Excluir obrigatoriamente `.env`, `.git/`, `node_modules/`, testes, `anexos/`, dumps, backups, logs, uploads locais e caches de configuração gerados fora do servidor. Incluir `vendor/`, assets compilados e lockfiles para rastreabilidade. Transferir por FTPS ou Gerenciador de Arquivos HTTPS do cPanel; FTP sem TLS não é aceitável.

## 5. Layout e smoke test

- O document root precisa apontar para `public/` (P68). Se o plano não permitir, parar e aprovar um layout seguro; nunca publicar a raiz completa.
- Criar `.env` no servidor por canal seguro, fora do pacote; produção com HTTPS, cookies seguros e debug falso.
- Confirmar permissões sem `777`; somente `storage/` e `bootstrap/cache/` graváveis.
- Verificar externamente que `/.env`, `/.git/HEAD`, `/composer.json`, logs, backups e storage privado retornam 403/404.
- O endpoint de saúde, quando adotado, informa apenas disponibilidade; nunca configuração interna.

## 6. Migration controlada por cron temporário

A HostGator documenta mínimo de 15 minutos em hospedagem compartilhada. Confirmar o plano real antes de configurar.

1. Fazer backup e registrar o release/SHA.
2. Confirmar PHP 8.4 no binário CLI e o caminho absoluto do projeto.
3. Criar comando wrapper CLI **fora da raiz pública**, com lock não bloqueante, marcador de sucesso por release e saída sanitizada. O wrapper deve executar somente `php artisan migrate --force` para o SHA esperado.
4. Agendar temporariamente em intervalo suportado (`*/15` somente após confirmação), acompanhar uma execução e conferir `migrations`/log sanitizado.
5. Remover imediatamente a tarefa temporária e o wrapper; nunca usar `migrate:fresh`, `db:wipe`, seed de demonstração ou endpoint HTTP.
6. Se a migration não for reversível com segurança, restauração é o rollback. Documentar antes do deploy.

Para filas permanentes, `schedule:run` no máximo a cada 15 minutos implica latência de 15 minutos ou mais. Usar `queue:work --stop-when-empty` com proteção contra sobreposição apenas após prova de timeout/memória. Inscrição e voto sempre validam o prazo no backend; cron não fecha a janela de negócio.

## 7. Backup, rollback e aceite remoto

- Backup inclui banco, uploads privados/públicos necessários e custódia separada de `APP_KEY`/`CPF_HMAC_KEY`.
- Testar restauração em QA isolado, incluindo download autorizado e mídia.
- Rollback de código restaura o pacote anterior; rollback de dados depende da migration. Nunca regenerar chaves durante rollback.
- Viabilidade remota só muda para comprovada após registrar: P62/P65/P68, PHP web/CLI, extensões, cron real, HTTPS, teste de raiz pública, migration/rollback, build compatível, e-mail, Turnstile e restore.
