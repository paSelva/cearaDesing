<?php

use App\Actions\Jury\ReplaceJudgeOnEntry;
use App\Actions\Proofs\RequestProof;
use App\Actions\Proofs\SubmitProof;
use App\Enums\AssignmentStatus;
use App\Enums\Role;
use App\Filament\Jurado\Resources\EvaluationResource\Pages\EvaluateAssignment;
use App\Filament\Jurado\Resources\EvaluationResource\Pages\ListEvaluations;
use App\Filament\Jurado\Resources\EvaluationResource\Pages\ViewEvaluation;
use App\Filament\Resources\EntryResource\Pages\ViewEntry;
use App\Filament\Resources\EntryResource\RelationManagers\JudgeAssignmentsRelationManager;
use App\Models\ScoreRevision;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(fn () => Storage::fake('local'));

function asJudge(User $judge): void
{
    test()->actingAs($judge);
    Filament::setCurrentPanel(Filament::getPanel('jurado'));
}

describe('acesso aos painéis', function () {
    it('jurado entra em /jurado e não em /admin; admin, participante e eleitor não entram em /jurado', function () {
        $judge = User::factory()->jurado()->create();

        $this->actingAs($judge)->get('/jurado/avaliacoes')->assertOk();
        $this->actingAs($judge)->get('/admin')->assertForbidden();

        foreach ([Role::Admin, Role::Participante, Role::Eleitor] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get('/jurado/avaliacoes')->assertForbidden();
        }
    });

    it('login do jurado não oferece recuperação de senha (P82)', function () {
        $this->get('/jurado/login')->assertOk()->assertDontSee('password-reset');
    });
});

describe('lista e IDOR', function () {
    it('jurado vê só as próprias atribuições ativas; substituída some', function () {
        [$entry, $assignments] = evaluationSetup();
        [$mine, $other] = [$assignments[0], $assignments[1]];

        asJudge($mine->judge);
        Livewire::test(ListEvaluations::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$other]);

        app(ReplaceJudgeOnEntry::class)->handle($mine, User::factory()->admin()->create(), ['new_judge_id' => User::factory()->jurado()->create()->id, 'reason' => 'Conflito.']);
        Livewire::test(ListEvaluations::class)->assertCanNotSeeTableRecords([$mine]);
    });

    it('jurado não abre nem avalia a atribuição de outro', function () {
        [, $assignments] = evaluationSetup();
        $this->actingAs($assignments[0]->judge);

        $this->get("/jurado/avaliacoes/{$assignments[1]->id}")->assertNotFound();
        $this->get("/jurado/avaliacoes/{$assignments[1]->id}/avaliar")->assertNotFound();
    });

    it('arquivos: jurado atribuído vê imagens; outro jurado e comprovações não', function () {
        [$entry, $assignments] = evaluationSetup();
        // Inscrição aprovada não aceita novos arquivos do participante (P70): anexa direto.
        $image = $entry->addMedia(fakeImage())->toMediaCollection('images');
        $proof = app(RequestProof::class)->handle($entry, User::factory()->admin()->create(), ['message' => 'Comprove.', 'deadline_at' => '2026-11-10 12:00:00']);
        app(SubmitProof::class)->handle($proof, $entry->user, fakePdf());
        $proofMedia = $proof->fresh()->getFirstMedia('proof');

        $this->actingAs($assignments[0]->judge)->get("/avaliacao/atribuicoes/{$assignments[0]->id}/arquivos/{$image->id}")->assertOk();
        $this->actingAs($assignments[0]->judge)->get("/avaliacao/atribuicoes/{$assignments[0]->id}/arquivos/{$proofMedia->id}")->assertNotFound();
        $this->actingAs($assignments[1]->judge)->get("/avaliacao/atribuicoes/{$assignments[0]->id}/arquivos/{$image->id}")->assertForbidden();
        $this->actingAs($entry->user)->get("/avaliacao/atribuicoes/{$assignments[0]->id}/arquivos/{$image->id}")->assertForbidden();
    });
});

