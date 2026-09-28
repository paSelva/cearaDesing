# Deploy na HostGator (cPanel, sem SSH) — votacao.cearadesign.org.br

Passo a passo para colocar o sistema no ar no subdomínio **votacao.cearadesign.org.br** (P62), com o sistema inteiro nele: site do prêmio, inscrições, painéis `/admin` e `/jurado`, galeria e voto popular. Complementa o [runbook de ambiente](ambiente-deploy-sem-ssh.md).

Siga na ordem. Se algum passo não for possível no cPanel, **pare** e registre — não improvise (principalmente o passo 2).

Estrutura final no servidor (`USUARIO` = usuário do cPanel):

```
/home/USUARIO/
├── votacao/               ← aplicação (FORA do public_html)
│   ├── public/            ← raiz do subdomínio (a única pasta acessível pela web)
│   ├── .env               ← criado à mão, permissão 600/640
│   ├── deploy/cron-deploy.sh
│   └── storage/           ← uploads e logs (nunca sobrescrever em atualizações)
└── admin-inicial.ini      ← temporário, só no primeiro deploy (apagado automaticamente)
```

## 0. Antes de começar (na sua máquina)

1. Gerar o pacote a partir do commit aprovado: `bash scripts/build-release.sh develop` → `dist/votacao-<sha>.zip`.
2. Gerar os segredos e guardar num gerenciador de senhas (nunca no chat, no Git ou em linha de cron):
   - `APP_KEY`: `docker compose -p ceara_spec002_dev exec laravel.test php artisan key:generate --show`
   - `CPF_HMAC_KEY`: `openssl rand -hex 32` — **definir uma vez e nunca trocar** (P35).

## 1. PHP 8.4 e limites

1. **MultiPHP Manager**: selecionar o domínio `votacao.cearadesign.org.br` (depois do passo 2) → **PHP 8.4**. Precisa ser antes do upload: o pacote recusa PHP menor que 8.4.
2. **Select PHP Version / extensões** (se existir no plano): ativar `exif`, `intl`, `pdo_mysql`, `mbstring`, `zip`, `fileinfo`.
3. **MultiPHP INI Editor** (8.4 / domínio): `upload_max_filesize = 10M`, `post_max_size = 12M`, `display_errors = Off`, `memory_limit` ≥ `256M`.

## 2. Subdomínio com raiz em `public/` (P68)

1. **Domínios** (ou Subdomínios) → criar `votacao` em `cearadesign.org.br`.
2. **Raiz do documento**: `/home/USUARIO/votacao/public` — **não** `public_html/votacao`.
3. Se o cPanel não permitir apontar para fora do `public_html`, **pare aqui** e avise: é preciso decidir um layout seguro antes de continuar (senão `.env` e o código ficam expostos).
4. Registrar a resposta de P68 em `docs/06`.

## 3. MySQL (P65)

1. **phpMyAdmin** → aba SQL → `SELECT VERSION();` e registrar em `docs/06` (P65). Precisa ser **5.7.8 ou superior** (coluna JSON do voto popular).
2. **Bancos de dados MySQL** → criar o banco e um usuário com senha forte; dar **todos os privilégios só nesse banco**.
3. **MySQL Remoto**: não liberar nenhum host.

## 4. HTTPS

1. **SSL/TLS Status** → rodar AutoSSL para `votacao.cearadesign.org.br` e confirmar o certificado.
2. **Domínios** → ativar **Forçar redirecionamento HTTPS** para o subdomínio.

## 5. Upload

1. **Gerenciador de Arquivos** → em `/home/USUARIO/` criar a pasta `votacao`, enviar o zip para dentro dela e **Extrair**. Apagar o zip depois. (FTP só com TLS/FTPS.)
2. Criar `/home/USUARIO/votacao/.env` a partir de `deploy/env.producao.example`, preenchendo banco, `APP_KEY`, `CPF_HMAC_KEY` e (quando houver) Turnstile e e-mail. **Permissões** → `600` (ou `640`).
3. Conferir permissões: pastas `755`, arquivos `644`, nunca `777`. `storage/` e `bootstrap/cache/` precisam ser graváveis.

## 6. Descobrir o PHP 8.4 da linha de comando

O cron nem sempre usa o mesmo PHP do site. Criar um cron de teste (**Cron Jobs**, a cada 15 minutos), trocando `USUARIO`:

```
for p in /opt/cpanel/ea-php84/root/usr/bin/php /usr/local/bin/ea-php84 /usr/local/bin/php /usr/bin/php; do echo "$p: $($p -v 2>&1 | head -1)"; done > /home/USUARIO/php-versoes.txt 2>&1
```

Depois de 15 minutos, abrir `php-versoes.txt`, anotar o caminho que mostra **PHP 8.4**, **apagar o cron de teste** e o arquivo.

## 7. Primeiro admin (sem terminal)

Criar `/home/USUARIO/admin-inicial.ini` (fora da pasta `votacao`), permissão `600`:

```ini
name="Nome do administrador"
email="admin@exemplo.org.br"
password="uma-senha-forte-com-12-ou-mais-caracteres"
```

O passo 8 cria o admin e **apaga este arquivo**. Se o arquivo continuar lá depois, veja o log (passo 8) e apague-o à mão.

## 8. Cron temporário de deploy

**Cron Jobs** → a cada 15 minutos (`*/15 * * * *`), com o caminho do passo 6:

```
/bin/bash /home/USUARIO/votacao/deploy/cron-deploy.sh /CAMINHO/DO/PHP84
```

Ele roda uma vez por versão: migrations, dados da edição 2026 (só na primeira vez), admin do arquivo do passo 7 e limpeza de caches. Depois da primeira execução:

1. Abrir `votacao/storage/logs/deploy-<versão>.log` e conferir que termina com `== concluído`.
2. **Remover o cron.** Nunca agendar `migrate:fresh`, `db:wipe` ou seeders de demonstração.

Não é preciso cron permanente: o sistema não usa filas nem tarefas agendadas.

## 9. Conferência

1. Rodar `bash scripts/smoke-producao.sh` (na sua máquina ou pedir ao desenvolvimento). Tudo deve dar `ok`.
2. Entrar em `/admin` com o admin criado e conferir a configuração da edição (datas do §7).
3. Registrar em `docs/06`: P65, P68, caminho do PHP CLI e se há proxy na frente (saída do smoke test).

## Atualizações (próximos deploys)

1. Gerar o novo pacote (passo 0).
2. **Backup** do banco (phpMyAdmin → Exportar) e de `storage/` antes.
3. Enviar o zip e extrair **por cima**, mas **sem apagar/sobrescrever `.env` nem `storage/`** (o pacote traz só o esqueleto de `storage/`; confira antes de confirmar a substituição).
4. Recriar o cron do passo 8 até o log da nova versão terminar com `== concluído`; remover o cron.

## Pendências que não bloqueiam o deploy, mas bloqueiam funções

- **E-mail (P31):** sem provedor, "Esqueci minha senha" só grava o link no log do servidor. Uma opção é uma conta de e-mail do próprio cPanel via SMTP; a organização decide.
- **Turnstile (P52):** sem as chaves reais para este domínio, o cadastro de eleitor fica indisponível. A votação abre em 29/10.
- **Proxy:** se o smoke test mostrar Cloudflare/proxy na frente, o limite de cadastros por IP (P52) e o IP dos aceites (P53) precisam de ajuste antes de 29/10.
