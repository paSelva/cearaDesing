<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['edition_id', 'code', 'name', 'slug', 'description', 'is_student_only', 'sort'])]
class Category extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_student_only' => 'boolean',
        ];
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(Criterion::class)->orderBy('sort');
    }

    /** @return HasMany<Entry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    /**
     * Jurados fixos da categoria (P14).
     *
     * @return BelongsToMany<User, $this>
     */
    public function judges(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'category_judges')->withTimestamps();
    }
}
