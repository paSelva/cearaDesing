<x-layouts.site title="Galeria">
    <section class="max-w-3xl">
        <h1 class="font-display text-4xl font-bold uppercase leading-none text-tinta md:text-5xl">Galeria</h1>
        @if ($open)
            <p class="mt-4 text-lg">
                Os projetos aprovados pela organização no 1º Prêmio Ceará Tem Design.
            </p>
        @else
            <p class="mt-4 text-lg">
                A galeria abre junto com a divulgação do resultado.
            </p>
        @endif
    </section>

    @foreach ($categories as $category)
        <section class="mt-14" aria-labelledby="cat-{{ $category->id }}">
            <div class="flex flex-wrap items-baseline justify-between gap-3 border-b-2 border-tinta pb-3">
                <h2 id="cat-{{ $category->id }}" class="font-display text-2xl font-bold text-tinta">
                    <span class="text-laranja-escuro">{{ $category->code }}</span> {{ $category->name }}
                </h2>
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
                            @if ($entry->distinction)
                                <p class="text-sm">{{ $entry->distinction->label() }}, {{ $entry->final_score }} pontos</p>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endforeach
</x-layouts.site>
