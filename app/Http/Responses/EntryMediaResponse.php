<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Models\Entry;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Entrega um arquivo privado da inscrição; 404 se o arquivo não pertence a ela. */
final class EntryMediaResponse
{
    public static function make(Entry $entry, Media $media): StreamedResponse
    {
        abort_unless(
            $media->model_type === $entry->getMorphClass() && (int) $media->model_id === $entry->id,
            404,
        );

        return response()->stream(function () use ($media): void {
            $stream = $media->stream();
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => 'inline; filename="'.addcslashes($media->file_name, '"\\').'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
