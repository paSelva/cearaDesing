<?php

/**
 * Exemplo — Action SubmitEvaluation (docs/07, seção Júri).
 *
 * Regras cobertas:
 *  - atribuição do jurado logado e `pendente`
 *  - dentro do período de avaliação (judging_*, §7)
 *  - 4 notas preenchidas (§5), cada uma de 1 a 5
 *  - após o envio: status `enviada`, submitted_at, notas travadas (P19)
 *  - chama CalculateEntryResult: com 3 avaliações enviadas grava final_score e distinction (§5, §6)
 *
 * Namespaces, factories (states, relações) e assinatura de handle() são ilustrativos:
 * ajustar ao código real. Arquivo esperado: tests/Feature/Actions/<Área>/SubmitEvaluationTest.php
 */

use App\Enums\AssignmentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Criterion;
use App\Models\Edition;
use App\Models\Entry;
use App\Models\JudgeAssignment;
use App\Models\Score;
use Illuminate\Support\Carbon;

// use App\Actions\<Área>\SubmitEvaluation;

function fillScores(JudgeAssignment $assignment, array $values): void
{
    $criteria = $assignment->entry->category->criteria()->orderBy('id')->get();

    foreach ($values as $i => $value) {
        Score::factory()->for($assignment)->create([
            'criterion_id' => $criteria[$i]->id,
            'value'        => $value,
        ]);
    }
}

beforeEach(function () {
    $this->edition = Edition::factory()->create([
        'judging_starts_at' => Carbon::parse('2026-10-29 00:00:00', 'America/Fortaleza'),
        'judging_ends_at'   => Carbon::parse('2026-11-20 23:59:59', 'America/Fortaleza'),
    ]);

    $this->entry = Entry::factory()->aprovada()->create();
    Criterion::factory()->count(4)->for($this->entry->category)->create();   // 4 critérios (§5)

    $this->assignment = JudgeAssignment::factory()
        ->for($this->entry)
        ->for(jurado(), 'judge')
        ->create(['status' => AssignmentStatus::Pendente]);

    $this->travelTo(Carbon::parse('2026-11-05 10:00', 'America/Fortaleza'));
});

describe('SubmitEvaluation', function () {

    describe('✅ happy path', function () {

        it('envia a avaliação e trava as notas (P19)', function () {
            fillScores($this->assignment, [5, 4, 3, 5]);

            app(SubmitEvaluation::class)->handle($this->assignment);

            $fresh = $this->assignment->fresh();
            expect($fresh->status)->toBe(AssignmentStatus::Enviada)
                ->and($fresh->submitted_at)->not->toBeNull();
        });

        it('calcula nota final e distinção quando as 3 avaliações foram enviadas (§5, §6)', function () {
            // duas atribuições já enviadas, com 5+5+5+5 = 20 cada
            foreach (range(1, 2) as $_) {
                $done = JudgeAssignment::factory()->for($this->entry)->for(jurado(), 'judge')
                    ->create(['status' => AssignmentStatus::Enviada, 'submitted_at' => now()]);
                fillScores($done, [5, 5, 5, 5]);
            }
            fillScores($this->assignment, [4, 4, 3, 3]);   // 14 → total 54

            app(SubmitEvaluation::class)->handle($this->assignment);

            $entry = $this->entry->fresh();
            expect($entry->final_score)->toBe(54)
                ->and($entry->distinction->value)->toBe('ouro');   // 54 é o limite inferior do ouro
        });

    });

    describe('⚠️ edge cases', function () {

        it('com menos de 3 avaliações enviadas, não há resultado', function () {
            fillScores($this->assignment, [5, 5, 5, 5]);

            app(SubmitEvaluation::class)->handle($this->assignment);

            expect($this->entry->fresh()->final_score)->toBeNull()
                ->and($this->entry->fresh()->distinction)->toBeNull();
        });

        it('notas de atribuição substituída não entram no cálculo (docs/04 §9)', function () {
            $replaced = JudgeAssignment::factory()->for($this->entry)->for(jurado(), 'judge')
                ->create(['status' => AssignmentStatus::Substituida]);
            fillScores($replaced, [1, 1, 1, 1]);

            foreach (range(1, 2) as $_) {
                $done = JudgeAssignment::factory()->for($this->entry)->for(jurado(), 'judge')
                    ->create(['status' => AssignmentStatus::Enviada, 'submitted_at' => now()]);
                fillScores($done, [3, 3, 3, 3]);
            }
            fillScores($this->assignment, [3, 3, 3, 3]);

            app(SubmitEvaluation::class)->handle($this->assignment);

            expect($this->entry->fresh()->final_score)->toBe(36);   // 12 × 3, sem as notas 1
        });

        it('aceita envio no último segundo do período de avaliação (§7)', function () {
            $this->travelTo(Carbon::parse('2026-11-20 23:59:59', 'America/Fortaleza'));
            fillScores($this->assignment, [1, 1, 1, 1]);

            app(SubmitEvaluation::class)->handle($this->assignment);

            expect($this->assignment->fresh()->status)->toBe(AssignmentStatus::Enviada);
        });

    });

    describe('❌ cenários de erro', function () {

        it('rejeita envio com menos de 4 notas (§5)', function () {
            fillScores($this->assignment, [5, 5, 5]);

            expect(fn () => app(SubmitEvaluation::class)->handle($this->assignment))
                ->toThrow(BusinessRuleException::class);
        });

        it('rejeita reenvio de avaliação já enviada (P19)', function () {
            fillScores($this->assignment, [5, 5, 5, 5]);
            app(SubmitEvaluation::class)->handle($this->assignment);

            expect(fn () => app(SubmitEvaluation::class)->handle($this->assignment->fresh()))
                ->toThrow(BusinessRuleException::class);
        });

        it('rejeita envio fora do período de avaliação (§7)', function () {
            $this->travelTo(Carbon::parse('2026-11-21 00:00:00', 'America/Fortaleza'));
            fillScores($this->assignment, [5, 5, 5, 5]);

            expect(fn () => app(SubmitEvaluation::class)->handle($this->assignment))
                ->toThrow(BusinessRuleException::class);
        });

        it('rejeita atribuição substituída', function () {
            $this->assignment->update(['status' => AssignmentStatus::Substituida]);
            fillScores($this->assignment, [5, 5, 5, 5]);

            expect(fn () => app(SubmitEvaluation::class)->handle($this->assignment))
                ->toThrow(BusinessRuleException::class);
        });

    });

});
