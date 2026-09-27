<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Criterion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Criterion> */
class CriterionFactory extends Factory
{
    protected $model = Criterion::class;

    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'name' => fake()->words(2, true),
            'description' => null,
            'sort' => fake()->unique()->numberBetween(1, 255),
        ];
    }
}
