# Deploy na VPS — premiocearatemdesign.com.br

Instalação **limpa** numa VPS Ubuntu 24.04 **compartilhada com sites de clientes no ar**.
Regra que vale para tudo aqui: nada de outro site é editado, o nginx só recebe `reload`
(nunca `restart`) e nenhum pacote existente é atualizado ou removido sem decisão explícita.

O repositório é público: não escreva aqui IP, nomes de outros sites, credenciais ou chaves.
`VPS` é o alias do root no seu `~/.ssh/config`. Use **rede confiável** para todo acesso.

```text
/var/www/premio-ceara/            dono premioceara (sem senha, sem sudo)
├── current -> releases/SHA       nginx aponta para current/public
├── releases/SHA/
├── incoming/                     ZIP enviado; apagado após instalar
└── shared/                       700: .env (600) e storage/ (uploads e logs)
```

| Item | Valor |
|---|---|
| Banco / usuário | `premio_ceara` (só neste schema, só conexão local) |
| PHP | 8.4 de `packages.sury.org`, pool próprio em `/run/php/premio-ceara.sock` |
| PHP CLI | `/usr/bin/php8.4` (o comando `php` dos outros sites não muda) |
| Vhost | `/etc/nginx/sites-available/premiocearatemdesign.com.br` |
| Backup | `/var/backups/premio-ceara`, diário às 3h17, 14 dias |

O usuário `premioceara` é dono do código **e** roda o PHP-FPM, como no cPanel: simplifica as
permissões, mas uma falha de execução no app permitiria alterar o próprio código (não o dos
outros sites).

## 1. Antes de acessar o servidor

1. **DNS** (Registro.br → domínio → DNS): registro `A` do domínio e de `www` para o IPv4 da
   VPS. Não crie `AAAA` sem confirmar IPv6 na VPS. Confira: `dig +short premiocearatemdesign.com.br`.
2. **Turnstile** (painel da Cloudflare): widget com os hosts `premiocearatemdesign.com.br` e
   `www.premiocearatemdesign.com.br`; guarde as duas chaves no cofre. Sem elas o preflight recusa o deploy.
3. **SMTP** (P31): sem provedor, o sistema sobe com `MAIL_MAILER=log` e a **recuperação de
   senha não funciona**. Não abra inscrições nem votação assim.
4. Código aprovado na `main`, com CI verde. Anote o SHA completo.

## 2. Provisionamento (uma vez, como root)

Agrupe os comandos numa sessão só: conexões SSH repetidas podem acionar o fail2ban.

```bash
scp -r deploy VPS:/root/premio-ceara-deploy     # da raiz do repositório, no SHA aprovado
ssh VPS
cd /root/premio-ceara-deploy/vps
./provisionar.sh inventario
```

Pare e avalie se o inventário mostrar: MariaDB fora da série 10.11 ou sem acesso do root por
socket, `nginx -t` com erro, caminho ou usuário já em uso, PPA `ondrej/php` ativo, pouca
memória/disco, ou `AllowUsers`/`AllowGroups` no SSH. Guarde a saída da seção "Vizinhos".

```bash
./provisionar.sh php
./provisionar.sh base
./provisionar.sh http
./provisionar.sh certificado
```

- **php** simula a instalação e para se algo existente for atualizado ou removido. Se a VPS já
  tiver PHP, é esperado parar em `php-common` (pacote compartilhado entre versões). Avalie e,
  se aceitar, `ACEITO_ATUALIZACOES=sim ./provisionar.sh php`. Se o PPA antigo estiver ativo,
  outro site pode depender dele: siga a migração descrita pelo mantenedor, fora deste roteiro.
- **base** gera `APP_KEY`, `CPF_HMAC_KEY` e a senha do banco direto no `.env`, sem exibir.
  Repetir não altera o `.env`.
- **http** e **https** fazem backup de `/etc/nginx` em `/root`, validam, recarregam e desfazem
  sozinhos se algum vizinho mudar de resposta.
- **certificado** recusa se o DNS público ainda não apontar para esta máquina. Se não houver
  conta Let's Encrypt no servidor, acrescente `-m EMAIL`.

Depois, no servidor:

1. Edite `/var/www/premio-ceara/shared/.env` e preencha `TURNSTILE_*` e, se houver, `MAIL_*`.
2. Copie `APP_KEY` e `CPF_HMAC_KEY` para o cofre de senhas. **Nunca troque nenhuma das duas**:
   a primeira invalida sessões e dados cifrados; a segunda quebra o bloqueio de CPF duplicado.

## 3. Chave de deploy

Chave dedicada, sem reaproveitar a do root:

```bash
ssh-keygen -t ed25519 -f ~/.ssh/premio_ceara_deploy -C premio-ceara-deploy     # na sua máquina
```

