<?php

use App\Actions\Proofs\RequestProof;
use App\Actions\Proofs\ReviewProof;
use App\Actions\Proofs\SubmitProof;
use App\Enums\EntryStatus;
use App\Enums\ProofRequestStatus;
use App\Filament\Resources\EntryResource\Pages\ViewEntry;
use App\Filament\Resources\EntryResource\RelationManagers\ProofRequestsRelationManager;
use App\Models\ProofRequest;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(fn () => Storage::fake('local'));

function expectProofError(Closure $call, string $field): void
{
    try {
        $call();
        test()->fail("Esperava erro em {$field}.");
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($field);
    }
}

function requestInput(array $overrides = []): array
{
    return array_replace([
        'message' => 'Envie um documento que comprove a conclusão do projeto entre 2022 e 2026 (§2).',
        'deadline_at' => '2026-10-10 18:00:00',
    ], $overrides);
}

describe('RequestProof (P12, P56)', function () {
    it('cria pedido pendente com prazo, sem mudar o status da inscrição (P72)', function (string $state) {
        $admin = User::factory()->admin()->create();
        $entry = entryFor(participant(), regularCategory(openEdition()), $state);

        $proof = app(RequestProof::class)->handle($entry, $admin, requestInput());

        expect($proof->status)->toBe(ProofRequestStatus::Pendente)
            ->and($proof->requested_by)->toBe($admin->id)
            ->and($proof->deadline_at->format('Y-m-d H:i'))->toBe('2026-10-10 18:00')
            ->and($entry->fresh()->status->value)->toBe($state)
            ->and(Activity::where('log_name', 'comprovacao')->sole()->event)->toBe('solicitada');
    })->with(['enviada', 'aprovada']);

    it('não pede comprovação de rascunho nem de desclassificada', function (string $state) {
        $entry = entryFor(participant(), regularCategory(openEdition()), $state);

        expectProofError(fn () => app(RequestProof::class)->handle($entry, User::factory()->admin()->create(), requestInput()), 'entry');
    })->with(['rascunho', 'desclassificada']);

    it('exige mensagem e prazo futuro', function (array $overrides, string $field) {
        $entry = entryFor(participant(), regularCategory(openEdition()), 'enviada');

        expectProofError(fn () => app(RequestProof::class)->handle($entry, User::factory()->admin()->create(), requestInput($overrides)), $field);
    })->with([
        'sem mensagem' => [['message' => ''], 'message'],
        'mensagem em branco' => [['message' => '   '], 'message'],
        'sem prazo' => [['deadline_at' => ''], 'deadline_at'],
        'prazo no passado' => [['deadline_at' => '2026-09-30 12:00:00'], 'deadline_at'],
    ]);

    it('só admin pede', function () {
        $entry = entryFor(participant(), regularCategory(openEdition()), 'enviada');

        expectProofError(fn () => app(RequestProof::class)->handle($entry, User::factory()->jurado()->create(), requestInput()), 'entry');
    });
});

describe('SubmitProof (P54, P79)', function () {
    it('dono envia 1 PDF dentro do prazo', function () {
        $owner = participant();
        $entry = entryFor($owner, regularCategory(openEdition()), 'aprovada');
        $proof = app(RequestProof::class)->handle($entry, User::factory()->admin()->create(), requestInput());

        app(SubmitProof::class)->handle($proof, $owner, fakePdf(10 * 1024));

        $proof->refresh();
        expect($proof->status)->toBe(ProofRequestStatus::Respondida)
            ->and($proof->responded_at)->not->toBeNull()
            ->and($proof->is_late)->toBeFalse()
            ->and($proof->getFirstMedia('proof')->disk)->toBe('local');
    });

    it('aceita depois do prazo e marca como atrasada (P79), mesmo após o fim das inscrições', function () {
        $owner = participant();
        $entry = entryFor($owner, regularCategory(openEdition()), 'enviada');
        $proof = app(RequestProof::class)->handle($entry, User::factory()->admin()->create(), requestInput());

        $this->travelTo(Carbon::parse('2026-11-05 09:00:00', 'America/Fortaleza'));
        app(SubmitProof::class)->handle($proof, $owner, fakePdf());

        expect($proof->fresh()->is_late)->toBeTrue()
            ->and($proof->fresh()->status)->toBe(ProofRequestStatus::Respondida);
    });

    it('recusa arquivo inválido, acima de 10 MB ou ausente', function (mixed $file) {
        $owner = participant();
        $entry = entryFor($owner, regularCategory(openEdition()), 'enviada');
        $proof = app(RequestProof::class)->handle($entry, User::factory()->admin()->create(), requestInput());

        expectProofError(fn () => app(SubmitProof::class)->handle($proof, $owner, $file), 'proof');
        expect($proof->fresh()->status)->toBe(ProofRequestStatus::Pendente);
    })->with([
        'acima de 10 MB' => fn () => fakePdf(10 * 1024 + 1),
        'imagem' => fn () => fakeImage(),
        'ausente' => fn () => null,
    ]);

    it('não envia duas vezes nem em pedido de outro participante', function () {
        $owner = participant();
        $entry = entryFor($owner, regularCategory(openEdition()), 'enviada');
        $proof = app(RequestProof::class)->handle($entry, User::factory()->admin()->create(), requestInput());
        app(SubmitProof::class)->handle($proof, $owner, fakePdf());

        expectProofError(fn () => app(SubmitProof::class)->handle($proof->fresh(), $owner, fakePdf()), 'proof');

        $otherEntry = entryFor(participant(), regularCategory($entry->edition), 'enviada');
        $other = ProofRequest::factory()->for($otherEntry)->create();
        expectProofError(fn () => app(SubmitProof::class)->handle($other, $owner, fakePdf()), 'proof');
    });
});

