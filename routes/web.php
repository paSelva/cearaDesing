<?php

use App\Http\Controllers\Admin\EntryFileController;
use App\Http\Controllers\Admin\ProofFileController;
use App\Http\Controllers\Gallery\GalleryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Jurado\AssignmentFileController;
use App\Http\Controllers\Participant\DashboardController;
use App\Http\Controllers\Participant\EntryController;
use App\Http\Controllers\Participant\ProofController;
use App\Http\Controllers\Voter\RegisterVoterController;
use App\Http\Controllers\Voter\VoterHomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// Voto popular (SPEC-009): cadastro de eleitor separado do participante (P60).
Route::middleware('guest')->group(function (): void {
    Route::get('/eleitor/cadastro', [RegisterVoterController::class, 'create'])->name('voter.register');
    Route::post('/eleitor/cadastro', [RegisterVoterController::class, 'store'])->name('voter.register.store');
});

Route::middleware(['auth', 'role:eleitor'])->group(function (): void {
    Route::get('/eleitor', VoterHomeController::class)->name('voter.home');
    // Recomendação: limite de votos por minuto por eleitor (não é regra do edital).
    Route::post('/galeria/{entry}/votar', [GalleryController::class, 'vote'])->middleware('throttle:votes')->name('gallery.vote');
});

// Galeria pública (SPEC-010): a partir do início da votação (P55).
Route::get('/galeria', [GalleryController::class, 'index'])->name('gallery.index');
Route::get('/galeria/{entry}', [GalleryController::class, 'show'])->name('gallery.show');
Route::get('/galeria/{entry}/imagens/{media}', [GalleryController::class, 'image'])->name('gallery.image');

// Área do participante (SPEC-002/003). Login/cadastro/recuperação de senha: rotas do Fortify.
Route::middleware(['auth', 'role:participante'])
    ->prefix('participante')
    ->name('participant.')
    ->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::post('/inscricoes', [EntryController::class, 'store'])->name('entries.store');
        Route::get('/inscricoes/{entry}', [EntryController::class, 'edit'])->name('entries.edit');
        Route::put('/inscricoes/{entry}', [EntryController::class, 'update'])->name('entries.update');

        // Um arquivo por requisição (limites de upload do PHP na hospedagem compartilhada).
        Route::post('/inscricoes/{entry}/imagens', [EntryController::class, 'storeImage'])->name('entries.images.store');
        Route::delete('/inscricoes/{entry}/imagens/{media}', [EntryController::class, 'destroyImage'])->name('entries.images.destroy');
        Route::post('/inscricoes/{entry}/pdf', [EntryController::class, 'storePdf'])->name('entries.pdf.store');
        Route::delete('/inscricoes/{entry}/pdf', [EntryController::class, 'destroyPdf'])->name('entries.pdf.destroy');
        Route::get('/inscricoes/{entry}/arquivos/{media}', [EntryController::class, 'file'])->name('entries.file');

        // Comprovação (SPEC-005): independe do prazo de inscrições e do status da inscrição (P72, P79).
        Route::post('/comprovacoes/{proofRequest}', [ProofController::class, 'store'])->name('proofs.store');
        Route::get('/comprovacoes/{proofRequest}/arquivo', [ProofController::class, 'file'])->name('proofs.file');
    });

// Organização (SPEC-004): arquivos das inscrições para a triagem. O painel fica no Filament (/admin).
Route::middleware(['auth', 'role:admin'])
    ->prefix('organizacao')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/inscricoes/{entry}/arquivos/{media}', EntryFileController::class)->name('entries.file');
        Route::get('/comprovacoes/{proofRequest}/arquivo', ProofFileController::class)->name('proofs.file');
    });

// Jurado (SPEC-007): arquivos da inscrição atribuída. O painel fica no Filament (/jurado).
Route::middleware(['auth', 'role:jurado'])
    ->prefix('avaliacao')
    ->name('jurado.')
    ->group(function (): void {
        Route::get('/atribuicoes/{assignment}/arquivos/{media}', AssignmentFileController::class)->name('assignments.file');
    });
