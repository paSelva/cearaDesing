<?php

use App\Actions\Entries\AddEntryImage;
use App\Actions\Entries\SetSupplementaryPdf;
use App\Actions\Entries\UpdateEntry;
use App\Actions\Triage\ApproveEntry;
use App\Actions\Triage\DisqualifyEntry;
use App\Enums\EntryStatus;
use App\Enums\Role;
use App\Filament\Resources\EntryResource\Pages\ListEntries;
use App\Filament\Resources\EntryResource\Pages\ViewEntry;
use App\Models\Entry;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(fn () => Storage::fake('local'));

function expectTriageError(Closure $call, string $field = 'entry'): void
{
    try {
        $call();
        test()->fail("Esperava erro em {$field}.");
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($field);
    }
}

describe('ApproveEntry', function () {
    it('aprova enviada, registra quem e quando, e grava no activity log', function () {
        $admin = User::factory()->admin()->create();
        $entry = entryFor(participant(), regularCategory(openEdition()), 'enviada');

        app(ApproveEntry::class)->handle($entry, $admin);

        $entry->refresh();
        $log = Activity::query()->where('log_name', 'triagem')->sole();
        expect($entry->status)->toBe(EntryStatus::Aprovada)
            ->and($entry->approved_by)->toBe($admin->id)
            ->and($entry->approved_at)->not->toBeNull()
            ->and($log->causer_id)->toBe($admin->id)
            ->and($log->subject_id)->toBe($entry->id)
            ->and($log->event)->toBe('aprovada');
    });

    it('só aprova a partir de enviada', function (string $state) {
        $entry = entryFor(participant(), regularCategory(openEdition()), $state);

        expectTriageError(fn () => app(ApproveEntry::class)->handle($entry, User::factory()->admin()->create()));
        expect(Activity::count())->toBe(0);
    })->with(['rascunho', 'aprovada', 'desclassificada']);

    it('pode aprovar durante o período de inscrições (P70)', function () {
        $entry = entryFor(participant(), regularCategory(openEdition()), 'enviada');

        app(ApproveEntry::class)->handle($entry, User::factory()->admin()->create());

        expect($entry->fresh()->status)->toBe(EntryStatus::Aprovada);
    });

    it('recusa quem não é admin', function (Role $role) {
        $entry = entryFor(participant(), regularCategory(openEdition()), 'enviada');

        expectTriageError(fn () => app(ApproveEntry::class)->handle($entry, User::factory()->create(['role' => $role])));
    })->with([Role::Jurado, Role::Participante]);

    it('dois admins aprovando ao mesmo tempo: o segundo recebe erro', function () {
        $entry = entryFor(participant(), regularCategory(openEdition()), 'enviada');
        $stale = Entry::find($entry->id);

        app(ApproveEntry::class)->handle($entry, User::factory()->admin()->create());

        expectTriageError(fn () => app(ApproveEntry::class)->handle($stale, User::factory()->admin()->create()));
        expect(Activity::count())->toBe(1);
    });
});

describe('DisqualifyEntry (§8)', function () {
    it('desclassifica enviada ou aprovada com motivo obrigatório', function (string $state) {
        $admin = User::factory()->admin()->create();
        $entry = entryFor(participant(), regularCategory(openEdition()), $state);

        app(DisqualifyEntry::class)->handle($entry, $admin, ['reason' => 'Projeto fora do período do §2.']);

        $entry->refresh();
        $log = Activity::query()->where('log_name', 'triagem')->sole();
        expect($entry->status)->toBe(EntryStatus::Desclassificada)
            ->and($entry->disqualified_by)->toBe($admin->id)
            ->and($entry->disqualification_reason)->toBe('Projeto fora do período do §2.')
            ->and($log->properties['de'])->toBe($state)
            ->and($log->properties['motivo'])->toBe('Projeto fora do período do §2.');
    })->with(['enviada', 'aprovada']);

    it('exige motivo', function (array $input) {
        $entry = entryFor(participant(), regularCategory(openEdition()), 'enviada');

        expectTriageError(fn () => app(DisqualifyEntry::class)->handle($entry, User::factory()->admin()->create(), $input), 'reason');
        expect($entry->fresh()->status)->toBe(EntryStatus::Enviada);
    })->with([
        'sem motivo' => [[]],
        'motivo em branco' => [['reason' => '   ']],
    ]);

    it('não desclassifica rascunho nem desclassificada', function (string $state) {
        $entry = entryFor(participant(), regularCategory(openEdition()), $state);

        expectTriageError(fn () => app(DisqualifyEntry::class)->handle($entry, User::factory()->admin()->create(), ['reason' => 'x']));
    })->with(['rascunho', 'desclassificada']);
});

