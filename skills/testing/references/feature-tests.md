# Testes de Feature — Actions, Policies, rotas e Fortify

## Quando usar

Tudo que toca banco, sessão ou HTTP. Neste projeto é a maior parte da suíte, porque a regra
de negócio mora nas **Actions** (docs/03, docs/07) e elas gravam no banco.

Arquivos em `tests/Feature/...` com `RefreshDatabase` (configurado em `tests/Pest.php`).

---

## Actions (o coração da suíte)

docs/07 já lista, para cada Action, as **regras** e os **efeitos**. Cada regra vira pelo menos
um teste; cada efeito vira uma asserção.

```php
// Chamar a Action direto, pelo container (injeta dependências)
$action = app(CastPopularVote::class);
```

Mapeamento regra → teste (exemplo `CastPopularVote`, docs/07):

| Regra | Teste |
|---|---|
| role `eleitor` (P60) | participante/jurado sem perfil de eleitor → exceção |
| dentro do período (P38) | antes de `voting_starts_at` e depois de `voting_ends_at` → exceção; no limite → ok |
| inscrição `aprovada` (P37) | `enviada`/`desclassificada` → exceção |
| 1 por categoria (P36) | segundo voto na mesma categoria → exceção; em outra categoria → ok |
| voto final (P71) | não há método de troca; tentar votar de novo → exceção |
| pode votar no próprio projeto (P58) | eleitor dono do projeto (cadastro separado, P60) → ok |
| efeito: grava IP | `popular_votes.ip` preenchido |

```php
describe('CastPopularVote', function () {

    beforeEach(function () {
        $this->edition = Edition::factory()->create([
            'voting_starts_at' => Carbon::parse('2026-10-29 00:00:00', 'America/Fortaleza'),
            'voting_ends_at'   => Carbon::parse('2026-11-20 23:59:59', 'America/Fortaleza'),
        ]);
        $this->entry = Entry::factory()->aprovada()->create();
        $this->voter = eleitor();
        $this->travelTo(Carbon::parse('2026-11-01 12:00', 'America/Fortaleza'));
    });

    describe('✅ happy path', function () {
        it('registra o voto em inscrição aprovada dentro do período', function () {
            app(CastPopularVote::class)->handle($this->voter, $this->entry);

            expect(PopularVote::where('voter_id', $this->voter->id)->count())->toBe(1);
        });
    });

    describe('⚠️ edge cases', function () {
        it('aceita voto no último segundo do período (P38)', function () {
            $this->travelTo(Carbon::parse('2026-11-20 23:59:59', 'America/Fortaleza'));
            app(CastPopularVote::class)->handle($this->voter, $this->entry);
            expect(PopularVote::count())->toBe(1);
        });

        it('permite votar em outra categoria', function () {
            $other = Entry::factory()->aprovada()->create();   // factory cria outra categoria
            app(CastPopularVote::class)->handle($this->voter, $this->entry);
            app(CastPopularVote::class)->handle($this->voter, $other);
            expect(PopularVote::count())->toBe(2);
        });
    });

    describe('❌ cenários de erro', function () {
        it('rejeita segundo voto na mesma categoria (P36)', function () {
            $same = Entry::factory()->aprovada()->for($this->entry->category)->create();
            app(CastPopularVote::class)->handle($this->voter, $this->entry);

            expect(fn () => app(CastPopularVote::class)->handle($this->voter, $same))
                ->toThrow(BusinessRuleException::class);
        });

        it('rejeita inscrição não aprovada (P37)', function () {
            $entry = Entry::factory()->enviada()->create();
            expect(fn () => app(CastPopularVote::class)->handle($this->voter, $entry))
                ->toThrow(BusinessRuleException::class);
        });

        it('rejeita fora do período (P38)', function () {
            $this->travelTo(Carbon::parse('2026-11-21 00:00:00', 'America/Fortaleza'));
            expect(fn () => app(CastPopularVote::class)->handle($this->voter, $this->entry))
                ->toThrow(BusinessRuleException::class);
        });
    });

});
```

> Assinatura `handle($voter, $entry)` e relações das factories são ilustrativas — seguir o código real.

---

## Policies

Testar cada habilidade por papel. Dataset deixa a matriz explícita:

