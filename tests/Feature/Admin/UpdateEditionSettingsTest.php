<?php

use App\Actions\Admin\UpdateEditionSettings;
use App\Models\Edition;
use Illuminate\Validation\ValidationException;

function validEditionSettings(): array
{
    return [
        'name' => '1º Prêmio Ceará Tem Design 2026',
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

it('persiste parâmetros válidos e ignora atributos protegidos', function () {
    $edition = Edition::factory()->create();
    $settings = validEditionSettings();
    $settings['name'] = 'Nome atualizado pela organização';
    $settings['year'] = 2030;
    $settings['results_published_at'] = '2026-11-21 09:00:00';

    $updated = app(UpdateEditionSettings::class)->handle($edition, $settings);

    expect($updated->fresh()->name)->toBe('Nome atualizado pela organização')
        ->and($updated->fresh()->year)->toBe(Edition::YEAR)
        ->and($updated->fresh()->results_published_at)->toBeNull();
});

it('rejeita períodos invertidos e quantidade de jurados não positiva', function (string $field, mixed $value) {
    $settings = validEditionSettings();
    $settings[$field] = $value;

    expect(fn () => app(UpdateEditionSettings::class)->handle(Edition::factory()->create(), $settings))
        ->toThrow(ValidationException::class);
})->with([
    'inscrição encerra antes de começar' => ['registration_ends_at', '2026-09-27 23:59:59'],
    'avaliação termina antes de começar' => ['judging_ends_at', '2026-10-28 23:59:59'],
    'votação termina antes de começar' => ['voting_ends_at', '2026-10-28 23:59:59'],
    'elegibilidade termina antes de começar' => ['project_period_ends_on', '2021-12-31'],
    'zero jurados' => ['judges_per_entry', 0],
]);

it('rejeita faixas fora de ordem ou acima da pontuação máxima configurada', function (array $changes) {
    $settings = array_replace(validEditionSettings(), $changes);

    expect(fn () => app(UpdateEditionSettings::class)->handle(Edition::factory()->create(), $settings))
        ->toThrow(ValidationException::class);
})->with([
    'ordem incorreta' => [['band_gold_min' => 48]],
    'ouro acima do máximo de 60' => [['band_gold_min' => 61]],
    'limite repetido' => [['band_silver_min' => 54]],
]);

it('aceita e persiste faixas ordenadas dentro da pontuação máxima', function () {
    $edition = Edition::factory()->create();
    $settings = array_replace(validEditionSettings(), [
        'name' => 'Configuração válida',
        'band_gold_min' => 56,
        'band_silver_min' => 50,
        'band_bronze_min' => 44,
        'band_honorable_min' => 38,
    ]);

    app(UpdateEditionSettings::class)->handle($edition, $settings);

    expect($edition->fresh()->name)->toBe('Configuração válida')
        ->and($edition->fresh()->band_gold_min)->toBe(56);
});