describe('ReviewProof (P56)', function () {
    it('admin aceita ou recusa comprovação enviada, sem mudar a inscrição', function (ProofRequestStatus $decision) {
        $owner = participant();
        $admin = User::factory()->admin()->create();
        $entry = entryFor($owner, regularCategory(openEdition()), 'enviada');
        $proof = app(RequestProof::class)->handle($entry, $admin, requestInput());
        app(SubmitProof::class)->handle($proof, $owner, fakePdf());

        app(ReviewProof::class)->handle($proof, $admin, $decision);

        $proof->refresh();
        expect($proof->status)->toBe($decision)
            ->and($proof->reviewed_by)->toBe($admin->id)
            ->and($entry->fresh()->status)->toBe(EntryStatus::Enviada)
            ->and(Activity::where('log_name', 'comprovacao')->where('event', $decision->value)->count())->toBe(1);
    })->with([ProofRequestStatus::Aceita, ProofRequestStatus::Recusada]);

    it('não analisa pedido pendente nem já analisado', function () {
        $admin = User::factory()->admin()->create();
        $proof = ProofRequest::factory()->create();

        expectProofError(fn () => app(ReviewProof::class)->handle($proof, $admin, ProofRequestStatus::Aceita), 'proof');

        $proof->forceFill(['status' => ProofRequestStatus::Aceita])->save();
        expectProofError(fn () => app(ReviewProof::class)->handle($proof, $admin, ProofRequestStatus::Recusada), 'proof');
    });

    it('decisão precisa ser aceita ou recusada, e só admin decide', function () {
        $proof = ProofRequest::factory()->create();
        $proof->forceFill(['status' => ProofRequestStatus::Respondida])->save();

        expectProofError(fn () => app(ReviewProof::class)->handle($proof, User::factory()->admin()->create(), ProofRequestStatus::Pendente), 'proof');
        expectProofError(fn () => app(ReviewProof::class)->handle($proof, User::factory()->jurado()->create(), ProofRequestStatus::Aceita), 'proof');
    });
});

describe('pelo site e pelo painel', function () {
    it('participante vê o pedido no painel e na inscrição e envia o PDF', function () {
        $owner = participant();
        $entry = entryFor($owner, regularCategory(openEdition()), 'aprovada');
        $proof = app(RequestProof::class)->handle($entry, User::factory()->admin()->create(), requestInput());

        $this->actingAs($owner);
        $this->get('/participante')->assertSee('A organização pediu uma comprovação');
        $this->get("/participante/inscricoes/{$entry->id}")
            ->assertSee('Comprovações pedidas pela organização')
            ->assertSee('Envie um documento que comprove');

        $this->post("/participante/comprovacoes/{$proof->id}", ['proof' => fakePdf()])->assertSessionHasNoErrors();
        expect($proof->fresh()->status)->toBe(ProofRequestStatus::Respondida);

        $this->get("/participante/comprovacoes/{$proof->id}/arquivo")->assertOk()->assertHeader('Content-Type', 'application/pdf');
    });

    it('outro participante não vê nem envia; admin abre pela rota da organização', function () {
        $owner = participant();
        $entry = entryFor($owner, regularCategory(openEdition()), 'enviada');
        $admin = User::factory()->admin()->create();
        $proof = app(RequestProof::class)->handle($entry, $admin, requestInput());
        app(SubmitProof::class)->handle($proof, $owner, fakePdf());

        $this->actingAs(participant());
        $this->get("/participante/comprovacoes/{$proof->id}/arquivo")->assertForbidden();
        $this->post("/participante/comprovacoes/{$proof->id}", ['proof' => fakePdf()])->assertForbidden();

        $this->actingAs($admin)->get("/organizacao/comprovacoes/{$proof->id}/arquivo")->assertOk();
        foreach ([$owner, User::factory()->jurado()->create()] as $user) {
            $this->actingAs($user)->get("/organizacao/comprovacoes/{$proof->id}/arquivo")->assertForbidden();
        }
    });

    it('arquivo inexistente dá 404', function () {
        $owner = participant();
        $proof = app(RequestProof::class)->handle(entryFor($owner, regularCategory(openEdition()), 'enviada'), User::factory()->admin()->create(), requestInput());

        $this->actingAs($owner)->get("/participante/comprovacoes/{$proof->id}/arquivo")->assertNotFound();
    });

    it('admin pede, e depois aceita, pelo painel', function () {
        $owner = participant();
        $entry = entryFor($owner, regularCategory(openEdition()), 'enviada');
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(ProofRequestsRelationManager::class, ['ownerRecord' => $entry, 'pageClass' => ViewEntry::class])
            ->assertOk()
            ->callTableAction('requestProof', data: ['message' => 'Comprove a autoria.', 'deadline_at' => '2026-10-15 12:00'])
            ->assertHasNoTableActionErrors();

        $proof = ProofRequest::sole();
        expect($proof->message)->toBe('Comprove a autoria.');

        app(SubmitProof::class)->handle($proof, $owner, fakePdf());

        Livewire::test(ProofRequestsRelationManager::class, ['ownerRecord' => $entry->fresh(), 'pageClass' => ViewEntry::class])
            ->callTableAction('accept', $proof->fresh());

        expect($proof->fresh()->status)->toBe(ProofRequestStatus::Aceita);
    });

    it('página da inscrição no painel renderiza com a seção de comprovações', function () {
        $entry = entryFor(participant(), regularCategory(openEdition()), 'enviada');
        app(RequestProof::class)->handle($entry, User::factory()->admin()->create(), requestInput());

        $this->actingAs(User::factory()->admin()->create())
            ->get("/admin/inscricoes/{$entry->id}")
            ->assertOk()
            ->assertSeeLivewire(ProofRequestsRelationManager::class);
    });
});
