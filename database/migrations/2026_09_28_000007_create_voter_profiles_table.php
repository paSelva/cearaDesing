<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* Eleitor do voto popular (docs/04 §3, P35, P42, P52, P60 — SPEC-009). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voter_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            // HMAC-SHA256 do CPF normalizado: 1 cadastro por CPF (P35) sem guardar o CPF em claro.
            $table->char('cpf_hash', 64)->unique();
            // CPF criptografado com a chave da aplicação, para consulta pela organização.
            $table->text('cpf_encrypted');
            $table->string('registration_ip', 45);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voter_profiles');
    }
};
