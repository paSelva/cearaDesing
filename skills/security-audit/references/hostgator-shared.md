# HostGator Compartilhada (sem SSH) — Checklist de Segurança

Fonte das decisões: `docs/02-stack.md`. Plano compartilhado (P30, P63), **sem SSH** (P66),
SSH não será solicitado (P67). Sistema em **subdomínio** de domínio existente (P29, ⚠️ nome: P62).

---

## 1. Estrutura de pastas e raiz do subdomínio

**Objetivo:** só a pasta `public/` do Laravel pode ser acessível pela web.

- [ ] Raiz (document root) do subdomínio apontando para `.../<projeto>/public` (⚠️ P68 — verificar no cPanel → Domínios/Subdomínios)
- [ ] O restante do projeto (`app/`, `vendor/`, `.env`, `storage/`) **fora** da raiz servida
- [ ] Se o cPanel **não** permitir apontar para `public/`: não improvisar com `.htaccess` na raiz do projeto sem revisão — registrar como bloqueio e decidir a estrutura com o responsável (P68)

**Teste depois do deploy (devem retornar 403/404):**
```
https://<subdominio>/.env
https://<subdominio>/composer.json
https://<subdominio>/storage/logs/laravel.log
https://<subdominio>/../.env
```

---

## 2. `.env` no servidor

- [ ] Criado **manualmente** no servidor (Gerenciador de Arquivos), nunca enviado pelo pacote de deploy nem pelo Git
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` própria de produção
- [ ] Permissão restrita (ex.: `600` ou `640`, conforme o que o PHP do servidor precisar para ler)
- [ ] Backup do `.env` guardado fora do servidor, em local seguro (não no repositório)

---

## 3. Deploy (build local + upload)

Processo recomendado (docs/02):
1. `composer install --no-dev --optimize-autoloader` e `npm run build` localmente
2. Pacote com `vendor/` e `public/build/`
3. Upload por **FTPS** ou Gerenciador de Arquivos do cPanel
4. Migrations/comandos por **cron temporário**

Checklist:
- [ ] Pacote **não** contém `.env`, `.git/`, `node_modules/`, `tests/`, dumps `.sql`, `anexos/`
- [ ] Pacote **não** sobrescreve `storage/app` (uploads dos participantes) nem `storage/logs`
- [ ] `vendor/` gerado com `--no-dev`
- [ ] Sem `bootstrap/cache/config.php` gerado na máquina local (caminhos absolutos errados)

---

## 4. Cron temporário para artisan

Exemplo de comando no cPanel → Cron Jobs (ajustar caminho do PHP 8.4 e do projeto):

```
/usr/local/bin/php /home/<usuario>/<projeto>/artisan migrate --force >> /home/<usuario>/<projeto>/storage/logs/cron-deploy.log 2>&1
```

- [ ] Rodar **uma vez** e **remover** o cron logo depois (migration repetida a cada minuto é risco de corrupção e de `migrate:fresh` acidental)
- [ ] **Nunca** agendar `migrate:fresh`, `db:wipe`, `db:seed` em produção
- [ ] Conferir a saída em `storage/logs/cron-deploy.log` e apagar o log depois
- [ ] Confirmar o caminho do binário do PHP 8.4 no cPanel (⚠️ pode variar por servidor)

**Cron permanente (filas e agendamentos, docs/02):**
```
* * * * * /usr/local/bin/php /home/<usuario>/<projeto>/artisan schedule:run >> /dev/null 2>&1
```
Com `queue:work --stop-when-empty` agendado no `routes/console.php`.

---

## 5. Banco MySQL (cPanel)

- [ ] Usuário do banco da aplicação com privilégios **só** no banco do projeto
- [ ] Senha forte, gerada pelo cPanel
- [ ] "MySQL Remoto" sem hosts liberados (ou só o necessário, temporariamente)
- [ ] phpMyAdmin acessado só pelo cPanel (não expor instalação própria)
- [ ] Versão do MySQL registrada (⚠️ P65)

---

## 6. Permissões de arquivo

- [ ] Nada em `777`
- [ ] `storage/` e `bootstrap/cache/` graváveis pelo processo do PHP
- [ ] Arquivos de código só leitura para a web

---

## 7. HTTPS

- [ ] Certificado válido no subdomínio (verificar no cPanel → SSL/TLS Status)
- [ ] Redirecionamento HTTP → HTTPS (no `.htaccess` de `public/` ou configuração do cPanel)
- [ ] `APP_URL` com `https://` e `SESSION_SECURE_COOKIE=true`

---

## 8. Conta de hospedagem

- [ ] Senha do cPanel e contas FTP fortes e exclusivas
- [ ] Contas FTP criadas só para o necessário e removidas quando não usadas
- [ ] Autenticação em dois fatores no cPanel/Portal do Cliente, se disponível
- [ ] Backups: ver skill `security-database` (Passo 4)
