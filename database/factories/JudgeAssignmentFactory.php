<?php

namespace Database\Factories;

use App\Enums\AssignmentStatus;
use App\Models\Entry;
use App\Models\JudgeAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<JudgeAssignment> */
class JudgeAssignmentFactory extends Factory
{
    protected $model = JudgeAssignment::class;

    public function definition(): array
    {
        return [
            'entry_id' => Entry::factory()->aprovada(),
            'judge_id' => User::factory()->jurado(),
            'assigned_by' => User::factory()->admin(),
            'status' => AssignmentStatus::Pendente,
        ];
    }
}
