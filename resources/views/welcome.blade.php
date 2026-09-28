<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Fundação técnica do 1º Prêmio Ceará Tem Design 2026.">
        <title>{{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-stone-950 text-stone-50 antialiased">
        <main class="mx-auto flex min-h-screen max-w-4xl items-center px-6 py-16">
            <section>
                <p class="mb-4 text-sm font-semibold uppercase tracking-[0.22em] text-amber-300">1º Prêmio · 2026</p>
                <h1 class="max-w-3xl text-5xl font-semibold tracking-tight sm:text-7xl">Ceará Tem Design</h1>
                <p class="mt-6 max-w-2xl text-lg leading-8 text-stone-300">
                    Fundação técnica preparada. Os módulos de inscrição, júri, votação e premiação serão desenvolvidos nas próximas especificações.
                </p>
                <div class="mt-10 flex flex-wrap gap-4">
                    @auth
                        <a href="{{ route('participant.dashboard') }}" class="rounded-md bg-amber-300 px-5 py-2.5 font-semibold text-stone-950 hover:bg-amber-200">Área do participante</a>
                    @else
                        <a href="{{ route('register') }}" class="rounded-md bg-amber-300 px-5 py-2.5 font-semibold text-stone-950 hover:bg-amber-200">Criar conta de participante</a>
                        <a href="{{ route('login') }}" class="rounded-md border border-stone-700 px-5 py-2.5 font-semibold text-stone-100 hover:border-stone-500">Entrar</a>
                    @endauth
                </div>
            </section>
        </main>
    </body>
</html>
