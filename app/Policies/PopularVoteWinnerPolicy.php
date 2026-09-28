<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\PopularVoteWinner;
use App\Models\User;

/** Só a organização vê a apuração e decide empates (P48, P49). Registros criados pela apuração. */
class PopularVoteWinnerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === Role::Admin;
    }

    public function view(User $user, PopularVoteWinner $winner): bool
    {
        return $user->role === Role::Admin;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, PopularVoteWinner $winner): bool
    {
        return false;
    }

    public function delete(User $user, PopularVoteWinner $winner): bool
    {
        return false;
    }
}
