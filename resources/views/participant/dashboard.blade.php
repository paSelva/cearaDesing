@php($profile = auth()->user()->participantProfile)

<x-layouts.site title="Área do participante">
    <section>
        <p class="font-display text-lg font-medium text-laranja-escuro">Área do participante</p>
        <h1 class="mt-2 font-display text-4xl font-bold uppercase leading-none text-tinta md:text-5xl">Olá, {{ auth()->user()->name }}</h1>

        <x-flash />

        @if ($profile)
            <dl class="mt-8 grid max-w-2xl gap-4 rounded-lg border border-tinta/15 p-6 sm:grid-cols-2">
                <div>
                    <dt class="text-sm text-grafite/75">Tipo de pessoa</dt>
                    <dd class="mt-1">{{ $profile->person_type->label() }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-grafite/75">Tipo de participante</dt>
                    <dd class="mt-1">{{ $profile->participant_type->label() }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-grafite/75">E-mail</dt>
                    <dd class="mt-1">{{ auth()->user()->email }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-grafite/75">Cidade/UF</dt>
                    <dd class="mt-1">{{ $profile->city }}/{{ $profile->state }}</dd>
                </div>
            </dl>
        @endif
    </section>

    <section class="mt-12">
        <h2 class="font-display text-3xl font-bold uppercase leading-none text-tinta">Inscrições</h2>
        @if ($edition)
            <p class="mt-2 text-grafite/75">
                Um projeto por categoria.
                Inscrições de {{ $edition->registration_starts_at->format('d/m/Y') }}
                a {{ $edition->registration_ends_at->format('d/m/Y \à\s H:i') }} (horário de Fortaleza).
                @unless ($registrationOpen)
                    <strong class="text-laranja-escuro">O período de inscrições não está aberto.</strong>
                @endunless
            </p>
        @endif

        @error('category')
            <p class="mt-4 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900">{{ $message }}</p>
        @enderror
        @error('entry')
            <p class="mt-4 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900">{{ $message }}</p>
        @enderror

        <ul class="mt-6 divide-y divide-tinta/15 rounded-lg border border-tinta/15">
            @forelse ($categories as $category)
                @php($entry = $entriesByCategory->get($category->id))
                <li class="flex flex-wrap items-center justify-between gap-4 p-4">
                    <div>
                        <p class="font-medium">{{ $category->code }} · {{ $category->name }}</p>
                        @if ($entry)
                            <p class="mt-1 text-sm text-grafite/75">
                                {{ $entry->title ?: 'Projeto sem nome' }} — {{ $entry->status->label() }}
                            </p>
                            @if ($resultsPublished && $entry->final_score !== null)
                                <p class="mt-1 text-sm font-semibold text-tinta">
                                    Resultado: {{ $entry->distinction?->label() }}, {{ $entry->final_score }} pontos
                                </p>
                            @endif
                            @if ($entry->pending_proofs_count > 0)
                                <p class="mt-1 text-sm font-semibold text-laranja-escuro">A organização pediu uma comprovação. Abra a inscrição para enviar.</p>
                            @endif
                        @endif
                    </div>
                    @if ($entry)
                        <a href="{{ route('participant.entries.edit', $entry) }}" class="rounded-md border border-tinta/25 px-4 py-2 text-sm font-semibold hover:border-tinta">
                            {{ $entry->status->isEditableByParticipant() && $registrationOpen ? 'Continuar' : 'Ver' }}
                        </a>
                    @elseif ($registrationOpen)
                        <form method="POST" action="{{ route('participant.entries.store') }}">
                            @csrf
                            <input type="hidden" name="category_id" value="{{ $category->id }}">
                            <button type="submit" class="rounded-md bg-tinta px-4 py-2 text-sm font-semibold text-white hover:bg-laranja-escuro">Inscrever projeto</button>
                        </form>
                    @endif
                </li>
            @empty
                <li class="p-4 text-grafite/75">Nenhuma categoria disponível.</li>
            @endforelse
        </ul>
    </section>
</x-layouts.site>
