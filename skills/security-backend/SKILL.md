---
name: security-backend
description: >
  Auditoria e hardening de segurança do backend Laravel 13 (PHP 8.4) do Prêmio Ceará Tem
  Design: rotas, controllers, Actions, Form Requests, Policies/Gates, Fortify, Filament,
  Livewire, uploads com Spatie Media Library, filas por cron e configuração de produção
  na HostGator. Identifica vulnerabilidades reais, gera correções prontas e pesquisa
  online quando encontrar pacote ou padrão desconhecido. Use este skill sempre que o
  usuário mencionar: "segurança do backend", "rota segura?", "Laravel hardening",
  "Fortify", "rate limiting", "RateLimiter", "autenticação", "autorização", "Policy",
  "Gate", "RBAC", "papel/role", "IDOR", "middleware de auth", "validação de input",
  "Form Request", "mass assignment", "$fillable", "injeção", "SSRF", "upload de
  arquivo seguro", "media library", "APP_DEBUG", "erro expõe stack trace", "log com
  dados sensíveis", "variáveis de ambiente", "bcrypt", "argon2", "reset de senha",
  "rota sem auth", "Turnstile", "captcha", "fraude no voto", "auditoria de API",
  "OWASP backend", ou qualquer variação. Acione também quando o usuário mostrar rotas,
  controllers, Actions, Policies, Form Requests, providers, arquivos de config/ ou .env,
  ou disser "antes de ir pra produção" com código PHP visível.
---

# Security:Backend — Laravel (Prêmio Ceará Tem Design)

Auditoria da camada de aplicação. Analisa o código quando disponível; quando encontrar
pacote, middleware ou padrão desconhecido, pesquisa referências de segurança antes de opinar.

