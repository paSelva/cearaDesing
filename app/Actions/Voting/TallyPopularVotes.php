<?php

declare(strict_types=1);

namespace App\Actions\Voting;

use App\Enums\EntryStatus;
use App\Enums\Role;
use App\Models\Category;
use App\Models\Edition;
use App\Models\PopularVote;
use App\Models\PopularVoteWinner;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Apuração do voto popular por categoria (P40), depois do fim da votação. Só contam inscrições
 * aprovadas: votos em desclassificadas ficam no banco e são descartados (P78). Empate fica para
 * a decisão da organização (P49). Pode ser refeita; se o empate mudar, a decisão anterior cai.
 */
final class TallyPopularVotes
{
    public function __construct(private readonly VotingWindow $window) {}

    /** @return Collection<int, array{category_id: int, entry_id: int, votes: int}> parcial, maior primeiro */
    public function partial(Edition $edition): Collection
    {
        return PopularVote::query()
            ->join('entries', 'entries.id', '=', 'popular_votes.entry_id')
            ->where('entries.edition_id', $edition->id)
            ->where('entries.status', EntryStatus::Aprovada->value)
            ->groupBy('popular_votes.category_id', 'popular_votes.entry_id')
            ->selectRaw('popular_votes.category_id, popular_votes.entry_id, COUNT(*) as votes')
            ->orderByDesc('votes')
            ->get()
            ->map(fn ($row) => ['category_id' => (int) $row->category_id, 'entry_id' => (int) $row->entry_id, 'votes' => (int) $row->votes]);
    }

    /** @return Collection<int, PopularVoteWinner> */
    public function handle(User $admin): Collection
    {
        if ($admin->role !== Role::Admin) {
            throw ValidationException::withMessages(['tally' => 'Somente a organização apura o voto popular.']);
        }

        $edition = $this->window->current();
        if (! $this->window->votingEnded($edition)) {
            throw ValidationException::withMessages(['tally' => 'A apuração só pode ser feita depois do fim da votação.']);
        }

        return DB::transaction(function () use ($edition, $admin): Collection {
            $byCategory = $this->partial($edition)->groupBy('category_id');
            $results = collect();

            foreach (Category::query()->where('edition_id', $edition->id)->orderBy('sort')->get() as $category) {
                $rows = $byCategory->get($category->id, collect());
                $winner = PopularVoteWinner::query()->where('category_id', $category->id)->lockForUpdate()->first();

                if ($rows->isEmpty()) {
                    $winner?->delete();

                    continue;
                }

                $max = $rows->max('votes');
                $top = $rows->where('votes', $max)->pluck('entry_id')->sort()->values()->all();
                $winner ??= new PopularVoteWinner;
                $tieChanged = $winner->tied_entry_ids !== (count($top) > 1 ? $top : null);

                $winner->category_id = $category->id;
                $winner->votes = $max;
                $winner->was_tie = count($top) > 1;
                $winner->tied_entry_ids = $winner->was_tie ? $top : null;

                if (! $winner->was_tie) {
                    $winner->entry_id = $top[0];
                    $winner->decided_by = null;
                    $winner->decision_note = null;
                } elseif ($tieChanged || ! in_array($winner->entry_id, $top, true)) {
                    // Empate novo ou diferente do anterior: a decisão precisa ser refeita.
                    $winner->entry_id = null;
                    $winner->decided_by = null;
                    $winner->decision_note = null;
                }

                $winner->save();
                $results->push($winner);
            }

            activity('voto_popular')
                ->causedBy($admin)
                ->event('apuracao')
                ->withProperties(['categorias' => $results->count(), 'empates' => $results->where('was_tie', true)->count()])
                ->log('Voto popular apurado');

            return $results;
        });
    }
}
