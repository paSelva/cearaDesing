<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Tabelas de inscrição conforme docs/04 §4 (SPEC-003). A tabela `entries` já nasce com as
| colunas de triagem e resultado (SPEC-004 em diante): cada migration em produção custa
| um cron temporário na HostGator (P66).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('edition_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            // Campos do §4: nulos só no rascunho; obrigatórios para enviar.
            $table->string('title')->nullable();
            $table->text('authorship_credits')->nullable();
            $table->text('summary')->nullable();
            $table->text('context_solution')->nullable();
            $table->text('results_differentials')->nullable();
            $table->boolean('ai_used')->nullable();
            $table->text('ai_usage_description')->nullable();

            $table->enum('status', ['rascunho', 'enviada', 'aprovada', 'desclassificada'])->default('rascunho');
            $table->dateTime('submitted_at')->nullable();

            $table->dateTime('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('disqualified_at')->nullable();
            $table->foreignId('disqualified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('disqualification_reason')->nullable();

            $table->unsignedTinyInteger('final_score')->nullable();
            $table->enum('distinction', ['ouro', 'prata', 'bronze', 'mencao_honrosa', 'sem_distincao'])->nullable();

            $table->timestamps();

            // 1 projeto por categoria por participante (§4).
            $table->unique(['edition_id', 'category_id', 'user_id']);
        });

        Schema::create('entry_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entry_id')->constrained()->cascadeOnDelete();
            $table->string('url', 2048);
            $table->string('label')->nullable();
            $table->unsignedTinyInteger('sort');
            $table->timestamps();
        });

        // Um registro por aceite do §8, com data/hora e IP (P11, P53). Nunca alterado.
        Schema::create('entry_consents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entry_id')->constrained()->restrictOnDelete();
            $table->enum('consent', [
                'veracidade',
                'autorizacao_inscricao',
                'creditos',
                'uso_imagens_materiais',
                'regras_edital',
                'autorizacao_divulgacao',
            ]);
            $table->dateTime('accepted_at');
            $table->string('ip', 45);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_consents');
        Schema::dropIfExists('entry_links');
        Schema::dropIfExists('entries');
    }
};
