<?php

use App\Actions\Triage\DisqualifyEntry;
use App\Actions\Voting\CastPopularVote;
use App\Actions\Voting\DecidePopularVoteTie;
use App\Actions\Voting\RegisterVoter;
use App\Actions\Voting\TallyPopularVotes;
use App\Enums\Role;
use App\Filament\Resources\PopularVoteResource\Pages\ListPopularVotes;
use App\Filament\Resources\PopularVoteWinnerResource\Pages\ListPopularVoteWinners;
use App\Models\PopularVote;
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

function inVoting(string $moment = '2026-11-01 10:00:00'): void
{
    test()->travelTo(Carbon::parse($moment, 'America/Fortaleza'));
}

function makeVoter(string $cpf = '52998224725'): User
{
    $voter = app(RegisterVoter::class)->handle([
        'name' => 'Eleitor', 'email' => fake()->unique()->safeEmail(), 'password' => 'senha-sintetica-1',
        'password_confirmation' => 'senha-sintetica-1', 'cpf' => $cpf, 'cf-turnstile-response' => 'ok',
    ], fake()->unique()->ipv4());
    auth()->logout();

    return $voter;
}

function expectVoteError(Closure $call, string $field = 'vote'): void
{
    try {
        $call();
        test()->fail("Esperava erro em {$field}.");
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($field);
    }
}

describe('CastPopularVote', function () {
    it('eleitor vota em aprovada, 1 por categoria (P36), inclusive no próprio projeto (P58)', function () {
        $edition = openEdition();
        $category = regularCategory($edition);
        $entry = entryFor(participant(), $category, 'aprovada');
        $voter = makeVoter();
        inVoting();

        $vote = app(CastPopularVote::class)->handle($voter, $entry, '198.51.100.5');

        expect($vote->category_id)->toBe($category->id)
            ->and($vote->ip)->toBe('198.51.100.5');

        $other = entryFor(participant(), $category, 'aprovada');
        expectVoteError(fn () => app(CastPopularVote::class)->handle($voter, $other, '198.51.100.5'));
        expectVoteError(fn () => app(CastPopularVote::class)->handle($voter, $entry, '198.51.100.5'));

        $otherCategory = entryFor(participant(), regularCategory($edition), 'aprovada');
        app(CastPopularVote::class)->handle($voter, $otherCategory, '198.51.100.5');
        expect(PopularVote::count())->toBe(2);
    });

    it('respeita o período (P38) nas duas pontas', function (string $moment, bool $allowed) {
        $entry = entryFor(participant(), regularCategory(openEdition()), 'aprovada');
        $voter = makeVoter();
        inVoting($moment);

        $call = fn () => app(CastPopularVote::class)->handle($voter, $entry, '198.51.100.5');
        $allowed ? $call() : expectVoteError($call);
        expect(PopularVote::count())->toBe($allowed ? 1 : 0);
    })->with([
        'um segundo antes' => ['2026-10-28 23:59:59', false],
        'abertura' => ['2026-10-29 00:00:00', true],
        'último segundo' => ['2026-11-20 23:59:59', true],
        'um segundo depois' => ['2026-11-21 00:00:00', false],
    ]);

    it('só em aprovadas (P37)', function (string $state) {
        $entry = entryFor(participant(), regularCategory(openEdition()), $state);
        $voter = makeVoter();
        inVoting();

        expectVoteError(fn () => app(CastPopularVote::class)->handle($voter, $entry, '198.51.100.5'));
    })->with(['enviada', 'desclassificada']);

    it('só eleitor vota (P60)', function (Role $role) {
        $entry = entryFor(participant(), regularCategory(openEdition()), 'aprovada');
        inVoting();

        expectVoteError(fn () => app(CastPopularVote::class)->handle(User::factory()->create(['role' => $role]), $entry, '198.51.100.5'));
    })->with([Role::Participante, Role::Jurado, Role::Admin]);
});

