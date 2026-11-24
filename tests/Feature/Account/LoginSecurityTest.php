<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\Middleware\TrustHosts;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

function roleUser(Role $role): User
{
    return User::factory()->create(['role' => $role]);
}

describe('link de redefinição de senha', function () {
    it('usa o domínio de APP_URL mesmo com Host forjado', function () {
        config(['app.url' => 'https://premio.example.test']);
        Notification::fake();
        $user = roleUser(Role::Participante);

        $this->post('http://malicioso.test/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;

            return str_starts_with($url, "https://premio.example.test/reset-password/{$notification->token}?")
                && str_contains($url, 'email='.urlencode($user->email));
        });
    });

    it('em produção só o host de APP_URL é aceito', function () {
        config(['app.url' => 'https://premio.example.test']);
        $patterns = app(TrustHosts::class)->hosts();

        expect($patterns)->toHaveCount(1);
        $matches = fn (string $host): bool => preg_match('{'.$patterns[0].'}i', $host) === 1;
        expect($matches('premio.example.test'))->toBeTrue()
            ->and($matches('malicioso.test'))->toBeFalse()
            ->and($matches('premio.example.test.malicioso.test'))->toBeFalse()
            ->and($matches('sub.premio.example.test'))->toBeFalse()
            ->and($matches('premioXexample.test'))->toBeFalse();
    });
});

describe('limites por IP', function () {
    it('login: 30 por minuto, mesmo trocando o e-mail', function () {
        foreach (range(1, 30) as $i) {
            $this->post('/login', ['email' => "pessoa{$i}@example.test", 'password' => 'x'])->assertRedirect();
        }

        $this->post('/login', ['email' => 'outra@example.test', 'password' => 'x'])->assertTooManyRequests();
    });

    it('pedido de redefinição: 10 por hora', function () {
        foreach (range(1, 10) as $i) {
            $this->post('/forgot-password', ['email' => "pessoa{$i}@example.test"])->assertRedirect();
        }

        $this->post('/forgot-password', ['email' => 'outra@example.test'])
            ->assertTooManyRequests()
            ->assertSee('Muitas tentativas');
    });

    it('envio da nova senha: 10 por hora', function () {
        foreach (range(1, 10) as $_) {
            $this->post('/reset-password', [])->assertRedirect();
        }

        $this->post('/reset-password', [])->assertTooManyRequests();
    });

    it('cadastro: erro de preenchimento não gasta a cota', function () {
        foreach (range(1, 25) as $_) {
            $this->post('/register', [])->assertRedirect();
        }
    });

    it('cadastro: 20 concluídos por hora', function () {
        Route::post('/teste-cadastro', function () {
            Auth::login(User::factory()->create());

            return 'ok';
        })->middleware(['web', 'throttle:conta'])->name('register.store');
        Route::getRoutes()->refreshNameLookups();

        foreach (range(1, 20) as $_) {
            $this->post('/teste-cadastro')->assertOk();
            Auth::logout();
        }

        $this->post('/teste-cadastro')->assertTooManyRequests();
    });

    it('abrir as páginas de conta não tem limite', function () {
        foreach (range(1, 35) as $_) {
            $this->get('/forgot-password')->assertOk();
        }
    });
});

it('e-mail enviado como lista não gera erro 500', function (string $path) {
    $this->from('/login')->post($path, ['email' => ['a'], 'password' => 'x'])
        ->assertRedirect()
        ->assertSessionHasErrors('email');
})->with(['/login', '/forgot-password']);

describe('destino depois do login', function () {
    it('não leva à área de outro papel', function (string $visited, Role $role, string $home) {
        $this->get($visited)->assertRedirect();
        $user = roleUser($role);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect($home);
        $this->assertAuthenticatedAs($user);
    })->with([
        ['/admin', Role::Participante, '/participante'],
        ['/jurado', Role::Participante, '/participante'],
        ['/organizacao/inscricoes/1/arquivos/1', Role::Participante, '/participante'],
    ]);

    it('volta à página pedida quando é da própria área', function () {
        $user = roleUser(Role::Participante);
        $this->get('/participante/inscricoes/99');

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/participante/inscricoes/99');
    });

    it('o mesmo vale para o login dos painéis', function (string $panel, Role $role, string $intended, string $expected) {
        $user = roleUser($role);
        Filament::setCurrentPanel($panel);
        session(['url.intended' => url($intended)]);

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect(url($expected));
        $this->assertAuthenticatedAs($user);
    })->with([
        ['admin', Role::Admin, '/participante', '/admin'],
        ['admin', Role::Admin, '/admin/jurados', '/admin/jurados'],
        ['admin', Role::Admin, '/organizacao/inscricoes/1/arquivos/1', '/organizacao/inscricoes/1/arquivos/1'],
        ['jurado', Role::Jurado, '/admin', '/jurado'],
    ]);
});

describe('visitante e usuário já logado', function () {
    it('arquivo da organização ou do júri leva ao login do painel certo', function (string $path, string $login) {
        $this->get($path)->assertRedirect($login);
    })->with([
        ['/organizacao/inscricoes/1/arquivos/1', '/admin/login'],
        ['/avaliacao/atribuicoes/1/arquivos/1', '/jurado/login'],
        ['/participante', '/login'],
    ]);

    it('quem já entrou vai para a própria área ao abrir o login', function (Role $role, string $home) {
        $this->actingAs(roleUser($role))->get('/login')->assertRedirect($home);
    })->with([
        [Role::Participante, '/participante'],
        [Role::Admin, '/admin'],
        [Role::Jurado, '/jurado'],
    ]);

    it('a página 403 oferece sair e voltar para a própria área', function (string $path) {
        $this->actingAs(roleUser(Role::Participante))->get($path)
            ->assertForbidden()
            ->assertSee('Acesso não permitido')
            ->assertSee(route('logout'))
            ->assertSee(route('participant.dashboard'));
    })->with(['/admin', '/jurado']);
});
