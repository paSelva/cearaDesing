<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Área do participante (SPEC-002). Login/cadastro/recuperação de senha: rotas do Fortify.
Route::middleware(['auth', 'role:participante'])
    ->prefix('participante')
    ->name('participant.')
    ->group(function (): void {
        Route::view('/', 'participant.dashboard')->name('dashboard');
    });
