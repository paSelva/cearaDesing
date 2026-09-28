<?php

use App\Actions\Jury\AssignJudgesToCategory;
use App\Actions\Jury\AssignJudgesToEntry;
use App\Actions\Jury\ReplaceJudgeOnEntry;
use App\Actions\Jury\SetCategoryJudges;
use App\Enums\AssignmentStatus;
use App\Enums\EntryStatus;
use App\Filament\Resources\CategoryResource\Pages\ListCategories;
use App\Filament\Resources\EntryResource\Pages\ListEntries;
use App\Filament\Resources\EntryResource\Pages\ViewEntry;
use App\Filament\Resources\EntryResource\RelationManagers\JudgeAssignmentsRelationManager;
use App\Models\Edition;
use App\Models\Entry;
use App\Models\JudgeAssignment;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

function expectJuryError(Closure $call, string $field = 'judges'): void
{
    try {
        $call();
        test()->fail("Esperava erro em {$field}.");
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($field);
    }
}

/** Categoria com 3 jurados fixos. */
function categoryWithJudges(?Edition $edition = null, int $judges = 3): array
{
    $category = regularCategory($edition ?? openEdition());
    $ids = User::factory()->jurado()->count($judges)->create()->pluck('id')->all();
    $category->judges()->sync($ids);

    return [$category, $ids];
}

describe('SetCategoryJudges (P14)', function () {
    it('define até judges_per_entry jurados e registra no activity log', function () {
        $admin = User::factory()->admin()->create();
        $category = regularCategory(openEdition());
        $ids = User::factory()->jurado()->count(3)->create()->pluck('id')->all();

        app(SetCategoryJudges::class)->handle($category, $ids, $admin);

        expect($category->judges()->pluck('users.id')->sort()->values()->all())->toBe(collect($ids)->sort()->values()->all())
            ->and(Activity::where('log_name', 'juri')->where('event', 'jurados_da_categoria')->count())->toBe(1);
    });

    it('recusa mais que judges_per_entry, quem não é jurado e quem não é admin', function () {
        $admin = User::factory()->admin()->create();
        $category = regularCategory(openEdition());

        expectJuryError(fn () => app(SetCategoryJudges::class)->handle($category, User::factory()->jurado()->count(4)->create()->pluck('id')->all(), $admin));
        expectJuryError(fn () => app(SetCategoryJudges::class)->handle($category, [User::factory()->participante()->create()->id], $admin));
        expectJuryError(fn () => app(SetCategoryJudges::class)->handle($category, [User::factory()->jurado()->create()->id], User::factory()->jurado()->create()));
    });

    it('o limite acompanha a configuração da edição', function () {
        $edition = openEdition();
        $edition->forceFill(['judges_per_entry' => 2])->save();
        $category = regularCategory($edition);

        expectJuryError(fn () => app(SetCategoryJudges::class)->handle($category, User::factory()->jurado()->count(3)->create()->pluck('id')->all(), User::factory()->admin()->create()));
    });
});

