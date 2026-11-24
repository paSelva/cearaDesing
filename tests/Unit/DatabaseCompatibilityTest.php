<?php

declare(strict_types=1);

use App\Support\DatabaseCompatibility;

it('aceita os bancos ensaiados', function (string $version, string $comment, string $product) {
    expect(DatabaseCompatibility::validatedProduct($version, $comment))->toBe($product);
})->with([
    'MariaDB 10.11 (Ubuntu 24.04)' => ['10.11.13-MariaDB-0ubuntu0.24.04.1', 'Ubuntu 24.04', 'MariaDB'],
    'MariaDB 10.11 (imagem oficial)' => ['10.11.14-MariaDB-ubu2204', 'mariadb.org binary distribution', 'MariaDB'],
    'Percona 5.7.44-48' => ['5.7.44-48', 'Percona Server (GPL), Release 48', 'Percona Server'],
    'MySQL 8.0' => ['8.0.46', 'MySQL Community Server - GPL', 'MySQL/Percona'],
]);

it('recusa bancos sem ensaio', function (string $version, string $comment) {
    expect(DatabaseCompatibility::validatedProduct($version, $comment))->toBeNull();
})->with([
    'MariaDB 10.6' => ['10.6.22-MariaDB-0ubuntu0.22.04.1', 'Ubuntu 22.04'],
    'MariaDB 11.8 (snapshot isolation ligado)' => ['11.8.2-MariaDB', 'mariadb.org binary distribution'],
    'MariaDB 10.110 não é 10.11' => ['10.110.1-MariaDB', ''],
    'MariaDB só no comentário' => ['10.11.13', 'MariaDB Server'],
    'MySQL 5.7' => ['5.7.44', 'MySQL Community Server (GPL)'],
    'Percona 5.7 de outro release' => ['5.7.44-49', 'Percona Server (GPL), Release 49'],
    'vazio' => ['', ''],
]);
