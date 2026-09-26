---
name: testing
description: >
  Gera e mantém testes automatizados com Pest para o sistema do Prêmio Ceará Tem Design
  (Laravel 13, PHP 8.4, MySQL, Fortify, Filament 5, Livewire 4). Cobre Actions de negócio,
  Policies, Enums, rotas HTTP, fluxos do Fortify, componentes Livewire e Resources do
  Filament, sempre amarrando os testes às regras do edital (§x) e decisões confirmadas (P#).
  Use este skill SEMPRE que o usuário mencionar: "cria os testes", "adiciona testes",
  "quero cobertura", "testa essa Action", "testa essa rota", "testa essa Policy",
  "testa esse componente Livewire", "testa o painel do jurado", "testa essa função",
  "escreve o teste para", "Pest", "php artisan test", "coverage desse módulo",
  "preciso de testes", "antes de commitar quero testar", "vamos testar isso", "faz os testes",
  "suíte de testes", "testes unitários", "testes de feature", "testes de integração",
  "testa isso aqui", "verifica se quebrou", "como saber se quebrou", "garante que não quebra",
  ou qualquer variação. Acione também quando o usuário mostrar uma Action/Policy/componente
  novo e não mencionar testes, quando uma regra do edital não tiver cobertura, ao revisar
  um PR sem testes, ou quando disser "antes de ir pra produção".
  Esta skill é OBRIGATÓRIA: toda Action e toda regra do edital implementada deve ter teste.
  Nunca deixe código sem cobertura passar sem ao menos alertar e oferecer gerar os testes.
---

# Skill — Testes Automatizados com Pest (Prêmio Ceará Tem Design)

## Filosofia

**Todo código novo gera teste. Toda regra do edital tem um teste com o nome dela.**

Dois modos:
- **Modo Novo:** Action/Policy/componente recém-criado → gerar testes junto
- **Modo Retroativo:** código existente sem testes → mapear e cobrir, começando pelas regras críticas

Prioridade de cobertura (o que não pode quebrar num prêmio):
1. **Notas e resultado** — 1 a 5 (§5), 3 jurados, soma, faixas de distinção (§6), trava após envio (P19), correção só pela organização com justificativa (P46)
2. **Voto popular** — 1 por categoria (P36), só aprovadas (P37), só no período (P38), contagem oculta (P39)
3. **Inscrição** — 1 por categoria (§4), prazo em horário de Fortaleza (§7, P77), edição bloqueada após aprovação (P70)
4. **Acesso** — cada papel só vê o que deve (Policies, `canAccessPanel`)

Distribuição aproximada:
- **Unit** — Enums, cálculos puros (faixa de distinção), regras de validação `Cpf`/`Cnpj` (dígito verificador, P4, P42)
- **Feature** — Actions com banco, Policies, rotas HTTP, Fortify, Livewire, Filament (a maior parte neste projeto)
- **Browser/E2E** — só se solicitado

---

## Stack de testes

| Camada | Ferramenta |
|--------|-----------|
| Runner + assertions | **Pest** (instalado pelo `laravel new`) |
| Banco | **RefreshDatabase** + MySQL de teste (mesma versão do servidor, ⚠️ P65) |
| HTTP | `$this->get()/post()` do Laravel |
| Livewire | `Livewire::test()` / `livewire()` (pest-plugin-livewire) |
| Filament | helpers de teste do Filament sobre Livewire |
| Dados | **Model factories** + Faker `pt_BR` |
| Tempo | `$this->travelTo()` / `Carbon::setTestNow()` |
| HTTP externo | `Http::fake()` (Turnstile) |
| Arquivos | `Storage::fake()` + `UploadedFile::fake()` |
| Arquitetura | `arch()` do Pest |
| Cobertura | `php artisan test --coverage` (requer PCOV ou Xdebug) |

---

## Passo 1 — Identificar o contexto

1. **O que é?** Action, Policy, Enum, Form Request, rota/controller, componente Livewire, Resource Filament
2. **Qual regra implementa?** Achar o §/P# em `docs/01-escopo-edital.md`, `docs/04-modelo-dados.md` ou `docs/07-actions.md`
3. **Novo ou existente?** Existente → ler o arquivo inteiro e mapear o que falta
4. **Dependências:** banco, tempo (prazos), HTTP externo (Turnstile), arquivos (Media Library), fila

> Se a regra que o código implementa **não** estiver em `docs/` → perguntar ao usuário antes de
> escrever o teste. Teste não pode inventar regra de negócio.

---

## Passo 2 — Decidir o tipo de teste

```
Função/Enum/cálculo puro sem banco?           → Unit
Action com banco?                              → Feature (chamar a Action direto)
Policy?                                        → Feature (can/cannot por papel)
Rota HTTP / Fortify?                           → Feature com $this->post()...
Componente Livewire?                           → Feature com Livewire::test()
Resource/Page do Filament?                     → Feature com livewire() + helpers do Filament
Regra estrutural (Actions sem dd, sufixos)?    → arch()
```

> A regra de negócio mora na Action (docs/03, docs/07 — que já lista as regras de cada Action e exige testes Pest para elas). Teste a regra **na Action**; no Livewire/Filament
> teste só que a tela chama a Action, valida e autoriza. Não duplicar a mesma regra em 3 camadas.

---

## Passo 3 — Estrutura de cenários (obrigatória)

```php
describe('SubmitEvaluation', function () {

    describe('✅ happy path', function () {
        // entrada válida → estado esperado no banco
    });

    describe('⚠️ edge cases', function () {
        // limites: nota 1 e 5, último segundo do prazo, 3º jurado fechando a nota final
    });

    describe('❌ cenários de erro', function () {
        // nota 0 ou 6, jurado não atribuído, notas já enviadas (P19), fora do período
    });

});
```

Nome do teste cita a regra quando houver: `it('rejeita nota fora de 1 a 5 (§5)')`.

---

## Passo 4 — Convenções de arquivos

```
app/Actions/<Área>/SubmitEvaluation.php → tests/Feature/Actions/<Área>/SubmitEvaluationTest.php
app/Policies/EntryPolicy.php            → tests/Feature/Policies/EntryPolicyTest.php
app/Livewire/Voting/VoteButton.php      → tests/Feature/Livewire/Voting/VoteButtonTest.php
app/Filament/Jurado/Resources/...       → tests/Feature/Filament/Jurado/...Test.php
routes/web.php (site/galeria)           → tests/Feature/Http/...Test.php
app/Enums/Distinction.php               → tests/Unit/Enums/DistinctionTest.php
                                          tests/ArchTest.php
```

Espelhar `app/`; nunca misturar Unit e Feature no mesmo arquivo.

---

## Passo 5 — Setup do projeto (se não existir)

> 📄 `references/setup.md` — Pest, banco MySQL de teste, `phpunit.xml`, `tests/Pest.php`, factories, CI.

---

## Passo 6 — Gerar os testes

- **Enum / cálculo puro** → `references/unit-tests.md`
- **Action, Policy, rota, Fortify** → `references/feature-tests.md`
- **Livewire e Filament** → `references/livewire-filament-tests.md`
- Exemplos completos → `references/examples/`

---

## Padrão para função pura

```php
use App\Rules\Cpf;   // docs/07: regra de validação de apoio — ajustar ao nome real

describe('Cpf::isValid (P42 — só dígito verificador)', function () {

    describe('✅ happy path', function () {
        it('aceita CPF com dígitos corretos', function () {
            expect(Cpf::isValid('529.982.247-25'))->toBeTrue();
        });
    });

    describe('⚠️ edge cases', function () {
        it('aceita CPF sem máscara', fn () => expect(Cpf::isValid('52998224725'))->toBeTrue());
        it('rejeita sequência repetida', fn () => expect(Cpf::isValid('111.111.111-11'))->toBeFalse());
    });

    describe('❌ cenários de erro', function () {
        it('rejeita dígito verificador errado', fn () => expect(Cpf::isValid('529.982.247-24'))->toBeFalse());
        it('rejeita string vazia', fn () => expect(Cpf::isValid(''))->toBeFalse());
    });

});
```

> Se `Cpf` for uma `ValidationRule`, testar via `Validator::make(['cpf' => ...], ['cpf' => [new Cpf]])->passes()`. O método estático `isValid` acima é ilustrativo.

---

## Entrega final

1. **Arquivo `*Test.php` completo**
2. **Rodar um arquivo:** `php artisan test tests/Feature/Actions/<Área>/SubmitEvaluationTest.php`
3. **Filtrar:** `php artisan test --filter="rejeita nota"`
4. **Cobertura:** `php artisan test --coverage --min=80` (com PCOV/Xdebug)
5. **Próximo passo:** qual regra/Action ainda está sem teste

---

## Regra de qualidade mínima

Um teste só está "pronto" quando:
- [ ] Cobre o happy path
- [ ] Cobre pelo menos 2 edge cases (limites de nota, de prazo, de quantidade)
- [ ] Cobre o cenário de erro principal
- [ ] Cita a regra (§x / P#) no nome ou no `describe` quando testa regra de negócio
- [ ] É independente (RefreshDatabase; nada depende de ordem)
- [ ] Não usa `sleep` — tempo controlado com `travelTo`
- [ ] Não chama serviço externo real (`Http::fake()`, `Mail::fake()`, `Storage::fake()`)
- [ ] Nome descritivo: "rejeita segundo voto na mesma categoria (P36)", não "teste 1"

---

## References

- `references/setup.md` — Pest, banco de teste, factories, CI
- `references/unit-tests.md` — Enums, cálculos, validadores
- `references/feature-tests.md` — Actions, Policies, rotas, Fortify, uploads, tempo
- `references/livewire-filament-tests.md` — componentes Livewire e painéis Filament
- `references/examples/SubmitEvaluationTest.php` — exemplo de Action testada (júri)
- `references/examples/RegisterVoterTest.php` — exemplo de fluxo HTTP testado (cadastro de eleitor)
