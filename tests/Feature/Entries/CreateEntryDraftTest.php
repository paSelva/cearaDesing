<?php

use App\Actions\Entries\CreateEntryDraft;
use App\Enums\EntryStatus;
use App\Enums\ParticipantType;
use App\Models\Category;
use App\Models\Edition;
use App\Models\Entry;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

function expectDraftError(Closure $call, string $field = 'category'): void
{
    try {
        $call();
        test()->fail('Esperava ValidationException.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($field);
    }
}

describe('CreateEntryDraft', function () {

    describe('✅ happy path', function () {
        it('cria rascunho na categoria da edição 2026', function () {
            $edition = openEdition();
            $category = regularCategory($edition);
            $user = participant();

            $entry = app(CreateEntryDraft::class)->handle($user, $category);

            expect($entry->status)->toBe(EntryStatus::Rascunho)
                ->and($entry->edition_id)->toBe($edition->id)
                ->and($entry->user_id)->toBe($user->id);
        });

        it('permite o mesmo participante em categorias diferentes (P10)', function () {
            $edition = openEdition();
            $user = participant();

            app(CreateEntryDraft::class)->handle($user, regularCategory($edition));
            app(CreateEntryDraft::class)->handle($user, regularCategory($edition));

            expect(Entry::count())->toBe(2);
        });
    });

    describe('⚠️ edge cases — prazo (§7, P77)', function () {
        it('abre em 28/09 às 00:00:00 e fecha em 28/10 às 23:59:59, horário de Fortaleza', function (string $moment, bool $open) {
            $edition = openEdition();
            $category = regularCategory($edition);
            $this->travelTo(Carbon::parse($moment, 'America/Fortaleza'));

            $call = fn () => app(CreateEntryDraft::class)->handle(participant(), $category);

            if ($open) {
                expect($call()->exists)->toBeTrue();
            } else {
                expectDraftError($call, 'entry');
            }
        })->with([
            'um segundo antes da abertura' => ['2026-09-27 23:59:59', false],
            'abertura' => ['2026-09-28 00:00:00', true],
            'último segundo' => ['2026-10-28 23:59:59', true],
            'um segundo depois' => ['2026-10-29 00:00:00', false],
        ]);
    });

    describe('❌ cenários de erro', function () {
        it('recusa segunda inscrição na mesma categoria (§4)', function () {
            $edition = openEdition();
            $category = regularCategory($edition);
            $user = participant();

            app(CreateEntryDraft::class)->handle($user, $category);

            expectDraftError(fn () => app(CreateEntryDraft::class)->handle($user, $category));
            expect(Entry::count())->toBe(1);
        });

        it('em corrida, o índice único barra e devolve erro de campo (§4)', function () {
            $edition = openEdition();
            $category = regularCategory($edition);
            $user = participant();

            Entry::creating(function (Entry $creating) use ($user, $category) {
                if (! Entry::where('user_id', $user->id)->where('category_id', $category->id)->exists()) {
                    Entry::withoutEvents(fn () => Entry::factory()->for($user)->for($category)
                        ->state(['edition_id' => $category->edition_id])->create());
                }
            });

            expectDraftError(fn () => app(CreateEntryDraft::class)->handle($user, $category));
            expect(Entry::count())->toBe(1);
        });

        it('aplica a regra de estudantes nas duas direções (P9)', function (ParticipantType $type, bool $studentCategory) {
            $edition = openEdition();
            $category = $studentCategory ? studentCategory($edition) : regularCategory($edition);

            expectDraftError(fn () => app(CreateEntryDraft::class)->handle(participant($type), $category));
        })->with([
            'não estudante na 3.7' => [ParticipantType::Profissional, true],
            'estudante fora da 3.7' => [ParticipantType::Estudante, false],
        ]);

        it('estudante inscreve na categoria exclusiva (P9)', function () {
            $edition = openEdition();

            $entry = app(CreateEntryDraft::class)->handle(participant(ParticipantType::Estudante), studentCategory($edition));

            expect($entry->exists)->toBeTrue();
        });

        it('recusa categoria de outra edição', function () {
            $edition = openEdition();
            $old = Edition::factory()->make();
            $old->forceFill(['year' => 2025])->save();
            $category = Category::factory()->for($old)->create();

            expectDraftError(fn () => app(CreateEntryDraft::class)->handle(participant(), $category), 'entry');
        });

        it('recusa quem não é participante', function () {
            $edition = openEdition();

            expectDraftError(fn () => app(CreateEntryDraft::class)->handle(User::factory()->admin()->create(), regularCategory($edition)));
        });
    });

});
