<x-layouts.site title="Nova senha">
    <section class="mx-auto max-w-md">
        <h1 class="font-display text-4xl font-bold uppercase leading-none text-tinta md:text-5xl">Definir nova senha</h1>

        <form method="POST" action="{{ route('password.update') }}" class="mt-8 space-y-5">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">
            <x-form.field name="email" label="E-mail" type="email" autocomplete="email" :value="old('email', $request->email)" required />
            <x-form.field name="password" label="Nova senha" type="password" autocomplete="new-password" hint="Mínimo de 8 caracteres." required autofocus />
            <x-form.field name="password_confirmation" label="Confirme a nova senha" type="password" autocomplete="new-password" required />
            <x-form.button>Salvar nova senha</x-form.button>
        </form>
    </section>
</x-layouts.site>
