---
name: security-database
description: >
  Auditoria e hardening da camada de dados do Prêmio Ceará Tem Design: MySQL na HostGator
  (cPanel/phpMyAdmin, sem SSH), Eloquent/Query Builder do Laravel 13, migrations, índices
  únicos que garantem regras do edital, backups e LGPD. Gera correções prontas e pesquisa
  padrões desconhecidos. Use sempre que o usuário mencionar: "segurança do banco", "MySQL
  hardening", "SQL injection", "whereRaw", "DB::raw", "orderBy dinâmico", "mass
  assignment", "migration", "índice único", "CHECK constraint", "usuário do MySQL",
  "privilégios", "MySQL remoto", "phpMyAdmin", "backup", "dump do banco", "CPF no banco",
  "cpf_hash", "LGPD", "encrypted cast", "DB_PASSWORD", "vazamento de dados", "histórico de
  notas", ou variações. Acione também quando o usuário mostrar migrations, models,
  queries, seeders ou .env com DB_*, ou disser "antes de ir pra produção".
---

# Security:Database — MySQL + Eloquent (Prêmio Ceará Tem Design)

Auditoria da camada de dados. Analisa o código quando disponível; quando encontrar pacote
ou padrão desconhecido, pesquisa referências antes de opinar.

> **Regra do projeto:** tabelas, colunas e regras vêm de `docs/04-modelo-dados.md`
> (e do edital/decisões P#). Não criar coluna, limite ou regra que não esteja lá — perguntar.

---

## Passo 0 — Reconhecimento Inicial

1. Ler `references/cearadesign-context.md` e `docs/04-modelo-dados.md`.
2. Código disponível? `database/migrations`, `app/Models`, `app/Actions`, seeders, `.env` → analisar.
3. Versão do MySQL no servidor: ⚠️ P65 (5.7 ou 8.0). Ela muda o que o banco garante sozinho.
4. Ambiente: local (Herd/Sail), CI, produção (HostGator)?

---

## Passo 1 — Auditoria Eloquent / Query Builder

### 1.1 SQL cru e colunas dinâmicas

- [ ] `whereRaw`/`orderByRaw`/`selectRaw`/`DB::raw`/`DB::statement` com input concatenado → CRÍTICO
- [ ] `orderBy`/`where` com **nome de coluna** vindo do request sem allowlist
- [ ] Filtros e ordenações customizados em Filament/Livewire

```bash
grep -rnE "Raw\(|DB::raw|DB::(select|statement|unprepared)" app/ database/ routes/
```

> Exemplos completos: `references/patterns.md` e `../security-audit/references/eloquent-injection.md`.

### 1.2 Mass assignment

- [ ] `$fillable` explícito em todos os models; nada de `$guarded = []`
- [ ] Campos de sistema fora do `$fillable`: `role`, `status`, notas, nota final, distinção, `results_published_at`
- [ ] `create/update` com `$request->validated()`

### 1.3 Exposição de dados

- [ ] `$hidden` em `User` (`password`, `remember_token`) e perfis (CPF/CNPJ, telefone)
- [ ] Consultas públicas (galeria, resultados, avaliadores) com `select()` explícito das colunas permitidas (P44, P55, P57, P59)
- [ ] Nenhum `toArray()`/`toJson()` de model inteiro em view pública ou em propriedade pública do Livewire

---

## Passo 2 — Integridade no Banco (regras do edital)

O banco é a última linha de defesa contra corrida entre requisições e bugs de aplicação.

| Regra | Garantia no banco | Fonte |
|---|---|---|
| 1 projeto por categoria por participante | índice único `(edition_id, category_id, user_id)` em `entries` | §4 |
| 1 voto por categoria por eleitor | índice único `(voter_id, category_id)` em `popular_votes` | P36 |
| CPF único entre eleitores | índice único em `voter_profiles.cpf_hash` | P35 |
| Documento único entre participantes | índice único no perfil do participante | P74 |
| 1 atribuição por jurado por inscrição | único `(entry_id, judge_id)` em `judge_assignments` | docs/04 |
| 1 nota por critério por atribuição | único `(judge_assignment_id, criterion_id)` em `scores` | docs/04 |
| Nota 1–5 | `CHECK` só no MySQL ≥ 8.0.16; no 5.7 é ignorado → **validar na aplicação** | §5, ⚠️ P65 |
| Integridade referencial | FKs com `constrained()` e `restrictOnDelete()` onde apagar quebra histórico (notas, votos) | docs/04 |

- [ ] Migrations conferidas contra `docs/04-modelo-dados.md`
- [ ] Engine InnoDB (FKs e transações)
- [ ] Charset `utf8mb4` (nomes com acento, emojis em textos)
- [ ] Operações compostas (enviar 4 notas + travar; corrigir nota + registrar histórico) em `DB::transaction`

---

## Passo 3 — MySQL na HostGator (cPanel)

Sem SSH (P66): tudo pelo cPanel → **Bancos de dados MySQL** e phpMyAdmin.

### 3.1 Usuário e privilégios

- [ ] Usuário da aplicação com acesso **só** ao banco do projeto
- [ ] Privilégios: o app precisa de `SELECT, INSERT, UPDATE, DELETE`; migrations precisam também de `CREATE, ALTER, INDEX, DROP, REFERENCES`
  - Opção mais restrita: um usuário para migrations (usado só no cron temporário) e outro para a aplicação — ⚠️ avaliar se vale a complexidade (o `.env` só tem um conjunto de credenciais por padrão)
- [ ] Senha forte gerada pelo cPanel; nunca reaproveitada

### 3.2 Acesso externo

- [ ] cPanel → **MySQL Remoto**: nenhum host liberado (a aplicação conecta via `localhost`)
- [ ] Se liberar para depuração, remover logo depois
- [ ] `DB_HOST=localhost` (ou o valor que o cPanel indicar) no `.env` de produção

### 3.3 phpMyAdmin

- [ ] Acesso só pelo cPanel; nenhuma instalação própria de phpMyAdmin/Adminer em `public/`
- [ ] Edição manual de dados de produção evitada — correção de nota passa pelo admin com justificativa (P19, P46)

---

## Passo 4 — Backup e Recuperação

- [ ] Backup antes de **todo** deploy com migration
- [ ] Backups nos marcos do edital (§7): fim das inscrições (28/10), fim da avaliação/votação (20/11), antes de publicar o resultado (21/11)
- [ ] Onde: exportação pelo phpMyAdmin/cPanel ou cron com `mysqldump` (⚠️ confirmar disponibilidade no servidor)
- [ ] Arquivo de backup **fora** de `public/` e **fora** do repositório (`*.sql` no `.gitignore`)
- [ ] Cópia fora da HostGator, com acesso restrito (contém dados pessoais — §8)
- [ ] Restauração testada ao menos uma vez em ambiente local
- [ ] Uploads (`storage/app`) incluídos no backup — o banco sozinho não recupera imagens e PDFs

```
# Exemplo de cron (ajustar caminho, usuário e banco; credenciais num arquivo .my.cnf com permissão 600)
0 3 * * * mysqldump --defaults-extra-file=/home/<usuario>/.my.cnf --single-transaction <banco> | gzip > /home/<usuario>/backups/db-$(date +\%F).sql.gz
```

---

## Passo 5 — LGPD e Dados Sensíveis (§8)

Dados pessoais armazenados: CPF/CNPJ, nome, e-mail, telefone, cidade/UF (P4), CPF de eleitor
(P35), IP de cadastro e de aceites (P52, P53), comprovações em PDF (P54).

- [ ] Coletar só o que o edital/decisões pedem (nada de campos "por via das dúvidas")
- [ ] CPF do eleitor conforme docs/04 (Recomendação já registrada): `cpf_hash` (HMAC-SHA256 do CPF normalizado, **único**) + `cpf_encrypted` (cast `encrypted`). Conferir que o CPF **não** é gravado em claro em nenhuma outra coluna/log
- [ ] A chave do HMAC fica só no `.env`; se vazar, os hashes ficam sujeitos a força bruta (o espaço de CPFs é pequeno)
- [ ] Rotação de `APP_KEY` quebra colunas `encrypted` → usar `APP_PREVIOUS_KEYS`
- [ ] Histórico de alterações (activity log, P46) sem duplicar CPF/telefone no payload
- [ ] Retenção/eliminação dos dados após a edição (P32): ⚠️ depende de P28 (política de privacidade)
- [ ] Seeders/factories nunca usam dados reais

---

## Passo 6 — Aprendizado com Código Desconhecido

1. Pesquisar `"[pacote/padrão] laravel security"`, `"[pacote] CVE"`
2. Conferir no Packagist compatibilidade com Laravel 13 / PHP 8.4
3. Documentar no relatório e, se adotado, em `docs/02-stack.md`

---

## Passo 7 — Relatório Final

```markdown
# 🗄️ Relatório de Segurança — Banco de Dados
**Projeto:** Prêmio Ceará Tem Design 2026
**Data:** [data]
**Stack:** MySQL [versão, ⚠️ P65] · Laravel [versão] · HostGator compartilhada

## Resumo Executivo

## Achados por Severidade
### 🔴 CRÍTICO
### 🟠 ALTO
### 🟡 MÉDIO
### 🟢 BAIXO / INFORMATIVO

## ⚠️ Dependem de decisão (P#)

## Plano de Ação

## O que está BEM ✅

## Novos padrões aprendidos nesta sessão
```

---

## Referências

- `references/patterns.md` — padrões seguros de Eloquent, migrations e MySQL
- `references/cearadesign-context.md` — contexto do projeto relevante para segurança
