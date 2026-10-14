<?php

declare(strict_types=1);

namespace App\Actions\Evaluation;

use App\Enums\AssignmentStatus;
use App\Enums\EntryStatus;
use App\Models\JudgeAssignment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/** Regras do jurado sobre a própria atribuição (P19, §7). Chamar dentro da transação. */
final class EvaluationGuard
{
    public function lockForJudge(JudgeAssignment $assignment, User $judge): JudgeAssignment
    {
        $locked = JudgeAssignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();

        if ($locked->judge_id !== $judge->id) {
            throw ValidationException::withMessages(['scores' => 'Esta avaliação não é sua.']);
        }

        if ($locked->status !== AssignmentStatus::Pendente) {
            throw ValidationException::withMessages(['scores' => $locked->status === AssignmentStatus::Enviada
                ? 'Esta avaliação já foi enviada e as notas estão travadas.'
                : 'Você foi substituído nesta avaliação.']);
        }

        if ($locked->entry->status !== EntryStatus::Aprovada) {
            throw ValidationException::withMessages(['scores' => 'Esta inscrição não está mais em avaliação.']);
        }

        $edition = $locked->entry->edition;
        $now = now();
        if ($now->lessThan($edition->judging_starts_at) || $now->greaterThan($edition->judging_ends_at)) {
            throw ValidationException::withMessages(['scores' => 'Fora do período de avaliação ('
                .$edition->judging_starts_at->format('d/m/Y H:i').' a '.$edition->judging_ends_at->format('d/m/Y H:i').').']);
        }

        return $locked;
    }
}
