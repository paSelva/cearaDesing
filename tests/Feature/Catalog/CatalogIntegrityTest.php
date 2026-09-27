<?php

use App\Models\Category;
use App\Models\Criterion;
use App\Models\Edition;
use Database\Seeders\EditionSeeder;
use Illuminate\Database\QueryException;

it('carrega as relações edição → categorias → critérios', function () {
    $this->seed(EditionSeeder::class);

    $edition = Edition::query()->with('categories.criteria')->sole();

    expect($edition->categories)->toHaveCount(9)
        ->and($edition->categories->sum(fn (Category $category): int => $category->criteria->count()))->toBe(36);
});

it('protege unicidade de código, slug e ordem dos critérios no banco', function () {
    $this->seed(EditionSeeder::class);
    $edition = Edition::query()->sole();
    $category = Category::query()->where('code', '3.1')->firstOrFail();

    expect(fn () => Category::query()->create([
        'edition_id' => $edition->id,
        'code' => '3.1',
        'name' => 'Duplicada',
        'slug' => 'duplicada',
        'description' => 'Registro sintético para teste.',
        'sort' => 10,
    ]))->toThrow(QueryException::class);

    expect(fn () => Category::query()->create([
        'edition_id' => $edition->id,
        'code' => '9.1',
        'name' => 'Slug duplicado',
        'slug' => 'design-grafico-comunicacao-visual',
        'description' => 'Registro sintético para teste.',
        'sort' => 10,
    ]))->toThrow(QueryException::class);

    expect(fn () => Criterion::query()->create([
        'category_id' => $category->id,
        'name' => 'Ordem repetida',
        'sort' => 1,
    ]))->toThrow(QueryException::class);
});

it('impede excluir edição e categoria que ainda possuem catálogo relacionado', function () {
    $this->seed(EditionSeeder::class);

    expect(fn () => Edition::query()->sole()->delete())->toThrow(QueryException::class)
        ->and(fn () => Category::query()->where('code', '3.1')->firstOrFail()->delete())->toThrow(QueryException::class);
});
