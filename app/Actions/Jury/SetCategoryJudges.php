<?php

declare(strict_types=1);

namespace App\Actions\Jury;

use App\Models\Category;
use App\Models\Edition;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Define os jurados fixos da categoria (P14). O limite é `editions.judges_per_entry`
 * (3 pelo §5), não um número fixo no código. Mudar a lista não altera atribuições já feitas.
 */
final class SetCategoryJudges
{
    public function __construct(private readonly JuryGuard $guard) {}

    /** @param list<int> $judgeIds */
    public function handle(Category $category, array $judgeIds, User $admin): Category
    {
        $this->guard->ensureAdmin($admin);

        $edition = $category->edition;
        if ($edition->year !== Edition::YEAR) {
            throw ValidationException::withMessages(['judges' => 'Somente categorias da edição 2026.']);
        }

        $ids = $this->guard->ensureJudges($judgeIds);
        $max = $edition->judges_per_entry;
        if (count($ids) > $max) {
            throw ValidationException::withMessages(['judges' => "Cada categoria tem no máximo {$max} jurados fixos."]);
        }

        DB::transaction(function () use ($category, $ids, $admin): void {
            $before = $category->judges()->pluck('users.id')->all();
            $category->judges()->sync($ids);

            activity('juri')
                ->performedOn($category)
                ->causedBy($admin)
                ->event('jurados_da_categoria')
                ->withProperties(['antes' => $before, 'depois' => $ids])
                ->log('Jurados fixos da categoria definidos');
        });

        return $category->load('judges');
    }
}
