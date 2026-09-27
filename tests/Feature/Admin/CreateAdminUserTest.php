<?php

use App\Actions\Admin\CreateAdminUser;
use App\Enums\Role;
use Illuminate\Validation\ValidationException;

it('creates an administrator with a hashed password and no public role input', function () {
    $admin = app(CreateAdminUser::class)->handle([
        'name' => 'Admin sintético',
        'email' => 'admin@example.test',
        'password' => 'senha-sintetica-segura',
        'password_confirmation' => 'senha-sintetica-segura',
        'role' => 'participante',
    ]);

    expect($admin->role)->toBe(Role::Admin)
        ->and($admin->password)->not->toBe('senha-sintetica-segura')
        ->and(password_verify('senha-sintetica-segura', $admin->password))->toBeTrue();
});

it('rejects invalid administrator credentials input', function (array $overrides) {
    $input = [
        'name' => 'Admin sintético',
        'email' => 'admin@example.test',
        'password' => 'senha-sintetica-segura',
        'password_confirmation' => 'senha-sintetica-segura',
    ];

    expect(fn () => app(CreateAdminUser::class)->handle(array_replace($input, $overrides)))
        ->toThrow(ValidationException::class);
})->with([
    'senha curta' => [['password' => 'curta', 'password_confirmation' => 'curta']],
    'confirmação divergente' => [['password_confirmation' => 'outra-senha-segura']],
    'email inválido' => [['email' => 'invalid-email']],
]);

it('rejects an administrator email already in use', function () {
    $input = [
        'name' => 'Admin sintético',
        'email' => 'admin@example.test',
        'password' => 'senha-sintetica-segura',
        'password_confirmation' => 'senha-sintetica-segura',
    ];

    app(CreateAdminUser::class)->handle($input);

    expect(fn () => app(CreateAdminUser::class)->handle($input))
        ->toThrow(ValidationException::class);
});