describe('AssignJudgesToEntry (P15, P76)', function () {
    it('atribui os 3 jurados fixos a uma inscrição aprovada', function () {
        [$category, $ids] = categoryWithJudges();
        $admin = User::factory()->admin()->create();
        $entry = entryFor(participant(), $category, 'aprovada');

        $created = app(AssignJudgesToEntry::class)->handle($entry, $admin);

        expect($created)->toHaveCount(3)
            ->and($entry->judgeAssignments()->pluck('judge_id')->sort()->values()->all())->toBe(collect($ids)->sort()->values()->all())
            ->and($entry->judgeAssignments()->where('status', AssignmentStatus::Pendente->value)->count())->toBe(3)
            ->and(JudgeAssignment::first()->assigned_by)->toBe($admin->id)
            ->and(Activity::where('log_name', 'juri')->where('event', 'atribuicao')->count())->toBe(1);
    });

    it('só inscrições aprovadas recebem jurados (P76)', function (string $state) {
        [$category] = categoryWithJudges();
        $entry = entryFor(participant(), $category, $state);

        expectJuryError(fn () => app(AssignJudgesToEntry::class)->handle($entry, User::factory()->admin()->create()));
        expect(JudgeAssignment::count())->toBe(0);
    })->with(['rascunho', 'enviada', 'desclassificada']);

    it('recusa quando a categoria tem jurados de menos, sem criar nada', function () {
        [$category] = categoryWithJudges(judges: 2);
        $entry = entryFor(participant(), $category, 'aprovada');

        expectJuryError(fn () => app(AssignJudgesToEntry::class)->handle($entry, User::factory()->admin()->create()));
        expect(JudgeAssignment::count())->toBe(0);
    });

    it('recusa inscrição já completa', function () {
        [$category] = categoryWithJudges();
        $admin = User::factory()->admin()->create();
        $entry = entryFor(participant(), $category, 'aprovada');
        app(AssignJudgesToEntry::class)->handle($entry, $admin);

        expectJuryError(fn () => app(AssignJudgesToEntry::class)->handle($entry, $admin));
        expect(JudgeAssignment::count())->toBe(3);
    });

    it('aceita lista escolhida pelo admin', function () {
        [$category] = categoryWithJudges(judges: 0);
        $picked = User::factory()->jurado()->count(3)->create()->pluck('id')->all();
        $entry = entryFor(participant(), $category, 'aprovada');

        app(AssignJudgesToEntry::class)->handle($entry, User::factory()->admin()->create(), $picked);

        expect($entry->judgeAssignments()->count())->toBe(3);
    });
});

describe('AssignJudgesToCategory', function () {
    it('atribui às aprovadas incompletas e pula as completas', function () {
        [$category] = categoryWithJudges();
        $admin = User::factory()->admin()->create();
        $done = entryFor(participant(), $category, 'aprovada');
        app(AssignJudgesToEntry::class)->handle($done, $admin);
        $pending = entryFor(participant(), $category, 'aprovada');
        $notApproved = entryFor(participant(), $category, 'enviada');

        $result = app(AssignJudgesToCategory::class)->handle($category, $admin);

        expect($result['assigned'])->toBe([$pending->id])
            ->and($result['skipped'])->toBe([$done->id])
            ->and($result['failed'])->toBe([])
            ->and($notApproved->judgeAssignments()->count())->toBe(0);
    });

    it('informa as que falharam por falta de jurados', function () {
        [$category] = categoryWithJudges(judges: 1);
        $entry = entryFor(participant(), $category, 'aprovada');

        $result = app(AssignJudgesToCategory::class)->handle($category, User::factory()->admin()->create());

        expect($result['failed'])->toHaveKey($entry->id);
    });
});

