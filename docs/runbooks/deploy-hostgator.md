# Deploy no cPanel da HostGator — Ceará Tem Design 2026

**Caminho principal: SSH + GitHub Actions** (seção 0). O workflow
`.github/workflows/deploy.yml` gera o ZIP do SHA aprovado, envia por SSH e roda no servidor
`deploy/ssh-deploy.sh`, que chama o mesmo `deploy/cron-deploy.sh` (preflight, migrations,
seeder e admin inicial) e só então troca o link `current`.

**Alternativa sem SSH:** Gerenciador de Arquivos + Cron temporário (seções 5 a 7 e 11),
usada enquanto a HostGator não liberar o SSH ou se o pedido for negado.

Nenhum dos caminhos pressupõe root, WHM, Composer/npm no servidor nem que PHP Web e PHP
CLI sejam o mesmo executável.

Use os valores literais abaixo como marcadores:

- `USUARIO`: usuário da conta cPanel;
- `SHA`: os 40 caracteres de `VERSION`;
- `DOMINIO`: domínio final;
- `PHP_CLI`: caminho absoluto do PHP CLI 8.4.1+ confirmado no cPanel.

Estrutura recomendada:

```text
/home/USUARIO/ceara-design/
├── current -> releases/SHA     # só no caminho SSH; trocado de forma atômica
├── incoming/                   # só no caminho SSH; ZIP recebido do GitHub (apagado após sucesso)
├── releases/
│   └── SHA/                    # conteúdo de um único ZIP; não editar
└── shared/
    ├── .env                    # 600 ou 640; nunca substituir
    ├── admin-inicial.ini       # somente na primeira instalação; temporário
    └── storage/                # uploads, logs e estado; nunca substituir
```

O `cron-deploy.sh` cria links da release para `shared/.env` e `shared/storage`. O Document
Root aponta para `/home/USUARIO/ceara-design/current/public` no caminho SSH, ou para
`/home/USUARIO/ceara-design/releases/SHA/public` no caminho pelo Cron. Nunca para a raiz
da release.

## 0. Caminho principal: SSH + GitHub Actions

### 0.1 O que a HostGator documenta (hospedagem compartilhada)

