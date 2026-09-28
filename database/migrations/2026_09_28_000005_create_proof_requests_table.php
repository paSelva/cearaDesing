<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* Comprovação solicitada pela organização (docs/04, P12, P54, P56, P79 — SPEC-005). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proof_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entry_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->text('message');
            $table->dateTime('deadline_at');
            $table->enum('status', ['pendente', 'respondida', 'aceita', 'recusada'])->default('pendente');
            $table->dateTime('responded_at')->nullable();
            $table->boolean('is_late')->default(false);
            $table->dateTime('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proof_requests');
    }
};
