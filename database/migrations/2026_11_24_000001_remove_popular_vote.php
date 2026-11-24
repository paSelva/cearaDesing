<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Não haverá voto popular (P1, revista em 24/11/2026): remove votos, apuração, eleitores e as
 * datas da votação. Recusa-se a rodar se houver dados: apagá-los exige decisão explícita.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['popular_votes', 'popular_vote_winners', 'voter_profiles'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException("A tabela {$table} tem dados; remova-os por decisão explícita antes de continuar.");
            }
        }
        if (DB::table('users')->where('role', 'eleitor')->exists()) {
            throw new RuntimeException('Há contas de eleitor; remova-as por decisão explícita antes de continuar.');
        }

        Schema::dropIfExists('popular_vote_winners');
        Schema::dropIfExists('popular_votes');
        Schema::dropIfExists('voter_profiles');

        Schema::table('editions', function (Blueprint $table): void {
            $table->dropColumn(['voting_starts_at', 'voting_ends_at']);
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->enum('role', ['admin', 'jurado', 'participante'])->default('participante')->change();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Remoção do voto popular não é revertida por migration; use o backup e a release anterior.');
    }
};
