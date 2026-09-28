<?php

use App\Actions\Evaluation\SaveScores;
use App\Actions\Evaluation\SubmitEvaluation;
use App\Actions\Jury\AssignJudgesToEntry;
use App\Models\Criterion;
use App\Models\Entry;
use App\Models\JudgeAssignment;
use App\Models\User;
use Illuminate\Support\Carbon;

/** Inscrição aprovada, categoria com 4 critérios e 3 jurados atribuídos; relógio dentro da avaliação. */
function evaluationSetup(): array
{
    $edition = openEdition();
    $category = regularCategory($edition);
    foreach (range(1, 4) as $sort) {
        Criterion::factory()->for($category)->create(['sort' => $sort, 'name' => "Critério {$sort}"]);
    }
    $judges = User::factory()->jurado()->count(3)->create();
    $category->judges()->sync($judges->pluck('id'));
    $entry = entryFor(participant(), $category, 'aprovada');
    app(AssignJudgesToEntry::class)->handle($entry, User::factory()->admin()->create());

    inJudgingPeriod();

    return [$entry->fresh(), $entry->judgeAssignments()->with('judge')->orderBy('id')->get()];
}

function inJudgingPeriod(string $moment = '2026-11-01 10:00:00'): void
{
    test()->travelTo(Carbon::parse($moment, 'America/Fortaleza'));
}

/** @param list<int> $values 4 notas na ordem dos critérios */
function scoresFor(JudgeAssignment $assignment, array $values): array
{
    $criteria = $assignment->entry->category->criteria()->orderBy('sort')->pluck('id')->all();

    return array_combine($criteria, $values);
}

function evaluate(JudgeAssignment $assignment, array $values): void
{
    app(SaveScores::class)->handle($assignment, $assignment->judge, scoresFor($assignment, $values));
    app(SubmitEvaluation::class)->handle($assignment, $assignment->judge);
}

/** Distribui um total (12–60) em 12 notas de 1 a 5 e envia as 3 avaliações. */
function evaluateWithTotal(Entry $entry, int $total): void
{
    $values = array_fill(0, 12, 1);
    $remaining = $total - 12;
    for ($i = 0; $i < 12 && $remaining > 0; $i++) {
        $add = min(4, $remaining);
        $values[$i] += $add;
        $remaining -= $add;
    }

    foreach ($entry->judgeAssignments()->with('judge')->orderBy('id')->get()->values() as $index => $assignment) {
        evaluate($assignment, array_slice($values, $index * 4, 4));
    }
}
