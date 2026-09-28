@php
    $links = old('links', $entry->links->map(fn ($link) => ['url' => $link->url, 'label' => $link->label])->all());
    $maxLinks = config('premio.entry.max_links');
    $imageConfig = config('premio.entry.images');
    $aiUsed = old('ai_used', $entry->ai_used === null ? null : ($entry->ai_used ? '1' : '0'));
@endphp

<x-layouts.site :title="$entry->title ?: 'Inscrição'">
    <a href="{{ route('participant.dashboard') }}" class="text-sm text-laranja-escuro hover:underline">← Minhas inscrições</a>

    <header class="mt-4">
        <p class="font-display text-lg font-medium text-laranja-escuro">{{ $entry->category->code }} · {{ $entry->category->name }}</p>
        <h1 class="mt-2 font-display text-4xl font-bold uppercase leading-none text-tinta md:text-5xl">{{ $entry->title ?: 'Nova inscrição' }}</h1>
        <p class="mt-2 text-grafite/75">
            Status: <strong class="text-tinta">{{ $entry->status->label() }}</strong>
            @if ($entry->submitted_at)
                · enviada em {{ $entry->submitted_at->format('d/m/Y H:i') }}
            @endif
        </p>
        @if ($resultsPublished && $entry->final_score !== null)
            <p class="mt-4 rounded-md border border-amarelo bg-amarelo/20 px-4 py-3 text-lg font-semibold text-tinta">
                Resultado: {{ $entry->distinction?->label() }}, {{ $entry->final_score }} pontos
            </p>
        @endif
        @if (! $editable)
            <p class="mt-4 rounded-md border border-tinta/25 bg-white px-4 py-3 text-sm text-grafite">
                Esta inscrição não pode ser editada agora (fora do prazo de inscrições ou já aprovada na triagem).
            </p>
        @elseif ($isSubmitted)
            <p class="mt-4 rounded-md border border-tinta/25 bg-white px-4 py-3 text-sm text-grafite">
                Você pode editar até {{ $entry->edition->registration_ends_at->format('d/m/Y \à\s H:i') }} (horário de Fortaleza).
                Ao salvar alterações nos textos, confirme de novo as declarações do §8.
            </p>
        @endif
    </header>

    <x-flash />

    @error('upload')
        <p class="mt-6 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert">{{ $message }}</p>
    @enderror

    @if ($errors->has('entry'))
        <div class="mt-6 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->get('entry') as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Comprovações pedidas pela organização (P12, P54, P56, P79) --}}
    @if ($entry->proofRequests->isNotEmpty())
        <section class="mt-10 space-y-4 rounded-lg border border-amarelo p-5">
            <h2 class="font-display text-2xl font-bold text-tinta">Comprovações pedidas pela organização</h2>
            @error('proof')
                <p class="text-sm text-red-700">{{ $message }}</p>
            @enderror
            <ul class="space-y-5">
                @foreach ($entry->proofRequests as $proofRequest)
                    <li class="space-y-2 border-t border-tinta/15 pt-4 first:border-0 first:pt-0">
                        <p class="text-sm text-grafite/75">
                            Pedida em {{ $proofRequest->created_at->format('d/m/Y H:i') }}
                            · prazo: <strong class="text-tinta">{{ $proofRequest->deadline_at->format('d/m/Y \à\s H:i') }}</strong> (horário de Fortaleza)
                            · {{ $proofRequest->status->label() }}
                            @if ($proofRequest->is_late)
                                · enviada após o prazo
                            @endif
                        </p>
                        <p class="whitespace-pre-line text-tinta">{{ $proofRequest->message }}</p>

                        @if ($proofRequest->getFirstMedia('proof'))
                            <a href="{{ route('participant.proofs.file', $proofRequest) }}" target="_blank" rel="noopener noreferrer" class="text-sm text-laranja-escuro hover:underline">Ver o PDF enviado</a>
                        @endif

                        @can('submit', $proofRequest)
                            <form method="POST" action="{{ route('participant.proofs.store', $proofRequest) }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
                                @csrf
                                <input type="file" name="proof" accept=".pdf,application/pdf" required class="text-sm text-grafite">
                                <button type="submit" class="rounded-md bg-tinta px-4 py-2 text-sm font-semibold text-white hover:bg-laranja-escuro">Enviar comprovação (PDF até 10 MB)</button>
                            </form>
                            @if ($proofRequest->deadline_at->isPast())
                                <p class="text-xs text-grafite/75">O prazo passou, mas o envio ainda é aceito e fica marcado como atrasado; a organização decide.</p>
                            @endif
                        @endcan
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Imagens (§4, P6) — cada envio recarrega a página --}}
    <section class="mt-10 space-y-4">
        <h2 class="font-display text-2xl font-bold text-tinta">Imagens do projeto</h2>
        <p class="text-sm text-grafite/75">
            De {{ $imageConfig['min'] }} a {{ $imageConfig['max'] }} imagens, em JPG, PNG ou WebP, com até 5 MB cada. Envie uma por vez.
        </p>

        @if ($images->isNotEmpty())
            <ul class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                @foreach ($images as $image)
                    <li class="space-y-2">
                        <img src="{{ route('participant.entries.file', [$entry, $image]) }}" alt="{{ $image->name }}" class="aspect-square w-full rounded-md border border-tinta/15 object-cover" loading="lazy">
                        @if ($editable)
                            <form method="POST" action="{{ route('participant.entries.images.destroy', [$entry, $image]) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs text-red-700 hover:underline">Remover</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        @error('image')
            <p class="text-sm text-red-700">{{ $message }}</p>
        @enderror

        @if ($editable && $images->count() < $imageConfig['max'])
            <form method="POST" action="{{ route('participant.entries.images.store', $entry) }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
                @csrf
                <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required class="text-sm text-grafite">
                <button type="submit" class="rounded-md border border-tinta/25 px-4 py-2 text-sm font-semibold hover:border-tinta">Enviar imagem</button>
            </form>
        @endif
    </section>

    {{-- PDF complementar (P7, P45) --}}
    <section class="mt-10 space-y-4">
        <h2 class="font-display text-2xl font-bold text-tinta">PDF complementar (opcional)</h2>
        <p class="text-sm text-grafite/75">1 arquivo PDF de até 10 MB. Um novo envio substitui o anterior.</p>

        @if ($pdf)
            <div class="flex flex-wrap items-center gap-4 text-sm">
                <a href="{{ route('participant.entries.file', [$entry, $pdf]) }}" class="text-laranja-escuro hover:underline" target="_blank" rel="noopener noreferrer">{{ $pdf->name }}.pdf</a>
                @if ($editable)
                    <form method="POST" action="{{ route('participant.entries.pdf.destroy', $entry) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-xs text-red-700 hover:underline">Remover</button>
                    </form>
                @endif
            </div>
        @endif

        @error('pdf')
            <p class="text-sm text-red-700">{{ $message }}</p>
        @enderror

        @if ($editable)
            <form method="POST" action="{{ route('participant.entries.pdf.store', $entry) }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
                @csrf
                <input type="file" name="pdf" accept=".pdf,application/pdf" required class="text-sm text-grafite">
                <button type="submit" class="rounded-md border border-tinta/25 px-4 py-2 text-sm font-semibold hover:border-tinta">{{ $pdf ? 'Substituir PDF' : 'Enviar PDF' }}</button>
            </form>
        @endif
    </section>

    @if ($editable)
        <p class="mt-8 rounded-md border border-tinta/25 bg-white px-4 py-3 text-sm text-grafite">
            Dica: envie as imagens e o PDF <strong>antes</strong> de preencher os textos, ou salve os textos antes de enviar um arquivo — cada envio de arquivo recarrega a página.
        </p>
    @endif

    {{-- Textos do §4, IA (§8), links (P7), aceites (§8) e envio --}}
    <form method="POST" action="{{ route('participant.entries.update', $entry) }}" class="mt-10 space-y-6">
        @csrf
        @method('PUT')
        <fieldset @disabled(! $editable) class="space-y-6">
            <h2 class="font-display text-2xl font-bold text-tinta">Dados do projeto</h2>

            <x-form.field name="title" label="Nome do projeto" :value="$entry->title" maxlength="255" />
            <x-form.textarea name="authorship_credits" label="Autoria e principais créditos" :value="$entry->authorship_credits" maxlength="5000" />
            <x-form.textarea name="summary" label="Breve apresentação do projeto" :value="$entry->summary" maxlength="5000" />
            <x-form.textarea name="context_solution" label="Contexto e solução desenvolvida" :value="$entry->context_solution" maxlength="5000" />
            <x-form.textarea name="results_differentials" label="Principais resultados ou diferenciais" :value="$entry->results_differentials" maxlength="5000" hint="Até 5.000 caracteres por campo." />

            <fieldset class="space-y-3">
                <legend class="text-sm font-medium text-tinta">Houve participação relevante de IA generativa na criação ou produção do projeto? (§8)</legend>
                <div class="flex gap-6 text-sm">
                    <label class="flex items-center gap-2"><input type="radio" name="ai_used" value="0" @checked($aiUsed === '0') class="border-tinta/25 bg-white text-laranja-escuro"> Não</label>
                    <label class="flex items-center gap-2"><input type="radio" name="ai_used" value="1" @checked($aiUsed === '1') class="border-tinta/25 bg-white text-laranja-escuro"> Sim</label>
                </div>
                @error('ai_used')
                    <p class="text-sm text-red-700">{{ $message }}</p>
                @enderror
                <x-form.textarea name="ai_usage_description" label="Se sim, descreva como a IA foi usada" :value="$entry->ai_usage_description" maxlength="5000" />
            </fieldset>

            <fieldset class="space-y-3">
                <legend class="text-sm font-medium text-tinta">Links ou vídeos, quando necessários para a compreensão (até {{ $maxLinks }})</legend>
                @error('links')
                    <p class="text-sm text-red-700">{{ $message }}</p>
                @enderror
                @for ($i = 0; $i < $maxLinks; $i++)
                    <div class="grid gap-3 sm:grid-cols-[2fr_1fr]">
                        <div>
                            <input type="url" name="links[{{ $i }}][url]" value="{{ $links[$i]['url'] ?? '' }}" placeholder="https://" aria-label="Link {{ $i + 1 }}"
                                class="block w-full rounded-md border border-tinta/25 bg-white px-3 py-2 text-tinta focus:border-laranja-escuro focus:outline-none">
                            @error("links.$i.url")
                                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                            @enderror
                        </div>
                        <input type="text" name="links[{{ $i }}][label]" value="{{ $links[$i]['label'] ?? '' }}" placeholder="Descrição (opcional)" aria-label="Descrição do link {{ $i + 1 }}" maxlength="255"
                            class="block w-full rounded-md border border-tinta/25 bg-white px-3 py-2 text-tinta focus:border-laranja-escuro focus:outline-none">
                    </div>
                @endfor
            </fieldset>

            @if ($editable && ! $isSubmitted && $missing !== [])
                <div class="rounded-md border border-amarelo bg-amarelo/20 px-4 py-3 text-sm text-tinta">
                    <p class="font-medium">Para enviar a inscrição, falta:</p>
                    <ul class="mt-2 list-inside list-disc space-y-1">
                        @foreach ($missing as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($editable)
                @include('participant.entries._consents', ['requiredFor' => $isSubmitted ? 'salvar as alterações' : 'enviar a inscrição'])

                <div class="flex flex-wrap gap-3">
                    @if ($isSubmitted)
                        <button type="submit" name="intent" value="save" class="rounded-md bg-tinta px-5 py-2.5 font-semibold text-white hover:bg-laranja-escuro">Salvar alterações</button>
                    @else
                        <button type="submit" name="intent" value="save" class="rounded-md border border-tinta/25 px-5 py-2.5 font-semibold hover:border-tinta">Salvar rascunho</button>
                        <button type="submit" name="intent" value="submit" class="rounded-md bg-tinta px-5 py-2.5 font-semibold text-white hover:bg-laranja-escuro">Salvar e enviar inscrição</button>
                    @endif
                </div>
            @endif
        </fieldset>
    </form>
</x-layouts.site>
