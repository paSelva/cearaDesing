<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Validation\ValidationException;

function adminIni(string $dir, array $data): string
{
    $path = $dir.'/admin-'.uniqid().'.ini';
    $lines = [];
    foreach ($data as $key => $value) {
        $lines[] = "{$key}=\"{$value}\"";
    }
    file_put_contents($path, implode("\n", $lines)."\n");

    return $path;
}

describe('app:create-admin --from-file (deploy sem SSH)', function () {
    it('cria o admin a partir do arquivo e apaga o arquivo', function () {
        $path = adminIni(sys_get_temp_dir(), ['name' => 'Admin Deploy', 'email' => 'deploy@example.test', 'password' => 'senha-de-deploy-segura']);

        $this->artisan('app:create-admin', ['--from-file' => $path])
            ->expectsOutputToContain('Conta administrativa criada')
            ->assertSuccessful();

        $admin = User::where('email', 'deploy@example.test')->sole();
        expect($admin->role)->toBe(Role::Admin)
            ->and(password_verify('senha-de-deploy-segura', $admin->password))->toBeTrue()
            ->and(file_exists($path))->toBeFalse();
    });

    it('recusa arquivo dentro da pasta pública', function () {
        $path = adminIni(public_path(), ['name' => 'X', 'email' => 'x@example.test', 'password' => 'senha-de-deploy-segura']);

        try {
            $this->artisan('app:create-admin', ['--from-file' => $path])->assertFailed();
            expect(User::where('email', 'x@example.test')->exists())->toBeFalse();
        } finally {
            @unlink($path);
        }
    });

    it('recusa arquivo inexistente', function () {
        $this->artisan('app:create-admin', ['--from-file' => '/nao/existe.ini'])->assertFailed();
    });

    it('senha curta falha na validação e mantém o arquivo para correção', function () {
        $path = adminIni(sys_get_temp_dir(), ['name' => 'Admin', 'email' => 'curta@example.test', 'password' => 'curta']);

        try {
            expect(fn () => $this->artisan('app:create-admin', ['--from-file' => $path])->run())
                ->toThrow(ValidationException::class);
            expect(file_exists($path))->toBeTrue()
                ->and(User::where('email', 'curta@example.test')->exists())->toBeFalse();
        } finally {
            @unlink($path);
        }
    });
});
