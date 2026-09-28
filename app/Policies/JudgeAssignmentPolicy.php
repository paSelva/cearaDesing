<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\AssignmentStatus;
use App\Enums\Role;
use App\Models\JudgeAssignment;
use App\Models\User;

/** Atribuições de jurados. Nesta etapa, só a organização (SPEC-006); o jurado entra na SPEC-007. */
class JudgeAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === Role::Admin;
    }

    public function view(User $user, JudgeAssignment $assignment): bool
    {
        return $user->role === Role::Admin;
    }

    public function create(User $user): bool
    {
        return $user->role === Role::Admin;
    }

    public function replace(User $user, JudgeAssignment $assignment): bool
    {
        return $user->role === Role::Admin && $assignment->status !== AssignmentStatus::Substituida;
    }

    public function update(User $user, JudgeAssignment $assignment): bool
    {
        return false;
    }

    public function delete(User $user, JudgeAssignment $assignment): bool
    {
        return false;
    }
}
