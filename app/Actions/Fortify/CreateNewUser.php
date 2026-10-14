<?php

namespace App\Actions\Fortify;

use App\Actions\Account\RegisterParticipant;
use App\Models\User;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/** O cadastro público do site cria somente participantes (T1, SPEC-002). */
class CreateNewUser implements CreatesNewUsers
{
    public function __construct(private readonly RegisterParticipant $registerParticipant) {}

    /** @param array<string, mixed> $input */
    public function create(array $input): User
    {
        return $this->registerParticipant->handle($input);
    }
}
