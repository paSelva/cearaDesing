<?php

declare(strict_types=1);

namespace App\Actions\Evaluation;

use App\Enums\AssignmentStatus;
use App\Enums\Distinction;
use App\Models\Entry;
use App\Models\Score;

/**
 * Nota final e distinção (§5, §6; algoritmo em docs/04 §9). Só contam avaliações enviadas;
 * as substituídas ficam de fora. Com menos de `judges_per_entry` avaliações, limpa o resultado.
 * O resultado não é público até a publicação pelo admin (P69).
 */
final class CalculateEntryResult
{
    public function handle(Entry $entry): Entry
    {
        $edition = $entry->edition;
        $submitted = $entry->judgeAssignments()->where('status', AssignmentStatus::Enviada->value)->pluck('id');

        if ($submitted->count() < $edition->judges_per_entry) {
            $entry->final_score = null;
            $entry->distinction = null;
        } else {
            $criteria = $entry->category->criteria()->pluck('id');
            $total = (int) Score::query()
                ->whereIn('judge_assignment_id', $submitted)
                ->whereIn('criterion_id', $criteria)
                ->sum('value');

            $entry->final_score = $total;
            $entry->distinction = Distinction::fromScore($total, $edition);
        }

        $entry->save();

        return $entry;
    }
}
