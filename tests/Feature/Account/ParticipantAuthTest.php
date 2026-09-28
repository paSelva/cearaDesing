<?php

use App\Enums\Role;
use App\Models\ParticipantProfile;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

function registrationPayload(array $overrides = []): array
{
    return array_replace([
        'name' => 'João Estúdio',
        'email' => 'joao@example.test',
        'password' => 'senha-sintetica-1',
        'password_confirmation' => 'senha-sintetica-1',
        'person_type' => 'pf',
        'document' => '529.982.247-25',
        'phone' => '(85) 99999-0000',
        'city' => 'Fortaleza',
        'state' => 'CE',
        'participant_type' => 'profissional',
    ], $overrides);
}

describe('páginas públicas de conta', function () {
    it('exibe login, cadastro e recuperação de senha em português', function (string $path, string $text) {
        $this->get($path)->assertOk()->assertSee($text);
    })->with([
        ['/login', 'Entrar'],
        ['/register', 'Criar conta de participante'],
        ['/forgot-password', 'Recuperar senha'],
    ]);

    it('não expõe a confirmação de senha do Fortify', function () {
        $this->actingAs(ParticipantProfile::factory()->create()->user)
            ->get('/user/confirm-password')
            ->assertNotFound();
    });
});

describe('cadastro pelo site', function () {
    it('cria a conta, entra e leva à área do participante sem confirmar e-mail (P73)', function () {
        $this->post('/register', registrationPayload())->assertRedirect('/participante');

        $user = User::where('email', 'joao@example.test')->sole();
        $this->assertAuthenticatedAs($user);
        $this->get('/participante')->assertOk()->assertSee('João Estúdio');
    });

    it('mostra erros de validação em português', function () {
        $this->from('/register')
            ->post('/register', registrationPayload(['document' => '529.982.247-24', 'state' => 'XX']))
            ->assertRedirect('/register')
            ->assertSessionHasErrors(['document' => 'O CPF informado não é válido.', 'state' => 'O valor selecionado em UF é inválido.']);

        $this->assertGuest();
    });
});

describe('login', function () {
    it('participante entra e sai', function () {
        $user = ParticipantProfile::factory()->create()->user;

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/participante');
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    });

    it('recusa admin, jurado e eleitor com a mesma mensagem genérica', function (Role $role) {
        $user = User::factory()->create(['role' => $role]);

        $this->from('/login')
            ->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'E-mail ou senha inválidos.']);
        $this->assertGuest();
    })->with([Role::Admin, Role::Jurado, Role::Eleitor]);

    it('recusa senha errada com a mesma mensagem genérica', function () {
        $user = ParticipantProfile::factory()->create()->user;

        $this->post('/login', ['email' => $user->email, 'password' => 'errada'])
            ->assertSessionHasErrors(['email' => 'E-mail ou senha inválidos.']);
    });

    it('limita tentativas de login (5 por minuto por e-mail e IP)', function () {
        $user = ParticipantProfile::factory()->create()->user;

        foreach (range(1, 5) as $_) {
            $this->post('/login', ['email' => $user->email, 'password' => 'errada']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'errada'])->assertTooManyRequests();
    });
});

describe('recuperação de senha', function () {
    it('envia o link e redefine a senha', function () {
        Notification::fake();
        $user = ParticipantProfile::factory()->create()->user;

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHas('status', 'Se o e-mail estiver cadastrado, você receberá um link para redefinir a senha.');

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'nova-senha-sintetica',
            'password_confirmation' => 'nova-senha-sintetica',
        ])->assertRedirect('/login');

        expect(password_verify('nova-senha-sintetica', $user->fresh()->password))->toBeTrue();
    });

    it('não revela se o e-mail tem conta', function () {
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'ninguem@example.test'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Se o e-mail estiver cadastrado, você receberá um link para redefinir a senha.');

        Notification::assertNothingSent();
    });
});

describe('área do participante', function () {
    it('redireciona visitante para o login', function () {
        $this->get('/participante')->assertRedirect('/login');
    });

    it('bloqueia outros papéis', function (Role $role) {
        $this->actingAs(User::factory()->create(['role' => $role]))
            ->get('/participante')
            ->assertForbidden();
    })->with([Role::Admin, Role::Jurado, Role::Eleitor]);

    it('não mostra documento nem telefone na página', function () {
        $profile = ParticipantProfile::factory()->create(['document' => '52998224725', 'phone' => '85988887777']);

        $this->actingAs($profile->user)
            ->get('/participante')
            ->assertOk()
            ->assertDontSee('52998224725')
            ->assertDontSee('85988887777');
    });
});
