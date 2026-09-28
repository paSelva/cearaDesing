<?php

use App\Http\Controllers\Participant\DashboardController;
use App\Http\Controllers\Participant\EntryController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Área do participante (SPEC-002/003). Login/cadastro/recuperação de senha: rotas do Fortify.
Route::middleware(['auth', 'role:participante'])
    ->prefix('participante')
    ->name('participant.')
    ->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::post('/inscricoes', [EntryController::class, 'store'])->name('entries.store');
        Route::get('/inscricoes/{entry}', [EntryController::class, 'edit'])->name('entries.edit');
        Route::put('/inscricoes/{entry}', [EntryController::class, 'update'])->name('entries.update');
        Route::post('/inscricoes/{entry}/enviar', [EntryController::class, 'submit'])->name('entries.submit');

        // Um arquivo por requisição (limites de upload do PHP na hospedagem compartilhada).
        Route::post('/inscricoes/{entry}/imagens', [EntryController::class, 'storeImage'])->name('entries.images.store');
        Route::delete('/inscricoes/{entry}/imagens/{media}', [EntryController::class, 'destroyImage'])->name('entries.images.destroy');
        Route::post('/inscricoes/{entry}/pdf', [EntryController::class, 'storePdf'])->name('entries.pdf.store');
        Route::delete('/inscricoes/{entry}/pdf', [EntryController::class, 'destroyPdf'])->name('entries.pdf.destroy');
        Route::get('/inscricoes/{entry}/arquivos/{media}', [EntryController::class, 'file'])->name('entries.file');
    });
