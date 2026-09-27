<?php

namespace Database\Factories;

use App\Models\Edition;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Edition> */
class EditionFactory extends Factory
{
    protected $model = Edition::class;

    public function definition(): array
    {
        return [
            'name' => 'Edição de teste',
            'registration_starts_at' => '2026-09-28 00:00:00',
            'registration_ends_at' => '2026-10-28 23:59:59',
            'judging_starts_at' => '2026-10-29 00:00:00',
            'judging_ends_at' => '2026-11-20 23:59:59',
            'voting_starts_at' => '2026-10-29 00:00:00',
            'voting_ends_at' => '2026-11-20 23:59:59',
            'results_announce_on' => '2026-11-21',
            'ceremony_on' => '2026-11-26',
            'project_period_starts_on' => '2022-01-01',
            'project_period_ends_on' => '2026-10-28',
            'judges_per_entry' => 3,
            'band_gold_min' => 54,
            'band_silver_min' => 48,
            'band_bronze_min' => 42,
            'band_honorable_min' => 36,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(fn (Edition $edition) => $edition->forceFill(['year' => Edition::YEAR]));
    }
}
