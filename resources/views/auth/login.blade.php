<x-layouts.site title="Entrar">
    <section class="mx-auto max-w-md">
        <h1 class="text-3xl font-semibold tracking-tight">Entrar</h1>
        <p class="mt-2 text-stone-400">Área do participante do 1º Prêmio Ceará Tem Design.</p>

        @if (session('status'))
            <p class="mt-6 rounded-md border border-emerald-700 bg-emerald-950 px-4 py-3 text-sm text-emerald-200">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
            @csrf
            <x-form.field name="email" label="E-mail" type="email" autocomplete="email" required autofocus />
            <x-form.field name="password" label="Senha" type="password" autocomplete="current-password" required />

            <label class="flex items-center gap-2 text-sm text-stone-300">
                <input type="checkbox" name="remember" class="rounded border-stone-700 bg-stone-900 text-amber-300">
                Manter conectado
            </label>

            <x-form.button>Entrar</x-form.button>
        </form>

        <div class="mt-6 flex justify-between text-sm">
            <a href="{{ route('password.request') }}" class="text-amber-300 hover:underline">Esqueci minha senha</a>
            <a href="{{ route('register') }}" class="text-amber-300 hover:underline">Criar conta</a>
        </div>
    </section>
</x-layouts.site>
