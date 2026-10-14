# Deploy no cPanel da HostGator — Ceará Tem Design 2026

> **Atualizando um site que já está no ar?** Use o
> [guia rápido de deploy](guia-deploy-cpanel.md). Este documento é o roteiro completo da
> primeira instalação e a referência técnica.

**Caminho principal: Git Version Control do cPanel + branch `producao`** (seção 0). A conta
**não tem SSH** (confirmado em 13/10/2026). O workflow `.github/workflows/release-cpanel.yml`
gera o pacote do SHA aprovado (com `vendor/` e `public/build/`) e o publica como commit no
branch `producao`, junto de um `.cpanel.yml`. No cPanel, **Update from Remote** e **Deploy
HEAD Commit** rodam `deploy/install-release.sh`, que chama o mesmo `deploy/cron-deploy.sh`
(preflight, migrations, seeder e admin inicial) e só então troca o link `current`.

**Alternativa:** Gerenciador de Arquivos + Cron temporário (seções 5 a 7 e 11), se o deploy
pelo Git do cPanel não funcionar na conta. O workflow `deploy.yml` (SSH) fica dormente.

Nenhum caminho pressupõe root, WHM, Composer/npm no servidor nem que PHP Web e PHP CLI
sejam o mesmo executável.

Use os valores literais abaixo como marcadores:

- `USUARIO`: usuário da conta cPanel (nesta conta: `chega101`);
- `HOME`: pasta pessoal da conta (nesta conta: `/home1/chega101`, não `/home/...`);
- `SHA`: os 40 caracteres de `VERSION`;
- `DOMINIO`: domínio final;
- `PHP_CLI`: caminho absoluto do PHP CLI 8.4.1+ confirmado no servidor.

Estrutura:

```text
HOME/
├── repositories/cearaDesing/   # clone do Git Version Control; só leitura, nunca editar
└── ceara-design/
    ├── current -> releases/SHA # trocado de forma atômica após instalação concluída
    ├── releases/
    │   └── SHA/                # cópia do pacote; não editar
    └── shared/
        ├── .env                # 600 ou 640; nunca substituir
        ├── admin-inicial.ini   # somente na primeira instalação; temporário
        └── storage/            # uploads, logs e estado; nunca substituir
```

O `cron-deploy.sh` cria links da release para `shared/.env` e `shared/storage`. O Document
Root aponta para `HOME/ceara-design/current/public` (ou, se o cPanel recusar o link,
para `HOME/ceara-design/releases/SHA/public`). Nunca para a raiz da release nem para
`repositories/`.

## 0. Caminho principal: Git Version Control do cPanel

### 0.1 Por que um branch de release

