@props(['name', 'label', 'type' => 'text', 'hint' => null, 'value' => null])

<div>
    <label for="{{ $name }}" class="block text-sm font-medium text-stone-200">{{ $label }}</label>
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @unless ($type === 'password') value="{{ old($name, $value) }}" @endunless
        {{ $attributes->merge(['class' => 'mt-1 block w-full rounded-md border border-stone-700 bg-stone-900 px-3 py-2 text-stone-50 focus:border-amber-300 focus:outline-none focus:ring-1 focus:ring-amber-300']) }}
        @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
    >
    @if ($hint)
        <p class="mt-1 text-xs text-stone-400">{{ $hint }}</p>
    @endif
    @error($name)
        <p id="{{ $name }}-error" class="mt-1 text-sm text-red-400">{{ $message }}</p>
    @enderror
</div>
