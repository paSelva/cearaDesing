<fieldset class="space-y-3 rounded-lg border border-tinta/15 p-5">
    <legend class="px-1 text-sm font-semibold text-tinta">Declarações e autorização (§8 do edital)</legend>
    <p class="text-xs text-grafite/75">Obrigatório para {{ $requiredFor ?? 'enviar a inscrição' }}. Marque cada item; registramos a data, a hora e o IP de cada aceite.</p>
    @foreach ($consentTypes as $type)
        <label class="flex items-start gap-3 text-sm text-tinta">
            <input type="checkbox" name="consents[]" value="{{ $type->value }}" @checked(in_array($type->value, old('consents', []), true))
                class="mt-0.5 rounded border-tinta/25 bg-white text-laranja-escuro">
            <span>{{ $type->label() }}</span>
        </label>
    @endforeach
    <p class="text-xs text-grafite/75">A inscrição não transfere para a Associação Ceará Design os direitos autorais do projeto.</p>
    @error('consents')
        <p class="text-sm text-red-700">{{ $message }}</p>
    @enderror
</fieldset>
