<?php

use App\Actions\Evaluation\CorrectScore;
use App\Actions\Evaluation\SaveScores;
use App\Actions\Evaluation\SubmitEvaluation;
use App\Actions\Jury\ReplaceJudgeOnEntry;
use App\Actions\Triage\DisqualifyEntry;
use App\Enums\AssignmentStatus;
use App\Enums\Distinction;
use App\Models\Criterion;
use App\Models\JudgeAssignment;
use App\Models\Score;
use App\Models\ScoreRevision;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

function expectEvalError(Closure $call, string $field = 'scores'): void
{
    try {
        $call();
        test()->fail("Esperava erro em {$field}.");
    } catch (ValidationException $e) {
        expect(array_key_first($e->errors()))->toStartWith($field);
    }
}

describe('resultado (§5, §6, docs/04 §9)', function () {
    it('nota final é a soma e a distinção segue as faixas da edição', function (int $total, Distinction $expected) {
        [$entry] = evaluationSetup();

        evaluateWithTotal($entry, $total);

        $entry->refresh();
        expect($entry->final_score)->toBe($total)
            ->and($entry->distinction)->toBe($expected);
    })->with([
        'máximo' => [60, Distinction::Ouro],
        'limite ouro' => [54, Distinction::Ouro],
        'abaixo ouro' => [53, Distinction::Prata],
        'limite prata' => [48, Distinction::Prata],
        'abaixo prata' => [47, Distinction::Bronze],
        'limite bronze' => [42, Distinction::Bronze],
        'abaixo bronze' => [41, Distinction::MencaoHonrosa],
        'limite menção' => [36, Distinction::MencaoHonrosa],
        'abaixo menção' => [35, Distinction::SemDistincao],
        'mínimo' => [12, Distinction::SemDistincao],
    ]);

    it('com menos de 3 avaliações enviadas não há resultado', function () {
        [$entry, $assignments] = evaluationSetup();

        evaluate($assignments[0], [5, 5, 5, 5]);
        evaluate($assignments[1], [5, 5, 5, 5]);

        expect($entry->fresh()->final_score)->toBeNull()
            ->and($entry->fresh()->distinction)->toBeNull();
    });

    it('as faixas vêm da edição, não do código', function () {
        [$entry] = evaluationSetup();
        $entry->edition->forceFill(['band_gold_min' => 59])->save();

        evaluateWithTotal($entry, 54);

        expect($entry->fresh()->distinction)->toBe(Distinction::Prata);
    });

    it('substituição tira as notas antigas da soma e limpa o resultado até o substituto enviar', function () {
        [$entry, $assignments] = evaluationSetup();
        evaluateWithTotal($entry, 60);
        expect($entry->fresh()->final_score)->toBe(60);

        $outsider = User::factory()->jurado()->create();
        $replacement = app(ReplaceJudgeOnEntry::class)->handle($assignments[0], User::factory()->admin()->create(), ['new_judge_id' => $outsider->id, 'reason' => 'Conflito.']);
        expect($entry->fresh()->final_score)->toBeNull();

        evaluate($replacement->load('judge'), [1, 1, 1, 1]);
        expect($entry->fresh()->final_score)->toBe(44)   // 20 + 20 + 4
            ->and(Score::where('judge_assignment_id', $assignments[0]->id)->count())->toBe(4); // histórico mantido
    });
});

