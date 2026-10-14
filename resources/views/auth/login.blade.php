<x-layouts.site title="Entrar">
    <section class="mx-auto max-w-md">
        <h1 class="font-display text-4xl font-bold uppercase leading-none text-tinta md:text-5xl">Entrar</h1>
        <p class="mt-2 text-grafite/75">Área do participante do 1º Prêmio Ceará Tem Design.</p>

        @if (session('status'))
            <p class="mt-6 rounded-md border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
            @csrf
            <x-form.field name="email" label="E-mail" type="email" autocomplete="email" required autofocus />
            <x-form.field name="password" label="Senha" type="password" autocomplete="current-password" required />

            <label class="flex items-center gap-2 text-sm text-grafite">
                <input type="checkbox" name="remember" class="rounded border-tinta/25 bg-white text-laranja-escuro">
                Manter conectado
            </label>

            <x-form.button>Entrar</x-form.button>
        </form>

        <div class="mt-6 flex justify-between text-sm">
            <a href="{{ route('password.request') }}" class="text-laranja-escuro hover:underline">Esqueci minha senha</a>
            <a href="{{ route('register') }}" class="text-laranja-escuro hover:underline">Criar conta</a>
        </div>
    </section>
</x-layouts.site>
