@php($date = fn ($value) => $value?->locale('pt_BR')->translatedFormat('j \d\e F'))

<x-layouts.site title="Voto popular">
    <section class="max-w-2xl">
        <p class="font-display text-lg font-medium text-laranja-escuro">Voto popular</p>
        <h1 class="mt-2 font-display text-4xl font-bold uppercase leading-none text-tinta md:text-5xl">Olá, {{ auth()->user()->name }}</h1>

        <x-flash />

        @if ($edition)
            <p class="mt-6 text-lg">
                A votação acontece de {{ $date($edition->voting_starts_at) }} a {{ $date($edition->voting_ends_at) }}.
                Você pode votar em um projeto de cada categoria, entre os aprovados pela organização. O voto não pode ser trocado.
            </p>
        @endif

        <a href="{{ route('gallery.index') }}" class="site-button site-button--grande mt-8">Ver a galeria</a>
    </section>

    @if ($votes->isNotEmpty())
        <section class="mt-14 max-w-2xl">
            <h2 class="font-display text-2xl font-bold text-tinta">Seus votos</h2>
            <ul class="mt-4 divide-y divide-tinta/15 border-y border-tinta/15">
                @foreach ($votes as $vote)
                    <li class="py-3">
                        <span class="text-laranja-escuro">{{ $vote->entry->category->code }}</span>
                        {{ $vote->entry->category->name }}:
                        <a href="{{ route('gallery.show', $vote->entry) }}" class="site-link font-semibold text-tinta">{{ $vote->entry->title }}</a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-layouts.site>
