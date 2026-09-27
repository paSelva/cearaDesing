<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('year')->unique();
            $table->dateTime('registration_starts_at');
            $table->dateTime('registration_ends_at');
            $table->dateTime('judging_starts_at');
            $table->dateTime('judging_ends_at');
            $table->dateTime('voting_starts_at');
            $table->dateTime('voting_ends_at');
            $table->date('results_announce_on');
            $table->date('ceremony_on');
            $table->date('project_period_starts_on');
            $table->date('project_period_ends_on');
            $table->unsignedTinyInteger('judges_per_entry');
            $table->unsignedTinyInteger('band_gold_min');
            $table->unsignedTinyInteger('band_silver_min');
            $table->unsignedTinyInteger('band_bronze_min');
            $table->unsignedTinyInteger('band_honorable_min');
            $table->dateTime('results_published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('edition_id')->constrained()->restrictOnDelete();
            $table->string('code', 4)->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description');
            $table->boolean('is_student_only')->default(false);
            $table->unsignedTinyInteger('sort');
            $table->timestamps();
        });

        Schema::create('criteria', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('description', 500)->nullable();
            $table->unsignedTinyInteger('sort');
            $table->timestamps();

            $table->unique(['category_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('criteria');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('editions');
    }
};