No servidor, em `/home/premioceara/.ssh/authorized_keys` (pasta 700, arquivo 600, dono
`premioceara`), com o prefixo `restrict,pty`:

```text
restrict,pty ssh-ed25519 AAAA... premio-ceara-deploy
```

Para a chave guardada no GitHub, use outra, só com `restrict`. No seu `~/.ssh/config`, crie o
alias `premio-ceara` (usuário `premioceara`, essa chave).

## 4. Primeira instalação (manual)

À mão, e não pelo Actions, porque os logs do Actions são públicos. Na raiz do repositório:

```bash
SHA=SHA_COMPLETO_DA_MAIN
bash scripts/build-release.sh "$SHA"
scp "dist/ceara-tem-design-${SHA:0:12}.zip" "dist/ceara-tem-design-${SHA:0:12}.zip.sha256" premio-ceara:/var/www/premio-ceara/incoming/
ssh premio-ceara "bash -s -- install-zip /usr/bin/php8.4 /var/www/premio-ceara $SHA" < deploy/install-release.sh
```

Deve terminar em `== Deploy SHA ativo`. Se falhar, `current` não muda; o detalhe fica em
`shared/storage/logs/deploy-SHA.log`.

Administrador (senha em prompt oculto) e publicação:

```bash
ssh -t premio-ceara 'cd /var/www/premio-ceara/current && php8.4 artisan app:create-admin'
ssh -t VPS 'cd /root/premio-ceara-deploy/vps && ./provisionar.sh https && ./provisionar.sh backup'
```

## 5. Verificação, sempre de fora do servidor

```bash
bash scripts/smoke-producao.sh https://premiocearatemdesign.com.br
```

Abra também cada site da lista "Vizinhos" no navegador. Depois, sem dados reais: login do
admin, upload de imagem (até 5 MB) e PDF (até 10 MB), cadastro de eleitor com Turnstile,
recuperação de senha (com SMTP) e `certbot renew --dry-run --cert-name premiocearatemdesign.com.br`.

## 6. Próximos deploys

Repita a seção 4 (build, scp, install-zip), ou use **Actions → Deploy produção (SSH)**. Para o
Actions, crie o environment `production` com *required reviewers* e:

| Tipo | Nome | Valor |
|---|---|---|
| secret | `SSH_HOST`, `SSH_PORT`, `SSH_USER` | host, porta, `premioceara` |
| secret | `SSH_PRIVATE_KEY` | chave dedicada ao GitHub (seção 3) |
| secret | `SSH_KNOWN_HOSTS` | linha do `~/.ssh/known_hosts`, conferida em rede confiável |
| secret | `PHP_CLI` | `/usr/bin/php8.4` |
| secret | `DEPLOY_BASE` | `/var/www/premio-ceara` |
| variable | `APP_URL` | `https://premiocearatemdesign.com.br` |

A variável de repositório `PHP_CLI` pertence ao caminho do cPanel; não a altere.

Antes de release com migrations novas, rode `/usr/local/sbin/premio-ceara-backup` como root.
Não é preciso recarregar o PHP-FPM: o nginx passa o caminho real da release.

## 7. Rollback

Código (a release anterior fica guardada; o banco **não** volta):

```bash
ssh premio-ceara "bash -s -- rollback /usr/bin/php8.4 /var/www/premio-ceara SHA_ANTERIOR" < deploy/install-release.sh
```

Tirar o site do ar sem afetar os outros, como root: remova só o link
`/etc/nginx/sites-enabled/premiocearatemdesign.com.br`, `nginx -t` e `systemctl reload nginx`.

## 8. Operação

- **Backup fora da VPS:** o diário fica na mesma máquina. Copie para outro lugar e teste a
  restauração antes de abrir as inscrições.
- **PHP:** pacotes de `packages.sury.org` podem não entrar no `unattended-upgrades`. Planeje
  `apt-get install --only-upgrade 'php8.4-*'`.
- **Capacidade:** `pm.max_children = 10` é inicial. Reavalie com a memória livre da VPS e a
  carga esperada na votação.
- **HostGator:** a instalação antiga não é migrada. Desative-a para não haver dois sistemas no ar.

## Ensaio e o que falta comprovar

Em 24/11/2026 o roteiro rodou num container Ubuntu 24.04 (nginx 1.24, MariaDB 10.11.14, PHP
8.4.28, um site vizinho fictício): todas as etapas, smoke, recusa sem Turnstile/DNS/release,
limites de upload, retorno automático ao quebrar o vizinho, segunda release, rollback e restauração do backup.

**Não** foi exercitado: emissão real do certificado (usou autoassinado), systemd real, SSH,
firewall, Turnstile e SMTP reais, e o estado real da VPS.
