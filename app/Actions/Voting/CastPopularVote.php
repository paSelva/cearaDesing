<?php

declare(strict_types=1);

namespace App\Actions\Voting;

use App\Enums\EntryStatus;
use App\Enums\Role;
use App\Models\Entry;
use App\Models\PopularVote;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Voto popular (docs/07): eleitor (P60), dentro do período (P38), em inscrição aprovada (P37),
 * 1 por categoria (P36), final (P71), inclusive no próprio projeto (P58). Grava o IP.
 */
final class CastPopularVote
{
    public function __construct(private readonly VotingWindow $window) {}

    public function handle(User $voter, Entry $entry, string $ip): PopularVote
    {
        if ($voter->role !== Role::Eleitor || $voter->voterProfile === null) {
            throw ValidationException::withMessages(['vote' => 'Para votar, é preciso um cadastro de eleitor.']);
        }

        try {
            return DB::transaction(function () use ($voter, $entry, $ip): PopularVote {
                $locked = Entry::query()->whereKey($entry->id)->lockForUpdate()->firstOrFail();

                if (! $this->window->votingOpen($locked->edition)) {
                    throw ValidationException::withMessages(['vote' => 'A votação não está aberta.']);
                }

                if ($locked->status !== EntryStatus::Aprovada) {
                    throw ValidationException::withMessages(['vote' => 'Este projeto não está na votação.']);
                }

                if (PopularVote::query()->where('voter_id', $voter->id)->where('category_id', $locked->category_id)->exists()) {
                    throw ValidationException::withMessages(['vote' => 'Você já votou nesta categoria. O voto não pode ser trocado.']);
                }

                $vote = new PopularVote;
                $vote->voter_id = $voter->id;
                $vote->entry_id = $locked->id;
                $vote->category_id = $locked->category_id; // nunca da entrada
                $vote->ip = $ip;
                $vote->save();

                return $vote;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['vote' => 'Você já votou nesta categoria. O voto não pode ser trocado.']);
        }
    }
}
