<?php

declare(strict_types=1);

namespace App\Http\Controllers\Participant;

use App\Actions\Entries\EntryGuard;
use App\Enums\ParticipantType;
use App\Enums\ProofRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Edition;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function __invoke(Request $request, EntryGuard $guard): View
    {
        $user = $request->user();
        $edition = Edition::query()->where('year', Edition::YEAR)->first();
        $isStudent = $user->participantProfile?->participant_type === ParticipantType::Estudante;

        $categories = $edition === null
            ? collect()
            : Category::query()->where('edition_id', $edition->id)->orderBy('sort')->get()
                // P9: estudante só vê a categoria exclusiva; os demais, só as outras.
                ->filter(fn (Category $category) => $category->is_student_only === $isStudent)
                ->values();

        return view('participant.dashboard', [
            'edition' => $edition,
            'registrationOpen' => $edition !== null && $guard->isRegistrationOpen($edition),
            'categories' => $categories,
            'entriesByCategory' => $user->entries()
                ->with('category')
                ->withCount(['proofRequests as pending_proofs_count' => fn ($query) => $query->where('status', ProofRequestStatus::Pendente->value)])
                ->get()
                ->keyBy('category_id'),
        ]);
    }
}
