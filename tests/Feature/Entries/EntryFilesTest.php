<?php

use App\Actions\Entries\AddEntryImage;
use App\Actions\Entries\RemoveEntryImage;
use App\Actions\Entries\RemoveSupplementaryPdf;
use App\Actions\Entries\SetSupplementaryPdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => Storage::fake('local'));

function expectFileError(Closure $call, string $field): void
{
    try {
        $call();
        test()->fail("Esperava erro em {$field}.");
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($field);
    }
}

describe('imagens (§4, P6)', function () {
    it('aceita JPG, PNG e WebP até 5 MB no disco privado, com nome de arquivo gerado', function (string $name) {
        $entry = entryFor(participant(), regularCategory(openEdition()));

        $media = app(AddEntryImage::class)->handle($entry, fakeImage($name, 5 * 1024));

        expect($media->disk)->toBe('local')
            ->and($media->file_name)->not->toContain(pathinfo($name, PATHINFO_FILENAME))
            ->and($entry->imageCount())->toBe(1);
        Storage::disk('local')->assertExists($media->getPathRelativeToRoot());
    })->with(['foto.jpg', 'foto.png', 'foto.webp']);

    it('recusa arquivo acima de 5 MB, formato não permitido e arquivo ausente', function (?UploadedFile $file) {
        $entry = entryFor(participant(), regularCategory(openEdition()));

        expectFileError(fn () => app(AddEntryImage::class)->handle($entry, $file), 'image');
    })->with([
        'acima de 5 MB' => fn () => fakeImage('grande.jpg', 5 * 1024 + 1),
        'GIF' => fn () => fakeImage('anim.gif'),
        'PDF disfarçado' => fn () => UploadedFile::fake()->create('foto.jpg', 100, 'application/pdf'),
        'ausente' => fn () => null,
    ]);

    it('limita a 10 imagens por inscrição', function () {
        $entry = entryFor(participant(), regularCategory(openEdition()));
        foreach (range(1, 10) as $i) {
            app(AddEntryImage::class)->handle($entry, fakeImage("f{$i}.jpg", 10));
        }

        expectFileError(fn () => app(AddEntryImage::class)->handle($entry->fresh(), fakeImage('f11.jpg', 10)), 'image');
    });

    it('inscrição enviada não fica sem imagem', function () {
        $entry = entryFor(participant(), regularCategory(openEdition()), 'enviada');
        $only = app(AddEntryImage::class)->handle($entry, fakeImage());

        expectFileError(fn () => app(RemoveEntryImage::class)->handle($entry->fresh(), $only), 'image');

        $second = app(AddEntryImage::class)->handle($entry->fresh(), fakeImage('outra.jpg'));
        app(RemoveEntryImage::class)->handle($entry->fresh(), $only);
        expect($entry->fresh()->getMedia('images')->pluck('id')->all())->toBe([$second->id]);
    });

    it('não remove imagem de outra inscrição', function () {
        $edition = openEdition();
        $mine = entryFor(participant(), regularCategory($edition));
        $other = entryFor(participant(), regularCategory($edition));
        $foreign = app(AddEntryImage::class)->handle($other, fakeImage());

        expectFileError(fn () => app(RemoveEntryImage::class)->handle($mine, $foreign), 'image');
        expect($other->fresh()->getMedia('images'))->toHaveCount(1);
    });

    it('não altera arquivos de inscrição aprovada (P70)', function () {
        $entry = entryFor(participant(), regularCategory(openEdition()), 'aprovada');

        expectFileError(fn () => app(AddEntryImage::class)->handle($entry, fakeImage()), 'entry');
        expectFileError(fn () => app(SetSupplementaryPdf::class)->handle($entry, fakePdf()), 'entry');
    });
});

describe('PDF complementar (P7, P45)', function () {
    it('é opcional, único (substitui) e removível', function () {
        $entry = entryFor(participant(), regularCategory(openEdition()));

        app(SetSupplementaryPdf::class)->handle($entry, fakePdf());
        $second = app(SetSupplementaryPdf::class)->handle($entry->fresh(), fakePdf(10 * 1024));

        expect($entry->fresh()->getMedia('supplementary_pdf')->pluck('id')->all())->toBe([$second->id]);

        app(RemoveSupplementaryPdf::class)->handle($entry->fresh());
        expect($entry->fresh()->getMedia('supplementary_pdf'))->toHaveCount(0);
    });

    it('recusa acima de 10 MB e o que não é PDF', function (UploadedFile $file) {
        $entry = entryFor(participant(), regularCategory(openEdition()));

        expectFileError(fn () => app(SetSupplementaryPdf::class)->handle($entry, $file), 'pdf');
    })->with([
        'acima de 10 MB' => fn () => fakePdf(10 * 1024 + 1),
        'imagem' => fn () => fakeImage(),
    ]);
});
