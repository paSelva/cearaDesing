# Testes Unitários — Enums, cálculos e validadores

## Quando usar

Código **sem banco e sem HTTP**: Enums, funções de cálculo, regras de validação (`Cpf`, `Cnpj`).
Rodam em `tests/Unit` (sem `RefreshDatabase`).

Se precisar de model salvo, é **Feature** (`references/feature-tests.md`).

---

## Faixas de distinção (§6)

docs/04 §9 lista as fronteiras obrigatórias: **60, 54, 53, 48, 47, 42, 41, 36, 35 e 12**.
Se o cálculo da faixa estiver isolado (ex.: método no Enum recebendo a pontuação e as faixas
da edição), teste-o aqui com dataset:

```php
use App\Enums\Distinction;

describe('Distinction::fromScore (§6)', function () {

    $bands = ['gold' => 54, 'silver' => 48, 'bronze' => 42, 'honorable' => 36];   // editions.band_*

    it('classifica a pontuação na faixa correta', function (int $score, Distinction $expected) use ($bands) {
        expect(Distinction::fromScore($score, $bands))->toBe($expected);
    })->with([
        'máximo'            => [60, Distinction::Ouro],
        'limite ouro'       => [54, Distinction::Ouro],
        'abaixo ouro'       => [53, Distinction::Prata],
        'limite prata'      => [48, Distinction::Prata],
        'abaixo prata'      => [47, Distinction::Bronze],
        'limite bronze'     => [42, Distinction::Bronze],
        'abaixo bronze'     => [41, Distinction::MencaoHonrosa],
        'limite menção'     => [36, Distinction::MencaoHonrosa],
        'abaixo menção'     => [35, Distinction::SemDistincao],
        'mínimo'            => [12, Distinction::SemDistincao],
    ]);

});
```

> `Distinction::fromScore` e os nomes dos cases são ilustrativos. Os valores do enum no banco
> estão em docs/04 (`ouro`, `prata`, `bronze`, `mencao_honrosa`, `sem_distincao`).
> As faixas vêm de `editions.band_*` — **não** fixe 54/48/42/36 no código de produção.

---

## Enums

```php
it('valores do enum batem com o banco (docs/04)', function () {
    expect(array_map(fn ($c) => $c->value, EntryStatus::cases()))
        ->toBe(['rascunho', 'enviada', 'aprovada', 'desclassificada']);
});
```

Útil porque o enum PHP espelha o `enum` do MySQL — se um mudar sem o outro, quebra em produção.

---

## Regras de validação (Cpf/Cnpj — P4, P42)

```php
use App\Rules\Cpf;
use Illuminate\Support\Facades\Validator;

function cpfPasses(string $value): bool
{
    return Validator::make(['cpf' => $value], ['cpf' => [new Cpf]])->passes();
}

describe('Cpf (dígito verificador, P42)', function () {
    it('aceita CPF válido com e sem máscara', function (string $cpf) {
        expect(cpfPasses($cpf))->toBeTrue();
    })->with(['529.982.247-25', '52998224725']);

    it('rejeita inválidos', function (string $cpf) {
        expect(cpfPasses($cpf))->toBeFalse();
    })->with([
        'dígito errado'      => '529.982.247-24',
        'sequência repetida' => '111.111.111-11',
        'curto'              => '5299822472',
        'vazio'              => '',
        'letras'             => 'abc.def.ghi-jk',
    ]);
});
```

> Se a regra usar `Validator`, o teste precisa do app Laravel — use `uses(Tests\TestCase::class)`
> no arquivo ou mova para `tests/Feature`.

---

## Tempo em unidade

Para funções puras que recebem "agora", **passe o instante como parâmetro** em vez de chamar
`now()` lá dentro — o teste fica trivial. Se não der, use `Carbon::setTestNow()`.

---

## Anti-patterns

- ❌ Mockar o Eloquent para testar Action → use banco de teste (Feature)
- ❌ Testar getters/setters triviais
- ❌ Números mágicos sem citar a regra (§/P#)
- ❌ Um `it` com dez `expect` de cenários diferentes → use `->with()` (dataset)
