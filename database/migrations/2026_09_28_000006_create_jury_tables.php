<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Júri conforme docs/04 §3 e §5 (SPEC-006). Uma migration só com as quatro tabelas: cada
| migration em produção custa um cron temporário na HostGator (P66). `scores` e
| `score_revisions` são usadas a partir da SPEC-007.
*/
return new class extends Migration
{
    public function up(): void
    {
        // 3 jurados fixos por categoria (P14): o limite fica na aplicação.
        Schema::create('category_judges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['category_id', 'user_id']);
        });

        Schema::create('judge_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entry_id')->constrained()->restrictOnDelete();
            $table->foreignId('judge_id')->constrained('users')->restrictOnDelete();
            $table->enum('status', ['pendente', 'enviada', 'substituida'])->default('pendente');
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('replaced_at')->nullable();
            $table->foreignId('replaced_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('replacement_reason')->nullable();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['entry_id', 'judge_id']);
        });

        Schema::create('scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('judge_assignment_id')->constrained()->restrictOnDelete();
            $table->foreignId('criterion_id')->constrained('criteria')->restrictOnDelete();
            $table->unsignedTinyInteger('value');
            $table->timestamps();
            $table->unique(['judge_assignment_id', 'criterion_id']);
        });

        // Reforço opcional: o MySQL 5.7 ignora CHECK (⚠️ P65); a validação na aplicação é obrigatória.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE scores ADD CONSTRAINT scores_value_between_1_and_5 CHECK (value BETWEEN 1 AND 5)');
        }

        // Correção pela organização (P19, P46): nunca alterado.
        Schema::create('score_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('score_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('old_value');
            $table->unsignedTinyInteger('new_value');
            $table->text('reason');
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('score_revisions');
        Schema::dropIfExists('scores');
        Schema::dropIfExists('judge_assignments');
        Schema::dropIfExists('category_judges');
    }
};
