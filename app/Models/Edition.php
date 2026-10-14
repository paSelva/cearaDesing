<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'registration_starts_at',
    'registration_ends_at',
    'judging_starts_at',
    'judging_ends_at',
    'voting_starts_at',
    'voting_ends_at',
    'results_announce_on',
    'ceremony_on',
    'project_period_starts_on',
    'project_period_ends_on',
    'judges_per_entry',
    'band_gold_min',
    'band_silver_min',
    'band_bronze_min',
    'band_honorable_min',
])]
class Edition extends Model
{
    public const YEAR = 2026;

    use HasFactory;

    protected function casts(): array
    {
        return [
            'registration_starts_at' => 'datetime',
            'registration_ends_at' => 'datetime',
            'judging_starts_at' => 'datetime',
            'judging_ends_at' => 'datetime',
            'voting_starts_at' => 'datetime',
            'voting_ends_at' => 'datetime',
            'results_announce_on' => 'date',
            'ceremony_on' => 'date',
            'project_period_starts_on' => 'date',
            'project_period_ends_on' => 'date',
            'results_published_at' => 'datetime',
        ];
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }
}
