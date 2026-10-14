@php
    $user = auth()->user();
    $votedEntryId = $myVotes[$entry->category_id] ?? null;
@endphp

@if ($votingOpen)
    <div class="mt-8 max-w-3xl rounded-md border border-amarelo bg-amarelo/20 px-5 py-4">
        @if ($user?->role === \App\Enums\Role::Eleitor)
            @if ($votedEntryId === $entry->id)
                <p class="font-semibold text-tinta">Você votou neste projeto nesta categoria.</p>
            @elseif ($votedEntryId)
                <p>Você já votou em outro projeto desta categoria. O voto não pode ser trocado.</p>
            @else
                <form method="POST" action="{{ route('gallery.vote', $entry) }}" class="flex flex-wrap items-center gap-4"
                    onsubmit="return confirm('Confirmar seu voto neste projeto? O voto não pode ser trocado.');">
                    @csrf
                    <button type="submit" class="site-button">Votar neste projeto</button>
                    <span class="text-sm">Um voto por categoria. O voto não pode ser trocado.</span>
                </form>
            @endif
        @elseif ($user)
            <p>Para votar, é preciso um cadastro de eleitor, separado desta conta. Saia e crie o cadastro com outro e-mail.</p>
        @else
            <p>
                Para votar, <a href="{{ route('login') }}" class="site-link font-semibold text-tinta">entre</a> com seu cadastro de eleitor
                ou <a href="{{ route('voter.register') }}" class="site-link font-semibold text-tinta">faça o cadastro</a>.
            </p>
        @endif
    </div>
@endif