describe('ReplaceJudgeOnEntry (P16, P47)', function () {
    it('marca a atribuição como substituída e cria outra para qualquer jurado', function () {
        [$category] = categoryWithJudges();
        $admin = User::factory()->admin()->create();
        $entry = entryFor(participant(), $category, 'aprovada');
        app(AssignJudgesToEntry::class)->handle($entry, $admin);
        $old = $entry->judgeAssignments()->first();
        $outsider = User::factory()->jurado()->create();

        $new = app(ReplaceJudgeOnEntry::class)->handle($old, $admin, ['new_judge_id' => $outsider->id, 'reason' => 'Participou do projeto.']);

        $old->refresh();
        expect($old->status)->toBe(AssignmentStatus::Substituida)
            ->and($old->replaced_by)->toBe($admin->id)
            ->and($old->replacement_reason)->toBe('Participou do projeto.')
            ->and($new->judge_id)->toBe($outsider->id)
            ->and($new->status)->toBe(AssignmentStatus::Pendente)
            ->and($entry->judgeAssignments()->where('status', '!=', AssignmentStatus::Substituida->value)->count())->toBe(3);
    });

    it('exige motivo e jurado que ainda não esteve na inscrição', function (Closure $input, string $field) {
        [$category] = categoryWithJudges();
        $admin = User::factory()->admin()->create();
        $entry = entryFor(participant(), $category, 'aprovada');
        app(AssignJudgesToEntry::class)->handle($entry, $admin);
        [$first, $second] = $entry->judgeAssignments()->get()->all();

        expectJuryError(fn () => app(ReplaceJudgeOnEntry::class)->handle($first, $admin, $input($second)), $field);
    })->with([
        'sem motivo' => [fn ($second) => ['new_judge_id' => User::factory()->jurado()->create()->id, 'reason' => ' '], 'reason'],
        'jurado já na inscrição' => [fn ($second) => ['new_judge_id' => $second->judge_id, 'reason' => 'x'], 'new_judge_id'],
        'não é jurado' => [fn ($second) => ['new_judge_id' => User::factory()->admin()->create()->id, 'reason' => 'x'], 'new_judge_id'],
    ]);

    it('não substitui duas vezes a mesma atribuição', function () {
        [$category] = categoryWithJudges();
        $admin = User::factory()->admin()->create();
        $entry = entryFor(participant(), $category, 'aprovada');
        app(AssignJudgesToEntry::class)->handle($entry, $admin);
        $old = $entry->judgeAssignments()->first();
        $stale = JudgeAssignment::find($old->id);

        app(ReplaceJudgeOnEntry::class)->handle($old, $admin, ['new_judge_id' => User::factory()->jurado()->create()->id, 'reason' => 'Conflito.']);

        expectJuryError(fn () => app(ReplaceJudgeOnEntry::class)->handle($stale, $admin, ['new_judge_id' => User::factory()->jurado()->create()->id, 'reason' => 'Conflito.']), 'new_judge_id');
    });

    it('não substitui em inscrição desclassificada', function () {
        [$category] = categoryWithJudges();
        $admin = User::factory()->admin()->create();
        $entry = entryFor(participant(), $category, 'aprovada');
        app(AssignJudgesToEntry::class)->handle($entry, $admin);
        Entry::query()->whereKey($entry->id)->update(['status' => EntryStatus::Desclassificada->value]);

        expectJuryError(fn () => app(ReplaceJudgeOnEntry::class)->handle($entry->judgeAssignments()->first(), $admin, ['new_judge_id' => User::factory()->jurado()->create()->id, 'reason' => 'x']), 'new_judge_id');
    });
});

describe('painel', function () {
    it('admin define jurados da categoria pela tabela', function () {
        $category = regularCategory(openEdition());
        $ids = User::factory()->jurado()->count(2)->create()->pluck('id')->all();

        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(ListCategories::class)
            ->callTableAction('setJudges', $category, data: ['judges' => $ids])
            ->assertHasNoTableActionErrors();

        expect($category->judges()->count())->toBe(2);
    });

    it('admin atribui e substitui pela seção Jurados da inscrição, e vê o andamento na lista', function () {
        [$category] = categoryWithJudges();
        $entry = entryFor(participant(), $category, 'aprovada');
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(JudgeAssignmentsRelationManager::class, ['ownerRecord' => $entry, 'pageClass' => ViewEntry::class])
            ->callTableAction('assignJudges')
            ->assertHasNoTableActionErrors();
        expect($entry->judgeAssignments()->count())->toBe(3);

        $old = $entry->judgeAssignments()->first();
        $outsider = User::factory()->jurado()->create();
        Livewire::test(JudgeAssignmentsRelationManager::class, ['ownerRecord' => $entry->fresh(), 'pageClass' => ViewEntry::class])
            ->callTableAction('replace', $old, data: ['new_judge_id' => $outsider->id, 'reason' => 'Conflito identificado.'])
            ->assertHasNoTableActionErrors();
        expect($old->fresh()->status)->toBe(AssignmentStatus::Substituida);

        Livewire::test(ListEntries::class)->assertSee('0/3');
    });

    it('admin atribui em lote pela lista de inscrições', function () {
        [$category] = categoryWithJudges();
        $entry = entryFor(participant(), $category, 'aprovada');

        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(ListEntries::class)->callAction('assignCategory', data: ['category_id' => $category->id]);

        expect($entry->judgeAssignments()->count())->toBe(3);
    });

    it('jurado, participante e eleitor não gerenciam o júri', function () {
        $judge = User::factory()->jurado()->create();
        $assignment = JudgeAssignment::factory()->create();

        expect($judge->can('replace', $assignment))->toBeFalse()
            ->and($judge->can('create', JudgeAssignment::class))->toBeFalse();
        $this->actingAs($judge)->get('/admin/categories')->assertForbidden();
    });
});