describe('galeria (P24, P55, P57)', function () {
    it('fechada antes de 29/10, sem projetos nem imagens', function () {
        $entry = entryFor(participant(), regularCategory(openEdition()), 'aprovada');
        $image = $entry->addMedia(fakeImage())->toMediaCollection('images');

        $this->get('/galeria')->assertOk()->assertSee('A galeria abre com o voto popular')->assertDontSee($entry->title);
        $this->get("/galeria/{$entry->id}")->assertNotFound();
        $this->get("/galeria/{$entry->id}/imagens/{$image->id}")->assertNotFound();
    });

    it('mostra só aprovadas com nome, categoria e imagens; nada de autoria, textos ou dados pessoais', function () {
        $edition = openEdition();
        $category = regularCategory($edition);
        $approved = entryFor(participant(), $category, 'aprovada');
        $approved->forceFill(['title' => 'Projeto aprovado', 'authorship_credits' => 'Autoria secreta XYZ', 'summary' => 'Resumo secreto XYZ'])->save();
        $approved->links()->create(['url' => 'https://example.test/secreto', 'sort' => 1]);
        $sent = entryFor(participant(), $category, 'enviada');
        $sent->forceFill(['title' => 'Projeto só enviado'])->save();
        $gone = entryFor(participant(), $category, 'desclassificada');
        $gone->forceFill(['title' => 'Projeto desclassificado'])->save();
        inVoting();

        $this->get('/galeria')->assertOk()->assertSee($approved->title)->assertDontSee($sent->title)->assertDontSee($gone->title);
        $this->get("/galeria/{$approved->id}")
            ->assertOk()
            ->assertSee($approved->title)
            ->assertSee($category->name)
            ->assertDontSee('Autoria secreta XYZ')
            ->assertDontSee('Resumo secreto XYZ')
            ->assertDontSee('https://example.test/secreto')
            ->assertDontSee($approved->user->email);
        $this->get("/galeria/{$sent->id}")->assertNotFound();
    });

    it('imagem pública só da coleção de imagens; PDF complementar dá 404', function () {
        $entry = entryFor(participant(), regularCategory(openEdition()), 'aprovada');
        $image = $entry->addMedia(fakeImage())->toMediaCollection('images');
        $pdf = $entry->addMedia(fakePdf())->toMediaCollection('supplementary_pdf');
        inVoting();

        $this->get("/galeria/{$entry->id}/imagens/{$image->id}")->assertOk()->assertHeader('Cache-Control', 'max-age=300, public');
        $this->get("/galeria/{$entry->id}/imagens/{$pdf->id}")->assertNotFound();
    });

    it('distinção e nota só depois da publicação do resultado', function () {
        $edition = openEdition();
        $entry = entryFor(participant(), regularCategory($edition), 'aprovada');
        $entry->forceFill(['final_score' => 55, 'distinction' => 'ouro'])->save();
        inVoting('2026-11-22 10:00:00');

        $this->get("/galeria/{$entry->id}")->assertDontSee('Ouro');
        $edition->forceFill(['results_published_at' => now()])->save();
        $this->get("/galeria/{$entry->id}")->assertSee('Ouro, 55 pontos');
    });

    it('nunca mostra contagem de votos ao público (P39)', function () {
        $entry = entryFor(participant(), regularCategory(openEdition()), 'aprovada');
        $voter = makeVoter();
        inVoting();
        app(CastPopularVote::class)->handle($voter, $entry, '198.51.100.5');

        $this->get('/galeria')->assertDontSee('1 voto')->assertDontSee('votos');
    });
});

describe('votar pelo site', function () {
    it('eleitor vota pela página do projeto e vê o próprio voto', function () {
        $entry = entryFor(participant(), regularCategory(openEdition()), 'aprovada');
        $voter = makeVoter();
        inVoting();

        $this->actingAs($voter)->get("/galeria/{$entry->id}")->assertSee('Votar neste projeto');
        $this->actingAs($voter)->post("/galeria/{$entry->id}/votar")->assertRedirect("/galeria/{$entry->id}");
        $this->actingAs($voter)->get("/galeria/{$entry->id}")->assertSee('Você votou neste projeto');
        $this->actingAs($voter)->get('/eleitor')->assertSee($entry->title);
    });

    it('participante logado vê que precisa de cadastro de eleitor e não vota', function () {
        $entry = entryFor(participant(), regularCategory(openEdition()), 'aprovada');
        $participant = participant();
        inVoting();

        $this->actingAs($participant)->get("/galeria/{$entry->id}")->assertSee('é preciso um cadastro de eleitor')->assertDontSee('Votar neste projeto');
        $this->actingAs($participant)->post("/galeria/{$entry->id}/votar")->assertForbidden();
    });

    it('visitante é convidado a entrar ou se cadastrar', function () {
        $entry = entryFor(participant(), regularCategory(openEdition()), 'aprovada');
        inVoting();

        $this->get("/galeria/{$entry->id}")->assertSee('faça o cadastro');
        $this->post("/galeria/{$entry->id}/votar")->assertRedirect('/login');
    });
});

