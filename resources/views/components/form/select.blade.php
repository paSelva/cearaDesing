@props(['name', 'label', 'options' => []])

<div>
    <label for="{{ $name }}" class="block text-sm font-medium text-tinta">{{ $label }}</label>
    <select
        id="{{ $name }}"
        name="{{ $name }}"
        {{ $attributes->merge(['class' => 'mt-1 block w-full rounded-md border border-tinta/25 bg-white px-3 py-2 text-tinta focus:border-laranja-escuro focus:outline-none focus:ring-1 focus:ring-laranja-escuro']) }}
        @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
    >
        <option value="">Selecione</option>
        @foreach ($options as $value => $optionLabel)
            <option value="{{ $value }}" @selected(old($name) === (string) $value)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @error($name)
        <p id="{{ $name }}-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>
    @enderror
</div>