describe('aprovação trava o participante (P70)', function () {
    it('depois de aprovada, o participante não edita textos nem arquivos', function () {
        $owner = participant();
        $entry = entryFor($owner, regularCategory(openEdition()), 'enviada');
        app(AddEntryImage::class)->handle($entry, fakeImage());
        app(ApproveEntry::class)->handle($entry, User::factory()->admin()->create());
        $entry->refresh();

        expectTriageError(fn () => app(UpdateEntry::class)->handle($entry, completeTexts(['consents' => allConsents()]), '203.0.113.5'));
        expectTriageError(fn () => app(AddEntryImage::class)->handle($entry, fakeImage()));
        expectTriageError(fn () => app(SetSupplementaryPdf::class)->handle($entry, fakePdf()));
    });

    it('corrida: salvamento com instância antiga não passa por cima da aprovação', function () {
        $entry = entryFor(participant(), regularCategory(openEdition()), 'enviada');
        $stale = Entry::find($entry->id);

        // Aprovação acontece depois que a requisição do participante carregou a inscrição.
        Entry::query()->whereKey($entry->id)->update(['status' => EntryStatus::Aprovada->value, 'approved_at' => now()]);

        expectTriageError(fn () => app(UpdateEntry::class)->handle($stale, completeTexts(['title' => 'Mudou depois', 'consents' => allConsents()]), '203.0.113.5'));
        expectTriageError(fn () => app(AddEntryImage::class)->handle($stale, fakeImage()));
        expect($entry->fresh()->title)->not->toBe('Mudou depois')
            ->and($entry->fresh()->imageCount())->toBe(0);
    });
});

