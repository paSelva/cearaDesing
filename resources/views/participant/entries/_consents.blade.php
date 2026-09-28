<fieldset class="space-y-3 rounded-lg border border-stone-800 p-5">
    <legend class="px-1 text-sm font-semibold text-stone-200">Declarações e autorização (§8 do edital)</legend>
    <p class="text-xs text-stone-400">Obrigatório para {{ $requiredFor ?? 'enviar a inscrição' }}. Marque cada item; registramos a data, a hora e o IP de cada aceite.</p>
    @foreach ($consentTypes as $type)
        <label class="flex items-start gap-3 text-sm text-stone-200">
            <input type="checkbox" name="consents[]" value="{{ $type->value }}" @checked(in_array($type->value, old('consents', []), true))
                class="mt-0.5 rounded border-stone-700 bg-stone-900 text-amber-300">
            <span>{{ $type->label() }}</span>
        </label>
    @endforeach
    <p class="text-xs text-stone-400">A inscrição não transfere para a Associação Ceará Design os direitos autorais do projeto.</p>
    @error('consents')
        <p class="text-sm text-red-400">{{ $message }}</p>
    @enderror
</fieldset>
