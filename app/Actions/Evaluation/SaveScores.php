<?php

declare(strict_types=1);

namespace App\Actions\Evaluation;

use App\Models\JudgeAssignment;
use App\Models\Score;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * O jurado salva notas parciais antes de enviar (docs/07). Cada nota é inteiro de 1 a 5 (§5)
 * de um critério da categoria da inscrição. Nota em branco apaga a nota salva.
 */
final class SaveScores
{
    public function __construct(private readonly EvaluationGuard $guard) {}

    /** @param array<int|string, mixed> $scores [criterion_id => valor] */
    public function handle(JudgeAssignment $assignment, User $judge, array $scores): JudgeAssignment
    {
        return DB::transaction(function () use ($assignment, $judge, $scores): JudgeAssignment {
            $locked = $this->guard->lockForJudge($assignment, $judge);
            $criteria = $locked->entry->category->criteria()->pluck('id')->all();

            foreach ($scores as $criterionId => $value) {
                if (! in_array((int) $criterionId, $criteria, true)) {
                    throw ValidationException::withMessages(['scores' => 'Critério que não pertence à categoria desta inscrição.']);
                }

                if ($value === null || $value === '') {
                    Score::query()->where('judge_assignment_id', $locked->id)->where('criterion_id', (int) $criterionId)->delete();

                    continue;
                }

                if (filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]) === false) {
                    throw ValidationException::withMessages(["scores.{$criterionId}" => 'Cada nota deve ser um número inteiro de 1 a 5 (§5).']);
                }

                $score = Score::query()->where('judge_assignment_id', $locked->id)->where('criterion_id', (int) $criterionId)->first() ?? new Score;
                $score->judge_assignment_id = $locked->id;
                $score->criterion_id = (int) $criterionId;
                $score->value = (int) $value;
                $score->save();
            }

            return $locked;
        });
    }
}
