<?php

declare(strict_types=1);

namespace App\Actions\Evaluation;

use App\Enums\AssignmentStatus;
use App\Models\JudgeAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Envia a avaliação com as notas já salvas: exige todas as notas da categoria (4 pelo §5),
 * trava as notas (P19) e recalcula o resultado da inscrição.
 */
final class SubmitEvaluation
{
    public function __construct(
        private readonly EvaluationGuard $guard,
        private readonly CalculateEntryResult $calculate,
    ) {}

    public function handle(JudgeAssignment $assignment, User $judge): JudgeAssignment
    {
        return DB::transaction(function () use ($assignment, $judge): JudgeAssignment {
            $locked = $this->guard->lockForJudge($assignment, $judge);

            $criteria = $locked->entry->category->criteria()->pluck('id');
            $scored = $locked->scores()->whereIn('criterion_id', $criteria)->count();
            if ($criteria->isEmpty() || $scored < $criteria->count()) {
                throw ValidationException::withMessages(['scores' => "Dê as {$criteria->count()} notas antes de enviar (§5)."]);
            }

            $locked->status = AssignmentStatus::Enviada;
            $locked->submitted_at = now();
            $locked->save();

            $this->calculate->handle($locked->entry);

            return $locked;
        });
    }
}
