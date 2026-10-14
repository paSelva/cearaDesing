<x-layouts.site title="Cadastro de eleitor">
    <section class="mx-auto max-w-xl">
        <h1 class="font-display text-4xl font-bold uppercase leading-none text-tinta md:text-5xl">Cadastro para votar</h1>
        <p class="mt-4">
            O voto popular do 1º Prêmio Ceará Tem Design pede um cadastro de eleitor, separado da conta de participante.
            Cada CPF pode ter um cadastro, e cada eleitor vota uma vez por categoria.
        </p>

        <form method="POST" action="{{ route('voter.register.store') }}" class="mt-8 space-y-5">
            @csrf
            <x-form.field name="name" label="Nome completo" autocomplete="name" required autofocus />
            <x-form.field name="email" label="E-mail" type="email" autocomplete="email" required
                hint="Se você já tem conta de participante ou de jurado, use outro e-mail." />
            <x-form.field name="cpf" label="CPF" inputmode="numeric" required
                hint="Usado só para garantir um cadastro por pessoa. Guardamos o CPF criptografado." />

            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.field name="password" label="Senha" type="password" autocomplete="new-password" hint="Mínimo de 8 caracteres." required />
                <x-form.field name="password_confirmation" label="Confirme a senha" type="password" autocomplete="new-password" required />
            </div>

            <div>
                @if ($siteKey)
                    <div class="cf-turnstile" data-sitekey="{{ $siteKey }}" data-language="pt-br"></div>
                    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
                @else
                    <p class="rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900">A verificação anti-robô não está configurada; o cadastro de eleitor está indisponível.</p>
                @endif
                @error('cf-turnstile-response')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <x-form.button>Criar cadastro de eleitor</x-form.button>
        </form>

        <p class="mt-6 text-sm">
            Já tem cadastro de eleitor? <a href="{{ route('login') }}" class="site-link font-semibold text-tinta">Entrar</a>
        </p>
    </section>
</x-layouts.site>
