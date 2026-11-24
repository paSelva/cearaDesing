<x-layouts.site title="Acesso não permitido">
    <h1 class="font-display text-4xl font-bold uppercase leading-none text-tinta md:text-5xl">Acesso não permitido</h1>
    <p class="mt-6">
        @auth
            Sua conta não tem acesso a esta página. Se precisar de outra conta, use “Sair” no topo e entre novamente.
        @else
            Você não tem acesso a esta página.
        @endauth
    </p>
    @auth
        <a href="{{ \App\Support\RoleHome::for(auth()->user()->role) }}" class="site-button mt-8">Ir para a minha área</a>
    @endauth
</x-layouts.site>