- O SSH **não é ativado pelo cPanel**: nos planos Start, P, M, Turbo, Business e Plus ele é
  habilitado **pelo suporte**, por chamado
  ([artigo](https://suporte.hostgator.com.br/hc/pt-br/articles/30814927583379-Como-habilitar-o-acesso-SSH)).
  Em revenda, o próprio dono habilita no WHM (Modificar uma conta → Acesso ao Shell).
- Conexão: **porta 2222**, host = IP do servidor ou domínio já propagado, usuário = usuário
  do cPanel ([artigo](https://suporte.hostgator.com.br/hc/pt-br/articles/30812824748051-Como-acessar-um-servidor-via-SSH)).
  Chaves podem ser geradas/importadas no cPanel
  ([artigo](https://suporte.hostgator.com.br/hc/pt-br/articles/30819530838291-Como-gerar-chaves-de-acesso-SSH-no-cPanel)).
- A tabela de compatibilidade marca Node.js como indisponível na compartilhada e não lista
  Composer. Por isso o build acontece no GitHub Actions, nunca no servidor. O deploy pelo
  "Git Version Control" do cPanel não é usado: o repositório é privado e `vendor/` e
  `public/build/` não são versionados.

Esses dados vêm da documentação pública; a conta real ainda precisa confirmar (P66). Há
uma contradição na própria HostGator: a
[tabela de compatibilidade](https://suporte.hostgator.com.br/hc/pt-br/articles/30811116692115-Quais-s%C3%A3o-as-compatibilidades-da-HostGator)
marca "SSH2" como indisponível em compartilhada/revenda, enquanto o artigo de habilitação
diz que o suporte libera. Só o chamado resolve; mantenha o caminho pelo Cron pronto.

### 0.2 Preparação única

1. **Chamado na HostGator** pedindo o SSH da conta. No mesmo chamado, peça:
   - a **impressão digital (fingerprint) da chave do host SSH**, para conferir o
     `known_hosts`;
   - o caminho do **PHP CLI 8.4** (a documentação da HostGator usa o padrão
     `/opt/cpanel/ea-phpXX/root/usr/bin/php`; confirme o de 8.4);
   - se o Document Root do subdomínio pode ficar fora de `public_html` e passar por um link
     simbólico (`/home/USUARIO/ceara-design/current/public`) (P68).
2. **Chave de deploy exclusiva**, gerada na sua máquina (sem passphrase, usada só pelo
   GitHub):

   ```bash
   ssh-keygen -t ed25519 -C "deploy-github-ceara" -f ~/.ssh/ceara_deploy -N ""
   ```

   No cPanel: **Acesso SSH → Gerenciar chaves SSH → Importar chave**, cole o conteúdo de
   `ceara_deploy.pub` e clique em **Autorizar**. A chave privada vai só para o secret do
   GitHub; apague a cópia local depois de configurar.
3. **known_hosts:** rode `ssh-keyscan -p 2222 HOST` e compare o fingerprint
   (`ssh-keygen -lf`) com o informado pela HostGator antes de usar a saída. Use em
   `SSH_HOST` exatamente o mesmo `HOST` (IP ou domínio) do `ssh-keyscan`: a entrada
   `[HOST]:2222` do known_hosts precisa bater, senão a conexão é recusada.
4. **Primeira sessão SSH manual** (`ssh -p 2222 -i ~/.ssh/ceara_deploy USUARIO@HOST`):
   - confirme `PHP_CLI -v` e `PHP_CLI -m` (extensões da seção 1);
   - crie `/home/USUARIO/ceara-design/shared/` e o `shared/.env` (seção 5) com
     `chmod 600`. O `.env` e o `admin-inicial.ini` **nunca** passam pelo GitHub;
   - na primeira instalação, crie `shared/admin-inicial.ini` (seção 6).
5. **GitHub → Settings → Environments → `production`:**
   - **Required reviewers** e **Deployment branches: somente `main`**, se o plano do
     GitHub oferecer esses recursos para repositório privado (o repositório é privado e de
     conta pessoal; confira na tela). Sem eles, a proteção é: só quem tem escrita dispara
     o workflow, e o próprio job recusa execução fora da `main`;
   - se o plano não oferecer secrets de environment, cadastre os mesmos nomes em
     **Settings → Secrets and variables → Actions** do repositório;
   - **Secrets:** `SSH_HOST`, `SSH_PORT` (2222), `SSH_USER`, `SSH_PRIVATE_KEY`,
     `SSH_KNOWN_HOSTS`, `PHP_CLI`, `DEPLOY_BASE` (`/home/USUARIO/ceara-design`);
   - **Variables:** `APP_URL` (ex.: `https://acdesign.chegadim.com.br`).
6. Faça a seção 3 (domínio, PHP Web, limites de upload) e a seção 4 (banco) normalmente.
   O Document Root aponta para `/home/USUARIO/ceara-design/current/public`. Se o cPanel
   recusar esse caminho, use o da release (`releases/SHA/public`) e troque-o a cada
   atualização, como no caminho pelo Cron.

### 0.3 Deploy

1. Mergeie na `main` e copie o SHA completo do commit aprovado. O botão do workflow só
   aparece depois que `deploy.yml` estiver na `main`.
2. **Actions → Deploy produção (SSH) → Run workflow** (branch `main`): `sha` = SHA,
   `action` = `install`.
3. Aprove o environment `production`. O workflow:
   - recusa SHA que não esteja na `main`;
   - roda `scripts/build-release.sh SHA` no runner;
   - envia ZIP e `.sha256` por `scp` para `incoming/`;
   - roda `deploy/ssh-deploy.sh install` no servidor: confere o checksum com o PHP CLI,
     extrai com `ZipArchive` (não depende de `unzip`), executa `cron-deploy.sh`, mostra o
     log no job e só troca `current` se tudo passou;
   - mantém as 3 releases mais recentes (nunca apaga a ativa nem a anterior);
   - roda `scripts/smoke-producao.sh` contra `APP_URL`. O smoke não prova qual release
     está no ar (`/VERSION` é bloqueado de propósito): confira no log do job a linha
     `current -> .../releases/SHA`.
4. Se a instalação falhar, `current` continua na release anterior. O log completo fica em
   `shared/storage/logs/deploy-SHA.log`. Código 75 significa outra instalação em andamento.
5. Faça backup do banco antes de releases com migrations novas (seção 9, item 1).

### 0.4 Rollback de código

**Actions → Run workflow** com `action` = `rollback` e o SHA de uma release ainda
instalada e concluída. O link `current` volta para ela e os caches são limpos. O banco
**não** é alterado: confirme antes que a release é compatível com o schema atual (seção 10).

## 1. Requisitos comprovados e dados ainda pendentes

Dependências travadas em `composer.lock`:

- PHP CLI e Web **8.4.1 ou superior**;
- extensões PHP `ctype`, `date`, `dom`, `exif`, `fileinfo`, `filter`, `hash`,
  `iconv`, `intl`, `json`, `libxml`, `mbstring`, `openssl`, `pcre`, `PDO`,
  `pdo_mysql`, `session`, `tokenizer`, `xmlreader`, `zip` e `zlib`;
- HTTPS de saída do PHP para o Cloudflare Turnstile; `curl` é recomendado e deve ser
  confirmado pelo teste real;
- Apache/LiteSpeed compatível com `public/.htaccess` e rewrite, ou configuração equivalente;
- diretórios `shared/storage` e `bootstrap/cache` graváveis pelo mesmo usuário/handler da
  aplicação;
- Percona Server 5.7.44-48 com InnoDB e `utf8mb4`, produto e versão informados pelo
  cPanel e reproduzidos localmente com a imagem oficial `percona:5.7.44`;
- MySQL 8.0 também tem histórico de validação no ambiente Docker do projeto.

MariaDB e outras versões de MySQL/Percona não foram validadas e são bloqueadas pelo
preflight. Antes da instalação, obter e registrar:

1. produto e versão do banco já informados: Percona Server 5.7.44-48; reconfirmar
   (`SELECT VERSION(), @@version_comment`) antes de instalar;
2. o seletor oferece PHP 8.4, mas ainda falta confirmar versão/extensões efetivas do PHP
   Web e o caminho/versão/extensões do PHP CLI/Cron (não presuma que são iguais);
3. se o cPanel permite o Document Root fora de `public_html` e terminando em `/public`;
4. SSH liberado pela HostGator (seção 0); sem SSH, disponibilidade e frequência mínima de
   Cron;
5. domínio escolhido `acdesign.chegadim.com.br`; confirmar DNS, emissão de HTTPS e eventual
   proxy/CDN antes de assumir que o site está acessível por HTTPS;
6. host/porta SMTP, remetente e requisitos de TLS/autenticação;
7. limites de quota, upload, corpo da requisição, memória e tempo no PHP e no servidor web.

Pare antes de publicar se o Document Root seguro não for permitido, se PHP Web/CLI não
atenderem aos requisitos ou se o banco divergir do Percona Server 5.7.44-48 validado. Não use
`--ignore-platform-reqs`, não faça downgrade e não exponha a raiz da aplicação.

## 2. Gerar e conferir o pacote local

Escolha o **commit aprovado**, não o nome de uma branch. O build recusa `HEAD`, tags e nomes
de branch:

```bash
git show --no-patch --oneline SHA_COMPLETO
bash scripts/build-release.sh SHA_COMPLETO
```

Saídas:

```text
dist/ceara-tem-design-SHA12.zip
dist/ceara-tem-design-SHA12.zip.sha256
```

O build usa um arquivo Git isolado, Composer sem `require-dev` e `npm ci`. Usa Docker
quando disponível; também aceita PHP 8.4.1+ e Composer nativos via `PHP_BIN` e
`COMPOSER_BIN`. Composer e npm precisam de acesso aos repositórios de dependências; o Vite
precisa de DNS/HTTPS para `fonts.bunny.net`. Não lê o `.env` do checkout e não gera cache
de configuração.

Antes do envio, confira o checksum e abra o ZIP. Ele deve conter `VERSION`,
`vendor/autoload.php`, `public/build/manifest.json`, assets do Filament,
`public/.htaccess`, migrations, `EditionSeeder` e o esqueleto de `storage`; não deve
conter `.env`, `.git`, `node_modules`, testes, factories, dumps, logs, uploads,
`public/hot` ou dependências de desenvolvimento.

## 3. Preparar domínio e PHP pela interface

1. Em **Domínios**, crie/configure `acdesign.chegadim.com.br`.
2. Crie pelo Gerenciador de Arquivos
   `/home/USUARIO/ceara-design/releases/SHA/public`.
3. Defina o Document Root exatamente como essa pasta `public`. Não use a raiz da release.
4. Em **MultiPHP Manager/Select PHP Version**, selecione PHP Web 8.4.1+ para o domínio e
   habilite as extensões da seção 1.
5. Em **MultiPHP INI Editor**, desligue `display_errors`. A aplicação aceita imagens de
   até 5 MiB e PDFs de até 10 MiB; configure pelo menos
   `upload_max_filesize=10M` e `post_max_size=12M` (preferível 16M), e confira também
   limites do servidor web/WAF, quota e timeout. `memory_limit=256M` é uma recomendação
   operacional, não um requisito medido.

PHP Web e CLI devem ser verificados separadamente. Para descobrir o CLI sem Terminal, crie
um Cron temporário que teste **somente caminhos oferecidos/documentados pelo cPanel**, um
por vez, e grave `php -v` e `php -m` em arquivo privado sob
`/home/USUARIO/ceara-design/`. Não copie uma lista de caminhos de outro provedor.
Apague o Cron e o arquivo após identificar `PHP_CLI`.

## 4. Criar o banco

1. Em **MySQL Database Wizard**, crie um banco exclusivo e um usuário exclusivo.
2. Use os nomes completos mostrados pelo cPanel, inclusive o prefixo `USUARIO_`.
3. Associe o usuário ao banco com os privilégios necessários somente nesse banco.
4. Não habilite acesso remoto.
5. No **phpMyAdmin**, selecione esse banco e execute apenas consultas de inventário:

```sql
SELECT VERSION(), @@version_comment;
SELECT DATABASE(), @@default_storage_engine;
```

O phpMyAdmin administra o banco; não é necessário importar um dump numa instalação nova.
As migrations oficiais criam a estrutura. Registre produto/versão sem publicar credenciais.

## 5. Enviar a release e criar a configuração privada

1. Envie o ZIP para `releases/SHA/` e extraia ali. Não extraia sobre outra release.
2. Abra `VERSION`: deve conter o SHA completo aprovado.
3. Opcionalmente, envie também o `.sha256` e use um Cron temporário com o PHP CLI para
   calcular `hash_file('sha256', '/caminho/do.zip')`; compare visualmente e apague o Cron,
   o resultado e o ZIP depois da extração.
4. Crie `/home/USUARIO/ceara-design/shared/`.
5. Copie o conteúdo de `deploy/env.producao.example` para `shared/.env` e preencha
   **privadamente**. Use permissão 600; 640 somente se o handler exigir leitura em grupo.

Configuração obrigatória:

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://acdesign.chegadim.com.br`
  depois que o HTTPS estiver ativo. Esse valor fica somente no `.env` privado desta
  instalação; o exemplo versionado continua genérico e o código não fixa o domínio;
- `APP_KEY` gerada uma vez e guardada no gerenciador de senhas;
- `DB_HOST` fornecido pela hospedagem (nunca presuma `mysql` do Docker), porta e usuário/
  senha completos com prefixos do cPanel. O banco informado para esta instalação é
  `chega101_cearadesign`; mantenha esse nome em `DB_DATABASE` no `.env` privado;
- sessões/cache em banco, fila `sync`, filesystem `local`;
- Turnstile configurado no Cloudflare Dashboard → Turnstile para o host
  `acdesign.chegadim.com.br`: copie a Site Key para `TURNSTILE_SITE_KEY` e a Secret Key
  para `TURNSTILE_SECRET_KEY` no `.env` privado compartilhado. Esses são os nomes lidos
  pela release atual (`config/services.php`); nunca exponha a Secret Key. O arquivo
  `deploy/env.producao.example` contém somente placeholders genéricos;
- `CPF_HMAC_KEY` aleatória e imutável;
- SMTP real, quando contratado/testado.

`MAIL_MAILER=log` não entrega e-mail: é somente diagnóstico e deixa a recuperação de senha
inoperante para o usuário final. Para entrega, mude para `smtp`, preencha os campos do
mailer, valide TLS/remetente/DNS e faça um teste funcional.

Nunca troque `APP_KEY` ou `CPF_HMAC_KEY` numa atualização ou rollback. Guarde cópia
criptografada fora da hospedagem. Nunca coloque segredo em nome de arquivo, comando Cron,
log, chamado de suporte, chat ou Git.

## 6. Permissões e administrador inicial

Use como base diretórios 755 e arquivos 644. `shared/.env` e o arquivo do administrador
devem ser 600/640. Dê escrita apenas a `shared/storage` e ao
`bootstrap/cache` da release, conforme o usuário/handler real. Se o PHP roda como o usuário
da conta, 755 costuma bastar; se roda em grupo, 775/664 pode ser necessário. Nunca use 777.

Somente se ainda não existir administrador, crie
`/home/USUARIO/ceara-design/shared/admin-inicial.ini`, permissão 600:

```ini
name="Nome do administrador"
email="admin@dominio.example"
password="SENHA_FORTE_COM_12_OU_MAIS"
```

O comando aceita somente essas três chaves, exige senha com pelo menos 12 caracteres,
recusa arquivo dentro da aplicação/pasta pública ou com permissão aberta, cria senha com
hash e tenta apagar o arquivo após sucesso. Se a remoção falhar, o log avisa: apague-o
imediatamente. Não recrie esse arquivo em atualizações. A senha nunca é impressa.

## 7. Preflight e instalação pelo Cron

Crie um Cron temporário (por exemplo, a cada 15 minutos; use a frequência disponível):

```text
/bin/bash /home/USUARIO/ceara-design/releases/SHA/deploy/cron-deploy.sh /PHP_CLI /home/USUARIO/ceara-design/shared
```

Todos os caminhos são absolutos. O script:

1. cria/valida os links para `shared/.env` e `shared/storage`;
2. usa trava atômica contra concorrência e marcador por SHA;
3. valida PHP 8.4.1+, extensões, arquivos, permissões e configuração;
4. testa conexão, schema selecionado, Percona Server 5.7.44-48 (ou MySQL/Percona 8.0+)
   e InnoDB sem registrar credenciais;
5. executa apenas `migrate --force`;
6. executa o `EditionSeeder` oficial e idempotente;
7. processa o administrador inicial somente se o arquivo existir;
8. limpa caches no servidor e grava o marcador de conclusão.

Em falha, sai com código não zero e não cria o marcador. Uma falha de requisito ocorre antes
das migrations. O log privado fica em
`shared/storage/logs/deploy-SHA.log`. Se houver uma trava obsoleta após interrupção
confirmada, remova apenas `shared/storage/app/.deploy.lock` pelo Gerenciador de Arquivos e
repita. Nunca remova a trava enquanto um Cron estiver ativo.

Quando o log terminar em `== concluído`:

1. remova imediatamente o Cron temporário;
2. confirme que `admin-inicial.ini` foi removido;
3. apague o ZIP enviado;
4. confirme o Document Root da release e habilite AutoSSL/HTTPS forçado.

Repetir o Cron depois do sucesso apenas registra que a release já foi concluída; não roda
migration/seeder, não recria administrador, não redefine senha e não altera o `.env`.

## 8. Validação funcional

Rode de uma máquina externa:

```bash
bash scripts/smoke-producao.sh https://acdesign.chegadim.com.br
```

Confira também, sem dados reais:

1. `/up`, home, cadastro/login, `/admin/login`, `/jurado/login`, galeria e premiados;
2. login do admin e dados oficiais da edição/categorias;
3. upload de imagem abaixo de 5 MiB e PDF abaixo de 10 MiB;
4. rejeição de arquivos acima dos limites;
5. acesso autorizado aos arquivos e 403/404 sem autorização;
6. cadastro de eleitor/Turnstile;
7. envio real de recuperação de senha quando SMTP estiver ativo;
8. HTTPS, cabeçalhos e inexistência de acesso web a `.env`, `storage/app/private`,
   `vendor`, `artisan`, `VERSION` e logs.

Todos os uploads desta release usam `storage/app/private` e são servidos por controllers
com autorização. Não execute `storage:link`; ele não é necessário e poderia ampliar a
superfície pública.

Não há classes `ShouldQueue`, dispatch de jobs, listeners enfileirados nem tarefas em
`Schedule` nesta release. `QUEUE_CONNECTION=sync` é suficiente e não há Cron/worker
permanente. Essa conclusão deve ser reavaliada a cada release. O Cron acima é somente o
instalador temporário.

## 9. Atualização

1. Faça backup verificável do banco pelo phpMyAdmin, de `shared/storage` e das chaves.
2. Gere um ZIP de novo SHA aprovado e extraia em uma nova pasta `releases/NOVO_SHA`.
3. Não copie, mova ou substitua `shared/.env` e `shared/storage`.
4. Revise as migrations novas: precisam ser não destrutivas e compatíveis com o código ainda
   ativo durante a preparação.
5. Aponte o Cron temporário para a nova release e aguarde `== concluído`.
6. Valide a nova release antes da troca, quando houver URL de preview segura.
7. Troque o Document Root para `releases/NOVO_SHA/public`, confirme HTTPS e rode o smoke.
8. Remova o Cron. Mantenha a release anterior até o aceite e a política de retenção.

Nunca extraia cegamente sobre o site ativo, nem sobrescreva `.env`/storage.

## 10. Rollback

Para rollback **de código**, confirme primeiro que a release anterior é compatível com o
schema já migrado e então altere o Document Root de volta para
`releases/SHA_ANTERIOR/public`. Ambas usam o mesmo `.env` e storage.

Não execute `migrate:rollback` automaticamente. Se uma migration incompatível exigir
rollback de banco, coloque o site em manutenção, preserve uma cópia do estado atual e
restaure o backup correspondente sob procedimento aprovado; isso pode perder dados criados
depois do backup. Valide a consistência de banco e uploads antes de reabrir.

## 11. Quando usar o caminho pelo Cron

Use as seções 5 a 7 (Gerenciador de Arquivos + Cron temporário) somente se o SSH não for
liberado ou estiver indisponível no momento do deploy. O resultado no servidor é o mesmo:
ambos rodam `deploy/cron-deploy.sh`. A diferença é que, pelo Cron, o envio do ZIP, a
extração e a troca do Document Root são manuais.

Em nenhum caminho: rode Composer/npm no servidor, use root ou crie rota HTTP para Artisan,
migrations, instalação, comandos do sistema ou `phpinfo()`.
