<?php

use App\Actions\Admin\CreateJudge;
use App\Actions\Admin\SetJudgePassword;
use App\Enums\Role;
use App\Filament\Resources\JudgeResource\Pages\CreateJudge as CreateJudgePage;
use App\Filament\Resources\JudgeResource\Pages\ListJudges;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

describe('CreateJudge (P17)', function () {
    it('cria jurado com senha com hash, sem aceitar papel da entrada', function () {
        $judge = app(CreateJudge::class)->handle([
            'name' => 'Jurada Sintética',
            'email' => 'Jurada@Example.test',
            'password' => 'senha-jurado-segura',
            'password_confirmation' => 'senha-jurado-segura',
            'role' => 'admin',
        ]);

        expect($judge->role)->toBe(Role::Jurado)
            ->and($judge->email)->toBe('jurada@example.test')
            ->and(password_verify('senha-jurado-segura', $judge->password))->toBeTrue();
    });

    it('exige senha de pelo menos 12 caracteres e e-mail único', function (array $overrides) {
        User::factory()->create(['email' => 'usado@example.test']);

        expect(fn () => app(CreateJudge::class)->handle(array_replace([
            'name' => 'Jurado',
            'email' => 'novo@example.test',
            'password' => 'senha-jurado-segura',
            'password_confirmation' => 'senha-jurado-segura',
        ], $overrides)))->toThrow(ValidationException::class);
    })->with([
        'senha curta' => [['password' => 'curta123', 'password_confirmation' => 'curta123']],
        'confirmação divergente' => [['password_confirmation' => 'outra-senha-segura']],
        'e-mail já usado' => [['email' => 'usado@example.test']],
    ]);
});

describe('SetJudgePassword (P82)', function () {
    it('troca a senha do jurado e invalida o "lembrar de mim"', function () {
        $judge = User::factory()->jurado()->create(['remember_token' => 'token-antigo']);

        app(SetJudgePassword::class)->handle($judge, [
            'password' => 'nova-senha-do-jurado',
            'password_confirmation' => 'nova-senha-do-jurado',
        ]);

        $judge->refresh();
        expect(password_verify('nova-senha-do-jurado', $judge->password))->toBeTrue()
            ->and($judge->remember_token)->not->toBe('token-antigo');
    });

    it('não altera senha de quem não é jurado', function (Role $role) {
        $user = User::factory()->create(['role' => $role]);

        expect(fn () => app(SetJudgePassword::class)->handle($user, [
            'password' => 'nova-senha-do-jurado',
            'password_confirmation' => 'nova-senha-do-jurado',
        ]))->toThrow(ValidationException::class);
    })->with([Role::Admin, Role::Participante, Role::Eleitor]);

    it('exige 12 caracteres e confirmação', function () {
        $judge = User::factory()->jurado()->create();

        expect(fn () => app(SetJudgePassword::class)->handle($judge, [
            'password' => 'curta',
            'password_confirmation' => 'curta',
        ]))->toThrow(ValidationException::class);
    });
});

describe('painel /admin/jurados', function () {
    it('lista só jurados e só para admin', function () {
        $judge = User::factory()->jurado()->create();
        $participant = User::factory()->participante()->create();

        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(ListJudges::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$judge])
            ->assertCanNotSeeTableRecords([$participant]);
    });

    it('bloqueia não admin', function (Role $role) {
        $this->actingAs(User::factory()->create(['role' => $role]))
            ->get('/admin/jurados')
            ->assertForbidden();
    })->with([Role::Jurado, Role::Participante, Role::Eleitor]);

    it('admin cria jurado pelo painel', function () {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateJudgePage::class)
            ->fillForm([
                'name' => 'Jurado Painel',
                'email' => 'painel@example.test',
                'password' => 'senha-jurado-segura',
                'password_confirmation' => 'senha-jurado-segura',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(User::where('email', 'painel@example.test')->sole()->role)->toBe(Role::Jurado);
    });

    it('admin altera a senha do jurado pela ação da tabela', function () {
        $judge = User::factory()->jurado()->create();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ListJudges::class)
            ->callTableAction('changePassword', $judge, data: [
                'password' => 'nova-senha-do-jurado',
                'password_confirmation' => 'nova-senha-do-jurado',
            ])
            ->assertHasNoTableActionErrors();

        expect(password_verify('nova-senha-do-jurado', $judge->fresh()->password))->toBeTrue();
    });

    it('policy: admin só gerencia jurados e ninguém exclui contas', function () {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();
        $judge = User::factory()->jurado()->create();

        expect($admin->can('update', $judge))->toBeTrue()
            ->and($admin->can('update', $otherAdmin))->toBeFalse()
            ->and($admin->can('delete', $judge))->toBeFalse()
            ->and($judge->can('update', $judge))->toBeFalse();
    });
});