> **Regra do projeto:** regra de negócio só do edital (§x) ou de decisão confirmada (P#).
> O que não estiver em `docs/` vira pergunta — não inventar limites, campos ou prazos.

---

## Passo 0 — Reconhecimento Inicial

1. Ler `references/cearadesign-context.md` (atores, regras e decisões que afetam segurança).
2. Código disponível? `routes/`, `app/Actions`, `app/Policies`, `app/Http`, `app/Livewire`, `app/Filament`, `app/Providers`, `config/` → analisar diretamente.
3. Versões instaladas: `composer show laravel/framework laravel/fortify filament/filament livewire/livewire`.
4. Pacote desconhecido? → pesquisar antes de opinar (Passo 11).
5. Em produção? Qual data do edital (§7) está próxima? Correção arriscada perto de prazo → avaliar com o usuário.

---

## Passo 1 — Autenticação (Fortify)

### 1.1 Senhas

- [ ] Cast `'password' => 'hashed'` no `User` (bcrypt padrão do Laravel; argon2id opcional em `config/hashing.php`)
- [ ] Regras de senha com `Password::defaults()` definido no `AppServiceProvider`
- [ ] Nenhum lugar compara senha em texto (`Hash::check` apenas)

```php
// AppServiceProvider::boot()
Password::defaults(fn () => Password::min(8)->uncompromised());
```

> `uncompromised()` consulta a API do Have I Been Pwned — requer saída HTTP do servidor.
> Validar na HostGator antes de depender disso.

### 1.2 Cadastro

- [ ] `role` definido **no código** (`CreateNewUser`), nunca vindo do request
- [ ] Participante: CPF **ou** CNPJ, telefone, cidade/UF, tipo de participante (P4); sem confirmação de e-mail (P73)
- [ ] Participante: documento único entre participantes (P74)
- [ ] Eleitor (`RegisterVoter`): CPF único **entre eleitores** via `cpf_hash` (P35) — validação só do dígito verificador (P42); Turnstile validado no servidor + limite de 5 cadastros por IP por hora (P50, P52)
- [ ] Jurado e admin **não** passam pelo cadastro público — conta criada pelo admin (P17)

### 1.3 Sessão e login

- [ ] `RateLimiter::for('login', ...)` no `FortifyServiceProvider` (e-mail + IP)
- [ ] Filament: login do painel também tem throttle (conferir na versão instalada)
- [ ] `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true` em produção
- [ ] Reset de senha: `passwords.users.expire` e `throttle` em `config/auth.php`; depende de e-mail (⚠️ P31)

---

## Passo 2 — Autorização & Controle de Acesso

### 2.1 Papéis (T2: coluna `role` + Policies, sem pacote)

| Papel | Pode |
|---|---|
| `admin` | Painel `/admin`: triagem, atribuição de jurados (P15), substituição por conflito (P16, P47), correção de notas com justificativa (P19, P46), publicar resultado (P69) |
| `jurado` | Painel `/jurado`: só os projetos atribuídos (§5), 4 notas de 1 a 5, sem comentário (P18), notas travam após envio (P19) |
| `participante` | Área própria: 1 inscrição por categoria (§4), editar até o prazo e antes da aprovação (P8, P70), enviar comprovação (P12, P54) |
| `eleitor` | Votar: 1 voto por categoria (P36), só em aprovadas (P37), só no período (P38) |

- [ ] `canAccessPanel()` no `User` separando `admin` e `jurado`
- [ ] Todo Resource do Filament com Policy registrada
- [ ] Rotas da área do participante/eleitor com `auth` + checagem de `role` (middleware ou Gate)

### 2.2 IDOR

```php
// ❌ qualquer participante logado vê qualquer inscrição
public function show(Entry $entry) { return view('entries.show', compact('entry')); }

// ✅ Policy
public function show(Entry $entry)
{
    $this->authorize('view', $entry);   // EntryPolicy: $user->id === $entry->user_id
    return view('entries.show', compact('entry'));
}
```

- [ ] Download de comprovação/PDF passa por rota com Policy (nunca URL pública previsível)
- [ ] Jurado: query escopada pelas `judge_assignments` dele com status ≠ `substituida` — trocar o ID na URL não abre projeto de outro jurado

---

## Passo 3 — Validação de Input (Form Requests)

- [ ] Todo endpoint que grava usa Form Request (ou `$this->validate()` no Livewire)
- [ ] Controller/Action usa `$request->validated()` — nunca `$request->all()` (mass assignment)
- [ ] `$fillable` explícito; campos de sistema (`role`, `status`, notas, `final_score`, distinção) fora
- [ ] Links do participante: `url:http,https`
- [ ] Notas: `integer|between:1,5` (§5) — o `CHECK` do banco não vale no MySQL 5.7 (⚠️ P65)
- [ ] Datas/prazos checados no servidor, fuso `America/Fortaleza` (§7, P77)

```php
// Validação de formato; a regra completa fica na Action SubmitEvaluation (docs/07)
class SubmitEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('score', $this->route('assignment'));
    }

    public function rules(): array
    {
        return [
            'scores'   => ['required', 'array', 'size:4'],        // 4 critérios (§5)
            'scores.*' => ['required', 'integer', 'between:1,5'],
        ];
    }
}
```

> Mais vetores (raw SQL, orderBy dinâmico, `Rule::unique()->ignore`): `security-audit/references/eloquent-injection.md`.

---

## Passo 4 — Rate Limiting

```php
// AppServiceProvider::boot()
RateLimiter::for('voter-register', fn (Request $r) => Limit::perHour(5)->by($r->ip()));  // P52
RateLimiter::for('vote', fn (Request $r) => Limit::perMinute(10)->by($r->user()?->id ?: $r->ip()));
```

```php
Route::post('/eleitor/cadastro', ...)->middleware('throttle:voter-register');
```

- [ ] O limite do voto acima é **exemplo técnico**; o número real não foi decidido — confirmar antes de fixar
- [ ] Unicidade do voto garantida no **banco** (índice único `(voter_id, category_id)`, P36) — throttle não substitui
- [ ] `CACHE_STORE=database` (sem Redis garantido) — o limiter funciona com ele

---

## Passo 5 — Headers HTTP

O Laravel não adiciona headers de segurança por padrão → middleware próprio.
Código pronto: `security-audit/references/laravel-hardening.md` (seção 2).

---

## Passo 6 — CSRF e CORS

- [ ] Todas as rotas de formulário no grupo `web` (CSRF ativo)
- [ ] Nenhuma exceção de CSRF sem justificativa
- [ ] Não há API separada nem SPA (docs/02) → **não habilitar CORS**. Se aparecer `config/cors.php` com `*`, questionar

---

## Passo 7 — Tratamento de Erros

- [ ] `APP_DEBUG=false` em produção → CRÍTICO se `true`
- [ ] Views de erro próprias sem detalhes internos
- [ ] `BusinessRuleException` (docs/07) vira mensagem amigável em português, não 500

```php
// bootstrap/app.php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->dontFlash(['password', 'password_confirmation', 'current_password']);
})
```

---

## Passo 8 — Logging Seguro

- [ ] Não logar `$request->all()` (senha, CPF/CNPJ, telefone)
- [ ] Histórico de alterações (notas, status) via activity log (P46) sem copiar dados pessoais desnecessários
- [ ] `LOG_LEVEL=warning` ou superior em produção
- [ ] Rotação/limpeza de `storage/logs` (canal `daily` com `days`)

---

## Passo 9 — Upload de Arquivos (Media Library)

- [ ] Validação no servidor: `mimes`/`mimetypes` + `max` (KB)
  - Imagens: ⚠️ quantidade, formatos e tamanho — P6 (não fixar sem resposta)
  - Comprovação: 1 PDF, até 10 MB (P54) → `['required', 'file', 'mimetypes:application/pdf', 'max:10240']`
  - PDF complementar (P7): ⚠️ limites — P45
- [ ] Coleções de documento pessoal (comprovação) em disco **privado**; imagens públicas só depois da aprovação (galeria, P55)
- [ ] `acceptsMimeTypes()` e `singleFile()` nas coleções da Media Library quando aplicável
- [ ] Nome original nunca vira caminho; sem extensões executáveis
- [ ] Limite do PHP (`upload_max_filesize`, `post_max_size`) compatível — ⚠️ conferir no cPanel (MultiPHP INI Editor)

```php
public function registerMediaCollections(): void
{
    $this->addMediaCollection('proof')
        ->useDisk('local')                         // privado
        ->acceptsMimeTypes(['application/pdf'])
        ->singleFile();
}
```

---

## Passo 10 — SSRF e chamadas externas

- [ ] Validação do Turnstile: URL fixa da Cloudflare, timeout curto, falha = cadastro negado
- [ ] Nenhuma rota busca URL enviada pelo usuário (ex.: "importar imagem de link") sem allowlist
- [ ] `Http::timeout()` em toda chamada externa

---

## Passo 11 — Aprendizado com Código Desconhecido

1. Pesquisar: `"[pacote] laravel security"`, `"[pacote] CVE"`
2. Packagist: última release, compatibilidade com Laravel 13 / PHP 8.4, mantenedor
3. `composer audit` e GitHub Security Advisories do pacote
4. Registrar no relatório (e em `docs/02-stack.md` se o pacote for adotado)

---

## Passo 12 — Relatório Final

```markdown
# 🔒 Relatório de Segurança — Backend
**Projeto:** Prêmio Ceará Tem Design 2026
**Data:** [data]
**Stack:** Laravel [versão] · PHP [versão] · Fortify · Filament [versão] · Livewire [versão]

## Resumo Executivo

## Achados por Severidade
### 🔴 CRÍTICO — Corrigir antes de produção
### 🟠 ALTO — Corrigir em < 1 semana
### 🟡 MÉDIO — Próximo ciclo
### 🟢 BAIXO / Informativo

## ⚠️ Dependem de decisão (P#)

## Plano de Ação Priorizado
1. [O quê · arquivo/rota · correção exata]

## O que está BEM ✅

## Novos padrões aprendidos
```

---

## Referências

- `references/patterns.md` — padrões seguros/inseguros em Laravel com exemplos
- `references/cearadesign-context.md` — contexto do projeto relevante para segurança
