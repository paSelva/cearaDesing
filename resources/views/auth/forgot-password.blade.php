<x-layouts.site title="Recuperar senha">
    <section class="mx-auto max-w-md">
        <h1 class="font-display text-4xl font-bold uppercase leading-none text-tinta md:text-5xl">Recuperar senha</h1>
        <p class="mt-2 text-grafite/75">Informe o e-mail da sua conta para receber o link de redefinição.</p>

        @if (session('status'))
            <p class="mt-6 rounded-md border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-5">
            @csrf
            <x-form.field name="email" label="E-mail" type="email" autocomplete="email" required autofocus />
            <x-form.button>Enviar link</x-form.button>
        </form>

        <p class="mt-6 text-sm"><a href="{{ route('login') }}" class="text-laranja-escuro hover:underline">Voltar para o login</a></p>
    </section>
</x-layouts.site>
