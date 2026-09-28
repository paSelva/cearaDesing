<?php

use App\Actions\Voting\RegisterVoter;
use App\Enums\Role;
use App\Models\ParticipantProfile;
use App\Models\User;
use App\Models\VoterProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config([
        'services.cpf_hmac.key' => 'chave-hmac-de-teste',
        'services.turnstile.secret_key' => 'segredo-de-teste',
        'services.turnstile.site_key' => 'chave-publica-de-teste',
    ]);
    Http::preventStrayRequests();
    $GLOBALS['turnstile'] = 'ok';
    Http::fake(['challenges.cloudflare.com/*' => fn () => match ($GLOBALS['turnstile']) {
        'ok' => Http::response(['success' => true]),
        'invalido' => Http::response(['success' => false]),
        'erro' => Http::response('erro', 500),
    }]);
});

function voterInput(array $overrides = []): array
{
    return array_replace([
        'name' => 'Maria Eleitora',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'senha-sintetica-1',
        'password_confirmation' => 'senha-sintetica-1',
        'cpf' => '529.982.247-25',
        'cf-turnstile-response' => 'token-de-teste',
    ], $overrides);
}

function expectVoterError(Closure $call, string $field): void
{
    try {
        $call();
        test()->fail("Esperava erro em {$field}.");
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($field);
    }
}

describe('RegisterVoter (P35, P42, P52, P60)', function () {
    it('cria eleitor sem guardar o CPF em claro', function () {
        $user = app(RegisterVoter::class)->handle(voterInput(['email' => 'Maria@Example.test', 'role' => 'admin']), '198.51.100.20');

        $row = (array) DB::table('voter_profiles')->first();
        expect($user->role)->toBe(Role::Eleitor)
            ->and($user->email)->toBe('maria@example.test')
            ->and($user->voterProfile->cpf_encrypted)->toBe('52998224725')
            ->and($row['cpf_hash'])->toBe(hash_hmac('sha256', '52998224725', 'chave-hmac-de-teste'))
            ->and($row['registration_ip'])->toBe('198.51.100.20')
            ->and(implode('|', $row))->not->toContain('52998224725')
            ->and($user->email_verified_at)->toBeNull();
    });

    it('um cadastro por CPF, com ou sem máscara (P35)', function () {
        app(RegisterVoter::class)->handle(voterInput(), '198.51.100.20');

        expectVoterError(fn () => app(RegisterVoter::class)->handle(voterInput(['cpf' => '52998224725']), '198.51.100.21'), 'cpf_hash');
        expect(VoterProfile::count())->toBe(1);
    });

    it('participante PF pode ter cadastro de eleitor com o mesmo CPF, mas outro e-mail (P60, T4)', function () {
        $participant = ParticipantProfile::factory()->create(['document' => '52998224725'])->user;

        expectVoterError(fn () => app(RegisterVoter::class)->handle(voterInput(['email' => $participant->email]), '198.51.100.20'), 'email');

        $voter = app(RegisterVoter::class)->handle(voterInput(), '198.51.100.20');
        expect($voter->role)->toBe(Role::Eleitor);
    });

    it('recusa CPF inválido (P42) e dados obrigatórios', function (array $overrides, string $field) {
        expectVoterError(fn () => app(RegisterVoter::class)->handle(voterInput($overrides), '198.51.100.20'), $field);
    })->with([
        'CPF com dígito errado' => [['cpf' => '529.982.247-24'], 'cpf'],
        'CPF repetido' => [['cpf' => '111.111.111-11'], 'cpf'],
        'sem nome' => [['name' => ' '], 'name'],
        'senha curta' => [['password' => 'curta', 'password_confirmation' => 'curta'], 'password'],
    ]);

    it('Turnstile: recusa token inválido, ausente, erro de rede e chave não configurada', function (Closure $setup, array $overrides) {
        $setup();

        expectVoterError(fn () => app(RegisterVoter::class)->handle(voterInput($overrides), '198.51.100.20'), 'cf-turnstile-response');
        expect(User::count())->toBe(0);
    })->with([
        'token inválido' => [fn () => $GLOBALS['turnstile'] = 'invalido', []],
        'sem token' => [fn () => null, ['cf-turnstile-response' => '']],
        'Cloudflare fora do ar' => [fn () => $GLOBALS['turnstile'] = 'erro', []],
        'sem chave secreta' => [fn () => config(['services.turnstile.secret_key' => '']), []],
    ]);

    it('sem chave do HMAC, o cadastro fica indisponível (falha fechada)', function () {
        config(['services.cpf_hmac.key' => '']);

        expectVoterError(fn () => app(RegisterVoter::class)->handle(voterInput(), '198.51.100.20'), 'cpf');
        expect(User::count())->toBe(0);
    });

    it('limita a 5 cadastros concluídos por IP por hora; tentativas com erro não contam (P52)', function () {
        $cpfs = ['52998224725', '11144477735', '39053344705', '71428793860', '87748248800'];

        expectVoterError(fn () => app(RegisterVoter::class)->handle(voterInput(['cpf' => '529.982.247-24']), '203.0.113.9'), 'cpf');
        foreach ($cpfs as $cpf) {
            app(RegisterVoter::class)->handle(voterInput(['cpf' => $cpf]), '203.0.113.9');
        }

        expectVoterError(fn () => app(RegisterVoter::class)->handle(voterInput(['cpf' => '15350946056']), '203.0.113.9'), 'cpf');
        expect(app(RegisterVoter::class)->handle(voterInput(['cpf' => '15350946056']), '203.0.113.10')->exists)->toBeTrue();
    });
});

describe('pelo site', function () {
    it('cadastro entra logado e vai para a área do eleitor', function () {
        $this->get('/eleitor/cadastro')->assertOk()->assertSee('Cadastro para votar')->assertSee('cf-turnstile', false);

        $this->post('/eleitor/cadastro', voterInput(['email' => 'eleitor@example.test']))
            ->assertRedirect('/eleitor');

        $this->assertAuthenticatedAs(User::where('email', 'eleitor@example.test')->sole());
        $this->get('/eleitor')->assertOk()->assertSee('Seu cadastro de eleitor está pronto');
    });

    it('eleitor entra pelo login do site e vai para a área dele; não entra na do participante', function () {
        $voter = app(RegisterVoter::class)->handle(voterInput(['email' => 'eleitor@example.test']), '198.51.100.20');
        auth()->logout();

        $this->post('/login', ['email' => 'eleitor@example.test', 'password' => 'senha-sintetica-1'])->assertRedirect('/eleitor');
        $this->get('/participante')->assertForbidden();
    });

    it('participante continua indo para a área dele ao entrar', function () {
        $user = ParticipantProfile::factory()->create()->user;

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/participante');
        $this->get('/eleitor')->assertForbidden();
    });

    it('mostra aviso quando o Turnstile não está configurado', function () {
        config(['services.turnstile.site_key' => null]);

        $this->get('/eleitor/cadastro')->assertSee('cadastro de eleitor está indisponível');
    });
});
