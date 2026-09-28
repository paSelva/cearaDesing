<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* Voto popular: votos e apuração por categoria (docs/04 §6, P36–P49, P71, P78 — SPEC-010). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('popular_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('voter_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('entry_id')->constrained()->restrictOnDelete();
            // Cópia da categoria da inscrição, para o índice único (P36).
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('ip', 45);
            $table->timestamp('created_at')->nullable();
            $table->unique(['voter_id', 'category_id']);
        });

        Schema::create('popular_vote_winners', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->unique()->constrained()->restrictOnDelete();
            // Nulo enquanto houver empate sem decisão da organização (P49).
            $table->foreignId('entry_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('votes');
            $table->boolean('was_tie')->default(false);
            // Inscrições empatadas na apuração (a decisão só pode escolher entre elas).
            $table->json('tied_entry_ids')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('decision_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('popular_vote_winners');
        Schema::dropIfExists('popular_votes');
    }
};
