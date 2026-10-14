<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\EntryStatus;
use App\Enums\Role;
use App\Models\Entry;
use App\Models\User;

/**
 * Participante vê e edita só as próprias inscrições (IDOR). A organização consulta todas
 * e faz a triagem, mas nunca edita o conteúdo do participante (update continua só do dono).
 * Prazo e status (P8, P70) são reconferidos nas Actions.
 */
class EntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === Role::Admin;
    }

    public function view(User $user, Entry $entry): bool
    {
        return $this->isOwner($user, $entry) || $user->role === Role::Admin;
    }

    public function update(User $user, Entry $entry): bool
    {
        return $this->isOwner($user, $entry);
    }

    public function approve(User $user, Entry $entry): bool
    {
        return $user->role === Role::Admin && $entry->status === EntryStatus::Enviada;
    }

    public function disqualify(User $user, Entry $entry): bool
    {
        return $user->role === Role::Admin
            && in_array($entry->status, [EntryStatus::Enviada, EntryStatus::Aprovada], true);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, Entry $entry): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    private function isOwner(User $user, Entry $entry): bool
    {
        return $user->role === Role::Participante && $entry->user_id === $user->id;
    }
}
