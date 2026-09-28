<?php

namespace Database\Factories;

use App\Enums\ProofRequestStatus;
use App\Models\Entry;
use App\Models\ProofRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProofRequest> */
class ProofRequestFactory extends Factory
{
    protected $model = ProofRequest::class;

    public function definition(): array
    {
        return [
            'entry_id' => Entry::factory()->enviada(),
            'requested_by' => User::factory()->admin(),
            'message' => 'Envie um documento que comprove a data de conclusão do projeto.',
            'deadline_at' => now()->addDays(7),
            'status' => ProofRequestStatus::Pendente,
            'is_late' => false,
        ];
    }
}
