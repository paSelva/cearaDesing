<button type="submit" {{ $attributes->merge(['class' => 'w-full rounded-md bg-tinta px-4 py-2 font-semibold text-white hover:bg-laranja-escuro focus:outline-none focus:ring-2 focus:ring-laranja-escuro focus:ring-offset-2 focus:ring-offset-white']) }}>
    {{ $slot }}
</button>
