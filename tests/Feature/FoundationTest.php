<?php

use Illuminate\Support\Facades\Storage;

it('responde na página inicial e no endpoint de saúde', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Ceará Tem Design');

    $this->get('/up')->assertOk();
});

it('aplica cabeçalhos básicos de segurança', function () {
    $this->get('http://localhost/')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
        ->assertHeaderMissing('Strict-Transport-Security');

    $this->get('https://localhost/')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('não serve arquivos da raiz da aplicação pela superfície pública', function (string $path) {
    $this->get($path)->assertNotFound();
})->with([
    'env' => '/.env',
    'manifest Composer' => '/composer.json',
    'metadados Git' => '/.git/HEAD',
]);

it('mantém o disco local em storage privado', function () {
    Storage::disk('local')->put('spec-000/private-proof.txt', 'synthetic');

    expect(Storage::disk('local')->path('spec-000/private-proof.txt'))
        ->toStartWith(storage_path('app/private'));

    $this->get('/storage/spec-000/private-proof.txt')->assertForbidden();
});
