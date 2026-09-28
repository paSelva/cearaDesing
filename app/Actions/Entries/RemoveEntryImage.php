<?php

declare(strict_types=1);

namespace App\Actions\Entries;

use App\Enums\EntryStatus;
use App\Models\Entry;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class RemoveEntryImage
{
    public function __construct(private readonly EntryGuard $guard) {}

    public function handle(Entry $entry, Media $media): void
    {
        $this->guard->ensureEditable($entry);

        if ($media->model_type !== $entry->getMorphClass() || (int) $media->model_id !== $entry->id || $media->collection_name !== 'images') {
            throw ValidationException::withMessages(['image' => 'Imagem não encontrada nesta inscrição.']);
        }

        // Inscrição enviada precisa continuar completa (§4).
        $min = (int) config('premio.entry.images.min');
        if ($entry->status === EntryStatus::Enviada && $entry->imageCount() <= $min) {
            throw ValidationException::withMessages(['image' => 'A inscrição enviada precisa manter pelo menos 1 imagem. Envie outra antes de remover esta.']);
        }

        $media->delete();
    }
}
