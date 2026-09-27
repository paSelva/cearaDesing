<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\Edition;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class UpdateEditionSettings
{
    public const CRITERIA_PER_CATEGORY = 4;

    public const MAX_POINTS_PER_CRITERION = 5;

    /** @param array<string, mixed> $settings */
    public function handle(Edition $edition, array $settings): Edition
    {
        if ($edition->year !== Edition::YEAR) {
            throw ValidationException::withMessages([
                'edition' => 'Somente a edição 2026 pode ser configurada neste sistema.',
            ]);
        }

        $validated = Validator::make($settings, [
            'name' => ['required', 'string', 'max:255'],
            'registration_starts_at' => ['required', 'date'],
            'registration_ends_at' => ['required', 'date', 'after_or_equal:registration_starts_at'],
            'judging_starts_at' => ['required', 'date'],
            'judging_ends_at' => ['required', 'date', 'after_or_equal:judging_starts_at'],
            'voting_starts_at' => ['required', 'date'],
            'voting_ends_at' => ['required', 'date', 'after_or_equal:voting_starts_at'],
            'results_announce_on' => ['required', 'date'],
            'ceremony_on' => ['required', 'date'],
            'project_period_starts_on' => ['required', 'date'],
            'project_period_ends_on' => ['required', 'date', 'after_or_equal:project_period_starts_on'],
            'judges_per_entry' => ['required', 'integer', 'min:1', 'max:255'],
            'band_gold_min' => ['required', 'integer', 'min:1', 'max:255'],
            'band_silver_min' => ['required', 'integer', 'min:1', 'max:255'],
            'band_bronze_min' => ['required', 'integer', 'min:1', 'max:255'],
            'band_honorable_min' => ['required', 'integer', 'min:1', 'max:255'],
        ])->validate();

        $bands = [
            $validated['band_gold_min'],
            $validated['band_silver_min'],
            $validated['band_bronze_min'],
            $validated['band_honorable_min'],
        ];
        $maximumScore = $validated['judges_per_entry']
            * self::CRITERIA_PER_CATEGORY
            * self::MAX_POINTS_PER_CRITERION;

        if (! ($bands[0] > $bands[1] && $bands[1] > $bands[2] && $bands[2] > $bands[3])) {
            throw ValidationException::withMessages([
                'band_gold_min' => 'As faixas devem ser estritamente decrescentes: ouro, prata, bronze e menção honrosa.',
            ]);
        }

        if (max($bands) > $maximumScore) {
            throw ValidationException::withMessages([
                'band_gold_min' => "As faixas não podem ultrapassar a pontuação máxima configurada de {$maximumScore} pontos.",
            ]);
        }

        $edition->fill($validated);
        $edition->save();

        return $edition->refresh();
    }
}
