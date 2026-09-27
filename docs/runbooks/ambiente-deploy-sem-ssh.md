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
| banco | `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | credenciais só no backend/cPanel |
| sessão/cache/fila | `SESSION_DRIVER`, `SESSION_SECURE_COOKIE`, `SESSION_ENCRYPT`, `CACHE_STORE`, `QUEUE_CONNECTION` | drivers precisam ser comprovados na hospedagem |
| e-mail | `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | credenciais privadas; P31 |
| Turnstile | `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY` | sitekey pública; secret nunca no bundle/resposta |
| documento | `CPF_HMAC_KEY` | secret independente, com plano de rotação |

Não criar variável secreta `VITE_*`. `.env.example` contém somente vazios e exemplos fictícios. O `.env` de cada ambiente é criado fora do pacote e do Git.

## 3. Preparação local reproduzível

1. Confirmar por decisão registrada a major do Laravel e obter P65.
2. Instalar PHP 8.4 e extensões exigidas pelo lockfile; instalar Composer. Não usar o PHP 8.3 ou uma imagem MySQL arbitrária para declarar compatibilidade.
3. Criar a fundação e commitar `composer.lock` e `package-lock.json`. Instalar com `composer install` e `npm ci`.
4. Criar banco exclusivamente descartável, nomeado para a SPEC/QA, e conferir host/nome antes de qualquer `migrate:fresh`.
5. Executar `composer validate --strict`, auditorias de dependência, estilo adotado, testes, migration/rollback e `npm run build`.
6. Demonstrar discos separados: mídia elegível pública; rascunhos, PDFs e comprovações privados e servidos com Policy.

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
