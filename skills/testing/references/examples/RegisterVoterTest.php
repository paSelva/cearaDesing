<?php

/**
 * Exemplo — cadastro de eleitor pela rota HTTP (Action RegisterVoter, docs/07).
 *
 * Regras cobertas:
 *  - Turnstile válido, verificado no servidor (P52)
 *  - limite de 5 cadastros por IP por hora (P52)
 *  - CPF válido só pelo dígito verificador (P42)
 *  - 1 cadastro por CPF via cpf_hash (P35)
 *  - e-mail único em users (T4, P60)
 *  - role sempre `eleitor`, independente do que vier no formulário
 *  - CPF não fica em claro no banco (cpf_hash + cpf_encrypted, docs/04)
 *
 * Nome da rota, campos do formulário e mensagens são ilustrativos: ajustar ao código real.
 * Arquivo esperado: tests/Feature/Http/RegisterVoterTest.php
 */

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

function voterPayload(array $overrides = []): array
{
    return array_merge([
        'name'                  => 'Maria do Carmo',
        'email'                 => fake()->unique()->safeEmail(),
        'password'              => 'senha-forte-123',
        'password_confirmation' => 'senha-forte-123',
        'cpf'                   => '529.982.247-25',
        'cf-turnstile-response' => 'token-de-teste',
    ], $overrides);
}

beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);
    // CACHE_STORE=array nos testes (setup.md): o contador do RateLimiter zera a cada teste
});

describe('Cadastro de eleitor (RegisterVoter)', function () {

    describe('✅ happy path', function () {

        it('cria usuário eleitor com perfil', function () {
            $this->post(route('voter.register'), voterPayload(['email' => 'maria@example.com']))
                ->assertRedirect();

            $user = User::where('email', 'maria@example.com')->first();
            expect($user)->not->toBeNull()
                ->and($user->role)->toBe(Role::Eleitor)
                ->and($user->voterProfile)->not->toBeNull();
        });

        it('não grava o CPF em claro (docs/04, LGPD)', function () {
            $this->post(route('voter.register'), voterPayload());

            $row = DB::table('voter_profiles')->first();
            expect((array) $row)->not->toContain('52998224725')
                ->and((array) $row)->not->toContain('529.982.247-25')
                ->and(strlen($row->cpf_hash))->toBe(64);
        });

    });

    describe('⚠️ edge cases', function () {

        it('aceita CPF sem máscara', function () {
            $this->post(route('voter.register'), voterPayload(['cpf' => '52998224725']))
                ->assertSessionHasNoErrors();
        });

        it('ignora role enviado no formulário', function () {
            $this->post(route('voter.register'), voterPayload(['email' => 'x@example.com', 'role' => 'admin']));

            expect(User::where('email', 'x@example.com')->first()->role)->toBe(Role::Eleitor);
        });

        it('permite exatamente 5 cadastros por IP por hora e bloqueia o 6º (P52)', function () {
            $cpfs = ['52998224725', '11144477735', '39053344705', '71428793860', '87748248800'];
            foreach ($cpfs as $cpf) {
                $this->post(route('voter.register'), voterPayload(['cpf' => $cpf]))
                    ->assertSessionHasNoErrors();
                auth()->logout();
            }

            $this->post(route('voter.register'), voterPayload(['cpf' => '15350946056']))
                ->assertStatus(429);
        });

    });

    describe('❌ cenários de erro', function () {

        it('rejeita captcha inválido (P52)', function () {
            Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);

            $this->post(route('voter.register'), voterPayload())->assertSessionHasErrors();
            expect(User::count())->toBe(0);
        });

        it('rejeita CPF com dígito verificador errado (P42)', function () {
            $this->post(route('voter.register'), voterPayload(['cpf' => '529.982.247-24']))
                ->assertSessionHasErrors('cpf');
        });

        it('rejeita segundo cadastro com o mesmo CPF (P35)', function () {
            $this->post(route('voter.register'), voterPayload());
            auth()->logout();

            $this->post(route('voter.register'), voterPayload(['cpf' => '52998224725']))
                ->assertSessionHasErrors('cpf');
            expect(User::count())->toBe(1);
        });

        it('rejeita e-mail já usado por participante (T4, P60)', function () {
            $p = participante();

            $this->post(route('voter.register'), voterPayload(['email' => $p->email]))
                ->assertSessionHasErrors('email');
        });

    });

});
