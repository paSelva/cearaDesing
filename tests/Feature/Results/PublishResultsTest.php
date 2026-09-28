<?php

use App\Actions\Results\PublishResults;
use App\Actions\Voting\CastPopularVote;
use App\Actions\Voting\DecidePopularVoteTie;
use App\Actions\Voting\RegisterVoter;
use App\Actions\Voting\TallyPopularVotes;
use App\Filament\Resources\EditionResource\Pages\EditEdition;
use App\Models\PopularVoteWinner;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Storage::fake('local');
    config(['services.cpf_hmac.key' => 'chave-hmac-de-teste', 'services.turnstile.secret_key' => 'segredo']);
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);
});

function resultsVoter(string $cpf): User
{
    $voter = app(RegisterVoter::class)->handle([
        'name' => 'Eleitor', 'email' => fake()->unique()->safeEmail(), 'password' => 'senha-sintetica-1',
        'password_confirmation' => 'senha-sintetica-1', 'cpf' => $cpf, 'cf-turnstile-response' => 'ok',
    ], fake()->unique()->ipv4());
    auth()->logout();

    return $voter;
}

function expectPublishError(Closure $call): array
{
    try {
        $call();
        test()->fail('Esperava erro de publicação.');
    } catch (ValidationException $e) {
        return $e->errors()['publish'];
    }
}

/** Duas aprovadas com resultado (Ouro 55 e sem distinção 30) e uma desclassificada. */
function publishableSetup(): array
{
    $edition = openEdition();
    $category = regularCategory($edition);
    $gold = entryFor(participant(), $category, 'aprovada');
    $gold->forceFill(['title' => 'Projeto Ouro', 'final_score' => 55, 'distinction' => 'ouro', 'authorship_credits' => 'Estúdio Autor', 'summary' => 'Resumo do ouro'])->save();
    $gold->addMedia(fakeImage())->toMediaCollection('images');
    $none = entryFor(participant(), $category, 'aprovada');
    $none->forceFill(['title' => 'Projeto Sem Distinção', 'final_score' => 30, 'distinction' => 'sem_distincao'])->save();
    test()->travelTo(Carbon::parse('2026-11-21 10:00:00', 'America/Fortaleza'));

    return [$edition, $category, $gold, $none];
}

describe('PublishResults (P69)', function () {
    it('publica, registra no activity log e não publica duas vezes', function () {
        [$edition] = publishableSetup();
        $admin = User::factory()->admin()->create();

        app(PublishResults::class)->handle($admin);

        expect($edition->fresh()->results_published_at)->not->toBeNull()
            ->and(Activity::where('log_name', 'resultado')->sole()->causer_id)->toBe($admin->id);
        expectPublishError(fn () => app(PublishResults::class)->handle($admin));
    });

    it('bloqueia com aprovada sem resultado, voto não apurado ou empate sem decisão', function () {
        [$edition, $category, $gold] = publishableSetup();
        $admin = User::factory()->admin()->create();
        $incomplete = entryFor(participant(), $category, 'aprovada');

        expect(expectPublishError(fn () => app(PublishResults::class)->handle($admin)))
            ->toContain('1 inscrição(ões) aprovada(s) ainda sem as avaliações dos jurados.');
        $incomplete->forceFill(['final_score' => 40, 'distinction' => 'mencao_honrosa'])->save();

        // Votos no período, empate 1 x 1 entre dois projetos.
        $this->travelTo(Carbon::parse('2026-11-01 10:00:00', 'America/Fortaleza'));
        app(CastPopularVote::class)->handle(resultsVoter('52998224725'), $gold, '1.1.1.1');
        app(CastPopularVote::class)->handle(resultsVoter('11144477735'), $incomplete, '1.1.1.1');
        $this->travelTo(Carbon::parse('2026-11-21 10:00:00', 'America/Fortaleza'));

        expect(expectPublishError(fn () => app(PublishResults::class)->handle($admin)))->toContain('O voto popular ainda não foi apurado.');

        app(TallyPopularVotes::class)->handle($admin);
        expect(expectPublishError(fn () => app(PublishResults::class)->handle($admin)))
            ->toContain('1 empate(s) do voto popular aguardando decisão da organização (P49).');

        app(DecidePopularVoteTie::class)->handle(PopularVoteWinner::sole(), $admin, ['entry_id' => $gold->id]);
        app(PublishResults::class)->handle($admin);
        expect($edition->fresh()->results_published_at)->not->toBeNull();
    });

    it('só admin publica', function () {
        [$edition] = publishableSetup();

        expect(expectPublishError(fn () => app(PublishResults::class)->handle(User::factory()->jurado()->create())))
            ->toContain('Somente a organização publica o resultado.')
            ->and($edition->fresh()->results_published_at)->toBeNull();
    });

    it('botão no painel da edição publica', function () {
        [$edition] = publishableSetup();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(EditEdition::class, ['record' => $edition->getRouteKey()])->callAction('publishResults');

        expect($edition->fresh()->results_published_at)->not->toBeNull();
    });
});

