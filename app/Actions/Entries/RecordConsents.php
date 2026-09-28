<?php

declare(strict_types=1);

namespace App\Actions\Entries;

use App\Enums\ConsentType;
use App\Models\Entry;
use Illuminate\Validation\ValidationException;

/** Valida e grava os 6 aceites do §8 com data/hora e IP (P11, P53). Mantém o histórico. */
final class RecordConsents
{
    /** @param array<array-key, mixed> $accepted valores de ConsentType marcados */
    public function ensureAllAccepted(array $accepted): void
    {
        $missing = array_filter(
            ConsentType::cases(),
            fn (ConsentType $type) => ! in_array($type->value, $accepted, true),
        );

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'consents' => 'Marque todas as declarações e a autorização do §8 para continuar.',
            ]);
        }
    }

    public function record(Entry $entry, string $ip): void
    {
        $now = now();

        foreach (ConsentType::cases() as $type) {
            $entry->consents()->create([
                'consent' => $type,
                'accepted_at' => $now,
                'ip' => $ip,
            ]);
        }
    }
}
