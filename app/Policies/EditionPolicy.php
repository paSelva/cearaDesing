<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\Edition;
use App\Models\User;

class EditionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === Role::Admin;
    }

    public function view(User $user, Edition $edition): bool
    {
        return $user->role === Role::Admin && $edition->year === Edition::YEAR;
    }

    public function update(User $user, Edition $edition): bool
    {
        return $user->role === Role::Admin && $edition->year === Edition::YEAR;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, Edition $edition): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
