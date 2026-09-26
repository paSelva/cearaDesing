# Padrões de Segurança — MySQL + Eloquent

Exemplos para Laravel 13. Nomes de tabela/coluna seguem `docs/04-modelo-dados.md`;
quando um exemplo usar nome diferente, prevalece o doc.

---

## Queries — bindings sempre

```php
// ❌
DB::select("SELECT * FROM entries WHERE title LIKE '%$q%'");
Entry::whereRaw("category_id = $id")->get();

// ✅
DB::select('SELECT * FROM entries WHERE title LIKE ?', ['%'.$q.'%']);
Entry::where('category_id', $id)->get();
Entry::whereRaw('YEAR(created_at) = ?', [$year])->get();
```

`LIKE` com input: escapar `%` e `_` se a busca não deve aceitar curingas.

---

## Colunas dinâmicas — allowlist

```php
$allowed = ['created_at', 'title'];
$sort = in_array($request->sort, $allowed, true) ? $request->sort : 'created_at';
Entry::orderBy($sort, $request->dir === 'asc' ? 'asc' : 'desc')->paginate();
```

---

## SELECT seguro em superfícies públicas

```php
// Galeria (P55): só nome, categoria e imagens
Entry::query()
    ->where('status', EntryStatus::Aprovada)
    ->select(['id', 'title', 'category_id'])
    ->with(['category:id,name,slug', 'media'])
    ->paginate();
```

Nunca carregar `user` (dono) com todos os campos numa view pública — autoria e créditos
entram só na página de resultados (P44), e mesmo assim sem e-mail/CPF/telefone.

---

## IDOR — escopo pelo dono

```php
// ✅ participante
$entry = $request->user()->entries()->findOrFail($id);

// ✅ jurado — só atribuições ativas dele
$assignment = JudgeAssignment::query()
    ->where('judge_id', $request->user()->id)
    ->where('status', '!=', AssignmentStatus::Substituida)
    ->findOrFail($id);
```

---

## Migrations — integridade

```php
Schema::create('popular_votes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('voter_id')->constrained('users')->restrictOnDelete();
    $table->foreignId('entry_id')->constrained()->restrictOnDelete();
    $table->foreignId('category_id')->constrained()->restrictOnDelete();
    $table->timestamps();

    $table->unique(['voter_id', 'category_id']);   // P36
});
```

`CHECK` (só MySQL ≥ 8.0.16, ⚠️ P65):

```php
// Se a versão for 8.0.16+, reforço opcional — a validação na aplicação continua obrigatória
DB::statement('ALTER TABLE scores ADD CONSTRAINT scores_value_between_1_5 CHECK (value BETWEEN 1 AND 5)');
```

Nunca `migrate:fresh` / `db:wipe` em produção. Migrations de produção sempre com backup antes.

---

## Transações e corrida

```php
DB::transaction(function () use ($voter, $entry) {
    // Revalidar dentro da transação: período (P38), aprovada (P37)
    PopularVote::create([
        'voter_id'    => $voter->id,
        'entry_id'    => $entry->id,
        'category_id' => $entry->category_id,
    ]);
});
// UniqueConstraintViolationException → BusinessRuleException("Você já votou nesta categoria.")
```

Para contadores/agregações que decidem resultado (nota final, mais votado), calcular a partir
das linhas-fonte (scores, votes), não de colunas incrementadas por requisição.

---

## Coluna criptografada × unicidade

Desenho já registrado em `docs/04` (`voter_profiles`, Recomendação): `cpf_hash` + `cpf_encrypted`.

```php
// VoterProfile
protected function casts(): array
{
    return ['cpf_encrypted' => 'encrypted'];   // ciphertext muda a cada gravação → sem índice/busca
}

// RegisterVoter (docs/07)
$digits = preg_replace('/\D/', '', $cpf);
$profile->cpf_hash = hash_hmac('sha256', $digits, config('services.cpf_hmac_key')); // chave só no .env
$profile->cpf_encrypted = $digits;
```

> O nome da chave de configuração acima é ilustrativo — definir no código e documentar no `.env.example`.

---

## `.env` — credenciais do banco

```dotenv
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=<prefixo_cpanel>_<nome>
DB_USERNAME=<prefixo_cpanel>_<usuario>
DB_PASSWORD=<gerada no cPanel>
```

```bash
# .env nunca rastreado
git ls-files | grep -E '(^|/)\.env$'
# Histórico
git log --all --full-history -- .env
```

---

## Diagnóstico rápido (phpMyAdmin → SQL)

```sql
SELECT VERSION();                                 -- P65
SHOW GRANTS FOR CURRENT_USER();                   -- privilégios do usuário da aplicação
SELECT table_name, engine, table_collation
  FROM information_schema.tables
 WHERE table_schema = DATABASE();                 -- InnoDB + utf8mb4
SHOW INDEX FROM popular_votes;                    -- índices únicos das regras
```
