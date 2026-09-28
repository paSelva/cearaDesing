@props(['name', 'label', 'value' => null, 'hint' => null, 'disabled' => false])

<div>
    <label for="{{ $name }}" class="block text-sm font-medium text-tinta">{{ $label }}</label>
    <textarea
        id="{{ $name }}"
        name="{{ $name }}"
        rows="5"
        @disabled($disabled)
        {{ $attributes->merge(['class' => 'mt-1 block w-full rounded-md border border-tinta/25 bg-white px-3 py-2 text-tinta focus:border-laranja-escuro focus:outline-none focus:ring-1 focus:ring-laranja-escuro disabled:opacity-60']) }}
        @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
    >{{ old($name, $value) }}</textarea>
    @if ($hint)
        <p class="mt-1 text-xs text-grafite/75">{{ $hint }}</p>
    @endif
    @error($name)
        <p id="{{ $name }}-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>
    @enderror
</div>
