<?php

use App\Models\Category;
use App\Models\Criterion;
use App\Models\Edition;
use Database\Seeders\EditionSeeder;

it('semeia uma edição, nove categorias e 36 critérios oficiais', function () {
    $this->seed(EditionSeeder::class);

    expect(Edition::query()->count())->toBe(1)
        ->and(Category::query()->count())->toBe(9)
        ->and(Criterion::query()->count())->toBe(36)
        ->and(Edition::query()->firstOrFail()->results_published_at)->toBeNull()
        ->and(Category::query()->where('is_student_only', true)->pluck('code')->all())->toBe(['3.7']);

    expect(Category::query()->where('code', '3.1')->value('description'))
        ->toContain('Identidades visuais, marcas, embalagens')
        ->toContain('projetos relacionados.');
});

it('repete o seed sem duplicar registros nem sobrescrever parâmetros editados', function () {
    $this->seed(EditionSeeder::class);
    $edition = Edition::query()->firstOrFail();
    $edition->update(['name' => 'Nome configurado pela organização', 'band_gold_min' => 55]);

    $this->seed(EditionSeeder::class);

    expect(Edition::query()->count())->toBe(1)
        ->and(Category::query()->count())->toBe(9)
        ->and(Criterion::query()->count())->toBe(36)
        ->and($edition->fresh()->name)->toBe('Nome configurado pela organização')
        ->and($edition->fresh()->band_gold_min)->toBe(55);
});
