<?php

namespace Database\Factories;

use App\Enums\EntryStatus;
use App\Models\Category;
use App\Models\Entry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Entry> */
class EntryFactory extends Factory
{
    protected $model = Entry::class;

    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'edition_id' => fn (array $attributes) => Category::query()->findOrFail($attributes['category_id'])->edition_id,
            'user_id' => User::factory()->participante(),
            'status' => EntryStatus::Rascunho,
        ];
    }

    /** Todos os campos do §4 preenchidos (ainda sem imagem: anexar no teste). */
    public function complete(): static
    {
        return $this->state(fn () => [
            'title' => 'Projeto sintético',
            'authorship_credits' => 'Autoria sintética',
            'summary' => 'Apresentação sintética.',
            'context_solution' => 'Contexto e solução sintéticos.',
            'results_differentials' => 'Resultados sintéticos.',
            'ai_used' => false,
        ]);
    }

    public function enviada(): static
    {
        return $this->complete()->state(fn () => [
            'status' => EntryStatus::Enviada,
            'submitted_at' => now(),
        ]);
    }

    public function aprovada(): static
    {
        return $this->enviada()->state(fn () => [
            'status' => EntryStatus::Aprovada,
            'approved_at' => now(),
        ]);
    }

    public function desclassificada(): static
    {
        return $this->enviada()->state(fn () => [
            'status' => EntryStatus::Desclassificada,
            'disqualified_at' => now(),
        ]);
    }
}
