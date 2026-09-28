<?php

declare(strict_types=1);

namespace App\Http\Controllers\Voter;

use App\Http\Controllers\Controller;
use App\Models\Edition;
use Illuminate\View\View;

/** Área do eleitor. A galeria e o voto entram na SPEC-010. */
final class VoterHomeController extends Controller
{
    public function __invoke(): View
    {
        return view('voter.home', ['edition' => Edition::query()->where('year', Edition::YEAR)->first()]);
    }
}