describe('página de premiados (P21, P44, P51)', function () {
    it('antes da publicação não mostra nenhum projeto', function () {
        [, , $gold] = publishableSetup();

        $this->get('/premiados')->assertOk()->assertSee('O resultado ainda não foi publicado')->assertDontSee('Projeto Ouro');
    });

    it('depois mostra distinção, nota, autoria, textos e imagens; não lista quem ficou sem distinção', function () {
        [$edition, $category, $gold] = publishableSetup();
        app(PublishResults::class)->handle(User::factory()->admin()->create());
        $image = $gold->getFirstMedia('images');

        $this->get('/premiados')
            ->assertOk()
            ->assertSee($category->name)
            ->assertSee('Projeto Ouro')
            ->assertSee('Ouro, 55 pontos')
            ->assertSee('Estúdio Autor')
            ->assertSee('Resumo do ouro')
            ->assertSee("/galeria/{$gold->id}/imagens/{$image->id}", false)
            ->assertDontSee('Projeto Sem Distinção')
            ->assertDontSee($gold->user->email);
    });

    it('mostra o mais votado do voto popular (P51)', function () {
        [$edition, $category, $gold, $none] = publishableSetup();
        $admin = User::factory()->admin()->create();
        $this->travelTo(Carbon::parse('2026-11-01 10:00:00', 'America/Fortaleza'));
        app(CastPopularVote::class)->handle(resultsVoter('52998224725'), $none, '1.1.1.1');
        $this->travelTo(Carbon::parse('2026-11-21 10:00:00', 'America/Fortaleza'));
        app(TallyPopularVotes::class)->handle($admin);
        app(PublishResults::class)->handle($admin);

        $this->get('/premiados')->assertSee('Mais votado pelo público')->assertSee('Projeto Sem Distinção');
    });

    it('site mostra o link de premiados só depois da publicação', function () {
        publishableSetup();
        $this->get('/')->assertDontSee('Ver os premiados');

        app(PublishResults::class)->handle(User::factory()->admin()->create());
        $this->get('/')->assertSee('Ver os premiados');
    });
});

describe('participante (P22)', function () {
    it('vê nota final e distinção só depois da publicação', function () {
        [, , $gold] = publishableSetup();
        $owner = $gold->user;

        $this->actingAs($owner)->get('/participante')->assertDontSee('Resultado: Ouro');
        $this->actingAs($owner)->get("/participante/inscricoes/{$gold->id}")->assertDontSee('Resultado: Ouro');

        app(PublishResults::class)->handle(User::factory()->admin()->create());
        $this->actingAs($owner)->get('/participante')->assertSee('Resultado: Ouro, 55 pontos');
        $this->actingAs($owner)->get("/participante/inscricoes/{$gold->id}")->assertSee('Resultado: Ouro, 55 pontos');
    });
});
