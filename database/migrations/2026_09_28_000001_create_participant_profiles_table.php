<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participant_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->enum('person_type', ['pf', 'pj']);
            // CPF (11 dígitos) ou CNPJ (14 caracteres, numérico ou alfanumérico — P81), sem máscara.
            $table->string('document', 14)->unique();
            $table->string('phone', 20);
            $table->string('city', 120);
            $table->char('state', 2);
            $table->enum('participant_type', [
                'profissional',
                'escritorio_estudio_equipe_coletivo',
                'estudante',
                'empresa',
                'instituicao',
                'osc',
            ]);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participant_profiles');
    }
};
