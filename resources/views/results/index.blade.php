@php($date = fn ($value) => $value?->locale('pt_BR')->translatedFormat('j \d\e F'))

<x-layouts.site title="Premiados">
    <section class="max-w-3xl">
        <h1 class="font-display text-4xl font-bold uppercase leading-none text-tinta md:text-5xl">Premiados</h1>
        @if ($published)
            <p class="mt-4 text-lg">
                O resultado do 1º Prêmio Ceará Tem Design. O prêmio reconhece níveis de excelência: uma categoria pode ter mais de um projeto em cada distinção.
            </p>
        @else
            <p class="mt-4 text-lg">
                O resultado ainda não foi publicado.
                @if ($edition) A divulgação está prevista para {{ $date($edition->results_announce_on) }}.@endif
            </p>
        @endif
    </section>

    @foreach ($categories as $category)
        @php($winner = $popularWinners->get($category->id))
        @continue($category->entries->isEmpty() && ! $winner)

        <section class="mt-16" aria-labelledby="cat-{{ $category->id }}">
            <h2 id="cat-{{ $category->id }}" class="border-b-2 border-tinta pb-3 font-display text-3xl font-bold text-tinta">
                <span class="text-laranja-escuro">{{ $category->code }}</span> {{ $category->name }}
            </h2>

            @foreach ($awarded as $distinction)
                @php($entries = $category->entries->where('distinction', $distinction))
                @continue($entries->isEmpty())

                <h3 class="mt-10 font-display text-2xl font-bold text-tinta">{{ $distinction->label() }}</h3>
                <div class="mt-6 space-y-12">
                    @foreach ($entries as $entry)
                        <article class="grid gap-6 lg:grid-cols-[2fr_3fr]" aria-labelledby="entry-{{ $entry->id }}">
                            <div class="grid grid-cols-2 gap-3">
                                @foreach ($entry->getMedia('images')->take(4) as $image)
                                    <img src="{{ route('gallery.image', [$entry, $image]) }}" alt="Imagem {{ $loop->iteration }} de {{ $entry->title }}" loading="lazy"
                                        class="{{ $loop->first ? 'col-span-2' : '' }} aspect-[4/3] w-full object-cover">
                                @endforeach
                            </div>
                            <div>
                                <h4 id="entry-{{ $entry->id }}" class="font-display text-2xl font-bold text-tinta">{{ $entry->title }}</h4>
                                <p class="mt-1 font-semibold">{{ $distinction->label() }}, {{ $entry->final_score }} pontos</p>
                                <dl class="mt-4 space-y-3 leading-7">
                                    <div><dt class="font-semibold text-tinta">Autoria e principais créditos</dt><dd class="whitespace-pre-line">{{ $entry->authorship_credits }}</dd></div>
                                    <div><dt class="font-semibold text-tinta">Apresentação</dt><dd class="whitespace-pre-line">{{ $entry->summary }}</dd></div>
                                    <div><dt class="font-semibold text-tinta">Contexto e solução</dt><dd class="whitespace-pre-line">{{ $entry->context_solution }}</dd></div>
                                    <div><dt class="font-semibold text-tinta">Resultados e diferenciais</dt><dd class="whitespace-pre-line">{{ $entry->results_differentials }}</dd></div>
                                </dl>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endforeach

            @if ($winner)
                <div class="mt-10 border-l-4 border-laranja pl-4">
                    <h3 class="font-display text-xl font-bold text-tinta">Voto popular</h3>
                    <p class="mt-1">Mais votado pelo público: <a href="{{ route('gallery.show', $winner->entry) }}" class="site-link font-semibold text-tinta">{{ $winner->entry->title }}</a></p>
                </div>
            @endif
        </section>
    @endforeach
</x-layouts.site>
