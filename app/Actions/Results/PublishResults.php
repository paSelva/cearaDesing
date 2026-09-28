<?php

declare(strict_types=1);

namespace App\Actions\Results;

use App\Enums\EntryStatus;
use App\Enums\Role;
use App\Models\Edition;
use App\Models\PopularVote;
use App\Models\PopularVoteWinner;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Botão "Publicar resultado" do admin (P69, docs/07). Depois de publicado: página de premiados
 * (P21, P44, P51), distinção e nota na galeria (P57) e nota final na área do participante (P22).
 * As notas por jurado e por critério nunca são exibidas (P22).
 */
final class PublishResults
{
    /**
     * O que impede a publicação (Recomendação do docs/07).
     *
     * @return list<string>
     */
    public function pendingItems(Edition $edition): array
    {
        $pending = [];

        $withoutResult = $edition->categories()
            ->join('entries', 'entries.category_id', '=', 'categories.id')
            ->where('entries.status', EntryStatus::Aprovada->value)
            ->whereNull('entries.final_score')
            ->count();
        if ($withoutResult > 0) {
            $pending[] = "{$withoutResult} inscrição(ões) aprovada(s) ainda sem as avaliações dos jurados.";
        }

        $hasVotes = PopularVote::query()
            ->whereIn('category_id', $edition->categories()->pluck('id'))
            ->exists();
        $tallied = PopularVoteWinner::query()
            ->whereIn('category_id', $edition->categories()->pluck('id'))
            ->exists();
        if ($hasVotes && ! $tallied) {
            $pending[] = 'O voto popular ainda não foi apurado.';
        }

        $undecided = PopularVoteWinner::query()
            ->whereIn('category_id', $edition->categories()->pluck('id'))
            ->where('was_tie', true)
            ->whereNull('entry_id')
            ->count();
        if ($undecided > 0) {
            $pending[] = "{$undecided} empate(s) do voto popular aguardando decisão da organização (P49).";
        }

        return $pending;
    }

    public function handle(User $admin): Edition
    {
        if ($admin->role !== Role::Admin) {
            throw ValidationException::withMessages(['publish' => 'Somente a organização publica o resultado.']);
        }

        return DB::transaction(function () use ($admin): Edition {
            $edition = Edition::query()->where('year', Edition::YEAR)->lockForUpdate()->firstOrFail();

            if ($edition->results_published_at !== null) {
                throw ValidationException::withMessages(['publish' => 'O resultado já foi publicado.']);
            }

            $pending = $this->pendingItems($edition);
            if ($pending !== []) {
                throw ValidationException::withMessages(['publish' => $pending]);
            }

            $edition->forceFill(['results_published_at' => now()])->save();

            activity('resultado')
                ->performedOn($edition)
                ->causedBy($admin)
                ->event('publicado')
                ->log('Resultado publicado');

            return $edition;
        });
    }
}