describe('SaveScores e SubmitEvaluation', function () {
    it('salva notas parciais e depois envia, travando as notas (P19)', function () {
        [, $assignments] = evaluationSetup();
        $a = $assignments[0];

        app(SaveScores::class)->handle($a, $a->judge, [array_key_first(scoresFor($a, [4, 0, 0, 0])) => 4]);
        expect($a->scores()->count())->toBe(1);

        expectEvalError(fn () => app(SubmitEvaluation::class)->handle($a, $a->judge));
        expect($a->fresh()->status)->toBe(AssignmentStatus::Pendente);

        evaluate($a, [4, 3, 5, 2]);
        expect($a->fresh()->status)->toBe(AssignmentStatus::Enviada)
            ->and($a->fresh()->submitted_at)->not->toBeNull();

        expectEvalError(fn () => app(SaveScores::class)->handle($a->fresh(), $a->judge, scoresFor($a, [1, 1, 1, 1])));
        expect($a->scores()->orderBy('criterion_id')->pluck('value')->all())->toBe([4, 3, 5, 2]);
    });

    it('só aceita inteiros de 1 a 5 (§5), de critérios da categoria', function (Closure $scores) {
        [, $assignments] = evaluationSetup();
        $a = $assignments[0];

        expectEvalError(fn () => app(SaveScores::class)->handle($a, $a->judge, $scores($a)));
    })->with([
        'zero' => [fn ($a) => scoresFor($a, [0, 1, 1, 1])],
        'seis' => [fn ($a) => scoresFor($a, [6, 1, 1, 1])],
        'decimal' => [fn ($a) => scoresFor($a, ['3.5', 1, 1, 1])],
        'texto' => [fn ($a) => scoresFor($a, ['bom', 1, 1, 1])],
        'critério de outra categoria' => [fn ($a) => [Criterion::factory()->for(regularCategory($a->entry->edition))->create(['sort' => 1])->id => 3]],
    ]);

    it('respeita o período de avaliação (§7) nas duas pontas', function (string $moment, bool $allowed) {
        [, $assignments] = evaluationSetup();
        $a = $assignments[0];
        inJudgingPeriod($moment);

        $call = fn () => app(SaveScores::class)->handle($a, $a->judge, scoresFor($a, [3, 3, 3, 3]));
        $allowed ? $call() : expectEvalError($call);

        expect($a->scores()->count())->toBe($allowed ? 4 : 0);
    })->with([
        'um segundo antes' => ['2026-10-28 23:59:59', false],
        'abertura' => ['2026-10-29 00:00:00', true],
        'último segundo' => ['2026-11-20 23:59:59', true],
        'um segundo depois' => ['2026-11-21 00:00:00', false],
    ]);

    it('jurado não avalia atribuição de outro', function () {
        [, $assignments] = evaluationSetup();

        expectEvalError(fn () => app(SaveScores::class)->handle($assignments[0], $assignments[1]->judge, scoresFor($assignments[0], [3, 3, 3, 3])));
    });

    it('corrida: instância antiga não avalia depois de substituição ou desclassificação', function (string $what) {
        [$entry, $assignments] = evaluationSetup();
        $stale = JudgeAssignment::with('judge')->find($assignments[0]->id);
        $admin = User::factory()->admin()->create();

        $what === 'substituicao'
            ? app(ReplaceJudgeOnEntry::class)->handle($assignments[0], $admin, ['new_judge_id' => User::factory()->jurado()->create()->id, 'reason' => 'Conflito.'])
            : app(DisqualifyEntry::class)->handle($entry, $admin, ['reason' => 'Autoria.']);

        expectEvalError(fn () => app(SaveScores::class)->handle($stale, $stale->judge, scoresFor($stale, [3, 3, 3, 3])));
        expect($stale->scores()->count())->toBe(0);
    })->with(['substituicao', 'desclassificacao']);
});

describe('CorrectScore (P19, P46)', function () {
    it('corrige com justificativa, guarda histórico e recalcula', function () {
        [$entry, $assignments] = evaluationSetup();
        evaluateWithTotal($entry, 53);
        $admin = User::factory()->admin()->create();
        $score = $assignments[2]->scores()->where('value', '<', 5)->first();
        $old = $score->value;

        app(CorrectScore::class)->handle($score, $admin, ['new_value' => $old + 1, 'reason' => 'Erro de digitação do jurado.']);

        $revision = ScoreRevision::sole();
        expect($score->fresh()->value)->toBe($old + 1)
            ->and($revision->old_value)->toBe($old)
            ->and($revision->new_value)->toBe($old + 1)
            ->and($revision->changed_by)->toBe($admin->id)
            ->and($revision->reason)->toBe('Erro de digitação do jurado.')
            ->and($entry->fresh()->final_score)->toBe(54)
            ->and($entry->fresh()->distinction)->toBe(Distinction::Ouro)
            ->and(Activity::where('event', 'correcao_de_nota')->count())->toBe(1);
    });

    it('exige justificativa, nota de 1 a 5, avaliação enviada e admin', function () {
        [$entry, $assignments] = evaluationSetup();
        $admin = User::factory()->admin()->create();
        app(SaveScores::class)->handle($assignments[0], $assignments[0]->judge, scoresFor($assignments[0], [3, 3, 3, 3]));
        $pendingScore = $assignments[0]->scores()->first();

        expectEvalError(fn () => app(CorrectScore::class)->handle($pendingScore, $admin, ['new_value' => 4, 'reason' => 'x']), 'new_value');

        evaluate($assignments[0]->fresh()->load('judge'), [3, 3, 3, 3]);
        $score = $assignments[0]->scores()->first();
        expectEvalError(fn () => app(CorrectScore::class)->handle($score, $admin, ['new_value' => 4, 'reason' => '  ']), 'reason');
        expectEvalError(fn () => app(CorrectScore::class)->handle($score, $admin, ['new_value' => 6, 'reason' => 'x']), 'new_value');
        expectEvalError(fn () => app(CorrectScore::class)->handle($score, $admin, ['new_value' => 3, 'reason' => 'x']), 'new_value');
        expectEvalError(fn () => app(CorrectScore::class)->handle($score, $assignments[1]->judge, ['new_value' => 4, 'reason' => 'x']), 'new_value');
        expect(ScoreRevision::count())->toBe(0);
    });
});
