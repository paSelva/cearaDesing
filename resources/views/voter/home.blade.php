@php($date = fn ($value) => $value?->locale('pt_BR')->translatedFormat('j \d\e F'))

<x-layouts.site title="Voto popular">
    <section class="max-w-2xl">
        <p class="font-display text-lg font-medium text-laranja-escuro">Voto popular</p>
        <h1 class="mt-2 font-display text-4xl font-bold uppercase leading-none text-tinta md:text-5xl">Olá, {{ auth()->user()->name }}</h1>

        <x-flash />

        <p class="mt-6 text-lg">
            Seu cadastro de eleitor está pronto.
            @if ($edition)
                A votação acontece de {{ $date($edition->voting_starts_at) }} a {{ $date($edition->voting_ends_at) }}:
                você poderá votar em um projeto de cada categoria, entre os aprovados pela organização. O voto não pode ser trocado.
            @endif
        </p>
    </section>
</x-layouts.site>
