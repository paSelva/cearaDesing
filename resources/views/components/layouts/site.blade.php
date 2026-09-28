<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-stone-950 text-stone-50 antialiased">
        <header class="border-b border-stone-800">
            <nav class="mx-auto flex max-w-4xl items-center justify-between px-6 py-4">
                <a href="{{ url('/') }}" class="text-sm font-semibold uppercase tracking-[0.22em] text-amber-300">Ceará Tem Design</a>
                <div class="flex items-center gap-4 text-sm">
                    @auth
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="text-stone-300 hover:text-stone-50">Sair</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="text-stone-300 hover:text-stone-50">Entrar</a>
                        <a href="{{ route('register') }}" class="rounded-md bg-amber-300 px-3 py-1.5 font-semibold text-stone-950 hover:bg-amber-200">Criar conta</a>
                    @endauth
                </div>
            </nav>
        </header>
        <main class="mx-auto max-w-4xl px-6 py-12">
            {{ $slot }}
        </main>
    </body>
</html>
