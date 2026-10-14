@php
    /** @var \App\Models\JudgeAssignment $assignment */
    $assignment = $getRecord();
    $scores = $assignment->scores()->pluck('value', 'criterion_id');
@endphp

<dl class="space-y-2 text-sm">
    @foreach ($assignment->entry->category->criteria as $criterion)
        <div class="flex justify-between gap-4">
            <dt>{{ $criterion->name }}</dt>
            <dd class="font-semibold">{{ $scores[$criterion->id] ?? '—' }}</dd>
        </div>
    @endforeach
    <p class="pt-2 text-gray-500">
        {{ $assignment->status->label() }}@if ($assignment->submitted_at) em {{ $assignment->submitted_at->timezone('America/Fortaleza')->format('d/m/Y H:i') }}@endif.
    </p>
</dl>
