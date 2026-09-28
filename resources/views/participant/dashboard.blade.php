@php($profile = auth()->user()->participantProfile)

<x-layouts.site title="Área do participante">
    <section>
        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-amber-300">Área do participante</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight">Olá, {{ auth()->user()->name }}</h1>

        @if (session('status'))
            <p class="mt-6 rounded-md border border-emerald-700 bg-emerald-950 px-4 py-3 text-sm text-emerald-200">{{ session('status') }}</p>
        @endif

        <p class="mt-6 max-w-2xl text-stone-300">
            Sua conta está ativa. A inscrição de projetos ficará disponível nesta área.
        </p>

        @if ($profile)
            <dl class="mt-8 grid max-w-2xl gap-4 rounded-lg border border-stone-800 p-6 sm:grid-cols-2">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-stone-400">Tipo de pessoa</dt>
                    <dd class="mt-1">{{ $profile->person_type->label() }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-stone-400">Tipo de participante</dt>
                    <dd class="mt-1">{{ $profile->participant_type->label() }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-stone-400">E-mail</dt>
                    <dd class="mt-1">{{ auth()->user()->email }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-stone-400">Cidade/UF</dt>
                    <dd class="mt-1">{{ $profile->city }}/{{ $profile->state }}</dd>
                </div>
            </dl>
        @endif
    </section>
</x-layouts.site>
