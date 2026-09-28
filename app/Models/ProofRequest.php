<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProofRequestStatus;
use Database\Factories\ProofRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Pedido de comprovação (P12). Todos os campos são definidos pelas Actions;
 * nada vem de formulário por atribuição em massa.
 */
class ProofRequest extends Model implements HasMedia
{
    /** @use HasFactory<ProofRequestFactory> */
    use HasFactory, InteractsWithMedia;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'status' => ProofRequestStatus::class,
            'deadline_at' => 'datetime',
            'responded_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'is_late' => 'boolean',
        ];
    }

    public function registerMediaCollections(): void
    {
        // 1 PDF de até 10 MB (P54), disco privado.
        $this->addMediaCollection('proof')
            ->useDisk('local')
            ->acceptsMimeTypes(['application/pdf'])
            ->singleFile();
    }

    /** @return BelongsTo<Entry, $this> */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
