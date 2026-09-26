# Testes de Livewire e Filament

## Quando usar

- **Livewire:** área do participante e voto popular (docs/03, T1)
- **Filament:** painéis `/admin` e `/jurado`

A regra de negócio já está testada na Action (`references/feature-tests.md`). Aqui o foco é:
1. a tela **autoriza** (quem não pode, não consegue — mesmo manipulando o estado)
2. a tela **valida** o formato
3. a tela **chama a Action** e mostra o resultado/erro

---

## Livewire

```php
use function Pest\Livewire\livewire;   // pestphp/pest-plugin-livewire
// ou: Livewire::test(Componente::class)
```

### Fluxo básico

```php
it('participante salva rascunho da inscrição', function () {
    $user  = participante();
    $entry = Entry::factory()->for($user)->rascunho()->create();

    livewire(EntryForm::class, ['entryId' => $entry->id])
        ->actingAs($user)
        ->set('title', 'Cadeira Jangada')
        ->call('save')
        ->assertHasNoErrors();

    expect($entry->fresh()->title)->toBe('Cadeira Jangada');
});
```

> Em algumas versões, `actingAs` deve vir antes: `$this->actingAs($user); livewire(...)`. Conferir.

### Adulteração de estado (segurança)

```php
it('não permite trocar o entryId para editar inscrição de outro (IDOR)', function () {
    $me    = participante();
    $mine  = Entry::factory()->for($me)->rascunho()->create();
    $other = Entry::factory()->rascunho()->create();

    $this->actingAs($me);

    // Com #[Locked], alterar a propriedade lança exceção
    expect(fn () => livewire(EntryForm::class, ['entryId' => $mine->id])->set('entryId', $other->id))
        ->toThrow(Exception::class);
});

it('não permite editar inscrição aprovada (P70)', function () {
    $me    = participante();
    $entry = Entry::factory()->for($me)->aprovada()->create();
    $this->actingAs($me);

    livewire(EntryForm::class, ['entryId' => $entry->id])
        ->set('title', 'Outro')
        ->call('save')
        ->assertForbidden();
});
```

### Validação

```php
it('exige os campos do §4 para enviar', function () {
    // ...
    livewire(EntryForm::class, ['entryId' => $entry->id])
        ->set('title', '')
        ->call('submit')
        ->assertHasErrors(['title' => 'required']);
});

it('limita textos a 5.000 caracteres (P75)', function () {
    livewire(EntryForm::class, ['entryId' => $entry->id])
        ->set('summary', str_repeat('a', 5001))
        ->call('save')
        ->assertHasErrors(['summary' => 'max']);
});
```

### Upload

```php
it('envia comprovação em PDF (P54)', function () {
    Storage::fake('local');
    $file = UploadedFile::fake()->create('comprovante.pdf', 500, 'application/pdf');

    livewire(ProofUpload::class, ['entryId' => $entry->id])
        ->set('proof', $file)
        ->call('submit')
        ->assertHasNoErrors();
});
```

---

## Filament

Os helpers de teste do Filament rodam sobre Livewire. Antes de testar páginas de um painel
que não é o padrão, definir o painel atual:

```php
use Filament\Facades\Filament;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('jurado'));
});
```

### Acesso ao painel

```php
it('jurado acessa /jurado e não acessa /admin', function () {
    $this->actingAs(jurado());
    $this->get('/jurado')->assertOk();
    $this->get('/admin')->assertForbidden();
});

it('participante e eleitor não acessam nenhum painel', function (Closure $user) {
    $this->actingAs($user());
    $this->get('/admin')->assertForbidden();
    $this->get('/jurado')->assertForbidden();
})->with([fn () => participante(), fn () => eleitor()]);
```

### Tabela escopada (jurado só vê as atribuições dele)

```php
it('jurado vê só as próprias atribuições não substituídas', function () {
    $me = jurado();
    $mine      = JudgeAssignment::factory()->for($me, 'judge')->create();
    $replaced  = JudgeAssignment::factory()->for($me, 'judge')->substituida()->create();
    $others    = JudgeAssignment::factory()->create();

    $this->actingAs($me);

    livewire(ListAssignments::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$replaced, $others]);
});
```

### Actions do Filament

```php
it('admin publica o resultado (P69)', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(admin());

    livewire(ManageEdition::class)
        ->callAction('publishResults')
        ->assertHasNoActionErrors();

    expect(Edition::sole()->results_published_at)->not->toBeNull();
});

it('correção de nota exige justificativa (P46)', function () {
    // ...
    livewire(EditScore::class, ['record' => $score->getRouteKey()])
        ->callAction('correct', data: ['new_value' => 4, 'reason' => ''])
        ->assertHasActionErrors(['reason' => 'required']);
});
```

> Nomes de páginas, Resources e Actions do Filament acima são ilustrativos. Os métodos
> (`assertCanSeeTableRecords`, `callAction`, `assertHasActionErrors`) existem no Filament 3+;
> conferir assinaturas na documentação de testes do **Filament 5**.

---

## Anti-patterns

- ❌ Testar a regra de negócio inteira pela tela (já está na Action) — só o caminho feliz + autorização + validação
- ❌ Confiar em `->visible()`/`->hidden()` como teste de segurança — testar a **Policy**
- ❌ Asserções em HTML frágil (classes CSS); preferir `assertSee`/estado do banco
