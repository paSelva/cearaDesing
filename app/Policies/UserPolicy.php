<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

/** Gestão de contas no painel: nesta etapa, o admin gerencia só jurados (P17, P82). */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === Role::Admin;
    }

    public function view(User $user, User $target): bool
    {
        return $user->role === Role::Admin && $target->role === Role::Jurado;
    }

    public function create(User $user): bool
    {
        return $user->role === Role::Admin;
    }

    public function update(User $user, User $target): bool
    {
        return $user->role === Role::Admin && $target->role === Role::Jurado;
    }

    public function delete(User $user, User $target): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
