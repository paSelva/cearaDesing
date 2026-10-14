<?php

declare(strict_types=1);

namespace App\Http\Controllers\Voter;

use App\Http\Controllers\Controller;
use App\Models\Edition;
use App\Models\PopularVote;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Área do eleitor: período da votação e os próprios votos (nunca a contagem, P39). */
final class VoterHomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('voter.home', [
            'edition' => Edition::query()->where('year', Edition::YEAR)->first(),
            'votes' => PopularVote::query()->where('voter_id', $request->user()->id)->with('entry.category')->get(),
        ]);
    }
}
