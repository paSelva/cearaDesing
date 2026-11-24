<x-layouts.site :title="$entry->title">
    <a href="{{ route('gallery.index') }}" class="site-link text-sm font-semibold text-tinta">Voltar para a galeria</a>

    <header class="mt-6 max-w-3xl">
        <p class="font-display text-lg font-medium text-laranja-escuro">{{ $entry->category->code }} {{ $entry->category->name }}</p>
        <h1 class="mt-2 font-display text-4xl font-bold uppercase leading-none text-tinta md:text-5xl">{{ $entry->title }}</h1>
        @if ($entry->distinction)
            <p class="mt-3 text-lg">{{ $entry->distinction->label() }}, {{ $entry->final_score }} pontos</p>
        @endif
    </header>

    <ul class="mt-10 grid gap-6 md:grid-cols-2">
        @foreach ($images as $image)
            <li>
                <img src="{{ route('gallery.image', [$entry, $image]) }}" alt="Imagem {{ $loop->iteration }} de {{ $entry->title }}" loading="{{ $loop->first ? 'eager' : 'lazy' }}" class="w-full">
            </li>
        @endforeach
    </ul>
</x-layouts.site>
