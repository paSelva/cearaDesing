<?php

declare(strict_types=1);

namespace App\Actions\Jury;

use App\Enums\AssignmentStatus;
use App\Enums\EntryStatus;
use App\Models\Category;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Atalho da organização: atribui os jurados fixos a todas as inscrições aprovadas da categoria
 * que ainda não estão completas. Continua sendo uma ação manual do admin (P15).
 */
final class AssignJudgesToCategory
{
    public function __construct(private readonly AssignJudgesToEntry $assign) {}

    /** @return array{assigned: list<int>, skipped: list<int>, failed: array<int, string>} ids de inscrição */
    public function handle(Category $category, User $admin): array
    {
        $result = ['assigned' => [], 'skipped' => [], 'failed' => []];
        $required = $category->edition->judges_per_entry;

        $entries = $category->entries()
            ->where('status', EntryStatus::Aprovada->value)
            ->withCount(['judgeAssignments as active_assignments_count' => fn ($query) => $query->where('status', '!=', AssignmentStatus::Substituida->value)])
            ->orderBy('id')
            ->get();

        foreach ($entries as $entry) {
            if ($entry->active_assignments_count >= $required) {
                $result['skipped'][] = $entry->id;

                continue;
            }

            try {
                $this->assign->handle($entry, $admin);
                $result['assigned'][] = $entry->id;
            } catch (ValidationException $e) {
                $result['failed'][$entry->id] = (string) collect($e->errors())->flatten()->first();
            }
        }

        return $result;
    }
}
