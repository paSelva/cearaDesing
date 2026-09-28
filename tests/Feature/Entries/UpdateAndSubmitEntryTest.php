<?php

use App\Actions\Entries\AddEntryImage;
use App\Actions\Entries\SubmitEntry;
use App\Actions\Entries\UpdateEntry;
use App\Enums\ConsentType;
use App\Enums\EntryStatus;
use App\Models\EntryConsent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => Storage::fake('local'));

function expectEntryError(Closure $call, string $field): void
{
    try {
        $call();
        test()->fail("Esperava erro em {$field}.");
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($field);
    }
}

describe('UpdateEntry', function () {

    it('salva rascunho parcial, sem exigir todos os campos', function () {
        $entry = entryFor(participant(), regularCategory(openEdition()));

        app(UpdateEntry::class)->handle($entry, ['title' => 'Só o nome'], '203.0.113.5');

        expect($entry->fresh()->title)->toBe('Só o nome')
            ->and($entry->fresh()->summary)->toBeNull()
            ->and(EntryConsent::count())->toBe(0);
    });

    it('grava até 5 links, ignorando linhas vazias (P7, P75)', function () {
        $entry = entryFor(participant(), regularCategory(openEdition()));

        app(UpdateEntry::class)->handle($entry, completeTexts(['links' => [
            ['url' => 'https://example.test/video', 'label' => 'Vídeo'],
            ['url' => '', 'label' => ''],
            ['url' => 'https://example.test/site'],
        ]]), '203.0.113.5');

        expect($entry->links()->pluck('url')->all())->toBe(['https://example.test/video', 'https://example.test/site']);
    });

    it('recusa link sem http(s), mais de 5 links e texto acima de 5.000 caracteres (P75)', function (array $overrides, string $field) {
        $entry = entryFor(participant(), regularCategory(openEdition()));

        expectEntryError(fn () => app(UpdateEntry::class)->handle($entry, completeTexts($overrides), '203.0.113.5'), $field);
    })->with([
        'javascript:' => [['links' => [['url' => 'javascript:alert(1)']]], 'links.0.url'],
        '6 links' => [['links' => array_fill(0, 6, ['url' => 'https://example.test'])], 'links'],
        'texto longo' => [['summary' => str_repeat('a', 5001)], 'summary'],
        'IA sim sem descrição' => [['ai_used' => '1'], 'ai_usage_description'],
    ]);

    it('trata ai_used inteiro 1 como "sim"', function () {
        $entry = entryFor(participant(), regularCategory(openEdition()));

        app(UpdateEntry::class)->handle($entry, completeTexts(['ai_used' => 1, 'ai_usage_description' => 'Imagens geradas com IA.']), '203.0.113.5');

        expect($entry->fresh()->ai_used)->toBeTrue()
            ->and($entry->fresh()->ai_usage_description)->toBe('Imagens geradas com IA.');
    });

    it('limpa a descrição de IA quando a resposta é "não"', function () {
        $entry = entryFor(participant(), regularCategory(openEdition()));

        app(UpdateEntry::class)->handle($entry, completeTexts(['ai_used' => '0', 'ai_usage_description' => 'sobrou']), '203.0.113.5');

        expect($entry->fresh()->ai_used)->toBeFalse()
            ->and($entry->fresh()->ai_usage_description)->toBeNull();
    });

    it('inscrição enviada continua completa e exige os aceites de novo, com novo histórico', function () {
        $entry = entryFor(participant(), regularCategory(openEdition()), 'enviada');

        expectEntryError(fn () => app(UpdateEntry::class)->handle($entry, completeTexts(['summary' => '', 'consents' => allConsents()]), '203.0.113.5'), 'summary');
        expectEntryError(fn () => app(UpdateEntry::class)->handle($entry, completeTexts(), '203.0.113.5'), 'consents');

        app(UpdateEntry::class)->handle($entry, completeTexts(['title' => 'Nome novo', 'consents' => allConsents()]), '203.0.113.9');

        expect($entry->fresh()->title)->toBe('Nome novo')
            ->and(EntryConsent::where('entry_id', $entry->id)->count())->toBe(6)
            ->and(EntryConsent::first()->ip)->toBe('203.0.113.9');
    });

    it('não edita aprovada (P70), desclassificada nem fora do prazo (P8)', function (string $state, ?string $moment) {
        $entry = entryFor(participant(), regularCategory(openEdition()), $state);
        if ($moment) {
            $this->travelTo(Carbon::parse($moment, 'America/Fortaleza'));
        }

        expectEntryError(fn () => app(UpdateEntry::class)->handle($entry, completeTexts(['consents' => allConsents()]), '203.0.113.5'), 'entry');
    })->with([
        'aprovada' => ['aprovada', null],
        'desclassificada' => ['desclassificada', null],
        'enviada após o prazo' => ['enviada', '2026-10-29 00:00:00'],
    ]);
});

describe('SubmitEntry', function () {

    it('envia com campos do §4, 1 imagem e os 6 aceites com data/hora e IP (P11, P53)', function () {
        $entry = entryFor(participant(), regularCategory(openEdition()), 'completo');
        app(AddEntryImage::class)->handle($entry, fakeImage());

        app(SubmitEntry::class)->handle($entry, allConsents(), '198.51.100.7');

        $entry->refresh();
        $consents = EntryConsent::where('entry_id', $entry->id)->get();
        expect($entry->status)->toBe(EntryStatus::Enviada)
            ->and($entry->submitted_at)->not->toBeNull()
            ->and($consents)->toHaveCount(6)
            ->and($consents->pluck('consent')->all())->toEqualCanonicalizing(ConsentType::cases())
            ->and($consents->pluck('ip')->unique()->all())->toBe(['198.51.100.7'])
            ->and($consents->first()->accepted_at)->not->toBeNull();
    });

    it('lista o que falta e não envia incompleta', function () {
        $entry = entryFor(participant(), regularCategory(openEdition()));

        expect(app(SubmitEntry::class)->missingItems($entry))->toContain(
            'Preencha: nome do projeto.',
            'Informe se houve uso relevante de IA generativa (§8).',
            'Envie pelo menos 1 imagem do projeto.',
        );

        expectEntryError(fn () => app(SubmitEntry::class)->handle($entry, allConsents(), '198.51.100.7'), 'entry');
        expect($entry->fresh()->status)->toBe(EntryStatus::Rascunho);
    });

    it('exige os 6 aceites (§8)', function () {
        $entry = entryFor(participant(), regularCategory(openEdition()), 'completo');
        app(AddEntryImage::class)->handle($entry, fakeImage());

        $five = array_slice(allConsents(), 0, 5);
        expectEntryError(fn () => app(SubmitEntry::class)->handle($entry, $five, '198.51.100.7'), 'consents');
        expect(EntryConsent::count())->toBe(0);
    });

    it('não envia fora do prazo nem duas vezes', function () {
        $entry = entryFor(participant(), regularCategory(openEdition()), 'completo');
        app(AddEntryImage::class)->handle($entry, fakeImage());
        app(SubmitEntry::class)->handle($entry, allConsents(), '198.51.100.7');

        expectEntryError(fn () => app(SubmitEntry::class)->handle($entry->fresh(), allConsents(), '198.51.100.7'), 'entry');

        $late = entryFor(participant(), regularCategory($entry->edition), 'completo');
        app(AddEntryImage::class)->handle($late, fakeImage());
        $this->travelTo(Carbon::parse('2026-10-29 00:00:00', 'America/Fortaleza'));
        expectEntryError(fn () => app(SubmitEntry::class)->handle($late, allConsents(), '198.51.100.7'), 'entry');
    });
});
