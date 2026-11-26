<?php

use App\Actions\Results\PublishResults;
use App\Filament\Resources\EditionResource\Pages\EditEdition;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Storage::fake('local');
});

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

    it('bloqueia com aprovada sem resultado', function () {
        [$edition, $category, $gold] = publishableSetup();
        $admin = User::factory()->admin()->create();
        $incomplete = entryFor(participant(), $category, 'aprovada');

        expect(expectPublishError(fn () => app(PublishResults::class)->handle($admin)))
            ->toContain('1 inscrição(ões) aprovada(s) ainda sem as avaliações dos jurados.');
        $incomplete->forceFill(['final_score' => 40, 'distinction' => 'mencao_honrosa'])->save();
        app(PublishResults::class)->handle($admin);
        expect($edition->fresh()->results_published_at)->not->toBeNull();
    });

    it('bloqueia enquanto a avaliação não termina', function () {
        [$edition] = publishableSetup();
        $admin = User::factory()->admin()->create();
        $this->travelTo(Carbon::parse('2026-11-20 23:59:00', 'America/Fortaleza'));

        expect(expectPublishError(fn () => app(PublishResults::class)->handle($admin)))
            ->toContain('A avaliação vai até 20/11/2026 23:59; o resultado só pode ser publicado depois.')
            ->and($edition->fresh()->results_published_at)->toBeNull();

        $this->travelTo(Carbon::parse('2026-11-21 00:00:00', 'America/Fortaleza'));
        app(PublishResults::class)->handle($admin);
        expect($edition->fresh()->results_published_at)->not->toBeNull();
    });

    it('bloqueia com inscrição enviada sem triagem; rascunho não bloqueia', function () {
        [$edition, $category] = publishableSetup();
        $admin = User::factory()->admin()->create();
        entryFor(participant(), $category, 'rascunho');
        $sent = entryFor(participant(), $category, 'enviada');

        expect(expectPublishError(fn () => app(PublishResults::class)->handle($admin)))
            ->toBe(['1 inscrição(ões) enviada(s) ainda sem triagem (aprovar ou desclassificar).']);

        $sent->forceFill(['status' => 'desclassificada'])->save();
        app(PublishResults::class)->handle($admin);
        expect($edition->fresh()->results_published_at)->not->toBeNull();
    });

    it('bloqueia sem nenhuma aprovada com resultado', function () {
        $edition = openEdition();
        entryFor(participant(), regularCategory($edition), 'desclassificada');
        $this->travelTo(Carbon::parse('2026-11-21 10:00:00', 'America/Fortaleza'));

        expect(expectPublishError(fn () => app(PublishResults::class)->handle(User::factory()->admin()->create())))
            ->toBe(['Nenhuma inscrição aprovada tem resultado ainda.'])
            ->and($edition->fresh()->results_published_at)->toBeNull();
    });

    it('botão no painel avisa o bloqueio e não publica', function () {
        $edition = openEdition();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(EditEdition::class, ['record' => $edition->getRouteKey()])
            ->callAction('publishResults')
            ->assertNotified('Não publicado');

        expect($edition->fresh()->results_published_at)->toBeNull();
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

describe('página de premiados (P21, P44)', function () {
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
