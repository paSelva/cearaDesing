<?php

declare(strict_types=1);

namespace App\Actions\Entries;

use App\Enums\EntryStatus;
use App\Models\Entry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Envia a inscrição: rascunho → enviada, com os 6 aceites do §8 (docs/07, SPEC-003). */
final class SubmitEntry
{
    public function __construct(
        private readonly EntryGuard $guard,
        private readonly RecordConsents $consents,
    ) {}

    /** @param array<array-key, mixed> $acceptedConsents */
    public function handle(Entry $entry, array $acceptedConsents, string $ip): Entry
    {
        $this->guard->ensureRegistrationOpen($entry->edition);

        if ($entry->status !== EntryStatus::Rascunho) {
            throw ValidationException::withMessages(['entry' => 'Esta inscrição já foi enviada.']);
        }

        $missing = $this->missingItems($entry);
        if ($missing !== []) {
            throw ValidationException::withMessages(['entry' => $missing]);
        }

        $this->consents->ensureAllAccepted($acceptedConsents);

        return DB::transaction(function () use ($entry, $ip): Entry {
            $this->guard->lockEditable($entry);
            if ($entry->status !== EntryStatus::Rascunho) {
                throw ValidationException::withMessages(['entry' => 'Esta inscrição já foi enviada.']);
            }

            $entry->status = EntryStatus::Enviada;
            $entry->submitted_at = now();
            $entry->save();

            $this->consents->record($entry, $ip);

            return $entry->refresh();
        });
    }

    /**
     * O que falta para enviar (§4, §8, P6).
     *
     * @return list<string>
     */
    public function missingItems(Entry $entry): array
    {
        $labels = [
            'title' => 'nome do projeto',
            'authorship_credits' => 'autoria e principais créditos',
            'summary' => 'breve apresentação do projeto',
            'context_solution' => 'contexto e solução desenvolvida',
            'results_differentials' => 'principais resultados ou diferenciais',
        ];

        $missing = [];
        foreach ($labels as $field => $label) {
            if (trim((string) $entry->{$field}) === '') {
                $missing[] = "Preencha: {$label}.";
            }
        }

        if ($entry->ai_used === null) {
            $missing[] = 'Informe se houve uso relevante de IA generativa (§8).';
        } elseif ($entry->ai_used && trim((string) $entry->ai_usage_description) === '') {
            $missing[] = 'Descreva como a IA generativa foi usada no projeto.';
        }

        $images = $entry->imageCount();
        $min = (int) config('premio.entry.images.min');
        if ($images < $min) {
            $missing[] = "Envie pelo menos {$min} imagem do projeto.";
        }

        return $missing;
    }
}
