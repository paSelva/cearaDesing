# Deploy no cPanel sem SSH — Ceará Tem Design 2026

Procedimento principal pelo **Gerenciador de Arquivos + Cron Jobs**. Terminal/SSH é apenas
uma alternativa. Não pressupõe root, WHM, fornecedor específico nem que PHP Web e PHP CLI
sejam o mesmo executável.

Use os valores literais abaixo como marcadores:

- `USUARIO`: usuário da conta cPanel;
- `SHA`: os 40 caracteres de `VERSION`;
- `DOMINIO`: domínio final;
- `PHP_CLI`: caminho absoluto do PHP CLI 8.4.1+ confirmado no cPanel.

Estrutura recomendada:

```text
/home/USUARIO/ceara-design/
├── releases/
│   └── SHA/                    # conteúdo de um único ZIP; não editar
└── shared/
    ├── .env                    # 600 ou 640; nunca substituir
    ├── admin-inicial.ini       # somente na primeira instalação; temporário
    └── storage/                # uploads, logs e estado; nunca substituir
```

O Cron cria links da release para `shared/.env` e `shared/storage`. O Document Root deve
apontar somente para `/home/USUARIO/ceara-design/releases/SHA/public`.

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

1. reconfirmar produto e versão do banco (`SELECT VERSION(), @@version_comment`);
2. versão/extensões do PHP Web e o caminho/versão/extensões do PHP CLI/Cron;
3. se o cPanel permite o Document Root fora de `public_html` e terminando em `/public`;
4. disponibilidade e frequência mínima de Cron; disponibilidade de Terminal/SSH;
5. domínio final, emissão de HTTPS e eventual proxy/CDN;
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

1. Em **Domínios**, crie/configure `DOMINIO`.
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

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://DOMINIO`;
- `APP_KEY` gerada uma vez e guardada no gerenciador de senhas;
- `DB_HOST` fornecido pela hospedagem (nunca presuma `mysql` do Docker), porta, banco,
  usuário e senha completos com prefixos do cPanel;
- sessões/cache em banco, fila `sync`, filesystem `local`;
- chaves Turnstile cadastradas para o domínio final;
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
bash scripts/smoke-producao.sh https://DOMINIO
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

## 11. Alternativa com Terminal/SSH

Se o cPanel confirmar Terminal/SSH, use os mesmos caminhos, SHA, backups e preflight. O
Terminal apenas permite executar o script uma vez e observar o código de saída:

```bash
/bin/bash /home/USUARIO/ceara-design/releases/SHA/deploy/cron-deploy.sh \
  /PHP_CLI /home/USUARIO/ceara-design/shared
```

Não rode Composer/npm no servidor, não use root e não crie rota HTTP para Artisan,
migrations, instalação, comandos do sistema ou `phpinfo()`.
