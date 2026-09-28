<button type="submit" {{ $attributes->merge(['class' => 'w-full rounded-md bg-amber-300 px-4 py-2 font-semibold text-stone-950 hover:bg-amber-200 focus:outline-none focus:ring-2 focus:ring-amber-300 focus:ring-offset-2 focus:ring-offset-stone-950']) }}>
    {{ $slot }}
</button>
