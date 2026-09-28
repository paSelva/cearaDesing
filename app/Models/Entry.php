<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Distinction;
use App\Enums\EntryStatus;
use Database\Factories\EntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Inscrição de projeto (§4, docs/04 §4). Status, datas de envio/triagem e resultado
 * nunca vêm do formulário: são definidos pelas Actions.
 */
#[Fillable([
    'title',
    'authorship_credits',
    'summary',
    'context_solution',
    'results_differentials',
    'ai_used',
    'ai_usage_description',
])]
class Entry extends Model implements HasMedia
{
    /** @use HasFactory<EntryFactory> */
    use HasFactory, InteractsWithMedia;

    public const TEXT_FIELDS = [
        'title',
        'authorship_credits',
        'summary',
        'context_solution',
        'results_differentials',
    ];

    protected function casts(): array
    {
        return [
            'status' => EntryStatus::class,
            'distinction' => Distinction::class,
            'final_score' => 'integer',
            'ai_used' => 'boolean',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'disqualified_at' => 'datetime',
        ];
    }

    public function registerMediaCollections(): void
    {
        // Disco privado: as imagens só ficam públicas na galeria, a partir de 29/10 e só aprovadas (P55).
        $this->addMediaCollection('images')
            ->useDisk('local')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);

        $this->addMediaCollection('supplementary_pdf')
            ->useDisk('local')
            ->acceptsMimeTypes(['application/pdf'])
            ->singleFile();
    }

    /** Contagem direto no banco (a relação `media` carregada pode estar desatualizada). */
    public function imageCount(): int
    {
        return $this->media()->where('collection_name', 'images')->count();
    }

    /** @return BelongsTo<Edition, $this> */
    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<EntryLink, $this> */
    public function links(): HasMany
    {
        return $this->hasMany(EntryLink::class)->orderBy('sort');
    }

    /** @return HasMany<JudgeAssignment, $this> */
    public function judgeAssignments(): HasMany
    {
        return $this->hasMany(JudgeAssignment::class);
    }

    /** @return HasMany<ProofRequest, $this> */
    public function proofRequests(): HasMany
    {
        return $this->hasMany(ProofRequest::class)->latest();
    }

    /** @return HasMany<EntryConsent, $this> */
    public function consents(): HasMany
    {
        return $this->hasMany(EntryConsent::class);
    }
}
