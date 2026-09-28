@props(['name', 'label', 'options' => []])

<div>
    <label for="{{ $name }}" class="block text-sm font-medium text-stone-200">{{ $label }}</label>
    <select
        id="{{ $name }}"
        name="{{ $name }}"
        {{ $attributes->merge(['class' => 'mt-1 block w-full rounded-md border border-stone-700 bg-stone-900 px-3 py-2 text-stone-50 focus:border-amber-300 focus:outline-none focus:ring-1 focus:ring-amber-300']) }}
        @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
    >
        <option value="">Selecione</option>
        @foreach ($options as $value => $optionLabel)
            <option value="{{ $value }}" @selected(old($name) === (string) $value)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @error($name)
        <p id="{{ $name }}-error" class="mt-1 text-sm text-red-400">{{ $message }}</p>
    @enderror
</div>
