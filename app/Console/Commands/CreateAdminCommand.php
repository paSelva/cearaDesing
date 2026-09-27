<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Admin\CreateAdminUser;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateAdminCommand extends Command
{
    protected $signature = 'app:create-admin {--name= : Nome do administrador} {--email= : E-mail do administrador}';

    protected $description = 'Cria uma conta administrativa sem disponibilizar cadastro público';

    public function handle(CreateAdminUser $createAdmin): int
    {
        $name = $this->option('name') ?: text('Nome do administrador', required: true);
        $email = $this->option('email') ?: text('E-mail do administrador', required: true);
        $secret = password('Senha (mínimo 12 caracteres)', required: true);
        $confirmation = password('Confirme a senha', required: true);

        $createAdmin->handle([
            'name' => $name,
            'email' => $email,
            'password' => $secret,
            'password_confirmation' => $confirmation,
        ]);

        $this->info('Conta administrativa criada. A senha não foi exibida nem registrada.');

        return self::SUCCESS;
    }
}
