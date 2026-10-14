<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Admin\CreateAdminUser;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateAdminCommand extends Command
{
    protected $signature = 'app:create-admin
        {--name= : Nome do administrador}
        {--email= : E-mail do administrador}
        {--from-file= : Arquivo INI fora da pasta pública com name, email e password (deploy sem SSH); é apagado depois do uso}';

    protected $description = 'Cria uma conta administrativa sem disponibilizar cadastro público';

    public function handle(CreateAdminUser $createAdmin): int
    {
        if ($file = $this->option('from-file')) {
            return $this->fromFile((string) $file, $createAdmin);
        }

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

    /**
     * Modo não interativo para hospedagem sem SSH (cron temporário). A senha nunca passa pela
     * linha de comando: fica num arquivo fora da raiz pública, que é apagado depois do uso.
     */
    private function fromFile(string $file, CreateAdminUser $createAdmin): int
    {
        $path = realpath($file);
        if ($path === false || ! is_file($path) || ! is_readable($path)) {
            $this->error('Arquivo não encontrado ou sem permissão de leitura.');

            return self::FAILURE;
        }

        $public = realpath(public_path());
        if ($public !== false && str_starts_with($path, $public.DIRECTORY_SEPARATOR)) {
            $this->error('O arquivo não pode ficar dentro da pasta pública.');

            return self::FAILURE;
        }

        $base = realpath(base_path());
        if ($base !== false && str_starts_with($path, $base.DIRECTORY_SEPARATOR)) {
            $this->error('O arquivo deve ficar fora da pasta da aplicação.');

            return self::FAILURE;
        }

        $permissions = fileperms($path);
        if ($permissions === false || ($permissions & 0037) !== 0) {
            $this->error('Permissões inseguras: use 600 ou 640 no arquivo do administrador.');

            return self::FAILURE;
        }

        $data = parse_ini_file($path, false, INI_SCANNER_RAW);
        if (! is_array($data)) {
            $this->error('Arquivo inválido: use o formato INI com name, email e password.');

            return self::FAILURE;
        }

        if (array_diff(array_keys($data), ['name', 'email', 'password']) !== []) {
            $this->error('Arquivo inválido: somente name, email e password são permitidos.');

            return self::FAILURE;
        }

        $secret = (string) ($data['password'] ?? '');
        $createAdmin->handle([
            'name' => (string) ($data['name'] ?? ''),
            'email' => (string) ($data['email'] ?? ''),
            'password' => $secret,
            'password_confirmation' => $secret,
        ]);

        $deleted = @unlink($path);
        $this->info('Conta administrativa criada. A senha não foi exibida nem registrada.');
        if (! $deleted) {
            $this->warn('Não foi possível apagar o arquivo com a senha. Apague-o manualmente agora.');
        }

        return self::SUCCESS;
    }
}