- A HostGator compartilhada não oferece Node.js e não lista Composer
  ([compatibilidades](https://suporte.hostgator.com.br/hc/pt-br/articles/30811116692115-Quais-s%C3%A3o-as-compatibilidades-da-HostGator)),
  e `vendor/` e `public/build/` não são versionados na `main`. O build acontece no GitHub
  Actions e o resultado vira um commit no branch `producao`.
- O deploy do cPanel exige um `.cpanel.yml` na raiz do branch e **working tree limpa**; o
  modo pull roda as tarefas ao clicar em **Deploy HEAD Commit**
  ([documentação do cPanel](https://docs.cpanel.net/knowledge-base/web-services/guide-to-git-deployment/)).
  O `.cpanel.yml` existe só no `producao`; a `main` não pode ser implantada.
- O branch `producao` só avança (nunca force-push), porque o cPanel atualiza por pull.
- O workflow grava os arquivos byte a byte (`.gitattributes` com `* -text`) e confere que o
  checkout sai limpo; com normalização de fim de linha o cPanel veria o clone "sujo".

### 0.2 Sonda (uma vez, antes do primeiro deploy)

Ainda não está confirmado na conta que as tarefas do `.cpanel.yml` rodam sem SSH, nem com
qual `PATH`, pasta de trabalho e PHP. O branch `producao` começa com uma **sonda** que só
grava um relatório:

1. cPanel → **Git Version Control** → `cearaDesing` → **Manage**.
2. Aba **Pull or Deploy** → **Update from Remote**.
3. Aba **Basic Information** → **Checked-Out Branch** → `producao` → **Update**.
4. Aba **Pull or Deploy** → **Deploy HEAD Commit**.
5. No **Gerenciador de Arquivos** (com "mostrar arquivos ocultos"), abra
   `HOME/ceara-design-probe.txt` e envie o conteúdo para a equipe de desenvolvimento. Ele
   não contém senhas nem variáveis de ambiente.

O relatório confirma: se o deploy executa, a pasta de trabalho, `$HOME`, ferramentas
disponíveis (GNU `find`/`mv`, `tar`), os binários `ea-php8*` com versão e extensões, e
se uma variável exportada numa tarefa chega à seguinte. Com isso define-se `PHP_CLI`.
Se o arquivo não aparecer, o deploy pelo Git não executa nesta conta: use o caminho pelo
Cron (seção 11).

**Resultado na conta `chega101` (13/10/2026, sondas 1 e 2):**

- o deploy executa as tarefas como `chega101`, com pasta de trabalho no clone,
  `HOME=/home1/chega101`, `umask 0022`, e variável exportada persiste entre tarefas;
- GNU coreutils/findutils, `tar`, `realpath`, `unzip` e `git` disponíveis; kernel EL9;
- **`PHP_CLI=/opt/cpanel/ea-php84/root/usr/bin/php`** (8.4.26, todas as extensões exigidas).
  `/usr/local/bin/php` é 8.3 e `/usr/bin/php` é CGI: não use;
- links simbólicos dentro do `HOME` e a troca atômica `ln -s` + `mv -T` funcionam;
  **links que apontam para `/tmp` são removidos pelo servidor** (o instalador nunca cria);
- o clone fica com pasta 700 e arquivos 644.

Ainda não testado: se o Apache segue o link `current` no Document Root (P68), o que só se
confirma no primeiro deploy, pelo navegador.

### 0.3 Preparação única

1. **GitHub → Settings → Secrets and variables → Actions → Variables:** `PHP_CLI` =
   `/opt/cpanel/ea-php84/root/usr/bin/php` (confirmado pela sonda; não é segredo, vai
   escrito no `.cpanel.yml`). Configurada em 13/10/2026.
2. **Credencial do clone:** abra `HOME/repositories/cearaDesing/.git/config` no
   Gerenciador de Arquivos. Se a URL do `remote` tiver um token embutido, troque por uma
   deploy key **somente leitura** (GitHub → Settings → Deploy keys), pois o PHP da conta
   consegue ler esse arquivo.
3. Faça a seção 3 (domínio, PHP Web, limites de upload) e a seção 4 (banco).
4. Pelo Gerenciador de Arquivos, crie `HOME/ceara-design/shared/` e o `shared/.env`
   (seção 5), permissão 600. Na primeira instalação, crie também
   `shared/admin-inicial.ini` (seção 6). Nada disso passa pelo GitHub.
5. Confira cota de disco e de inodes no cPanel: o clone e as releases guardadas
   (a ativa e a anterior) somam cerca de três cópias de `vendor/` (~140 MB cada, ~16 mil arquivos).

### 0.4 Deploy

1. Mergeie na `main` e copie o SHA completo do commit aprovado. O botão do workflow só
   aparece depois que `release-cpanel.yml` estiver na `main`.
2. **Actions → Release para o cPanel (branch producao) → Run workflow** (branch `main`),
   `sha` = SHA. O workflow recusa SHA fora da `main` e execução a partir de outro branch.
3. Faça backup do banco pelo phpMyAdmin antes de releases com migrations novas.
4. No cPanel: **Git Version Control → Manage → Pull or Deploy → Update from Remote →
   Deploy HEAD Commit**.
5. Confira no Gerenciador de Arquivos `HOME/ceara-design-install.log` (sempre gravado, mesmo
   quando a falha acontece antes de existir `ceara-design/shared`) ou
   `HOME/ceara-design/shared/storage/logs/install-SHA.log`:
   deve terminar em `== Deploy SHA ativo`, com a linha `current -> .../releases/SHA`.
   Se falhar, `current` continua na release anterior; o detalhe fica em `deploy-SHA.log`.
6. Na primeira vez:
   - ajuste o Document Root para `HOME/ceara-design/current/public` (aceito pelo cPanel em
     13/10/2026, P68) e confirme que `admin-inicial.ini` foi apagado;
   - **depois** de ajustar o Document Root, aplique **PHP 8.4 (ea-php84)** ao domínio no
     MultiPHP Manager. O cPanel grava o handler no `.htaccess` da raiz
     (`current/public/.htaccess`); se for aplicado antes, o bloco fica na raiz antiga e o
     site roda o PHP padrão da conta (8.3: erro "Composer detected issues"). Nas releases
     seguintes, `install-release.sh` copia esse bloco da release ativa para a nova.
7. Rode `bash scripts/smoke-producao.sh https://DOMINIO` de uma máquina externa.

### 0.5 Rollback de código

Rode o workflow de novo com o SHA anterior e repita o passo 4. Se a release ainda estiver
guardada, ela é reaproveitada; se não, é recriada e o `cron-deploy.sh` roda de novo
(migrations e seeder são idempotentes). O banco **não** é revertido: confirme antes que a
release anterior é compatível com o schema atual (seção 10).

### 0.6 Se um dia houver SSH

O workflow `deploy.yml` (dormente) faz o mesmo por `scp`/`ssh` na porta 2222 e usa
`install-release.sh install-zip`. A HostGator libera SSH na compartilhada por chamado
([artigo](https://suporte.hostgator.com.br/hc/pt-br/articles/30814927583379-Como-habilitar-o-acesso-SSH)),
mas a própria tabela de compatibilidade o marca como indisponível. Exige os secrets
`SSH_HOST`, `SSH_PORT`, `SSH_USER`, `SSH_PRIVATE_KEY`, `SSH_KNOWN_HOSTS`, `PHP_CLI` e
`DEPLOY_BASE`, e a variável `APP_URL`.

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
4. deploy pelo Git Version Control executando (sonda da seção 0.2); se não executar,
   disponibilidade e frequência mínima de Cron;
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

Use as seções 5 a 7 (Gerenciador de Arquivos + Cron temporário) somente se o deploy pelo
Git Version Control não executar na conta (sonda da seção 0.2). O resultado no servidor é
o mesmo: ambos rodam `deploy/cron-deploy.sh`. Pelo Cron, o envio do ZIP, a extração e a
troca do Document Root são manuais.

Em nenhum caminho: rode Composer/npm no servidor, use root ou crie rota HTTP para Artisan,
migrations, instalação, comandos do sistema ou `phpinfo()`.
