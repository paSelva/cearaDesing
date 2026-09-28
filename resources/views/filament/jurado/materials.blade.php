@php
    /** @var \App\Models\JudgeAssignment $assignment */
    $assignment = $getRecord();
    $entry = $assignment->entry;
    $images = $entry->getMedia('images');
    $pdf = $entry->getFirstMedia('supplementary_pdf');
@endphp

<div class="space-y-4">
    @if ($images->isNotEmpty())
        <ul class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ($images as $image)
                <li>
                    <a href="{{ route('jurado.assignments.file', [$assignment, $image]) }}" target="_blank" rel="noopener noreferrer">
                        <img src="{{ route('jurado.assignments.file', [$assignment, $image]) }}" alt="{{ $image->name }}" loading="lazy"
                            style="aspect-ratio: 1; width: 100%; object-fit: cover; border-radius: 0.5rem;">
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

    <p class="text-sm">
        PDF complementar:
        @if ($pdf)
            <a href="{{ route('jurado.assignments.file', [$assignment, $pdf]) }}" target="_blank" rel="noopener noreferrer" class="text-primary-600 underline">{{ $pdf->name }}.pdf</a>
        @else
            não enviado.
        @endif
    </p>

    @if ($entry->links->isNotEmpty())
        <ul class="list-disc space-y-1 pl-5 text-sm">
            @foreach ($entry->links as $link)
                <li>
                    <a href="{{ $link->url }}" target="_blank" rel="noopener noreferrer" class="text-primary-600 underline">{{ $link->label ?: $link->url }}</a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
