<?php

declare(strict_types=1);

namespace App\Actions\Entries;

use App\Enums\EntryStatus;
use App\Models\Entry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Salva os textos do §4, a declaração de IA (§8) e os links (P7) — docs/07, SPEC-003.
 *
 * Rascunho: campos podem ficar vazios. Enviada: tudo continua obrigatório e os 6 aceites
 * do §8 são pedidos de novo, gerando um novo conjunto de aceites (histórico).
 */
final class UpdateEntry
{
    public function __construct(
        private readonly EntryGuard $guard,
        private readonly RecordConsents $consents,
    ) {}

    /** @param array<string, mixed> $input */
    public function handle(Entry $entry, array $input, string $ip): Entry
    {
        $this->guard->ensureEditable($entry);

        $isSubmitted = $entry->status === EntryStatus::Enviada;
        $textMax = (int) config('premio.entry.text_max_length');
        $presence = $isSubmitted ? 'required' : 'nullable';

        $input['links'] = $this->filledLinks($input['links'] ?? []);

        $validated = Validator::make($input, [
            'title' => [$presence, 'string', 'max:255'],
            'authorship_credits' => [$presence, 'string', "max:{$textMax}"],
            'summary' => [$presence, 'string', "max:{$textMax}"],
            'context_solution' => [$presence, 'string', "max:{$textMax}"],
            'results_differentials' => [$presence, 'string', "max:{$textMax}"],
            'ai_used' => [$presence, 'in:0,1'],
            'ai_usage_description' => ['nullable', 'required_if:ai_used,1', 'string', "max:{$textMax}"],
            'links' => ['array', 'max:'.config('premio.entry.max_links')],
            'links.*.url' => ['required', 'url:http,https', 'max:2048'],
            'links.*.label' => ['nullable', 'string', 'max:255'],
        ], [
            'ai_usage_description.required_if' => 'Descreva como a IA generativa foi usada no projeto.',
            'links.max' => 'Informe no máximo :max links.',
            'links.*.url.url' => 'Informe um link completo, começando com http:// ou https://.',
        ], [
            'title' => 'nome do projeto',
            'authorship_credits' => 'autoria e principais créditos',
            'summary' => 'breve apresentação do projeto',
            'context_solution' => 'contexto e solução desenvolvida',
            'results_differentials' => 'principais resultados ou diferenciais',
            'ai_used' => 'uso de IA generativa',
            'ai_usage_description' => 'descrição do uso de IA',
        ])->validate();

        if ($isSubmitted) {
            $this->consents->ensureAllAccepted((array) ($input['consents'] ?? []));
        }

        return DB::transaction(function () use ($entry, $validated, $isSubmitted, $ip): Entry {
            $aiUsed = isset($validated['ai_used']) ? (string) $validated['ai_used'] === '1' : null;

            $entry->fill([
                'title' => $validated['title'] ?? null,
                'authorship_credits' => $validated['authorship_credits'] ?? null,
                'summary' => $validated['summary'] ?? null,
                'context_solution' => $validated['context_solution'] ?? null,
                'results_differentials' => $validated['results_differentials'] ?? null,
                'ai_used' => $aiUsed,
                'ai_usage_description' => $aiUsed ? ($validated['ai_usage_description'] ?? null) : null,
            ]);
            $entry->save();

            $entry->links()->delete();
            foreach (array_values($validated['links']) as $index => $link) {
                $entry->links()->create([
                    'url' => $link['url'],
                    'label' => $link['label'] ?? null,
                    'sort' => $index + 1,
                ]);
            }

            if ($isSubmitted) {
                $this->consents->record($entry, $ip);
            }

            return $entry->refresh();
        });
    }

    /**
     * Remove linhas de link totalmente vazias (o formulário sempre mostra 5 campos).
     *
     * @return array<int, array<string, mixed>>
     */
    private function filledLinks(mixed $links): array
    {
        if (! is_array($links)) {
            throw ValidationException::withMessages(['links' => 'Links inválidos.']);
        }

        return array_values(array_filter(
            $links,
            fn ($link) => ! is_array($link) || trim((string) ($link['url'] ?? '')) !== '' || trim((string) ($link['label'] ?? '')) !== '',
        ));
    }
}
