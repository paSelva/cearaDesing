<x-layouts.site title="Recuperar senha">
    <section class="mx-auto max-w-md">
        <h1 class="text-3xl font-semibold tracking-tight">Recuperar senha</h1>
        <p class="mt-2 text-stone-400">Informe o e-mail da sua conta para receber o link de redefinição.</p>

        @if (session('status'))
            <p class="mt-6 rounded-md border border-emerald-700 bg-emerald-950 px-4 py-3 text-sm text-emerald-200">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-5">
            @csrf
            <x-form.field name="email" label="E-mail" type="email" autocomplete="email" required autofocus />
            <x-form.button>Enviar link</x-form.button>
        </form>

        <p class="mt-6 text-sm"><a href="{{ route('login') }}" class="text-amber-300 hover:underline">Voltar para o login</a></p>
    </section>
</x-layouts.site>
