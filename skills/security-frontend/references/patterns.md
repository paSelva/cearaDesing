# Padrões de Segurança — Blade, Livewire, Filament

---

## Blade — escape por contexto

| Contexto | Use | Evite |
|---|---|---|
| Texto HTML | `{{ $x }}` | `{!! $x !!}` |
| Texto com quebras de linha | `{!! nl2br(e($x)) !!}` ou CSS `whitespace-pre-line` | `{!! nl2br($x) !!}` |
| Atributo | `title="{{ $x }}"` | atributo sem aspas |
| URL | `href="{{ $url }}"` **com** validação `url:http,https` na gravação | URL livre |
| JS / Alpine | `@js($x)` | `'{{ $x }}'` dentro de `<script>`/`x-data` |
| JSON para JS | `{{ Js::from($data) }}` | `json_encode` sem flags |

Busca rápida:
```bash
grep -rn "{!!" resources/views
grep -rnE "x-html|innerHTML|v-html" resources/
```

---

## Livewire — estado confiável

```php
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;

class EntryForm extends Component
{
    #[Locked]
    public int $entryId;                // cliente não pode trocar

    #[Validate('required|string|max:255')]
    public string $title = '';          // editável, validado

    public function save(UpdateEntry $action): void   // docs/07; criação usa CreateEntryDraft
    {
        $entry = Entry::findOrFail($this->entryId);
        $this->authorize('update', $entry);        // dono, prazo, não aprovada (P8, P70)

        $action->handle($entry, $this->validate());
    }
}
```

Regras:
- Nunca `public User $user` com dados pessoais num componente público.
- Métodos auxiliares que não devem ser chamados pelo cliente: `protected`/`private`.
- Redirecionar/recarregar depois de mudança de estado importante (voto, envio de notas).

---

## Livewire — uploads

```php
use Livewire\WithFileUploads;

public $proof;

public function rules(): array
{
    return ['proof' => ['required', 'file', 'mimetypes:application/pdf', 'max:10240']]; // P54
}
```

```php
// config/livewire.php (publicar com php artisan livewire:publish --config)
'temporary_file_upload' => [
    'rules' => ['required', 'file', 'max:10240'],  // teto geral; regra específica no componente
    // ...
],
```

⚠️ Limite das imagens (P6) ainda não definido — não fixar número.

---

## Filament — acesso e escopo

```php
// Painel do jurado: só as atribuições ativas do jurado logado
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()
        ->whereHas('assignments', fn ($q) => $q
            ->where('judge_id', auth()->id())
            ->where('status', '!=', AssignmentStatus::Substituida));
}
```

- Policy registrada para o model do Resource (Filament respeita `viewAny`, `view`, `create`, `update`, `delete`).
- Actions customizadas: `->authorize(...)` ou `->visible(...)` **e** checagem na Action de negócio.
- `->hidden()`/`->visible()` só escondem a UI — a proteção é a Policy.

---

## CSP inicial (Report-Only)

Ponto de partida — ajustar depois de observar o console:

```
Content-Security-Policy-Report-Only:
  default-src 'self';
  script-src 'self' 'unsafe-inline' 'unsafe-eval' https://challenges.cloudflare.com;
  style-src 'self' 'unsafe-inline';
  img-src 'self' data: blob:;
  font-src 'self' data:;
  connect-src 'self';
  frame-src https://challenges.cloudflare.com;
  frame-ancestors 'self';
  form-action 'self';
  base-uri 'self';
  object-src 'none'
```

- `'unsafe-eval'`: builds padrão do Alpine usam `new Function` para avaliar expressões. Se quiser removê-lo, pesquisar o build CSP do Alpine e a compatibilidade com Livewire/Filament da versão instalada antes.
- Fontes externas (ex.: Google Fonts) só se o layout usar — acrescentar domínio explicitamente.
- Nonce do Vite (`Vite::useCspNonce()`) é uma opção para endurecer `script-src` — testar com Livewire/Filament antes de ativar.

---

## Turnstile (P52)

```blade
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>

<form method="POST" action="{{ route('voter.register') }}">
    @csrf
    {{-- campos --}}
    <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.key') }}"></div>
    <button type="submit">Cadastrar</button>
</form>
```

A **site key** é pública; a **secret key** fica só no servidor. Validação no servidor:
`../security-backend/references/patterns.md`.

---

## Mensagens que não vazam informação

```blade
{{-- ❌ --}}
<p>Não existe conta com o e-mail {{ old('email') }}.</p>

{{-- ✅ --}}
<p>E-mail ou senha inválidos.</p>
```

Na recuperação de senha, a tradução padrão de `passwords.user` ("não encontramos um usuário
com esse e-mail") **revela** se a conta existe. Avaliar trocar por mensagem neutra
("se o e-mail estiver cadastrado, você receberá o link") em `lang/pt_BR/passwords.php`.
