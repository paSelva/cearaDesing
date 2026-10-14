<?php

declare(strict_types=1);

// O preflight recusa APP_KEY mal colada antes das migrations (em produção, virou erro 500).
it('recusa APP_KEY com tamanho inválido', function (string $key) {
    config(['app.key' => $key]);

    $this->artisan('app:deploy-preflight')
        ->expectsOutputToContain('APP_KEY ausente ou inválida')
        ->assertFailed();
})->with([
    'vazia' => '',
    'base64 curta' => 'base64:'.base64_encode(random_bytes(16)),
    'sem prefixo base64' => base64_encode(random_bytes(32)),
    'base64 inválido' => 'base64:<COLE_AQUI>',
]);

it('aceita APP_KEY gerada no formato do Laravel', function () {
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

    $this->artisan('app:deploy-preflight')
        ->doesntExpectOutputToContain('APP_KEY ausente ou inválida')
        ->assertFailed(); // ambiente de teste falha em outros itens (APP_ENV, HTTPS...)
});
