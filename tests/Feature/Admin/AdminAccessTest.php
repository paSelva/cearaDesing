<?php

use App\Enums\Role;
use App\Filament\Resources\CategoryResource\Pages\ListCategories;
use App\Filament\Resources\CriterionResource\Pages\ListCriteria;
use App\Filament\Resources\EditionResource\Pages\EditEdition;
use App\Filament\Resources\EditionResource\Pages\ListEditions;
use App\Models\Category;
use App\Models\Criterion;
use App\Models\Edition;
use App\Models\User;
use Livewire\Livewire;

it('redirects visitors to the panel login and restricts the panel to admins', function () {
    $this->get('/admin')->assertRedirect('/admin/login');

    foreach ([Role::Jurado, Role::Participante, Role::Eleitor] as $role) {
        $this->actingAs(User::factory()->create(['role' => $role]))
            ->get('/admin')
            ->assertForbidden();
    }

    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertOk();
});

it('allows admins to consult catalog and edition settings but does not expose catalog mutations', function () {
    $admin = User::factory()->admin()->create();
    $edition = Edition::factory()->create();
    $category = Category::factory()->for($edition)->create();
    $criterion = Criterion::factory()->for($category)->create();

    $this->actingAs($admin);

    Livewire::test(ListCategories::class)->assertOk()->assertCanSeeTableRecords([$category]);
    Livewire::test(ListCriteria::class)->assertOk()->assertCanSeeTableRecords([$criterion]);
    Livewire::test(ListEditions::class)->assertOk()->assertCanSeeTableRecords([$edition]);
    Livewire::test(EditEdition::class, ['record' => $edition->getRouteKey()])->assertOk();

    $participant = User::factory()->create();
    $edition->forceFill(['year' => 2025])->save();
    $outsideCategory = Category::factory()->for($edition)->create();
    $outsideCriterion = Criterion::factory()->for($outsideCategory)->create();

    expect($admin->can('create', Category::class))->toBeFalse()
        ->and($admin->can('update', $category))->toBeFalse()
        ->and($admin->can('delete', $category))->toBeFalse()
        ->and($admin->can('create', Edition::class))->toBeFalse()
        ->and($admin->can('delete', $edition))->toBeFalse()
        ->and($admin->can('view', $outsideCategory))->toBeFalse()
        ->and($admin->can('view', $outsideCriterion))->toBeFalse()
        ->and($participant->can('viewAny', Category::class))->toBeFalse()
        ->and($participant->can('update', $edition))->toBeFalse();
});

it('does not allow a public mass assignment to promote a user to admin', function () {
    $user = User::factory()->create();

    $user->fill(['role' => Role::Admin]);

    expect($user->fresh()->role)->toBe(Role::Participante);

    // SPEC-002: /register passou a existir (cadastro público de participante, T1).
    // A garantia continua: o cadastro público nunca cria admin, mesmo com role no POST.
    $this->post('/register', [
        'name' => 'Tentativa de promoção',
        'email' => 'promocao@example.test',
        'password' => 'senha-sintetica-1',
        'password_confirmation' => 'senha-sintetica-1',
        'person_type' => 'pf',
        'document' => '529.982.247-25',
        'phone' => '(85) 99999-0000',
        'city' => 'Fortaleza',
        'state' => 'CE',
        'participant_type' => 'profissional',
        'role' => 'admin',
    ]);

    expect(User::where('email', 'promocao@example.test')->sole()->role)->toBe(Role::Participante);
});
