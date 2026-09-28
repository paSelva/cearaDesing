<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\Entry;
use App\Models\User;

/**
 * Participante vê e edita só as próprias inscrições (IDOR).
 * Prazo e status (P8, P70) são verificados nas Actions. Admin/triagem: SPEC-004.
 */
class EntryPolicy
{
    public function view(User $user, Entry $entry): bool
    {
        return $user->role === Role::Participante && $entry->user_id === $user->id;
    }

    public function update(User $user, Entry $entry): bool
    {
        return $this->view($user, $entry);
    }

    public function delete(User $user, Entry $entry): bool
    {
        return false;
    }
}
