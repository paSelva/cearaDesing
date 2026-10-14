<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Throwable;

final class DeployPreflightCommand extends Command
{
    protected $signature = 'app:deploy-preflight';

    protected $description = 'Valida a configuração e a conexão antes de alterar o banco em produção';

    public function handle(): int
    {
        $errors = [];

        $this->require(config('app.env') === 'production', 'APP_ENV deve ser production.', $errors);
        $this->require(config('app.debug') === false, 'APP_DEBUG deve ser false.', $errors);
        $this->require(
            str_starts_with((string) config('app.url'), 'https://'),
            'APP_URL deve usar HTTPS e o domínio final.',
            $errors,
        );
        $this->require(
            $this->validAppKey(),
            'APP_KEY ausente ou inválida: use "base64:" seguido de 32 bytes aleatórios em base64 (44 caracteres).',
            $errors,
        );
        $this->require(
            config('database.default') === 'mysql',
            'DB_CONNECTION deve ser mysql; MariaDB não foi validado para esta release.',
            $errors,
        );
        $this->require(config('session.driver') === 'database', 'SESSION_DRIVER deve ser database.', $errors);
        $this->require(config('cache.default') === 'database', 'CACHE_STORE deve ser database.', $errors);
        $this->require(config('queue.default') === 'sync', 'QUEUE_CONNECTION deve ser sync (não há worker nesta release).', $errors);
        $this->require(config('filesystems.default') === 'local', 'FILESYSTEM_DISK deve ser local (privado).', $errors);
        $this->require((string) config('services.cpf_hmac.key') !== '', 'CPF_HMAC_KEY não foi definida.', $errors);
        $this->require((string) config('services.turnstile.site_key') !== '', 'TURNSTILE_SITE_KEY não foi definida.', $errors);
        $this->require((string) config('services.turnstile.secret_key') !== '', 'TURNSTILE_SECRET_KEY não foi definida.', $errors);

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        try {
            $server = DB::selectOne(
                'SELECT VERSION() AS version, @@version_comment AS version_comment, DATABASE() AS database_name, @@default_storage_engine AS storage_engine',
            );
        } catch (Throwable $exception) {
            $this->error('Falha ao conectar ao banco configurado (credenciais não são exibidas).');
            $this->line('Tipo: '.$exception::class);

            return self::FAILURE;
        }

        $version = (string) ($server->version ?? '');
        $versionComment = (string) ($server->version_comment ?? '');
        $database = (string) ($server->database_name ?? '');
        $engine = (string) ($server->storage_engine ?? '');
        $configuredDatabase = (string) config('database.connections.mysql.database');

        if (str_contains(strtolower($version.' '.$versionComment), 'mariadb')) {
            $this->error('O servidor informou MariaDB; esta release ainda não foi validada nesse produto.');

            return self::FAILURE;
        }

        $numericVersion = preg_replace('/[^0-9.].*$/', '', $version) ?: '0';
        $isValidatedPercona57 = str_contains(strtolower($versionComment), 'percona server')
            && preg_match('/^5\.7\.44-48(?:\D|$)/', $version) === 1;
        $isValidatedMysql8 = version_compare($numericVersion, '8.0.0', '>=');

        if (! $isValidatedPercona57 && ! $isValidatedMysql8) {
            $this->error(
                'Banco não validado: use Percona Server 5.7.44-48 ou MySQL/Percona 8.0+; MariaDB e outras versões exigem validação específica.',
            );

            return self::FAILURE;
        }

        if ($database === '' || ! hash_equals($configuredDatabase, $database)) {
            $this->error('A conexão não selecionou exatamente o banco configurado em DB_DATABASE.');

            return self::FAILURE;
        }

        if (strcasecmp($engine, 'InnoDB') !== 0) {
            $this->error('O mecanismo padrão do banco deve ser InnoDB.');

            return self::FAILURE;
        }

        $this->info('Preflight da aplicação concluído.');
        $databaseProduct = $isValidatedPercona57 ? 'Percona Server' : 'MySQL/Percona';
        $this->line("Banco: {$databaseProduct} {$version}; schema selecionado; InnoDB.");
        $this->line('Configuração: production, debug desativado, HTTPS, armazenamento privado e fila síncrona.');

        return self::SUCCESS;
    }

    // Mesma leitura do EncryptionServiceProvider: sem isso, uma chave mal colada só aparece
    // como erro 500 no site, depois das migrations.
    private function validAppKey(): bool
    {
        $key = (string) config('app.key');
        if (str_starts_with($key, 'base64:')) {
            $key = (string) base64_decode(substr($key, 7), true);
        }

        return $key !== '' && Encrypter::supported($key, (string) config('app.cipher'));
    }

    /** @param list<string> $errors */
    private function require(bool $condition, string $message, array &$errors): void
    {
        if (! $condition) {
            $errors[] = $message;
        }
    }
}
