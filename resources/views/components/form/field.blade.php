@props(['name', 'label', 'type' => 'text', 'hint' => null, 'value' => null])

<div>
    <label for="{{ $name }}" class="block text-sm font-medium text-tinta">{{ $label }}</label>
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @unless ($type === 'password') value="{{ old($name, $value) }}" @endunless
        {{ $attributes->merge(['class' => 'mt-1 block w-full rounded-md border border-tinta/25 bg-white px-3 py-2 text-tinta focus:border-laranja-escuro focus:outline-none focus:ring-1 focus:ring-laranja-escuro']) }}
        @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
    >
    @if ($hint)
        <p class="mt-1 text-xs text-grafite/75">{{ $hint }}</p>
    @endif
    @error($name)
        <p id="{{ $name }}-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>
    @enderror
</div>
