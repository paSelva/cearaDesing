<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\Criterion;
use App\Models\User;

class CriterionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === Role::Admin;
    }

    public function view(User $user, Criterion $criterion): bool
    {
        return $user->role === Role::Admin;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Criterion $criterion): bool
    {
        return false;
    }

    public function delete(User $user, Criterion $criterion): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
