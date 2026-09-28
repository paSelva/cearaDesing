@php($date = fn ($value) => $value?->locale('pt_BR')->translatedFormat('j \d\e F'))

<x-layouts.site title="Galeria e voto popular">
    <section class="max-w-3xl">
        <h1 class="font-display text-4xl font-bold uppercase leading-none text-tinta md:text-5xl">Galeria</h1>
        @if ($open)
            <p class="mt-4 text-lg">
                Os projetos aprovados pela organização no 1º Prêmio Ceará Tem Design.
                @if ($votingOpen)
                    O voto popular vai até {{ $date($edition->voting_ends_at) }}: quem tem cadastro de eleitor vota em um projeto de cada categoria.
                @endif
            </p>
        @else
            <p class="mt-4 text-lg">
                A galeria abre com o voto popular
                @if ($edition) em {{ $date($edition->voting_starts_at) }}@endif.
                Até lá, você já pode <a href="{{ route('voter.register') }}" class="site-link font-semibold text-tinta">fazer seu cadastro de eleitor</a>.
            </p>
        @endif
        <x-flash />
    </section>

    @foreach ($categories as $category)
        <section class="mt-14" aria-labelledby="cat-{{ $category->id }}">
            <div class="flex flex-wrap items-baseline justify-between gap-3 border-b-2 border-tinta pb-3">
                <h2 id="cat-{{ $category->id }}" class="font-display text-2xl font-bold text-tinta">
                    <span class="text-laranja-escuro">{{ $category->code }}</span> {{ $category->name }}
                </h2>
                @if ($myVotes->has($category->id))
                    <p class="text-sm font-semibold text-emerald-900">Você já votou nesta categoria</p>
                @endif
            </div>
            <ul class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($category->entries as $entry)
                    @php($cover = $entry->getFirstMedia('images'))
                    <li>
                        <a href="{{ route('gallery.show', $entry) }}" class="group block">
                            <div class="aspect-[4/3] overflow-hidden bg-amarelo/20">
                                @if ($cover)
                                    <img src="{{ route('gallery.image', [$entry, $cover]) }}" alt="" loading="lazy" class="h-full w-full object-cover">
                                @endif
                            </div>
                            <p class="mt-3 font-semibold text-tinta group-hover:text-laranja-escuro">{{ $entry->title }}</p>
                            @if ($showResults && $entry->distinction)
                                <p class="text-sm">{{ $entry->distinction->label() }}, {{ $entry->final_score }} pontos</p>
                            @endif
                            @if (($myVotes[$category->id] ?? null) === $entry->id)
                                <p class="text-sm font-semibold text-emerald-900">Seu voto</p>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endforeach
</x-layouts.site>
