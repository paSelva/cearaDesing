# Padrões de Segurança — Laravel (backend)

Exemplos para Laravel 13 / PHP 8.4. Conferir a documentação da versão instalada antes de aplicar.

---

## Actions + Policy (regra num lugar só)

A arquitetura do projeto (docs/03) põe a regra de negócio em `app/Actions`. Controllers,
Resources do Filament e componentes Livewire **autorizam e chamam a Action**. A Action
revalida as invariantes (não confia que o chamador checou).

```php
// app/Actions/<Área>/SubmitEvaluation.php (nome e regras: docs/07)
final class SubmitEvaluation
{
    public function __construct(private CalculateEntryResult $calculate) {}

    public function handle(JudgeAssignment $assignment): void
    {
        if ($assignment->status !== AssignmentStatus::Pendente) {
            throw new BusinessRuleException('Estas notas já foram enviadas.');   // P19
        }
        if ($assignment->scores()->count() !== 4) {
            throw new BusinessRuleException('Preencha as 4 notas antes de enviar.'); // §5
        }
        // + período de avaliação (judging_*, §7) — ver docs/07

        DB::transaction(function () use ($assignment) {
            $assignment->update([
                'status'       => AssignmentStatus::Enviada,
                'submitted_at' => now(),
            ]);
            $this->calculate->handle($assignment->entry);
        });
    }
}
```

```php
// app/Policies/JudgeAssignmentPolicy.php
public function score(User $user, JudgeAssignment $assignment): bool
{
    return $user->role === Role::Jurado
        && $assignment->judge_id === $user->id
        && $assignment->status === AssignmentStatus::Pendente;   // enviada = travada (P19)
}
```

> Colunas e enums seguem `docs/04-modelo-dados.md`; se divergir, vale o doc.

---

## Papéis sem pacote (T2)

```php
// app/Enums/Role.php
enum Role: string
{
    case Admin = 'admin';
    case Jurado = 'jurado';
    case Participante = 'participante';
    case Eleitor = 'eleitor';
}

// app/Models/User.php
protected function casts(): array
{
    return [
        'password' => 'hashed',
        'role'     => Role::class,
    ];
}
```

Middleware simples por papel:

```php
// app/Http/Middleware/EnsureRole.php
public function handle(Request $request, Closure $next, string $role): Response
{
    abort_unless($request->user()?->role === Role::from($role), 403);
    return $next($request);
}
```

```php
Route::middleware(['auth', 'role:participante'])->group(function () { /* ... */ });
```

---

## Prazos no servidor (§7)

```php
// Nunca confiar em botão desabilitado no front
$edition = Edition::sole();                 // registro único (P32)
abort_if(now('America/Fortaleza')->greaterThan($edition->registration_ends_at), 403, 'Inscrições encerradas.');
```

Colocar essa checagem na **Policy** ou na **Action**, não só no controller.

---

## Unicidade no banco + aplicação

```php
// migration
$table->unique(['edition_id', 'category_id', 'user_id']);   // 1 projeto por categoria (§4)

// popular_votes
$table->unique(['voter_id', 'category_id']);       // 1 voto por categoria (P36)
```

Na Action, converter `UniqueConstraintViolationException` em `BusinessRuleException` (corrida entre
duas requisições simultâneas passa pela validação, mas não pelo índice).

---

## Turnstile (P52)

```php
$response = Http::asForm()
    ->timeout(5)
    ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
        'secret'   => config('services.turnstile.secret'),
        'response' => $request->input('cf-turnstile-response'),
        'remoteip' => $request->ip(),
    ]);

if (! $response->ok() || ! $response->json('success')) {
    throw ValidationException::withMessages(['captcha' => 'Verificação falhou.']);
}
```

Se um pacote de integração for adotado (⚠️ versão não verificada, docs/02), preferir o pacote.
A chave secreta fica só no `.env`.

---

## IP real atrás de proxy

O limite por IP (P52) e o IP dos aceites (P53) dependem de `$request->ip()` correto. Se a
HostGator ou a Cloudflare estiverem na frente, configurar `trustProxies` em `bootstrap/app.php`
**apenas** para os proxies conhecidos — `at: '*'` permite forjar `X-Forwarded-For`.
⚠️ Confirmar a topologia de rede do servidor antes.

---

## Downloads privados

```php
Route::get('/comprovacoes/{media}', function (Media $media) {
    Gate::authorize('download', $media);    // dono, admin
    return response()->download($media->getPath(), $media->file_name);
})->middleware('auth');
```

Ou URL assinada com expiração curta (`URL::temporarySignedRoute`).

---

## Checklist de dependências (Composer)

```bash
composer audit                 # vulnerabilidades conhecidas
composer outdated --direct     # desatualizados
composer show -i | wc -l       # sanity check do tamanho da árvore
```

- Typosquatting: conferir vendor/nome no Packagist (`spatie/...`, `filament/...`, `laravel/...`)
- Evitar pacotes abandonados (sem release há muito tempo, sem suporte a Laravel 13)
