# Validação da release em Percona Server 5.7.44-48

## Alvo informado

No cPanel, `SELECT VERSION(), @@version_comment` retornou `5.7.44-48` e
`Percona Server (GPL), Release 48`. A imagem oficial `percona:5.7.44` usada no ensaio
local reportou o mesmo produto e versão (`5.7.44-48`), em ambiente Linux x86_64 isolado.
O container usou volume e rede próprios, sem portas publicadas no host. O MySQL 8 local e
seus volumes não foram usados nem alterados; nenhuma conexão com o banco remoto foi feita.

Referências do fornecedor: [notas oficiais Percona 5.7.44-48](https://docs.percona.com/percona-server/5.7/release-notes/5.7.44-48.html)
e [imagem oficial `percona:5.7.44`](https://hub.docker.com/_/percona).

## Auditoria de compatibilidade

- As dependências travadas pedem PHP `>=8.4.1` e extensões `ctype`, `date`, `dom`, `exif`,
  `fileinfo`, `filter`, `hash`, `iconv`, `intl`, `json`, `libxml`, `mbstring`, `openssl`,
  `pcre`, `PDO`, `pdo_mysql`, `session`, `tokenizer`, `xmlreader`, `zip` e `zlib`.
- As migrations usam InnoDB, `utf8mb4_unicode_ci`, chaves estrangeiras e índices compostos
  dentro do limite de 3072 bytes. O servidor de ensaio usou `innodb_default_row_format=dynamic`
  e `innodb_large_prefix=1`; todas as migrations e índices foram criados com sucesso.
  Colunas JSON usadas pela aplicação são suportadas desde MySQL 5.7.8.
- Não foram encontrados `JSON_TABLE`, colunas geradas, índices funcionais, CTEs, funções de
  janela ou outras construções exclusivas do MySQL 8 nas migrations/SQL da aplicação.
- A migration de `scores` só adiciona o `CHECK (value BETWEEN 1 AND 5)` em MySQL 8.0.16+.
  Em Percona 5.7.44-48 ele é omitido. A regra continua na aplicação: `SaveScores` aceita
  somente inteiro de 1 a 5 e `CorrectScore` aplica validação de inteiro/faixa. Os testes
  cobriram zero, seis, decimal e texto; portanto, a regra de negócio não depende do `CHECK`.
- Não foi encontrada incompatibilidade de sintaxe ou recurso exclusivo do MySQL 8. MariaDB
  não foi testado e continua rejeitado pelo preflight.

## Ensaios executados

| Ensaio | Resultado |
|---|---|
| Consulta de versão, comentário do fornecedor, InnoDB, charset/collation | `5.7.44-48`, Percona Server Release 48; `utf8mb4`, `utf8mb4_unicode_ci`, InnoDB |
| Migrations oficiais em schema descartável novo | 13 migrations aplicadas, sem erro |
| `migrate:status` | Todas as 13 migrations reportadas como `Ran` |
| `EditionSeeder` | Executado duas vezes com sucesso no mesmo schema |
| Preflight da aplicação com variáveis sintéticas de produção | Sucesso; identificou explicitamente Percona Server 5.7.44-48 e schema correto |
| ZIP extraído e independente do checkout | `artisan --version`, autoload de produção, preflight, `migrate:status` e duas execuções do seeder concluíram no pacote extraído |
| Testes de avaliação, voto, publicação de resultados e criação de admin por arquivo | 93 testes/execuções reportadas como warnings pelo Pest, 289 assertions, sem falhas |
| Testes de saúde, headers, superfície pública e storage privado | 6 warnings, 19 assertions, sem falhas com `APP_URL=http://localhost` |
| Wrapper no pacote extraído | Trava concorrente retornou 75; requisito Turnstile ausente interrompeu antes de `migrate`; instalação criou o admin sintético, removeu o INI, preservou `.env` e upload sentinela, restringiu log a 600 e marcou a release |
| Repetição do Cron após marcador | Sucesso sem refazer migrations/seeder/admin; a conta sintética continuou única |
| Suíte completa | 22 testes passaram; 1 falhou porque o teste esperava ausência de HSTS em HTTP, mas a configuração de ensaio usava `APP_URL=https://example.test`. O teste de fundação isolado passou após usar `APP_URL=http://localhost`. |

Os warnings reportados pelo Pest não foram falhas nem interromperam os ensaios. O ambiente
foi descartável, com chave e credenciais sintéticas. A suíte não executou `migrate:fresh` ou
`db:wipe`; testes usaram schema previamente migrado e transações. Esse resultado comprova
compatibilidade local com a imagem e versão informadas, mas não substitui a checagem das
extensões/permissões do PHP no cPanel nem um teste funcional no domínio final.

## Mudanças relacionadas

- O preflight aceita Percona Server 5.7.44-48 e MySQL/Percona 8.0+; mantém MariaDB e outras
  versões de 5.7 bloqueadas até ensaio próprio.
- O build inclui `dom` e `xmlreader`, exigidas pela plataforma das dependências travadas.
- `docs/04-modelo-dados.md` e `docs/03-arquitetura.md` registram que a proteção de nota é
  validada pela aplicação e não depende de `CHECK`.
- `scripts/build-release.sh` aceita SHA explícito, constrói em pasta isolada e grava versão e
  checksum; a geração da release foi executada com PHP 8.4.26 nativo porque o Docker Desktop
  do host não estava operacional.

## Ainda precisa ser confirmado no cPanel

PHP Web e PHP CLI/Cron com versão/extensões efetivas; Document Root permitido; Cron e
Terminal/SSH; domínio e HTTPS; SMTP; limites de upload, quota e permissões. A versão do
banco remoto foi informada, mas o servidor remoto não foi alterado nem migrado.
