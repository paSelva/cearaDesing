<?php

declare(strict_types=1);

namespace App\Actions\Entries;

use App\Models\Entry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** Adiciona 1 imagem por requisição (§4, P6: até 10, JPG/PNG/WebP, 5 MB). */
final class AddEntryImage
{
    public function __construct(private readonly EntryGuard $guard) {}

    public function handle(Entry $entry, mixed $file): Media
    {
        $this->guard->ensureEditable($entry);

        $config = config('premio.entry.images');
        Validator::make(['image' => $file], [
            'image' => ['required', 'file', 'image', 'mimes:'.implode(',', $config['mimes']), 'max:'.$config['max_kb']],
        ], [
            'image.max' => 'Cada imagem pode ter no máximo 5 MB.',
            'image.mimes' => 'Envie imagens em JPG, PNG ou WebP.',
            'image.image' => 'Envie imagens em JPG, PNG ou WebP.',
            'image.uploaded' => 'A imagem não foi recebida. Cada imagem pode ter no máximo 5 MB.',
        ], ['image' => 'imagem'])->validate();

        // Trava a inscrição para dois envios simultâneos não passarem do limite.
        return DB::transaction(function () use ($entry, $file, $config): Media {
            $this->guard->lockEditable($entry);

            if ($entry->imageCount() >= $config['max']) {
                throw ValidationException::withMessages(['image' => "Limite de {$config['max']} imagens por inscrição."]);
            }

            /** @var UploadedFile $file */
            return $entry->addMedia($file)
                ->usingName(Str::limit(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 200, ''))
                ->usingFileName(Str::uuid().'.'.strtolower($file->guessExtension() ?? 'jpg'))
                ->toMediaCollection('images');
        });
    }
}
