@props(['name', 'label', 'value' => null, 'hint' => null, 'disabled' => false])

<div>
    <label for="{{ $name }}" class="block text-sm font-medium text-stone-200">{{ $label }}</label>
    <textarea
        id="{{ $name }}"
        name="{{ $name }}"
        rows="5"
        @disabled($disabled)
        {{ $attributes->merge(['class' => 'mt-1 block w-full rounded-md border border-stone-700 bg-stone-900 px-3 py-2 text-stone-50 focus:border-amber-300 focus:outline-none focus:ring-1 focus:ring-amber-300 disabled:opacity-60']) }}
        @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
    >{{ old($name, $value) }}</textarea>
    @if ($hint)
        <p class="mt-1 text-xs text-stone-400">{{ $hint }}</p>
    @endif
    @error($name)
        <p id="{{ $name }}-error" class="mt-1 text-sm text-red-400">{{ $message }}</p>
    @enderror
</div>
