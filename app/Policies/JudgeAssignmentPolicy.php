<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\AssignmentStatus;
use App\Enums\EntryStatus;
use App\Enums\Role;
use App\Models\JudgeAssignment;
use App\Models\User;

/**
 * Atribuições de jurados. Admin gerencia (SPEC-006); o jurado vê e avalia só as próprias
 * atribuições ativas de inscrições aprovadas (SPEC-007). Prazo e trava são reconferidos nas Actions.
 */
class JudgeAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [Role::Admin, Role::Jurado], true);
    }

    public function view(User $user, JudgeAssignment $assignment): bool
    {
        return $user->role === Role::Admin || $this->isActiveForJudge($user, $assignment);
    }

    public function create(User $user): bool
    {
        return $user->role === Role::Admin;
    }

    /** O jurado edita notas só enquanto a própria avaliação está pendente (P19). */
    public function update(User $user, JudgeAssignment $assignment): bool
    {
        return $this->isActiveForJudge($user, $assignment) && $assignment->status === AssignmentStatus::Pendente;
    }

    public function replace(User $user, JudgeAssignment $assignment): bool
    {
        return $user->role === Role::Admin && $assignment->status !== AssignmentStatus::Substituida;
    }

    public function delete(User $user, JudgeAssignment $assignment): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    private function isActiveForJudge(User $user, JudgeAssignment $assignment): bool
    {
        return $user->role === Role::Jurado
            && $assignment->judge_id === $user->id
            && $assignment->status !== AssignmentStatus::Substituida
            && $assignment->entry->status === EntryStatus::Aprovada;
    }
}