```php
it('só o dono edita a inscrição antes da aprovação (P8, P70)', function (string $who, bool $can) {
    $owner = participante();
    $entry = Entry::factory()->for($owner)->enviada()->create();
    $user  = match ($who) {
        'dono'   => $owner,
        'outro'  => participante(),
        'jurado' => jurado(),
        'admin'  => admin(),
    };

    expect($user->can('update', $entry))->toBe($can);
})->with([
    ['dono', true],
    ['outro', false],
    ['jurado', false],
    ['admin', false],   // ⚠️ confirmar: admin edita conteúdo da inscrição? não está em docs → perguntar
]);
```

Casos obrigatórios:
- dono × outro participante (IDOR)
- inscrição `aprovada` bloqueia edição (P70); prazo encerrado bloqueia (§7, P77)
- jurado só vê atribuição própria e não `substituida`; atribuição `enviada` não aceita nota (P19)
- `canAccessPanel`: admin só `/admin`, jurado só `/jurado`, participante/eleitor nenhum

---

## Rotas HTTP

```php
it('visitante não acessa a área do participante', function () {
    $this->get(route('participant.dashboard'))->assertRedirect(route('login'));
});

it('participante não acessa o painel do jurado', function () {
    $this->actingAs(participante())->get('/jurado')->assertForbidden();
});

it('galeria não expõe e-mail nem documento (P55)', function () {
    $entry = Entry::factory()->aprovada()->create();
    $this->get(route('gallery.index'))
        ->assertOk()
        ->assertSee($entry->title)
        ->assertDontSee($entry->user->email);
});

it('resultado não aparece antes do botão Publicar (P69)', function () {
    $entry = Entry::factory()->aprovada()->create(['final_score' => 55, 'distinction' => 'ouro']);
    $this->get(route('results.index'))->assertDontSee('55');
});
```

Nomes de rota ilustrativos — usar os de `routes/web.php`.

---

## Fortify (cadastro e login)

```php
it('cadastro de participante não aceita role vindo do formulário', function () {
    $this->post(route('register'), [
        // ... campos válidos do P4
        'role' => 'admin',
    ]);

    expect(User::first()->role)->toBe(Role::Participante);
});

it('limita tentativas de login', function () {
    $user = participante();
    foreach (range(1, 5) as $_) {
        $this->post(route('login'), ['email' => $user->email, 'password' => 'errada']);
    }
    $this->post(route('login'), ['email' => $user->email, 'password' => 'errada'])
        ->assertStatus(429);
});
```

> O número de tentativas deve bater com o `RateLimiter::for('login')` configurado.

---

## Uploads (Media Library)

```php
it('aceita comprovação em PDF de até 10 MB (P54)', function () {
    Storage::fake('local');
    $file = UploadedFile::fake()->create('comprovante.pdf', 10240, 'application/pdf');
    // chamar SubmitProof / rota correspondente e verificar a mídia na coleção 'proof'
});

it('rejeita comprovação acima de 10 MB ou fora de PDF (P54)', function (UploadedFile $file) {
    Storage::fake('local');
    // espera erro de validação
})->with([
    'grande' => fn () => UploadedFile::fake()->create('c.pdf', 10241, 'application/pdf'),
    'imagem' => fn () => UploadedFile::fake()->image('c.jpg'),
]);
```

⚠️ Limites de imagem (P6) e PDF complementar (P45) em aberto — não escrever teste com número inventado.

---

## Tempo (§7)

Sempre com horário explícito em `America/Fortaleza` e testando **os dois lados** da fronteira:

```php
$this->travelTo(Carbon::parse('2026-10-28 23:59:59', 'America/Fortaleza'));  // ok
$this->travelTo(Carbon::parse('2026-10-29 00:00:00', 'America/Fortaleza'));  // encerrado
```

---

## Turnstile (P52)

```php
it('rejeita cadastro de eleitor com captcha inválido', function () {
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);
    $this->post(route('voter.register'), [/* dados válidos */])
        ->assertSessionHasErrors();
    expect(User::count())->toBe(0);
});
```

---

## Dicas

- `assertDatabaseHas` / `assertDatabaseMissing` para efeitos
- Não depender de IDs fixos; criar tudo na factory
- Testar a **correção de nota** (P19, P46): `CorrectScore` exige justificativa, grava `score_revisions` e recalcula o resultado
- Testar **substituição de jurado** (P16, P47): notas da atribuição `substituida` não entram no cálculo (docs/04 §9)

## Ver exemplos completos

- `references/examples/SubmitEvaluationTest.php`
- `references/examples/RegisterVoterTest.php`