describe('apuração e parcial (P40, P48, P49, P78)', function () {
    it('parcial do admin conta só aprovadas; desclassificada sai da contagem (P78)', function () {
        $edition = openEdition();
        $category = regularCategory($edition);
        [$a, $b] = [entryFor(participant(), $category, 'aprovada'), entryFor(participant(), $category, 'aprovada')];
        inVoting();
        app(CastPopularVote::class)->handle(makeVoter('52998224725'), $a, '1.1.1.1');
        app(CastPopularVote::class)->handle(makeVoter('11144477735'), $b, '1.1.1.1');
        app(DisqualifyEntry::class)->handle($b, User::factory()->admin()->create(), ['reason' => 'Autoria.']);

        $partial = app(TallyPopularVotes::class)->partial($edition);
        expect($partial->pluck('entry_id')->all())->toBe([$a->id]);

        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(ListPopularVotes::class)->assertCanSeeTableRecords([$a])->assertCanNotSeeTableRecords([$b]);
    });

    it('apura depois do fim: vencedor por categoria; empate aguarda a organização', function () {
        $edition = openEdition();
        [$c1, $c2] = [regularCategory($edition), regularCategory($edition)];
        [$a, $b] = [entryFor(participant(), $c1, 'aprovada'), entryFor(participant(), $c1, 'aprovada')];
        [$x, $y] = [entryFor(participant(), $c2, 'aprovada'), entryFor(participant(), $c2, 'aprovada')];
        $admin = User::factory()->admin()->create();
        inVoting();
        $voters = collect(['52998224725', '11144477735', '39053344705'])->map(fn ($cpf) => makeVoter($cpf));
        app(CastPopularVote::class)->handle($voters[0], $a, '1.1.1.1');
        app(CastPopularVote::class)->handle($voters[1], $a, '1.1.1.1');
        app(CastPopularVote::class)->handle($voters[2], $b, '1.1.1.1');
        app(CastPopularVote::class)->handle($voters[0], $x, '1.1.1.1');
        app(CastPopularVote::class)->handle($voters[1], $y, '1.1.1.1');

        expectVoteError(fn () => app(TallyPopularVotes::class)->handle($admin), 'tally');

        inVoting('2026-11-21 00:00:00');
        app(TallyPopularVotes::class)->handle($admin);

        $w1 = PopularVoteWinner::where('category_id', $c1->id)->sole();
        $w2 = PopularVoteWinner::where('category_id', $c2->id)->sole();
        expect($w1->entry_id)->toBe($a->id)->and($w1->votes)->toBe(2)->and($w1->was_tie)->toBeFalse()
            ->and($w2->entry_id)->toBeNull()->and($w2->was_tie)->toBeTrue()
            ->and($w2->tied_entry_ids)->toEqualCanonicalizing([$x->id, $y->id]);

        expectVoteError(fn () => app(DecidePopularVoteTie::class)->handle($w2, $admin, ['entry_id' => $a->id]), 'entry_id');
        app(DecidePopularVoteTie::class)->handle($w2, $admin, ['entry_id' => $y->id, 'decision_note' => 'Critério da organização.']);
        expect($w2->fresh()->entry_id)->toBe($y->id)->and($w2->fresh()->decided_by)->toBe($admin->id);

        // Reapurar sem mudança mantém a decisão; desclassificação que desfaz o empate limpa a decisão.
        app(TallyPopularVotes::class)->handle($admin);
        expect($w2->fresh()->entry_id)->toBe($y->id);
        app(DisqualifyEntry::class)->handle($y, $admin, ['reason' => 'Autoria.']);
        app(TallyPopularVotes::class)->handle($admin);
        expect($w2->fresh()->entry_id)->toBe($x->id)->and($w2->fresh()->was_tie)->toBeFalse()->and($w2->fresh()->decided_by)->toBeNull()
            ->and(Activity::where('log_name', 'voto_popular')->count())->toBe(4);
    });

    it('telas do admin: parcial, apurar e decidir empate', function () {
        $edition = openEdition();
        $category = regularCategory($edition);
        [$a, $b] = [entryFor(participant(), $category, 'aprovada'), entryFor(participant(), $category, 'aprovada')];
        $admin = User::factory()->admin()->create();
        inVoting();
        app(CastPopularVote::class)->handle(makeVoter('52998224725'), $a, '1.1.1.1');
        app(CastPopularVote::class)->handle(makeVoter('11144477735'), $b, '1.1.1.1');

        $this->actingAs($admin);
        Livewire::test(ListPopularVotes::class)->assertActionHidden('tally');

        inVoting('2026-11-21 00:00:00');
        Livewire::test(ListPopularVotes::class)->callAction('tally');
        $winner = PopularVoteWinner::sole();

        Livewire::test(ListPopularVoteWinners::class)
            ->callTableAction('decideTie', $winner, data: ['entry_id' => $b->id, 'decision_note' => 'Decisão.'])
            ->assertHasNoTableActionErrors();
        expect($winner->fresh()->entry_id)->toBe($b->id);
    });

    it('só admin vê parcial e apuração', function (Role $role) {
        $this->actingAs(User::factory()->create(['role' => $role]))->get('/admin/voto-popular')->assertForbidden();
    })->with([Role::Jurado, Role::Participante, Role::Eleitor]);
});