describe('tela de avaliação', function () {
    it('mostra o projeto com autoria (P13), sem dados de conta do participante', function () {
        [$entry, $assignments] = evaluationSetup();
        $email = $entry->user->email;

        $this->actingAs($assignments[0]->judge)
            ->get("/jurado/avaliacoes/{$assignments[0]->id}/avaliar")
            ->assertOk()
            ->assertSee($entry->title)
            ->assertSee($entry->authorship_credits)
            ->assertSee('Critério 1')
            ->assertSee('Excelente')
            ->assertDontSee($email)
            ->assertDontSee($entry->user->participantProfile->phone);
    });

    it('salvar guarda as notas; salvar e enviar trava; envio incompleto mantém o que foi marcado', function () {
        [$entry, $assignments] = evaluationSetup();
        $a = $assignments[0];
        $ids = array_keys(scoresFor($a, [0, 0, 0, 0]));
        asJudge($a->judge);

        Livewire::test(EvaluateAssignment::class, ['record' => $a->getRouteKey()])
            ->fillForm(['scores' => [$ids[0] => '4', $ids[1] => '5']])
            ->call('submitEvaluation');

        expect($a->fresh()->status)->toBe(AssignmentStatus::Pendente)
            ->and($a->scores()->orderBy('criterion_id')->pluck('value')->all())->toBe([4, 5]);

        Livewire::test(EvaluateAssignment::class, ['record' => $a->getRouteKey()])
            ->assertSchemaStateSet(["scores.{$ids[0]}" => '4', "scores.{$ids[1]}" => '5'])
            ->fillForm(['scores' => [$ids[0] => '4', $ids[1] => '5', $ids[2] => '3', $ids[3] => '2']])
            ->call('submitEvaluation');

        expect($a->fresh()->status)->toBe(AssignmentStatus::Enviada);

        $this->actingAs($a->judge)->get("/jurado/avaliacoes/{$a->id}/avaliar")->assertForbidden();
    });

    it('a página da avaliação não mostra notas de outros jurados nem a nota final', function () {
        [$entry, $assignments] = evaluationSetup();
        evaluate($assignments[1], [1, 1, 1, 1]);
        evaluate($assignments[2], [1, 1, 1, 1]);
        evaluate($assignments[0], [5, 5, 5, 5]);
        expect($entry->fresh()->final_score)->toBe(28);

        asJudge($assignments[0]->judge);
        Livewire::test(ViewEvaluation::class, ['record' => $assignments[0]->getRouteKey()])
            ->assertOk()
            ->assertSee('Avaliação enviada')
            ->assertDontSee('Nota final')
            ->assertDontSee('Distinção')
            ->assertDontSee('Critério 1: 1');
    });
});

describe('organização', function () {
    it('admin vê as notas e corrige pela seção Jurados, com histórico', function () {
        [$entry, $assignments] = evaluationSetup();
        evaluateWithTotal($entry, 36);
        $a = $assignments[0]->fresh();
        $score = $a->scores()->first();

        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(JudgeAssignmentsRelationManager::class, ['ownerRecord' => $entry->fresh(), 'pageClass' => ViewEntry::class])
            ->assertSee(' = ')
            ->callTableAction('correctScore', $a, data: ['score_id' => $score->id, 'new_value' => $score->value === 5 ? 4 : $score->value + 1, 'reason' => 'Erro na soma.'])
            ->assertHasNoTableActionErrors();

        expect(ScoreRevision::count())->toBe(1);
        $this->get("/admin/inscricoes/{$entry->id}")->assertOk()->assertSee('Nota final');
    });

    it('participante não vê o resultado antes da publicação (P69)', function () {
        [$entry] = evaluationSetup();
        evaluateWithTotal($entry, 55);

        $this->actingAs($entry->user)->get("/participante/inscricoes/{$entry->id}")
            ->assertOk()
            ->assertDontSee('Nota final')
            ->assertDontSee('Distinção')
            ->assertDontSee('Ouro');
    });
});
