@php
    /** @var \App\Models\Entry $record */
    $record = $getRecord();
    $images = $record->getMedia('images');
    $pdf = $record->getFirstMedia('supplementary_pdf');
@endphp

<div class="space-y-4">
    @if ($images->isEmpty())
        <p class="text-sm text-gray-500">Nenhuma imagem.</p>
    @else
        <ul class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ($images as $image)
                <li>
                    <a href="{{ route('admin.entries.file', [$record, $image]) }}" target="_blank" rel="noopener noreferrer">
                        <img src="{{ route('admin.entries.file', [$record, $image]) }}" alt="{{ $image->name }}" loading="lazy"
                            style="aspect-ratio: 1; width: 100%; object-fit: cover; border-radius: 0.5rem;">
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

    <p class="text-sm">
        PDF complementar:
        @if ($pdf)
            <a href="{{ route('admin.entries.file', [$record, $pdf]) }}" target="_blank" rel="noopener noreferrer" class="text-primary-600 underline">{{ $pdf->name }}.pdf</a>
        @else
            não enviado.
        @endif
    </p>
</div>
