<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Edition;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Category> */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'edition_id' => Edition::factory(),
            'code' => fake()->unique()->numerify('9.#'),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'is_student_only' => false,
            'sort' => fake()->unique()->numberBetween(1, 255),
        ];
    }
}
