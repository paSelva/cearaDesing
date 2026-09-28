<?php

namespace Database\Factories;

use App\Enums\ParticipantType;
use App\Enums\PersonType;
use App\Models\ParticipantProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ParticipantProfile> */
class ParticipantProfileFactory extends Factory
{
    protected $model = ParticipantProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->participante(),
            'person_type' => PersonType::Pf,
            'document' => fake()->unique()->cpf(false),
            'phone' => '85999990000',
            'city' => 'Fortaleza',
            'state' => 'CE',
            'participant_type' => ParticipantType::Profissional,
        ];
    }
}
