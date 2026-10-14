<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ProofRequestStatus;
use App\Enums\Role;
use App\Models\ProofRequest;
use App\Models\User;

class ProofRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === Role::Admin;
    }

    public function create(User $user): bool
    {
        return $user->role === Role::Admin;
    }

    public function view(User $user, ProofRequest $proofRequest): bool
    {
        return $user->role === Role::Admin || $this->isOwner($user, $proofRequest);
    }

    public function submit(User $user, ProofRequest $proofRequest): bool
    {
        return $this->isOwner($user, $proofRequest) && $proofRequest->status === ProofRequestStatus::Pendente;
    }

    public function review(User $user, ProofRequest $proofRequest): bool
    {
        return $user->role === Role::Admin && $proofRequest->status === ProofRequestStatus::Respondida;
    }

    public function update(User $user, ProofRequest $proofRequest): bool
    {
        return false;
    }

    public function delete(User $user, ProofRequest $proofRequest): bool
    {
        return false;
    }

    private function isOwner(User $user, ProofRequest $proofRequest): bool
    {
        return $user->role === Role::Participante && $proofRequest->entry->user_id === $user->id;
    }
}
