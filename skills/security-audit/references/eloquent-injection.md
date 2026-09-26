# Injeção e Mass Assignment — Eloquent / Query Builder (MySQL)

O Query Builder e o Eloquent usam **bindings PDO** para valores. O risco aparece quando
input do usuário entra como **SQL cru** ou como **nome de coluna/direção**, que não são
parametrizados.

---

## 1. SQL cru com concatenação → CRÍTICO

```php
// ❌ VULNERÁVEL
Entry::whereRaw("title LIKE '%{$request->q}%'")->get();
DB::select("SELECT * FROM entries WHERE category_id = " . $request->category);

// ✅ SEGURO — bindings
Entry::whereRaw('title LIKE ?', ['%'.$request->q.'%'])->get();
Entry::where('title', 'like', '%'.$request->q.'%')->get();
DB::select('SELECT * FROM entries WHERE category_id = ?', [$request->integer('category')]);
```

Métodos a revisar sempre: `whereRaw`, `orWhereRaw`, `havingRaw`, `orderByRaw`, `selectRaw`,
`groupByRaw`, `DB::raw`, `DB::select/insert/update/delete/statement/unprepared`.

```bash
grep -rnE "Raw\(|DB::raw|DB::(select|statement|unprepared)" app/ database/ routes/
```

---

## 2. Colunas e direção dinâmicas → allowlist

```php
// ❌ VULNERÁVEL — nome de coluna não é parametrizado
$entries->orderBy($request->sort, $request->dir);
$entries->where($request->field, $request->value);

// ✅ SEGURO
$sort = in_array($request->sort, ['created_at', 'title'], true) ? $request->sort : 'created_at';
$dir  = $request->dir === 'asc' ? 'asc' : 'desc';
$entries->orderBy($sort, $dir);
```

Vale também para colunas JSON (`where('meta->'.$input, ...)`) e para filtros de tabela
customizados no Filament/Livewire.

---

## 3. Mass assignment

```php
// ❌ VULNERÁVEL — atacante envia role=admin, status=aprovada, final_score=60
User::create($request->all());
$entry->update($request->all());

// ✅ SEGURO — Form Request + validated() + $fillable explícito
$entry->update($request->validated());
```

```php
class Entry extends Model
{
    protected $fillable = ['title', 'description', /* ... campos do §4 */];
    // NÃO incluir: user_id, status, final_score, distinction
}
```

Campos que **nunca** vêm do formulário do participante/eleitor: `role`, `status` da inscrição,
notas e nota final, distinção, `results_published_at`. Eles são definidos pelas Actions (docs/07).

Opcional em desenvolvimento: `Model::shouldBeStrict(! app()->isProduction())` no
`AppServiceProvider` — dispara exceção ao tentar preencher atributo fora do `$fillable`.

---

## 4. Regras de validação com input do usuário

```php
// ❌ ignore() com ID vindo do request permite burlar a regra unique
Rule::unique('users')->ignore($request->user_id);

// ✅ ID vem do usuário autenticado / model resolvido
Rule::unique('users')->ignore($request->user()->id);
```

`exists:tabela,coluna` e `unique:tabela,coluna` com **tabela/coluna fixas** no código.

---

## 5. Enumeração e IDOR via route model binding

```php
// ❌ qualquer participante acessa /inscricoes/15
Route::get('/inscricoes/{entry}', fn (Entry $entry) => view('entries.show', compact('entry')));

// ✅ scoped binding ou Policy
Route::get('/inscricoes/{entry}', ShowEntry::class)->can('view', 'entry');
```

---

## 6. MySQL 5.7 × 8.0 (⚠️ P65)

- `CHECK` (ex.: nota entre 1 e 5) é **ignorado** no 5.7 e só aplicado a partir do 8.0.16.
  A garantia principal fica na aplicação (validação + Action).
- Índices únicos são aplicados nas duas versões → usar para regras de unicidade
  (1 projeto por categoria por participante — `(edition_id, category_id, user_id)`, §4; 1 voto por categoria por eleitor — `(voter_id, category_id)`, P36; `cpf_hash` único, P35).
