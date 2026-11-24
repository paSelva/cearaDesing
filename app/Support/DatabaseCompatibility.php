<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Bancos em que as migrations, o seeder e a suíte foram ensaiados. Qualquer outro é recusado
 * pelo preflight até passar pelo mesmo ensaio (docs/runbooks/compatibilidade-*.md).
 */
final class DatabaseCompatibility
{
    /** Nome do produto validado, ou null se o servidor não foi ensaiado. */
    public static function validatedProduct(string $version, string $versionComment): ?string
    {
        // MariaDB precisa ser decidido antes do MySQL: "10.x" passaria na comparação ">= 8.0".
        // Só a série 10.11: a partir da 11.6 o innodb_snapshot_isolation vem ligado e muda o
        // comportamento dos bloqueios (lockForUpdate) usados na inscrição e na avaliação.
        if (str_contains(strtolower($version.' '.$versionComment), 'mariadb')) {
            return preg_match('/^10\.11\.\d+-MariaDB/i', $version) === 1 ? 'MariaDB' : null;
        }

        if (str_contains(strtolower($versionComment), 'percona server')
            && preg_match('/^5\.7\.44-48(?:\D|$)/', $version) === 1) {
            return 'Percona Server';
        }

        $numericVersion = preg_replace('/[^0-9.].*$/', '', $version) ?: '0';

        return version_compare($numericVersion, '8.0.0', '>=') ? 'MySQL/Percona' : null;
    }
}