describe('painel /admin/inscricoes', function () {
    it('admin lista inscrições enviadas; rascunhos ficam ocultos por padrão', function () {
        $edition = openEdition();
        $sent = entryFor(participant(), regularCategory($edition), 'enviada');
        $draft = entryFor(participant(), regularCategory($edition));

        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(ListEntries::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$sent])
            ->assertCanNotSeeTableRecords([$draft]);
    });

    it('admin vê a inscrição com participante, textos e aceites', function () {
        $owner = participant();
        $entry = entryFor($owner, regularCategory(openEdition()), 'enviada');
        $entry->consents()->create(['consent' => 'veracidade', 'accepted_at' => now(), 'ip' => '203.0.113.77']);

        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(ViewEntry::class, ['record' => $entry->getRouteKey()])
            ->assertOk()
            ->assertSee($entry->title)
            ->assertSee($owner->email)
            ->assertSee('203.0.113.77');
    });

    it('admin aprova e desclassifica pelas ações da tabela', function () {
        $edition = openEdition();
        $toApprove = entryFor(participant(), regularCategory($edition), 'enviada');
        $toDisqualify = entryFor(participant(), regularCategory($edition), 'enviada');

        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(ListEntries::class)
            ->callTableAction('approve', $toApprove)
            ->callTableAction('disqualify', $toDisqualify, data: ['reason' => 'Autoria não confirmada.']);

        expect($toApprove->fresh()->status)->toBe(EntryStatus::Aprovada)
            ->and($toDisqualify->fresh()->status)->toBe(EntryStatus::Desclassificada)
            ->and($toDisqualify->fresh()->disqualification_reason)->toBe('Autoria não confirmada.');
    });

    it('ação de desclassificar exige motivo no formulário', function () {
        $entry = entryFor(participant(), regularCategory(openEdition()), 'enviada');

        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(ListEntries::class)
            ->callTableAction('disqualify', $entry, data: ['reason' => ''])
            ->assertHasTableActionErrors(['reason' => 'required']);

        expect($entry->fresh()->status)->toBe(EntryStatus::Enviada);
    });

    it('páginas do painel renderizam por HTTP, com imagem, PDF e link', function () {
        $entry = entryFor(participant(), regularCategory(openEdition()), 'enviada');
        $image = app(AddEntryImage::class)->handle($entry, fakeImage());
        $pdf = app(SetSupplementaryPdf::class)->handle($entry, fakePdf());
        $entry->links()->create(['url' => 'https://example.test/video', 'label' => 'Vídeo', 'sort' => 1]);

        $this->actingAs(User::factory()->admin()->create());
        $this->get('/admin/inscricoes')->assertOk()->assertSee($entry->title);
        $this->get("/admin/inscricoes/{$entry->id}")
            ->assertOk()
            ->assertSee("/organizacao/inscricoes/{$entry->id}/arquivos/{$image->id}", false)
            ->assertSee("/organizacao/inscricoes/{$entry->id}/arquivos/{$pdf->id}", false)
            ->assertSee('https://example.test/video');
    });

    it('outros papéis não acessam', function (Role $role) {
        $this->actingAs(User::factory()->create(['role' => $role]))
            ->get('/admin/inscricoes')
            ->assertForbidden();
    })->with([Role::Jurado, Role::Participante, Role::Eleitor]);

    it('admin não ganha edição de conteúdo (update continua só do dono)', function () {
        $admin = User::factory()->admin()->create();
        $entry = entryFor(participant(), regularCategory(openEdition()), 'enviada');

        expect($admin->can('view', $entry))->toBeTrue()
            ->and($admin->can('update', $entry))->toBeFalse();

        $this->actingAs($admin);
        $this->put("/participante/inscricoes/{$entry->id}", completeTexts(['consents' => allConsents()]))->assertForbidden();
        $this->post("/participante/inscricoes/{$entry->id}/imagens", ['image' => fakeImage()])->assertForbidden();
    });
});

describe('arquivos para a triagem', function () {
    it('admin abre arquivo da inscrição; arquivo de outra inscrição dá 404', function () {
        $edition = openEdition();
        $entry = entryFor(participant(), regularCategory($edition), 'enviada');
        $other = entryFor(participant(), regularCategory($edition), 'enviada');
        $image = app(AddEntryImage::class)->handle($entry, fakeImage());
        $foreign = app(AddEntryImage::class)->handle($other, fakeImage());

        $this->actingAs(User::factory()->admin()->create());
        $this->get("/organizacao/inscricoes/{$entry->id}/arquivos/{$image->id}")
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->get("/organizacao/inscricoes/{$entry->id}/arquivos/{$foreign->id}")->assertNotFound();
    });

    it('jurado, participante (até o dono) e eleitor não usam a rota da organização', function () {
        $owner = participant();
        $entry = entryFor($owner, regularCategory(openEdition()), 'enviada');
        $image = app(AddEntryImage::class)->handle($entry, fakeImage());

        foreach ([User::factory()->jurado()->create(), $owner, User::factory()->eleitor()->create()] as $user) {
            $this->actingAs($user)->get("/organizacao/inscricoes/{$entry->id}/arquivos/{$image->id}")->assertForbidden();
        }
    });

    it('participante continua vendo os próprios arquivos depois da aprovação', function () {
        $owner = participant();
        $entry = entryFor($owner, regularCategory(openEdition()), 'enviada');
        $image = app(AddEntryImage::class)->handle($entry, fakeImage());
        app(ApproveEntry::class)->handle($entry, User::factory()->admin()->create());

        $this->actingAs($owner)->get("/participante/inscricoes/{$entry->id}/arquivos/{$image->id}")->assertOk();
        $this->actingAs($owner)->get("/participante/inscricoes/{$entry->id}")->assertOk()->assertSee('Aprovada na triagem');
    });
});
