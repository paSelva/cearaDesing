# Laravel Hardening — Prêmio Ceará Tem Design

Configurações de segurança para Laravel 13 + Fortify + Filament 5 + Livewire 4.
Antes de aplicar qualquer trecho, conferir a documentação da versão instalada
(`composer show laravel/framework filament/filament livewire/livewire`) — nomes de
classes e opções mudam entre versões maiores.

---

## 1. `.env` de produção (mínimo)

```dotenv
APP_ENV=production
APP_DEBUG=false                 # CRÍTICO: true expõe .env, queries e caminhos na tela de erro
APP_URL=https://<subdominio>    # ⚠️ P62

LOG_LEVEL=warning               # evita logar payloads em nível debug

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true      # cookie só via HTTPS
SESSION_ENCRYPT=true
SESSION_SAME_SITE=lax

CACHE_STORE=database            # sem Redis garantido (docs/02)
QUEUE_CONNECTION=database
```

- `APP_KEY` gerada **uma vez** para produção e nunca reaproveitada do ambiente local.
- Fuso `America/Fortaleza` em `config/app.php` (docs/04) — todas as checagens de prazo (§7) no servidor.

---

## 2. Headers de segurança (middleware próprio)

O Laravel não envia headers de segurança por padrão. Middleware simples:

```php
// app/Http/Middleware/SecurityHeaders.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
```

Registrar em `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
})
```

**CSP:** Livewire/Alpine e Filament usam scripts e estilos inline; o Turnstile carrega de
`challenges.cloudflare.com`. Comece com `Content-Security-Policy-Report-Only`, observe
o console e só depois ative. Ver `../security-frontend/references/patterns.md`.

**`includeSubDomains`:** o sistema fica em subdomínio de um domínio existente (P29). HSTS com
`includeSubDomains` no subdomínio afeta só os sub-subdomínios dele — ok. **Não** configurar HSTS
no domínio principal a partir deste projeto.

---

## 3. Fortify

```php
// app/Providers/FortifyServiceProvider.php
RateLimiter::for('login', function (Request $request) {
    $key = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());
    return Limit::perMinute(5)->by($key);
});
```

- `config/fortify.php` → `features`: ativar só o que foi decidido (T1). Confirmação de e-mail **não** é exigida do participante (P73) — não ligar `emailVerification()` para eles sem nova decisão.
- `CreateNewUser` (Action do Fortify) define o `role` **no código** (participante ou eleitor, conforme a rota), nunca a partir do input.
- Eleitor: validar o token do Turnstile no servidor dentro do `CreateNewUser`/Form Request (P52) + `RateLimiter` de 5 cadastros por IP por hora (docs/04).
- Reset de senha depende de e-mail transacional (⚠️ P31).

---

## 4. Filament (painéis `/admin` e `/jurado`)

```php
// app/Models/User.php
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser
{
    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin'  => $this->role === Role::Admin,
            'jurado' => $this->role === Role::Jurado,
            default  => false,
        };
    }
}
```

- Toda Resource com **Policy** (`viewAny`, `view`, `update`, `delete`...).
- Actions do Filament chamam as **Actions de negócio** (`app/Actions/<Área>/`, nomes em docs/07) — a regra fica em um lugar só.
- Painel do jurado: listar só atribuições do jurado logado com status diferente de `substituida` (docs/04).

---

## 5. Livewire

- Propriedades públicas são **editáveis pelo cliente**. IDs e flags sensíveis com `#[Locked]`.
- Cada método público chamável (`save`, `submit`, `vote`) faz `$this->authorize(...)` e valida.
- Não guardar model inteiro em propriedade pública quando só o ID é necessário.

```php
use Livewire\Attributes\Locked;

class EditEntry extends Component
{
    #[Locked]
    public int $entryId;

    public function save(UpdateEntry $action): void   // app/Actions/<Área>/UpdateEntry (docs/07)
    {
        $entry = Entry::findOrFail($this->entryId);
        $this->authorize('update', $entry);   // dono + prazo + não aprovada (P8, P70)
        $action->handle($entry, $this->validate());
    }
}
```

---

## 6. Erros e logs

- `APP_DEBUG=false` → o Laravel mostra página genérica (personalizar `resources/views/errors/500.blade.php` sem detalhes).
- Não logar `$request->all()` (contém senha/CPF). Se precisar, `$request->except(['password', 'password_confirmation', 'cpf', 'cnpj'])`.
- `storage/logs` fora do alcance público (garantido se a raiz é `public/`, ⚠️ P68).

---

## 7. Cache de configuração sem SSH

`php artisan config:cache` grava **caminhos absolutos** da máquina onde roda. Não gerar o cache
localmente para enviar ao servidor. Rodar `optimize` pelo cron temporário no próprio servidor
(`references/hostgator-shared.md`) ou não usar cache de config.
