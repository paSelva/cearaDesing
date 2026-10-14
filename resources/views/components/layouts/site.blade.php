<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ isset($title) ? $title.' · ' : '' }}Prêmio Ceará Tem Design</title>
        <link rel="icon" href="{{ asset('images/associacao-ceara-design.png') }}">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="site flex min-h-screen flex-col bg-white font-texto text-grafite antialiased">
        <a href="#conteudo" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:bg-tinta focus:px-4 focus:py-2 focus:text-white">Ir para o conteúdo</a>

        <header class="border-b border-tinta/10 bg-white">
            <nav class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4 px-6 py-4" aria-label="Principal">
                <a href="{{ url('/') }}" aria-label="Prêmio Ceará Tem Design — início">
                    <img src="{{ asset('images/associacao-ceara-design.png') }}" alt="Associação Ceará Design" width="122" height="52" class="h-12 w-auto">
                </a>
                <div class="flex items-center gap-5 text-[0.95rem] font-semibold">
                    @auth
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="site-link text-tinta">Sair</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="site-link text-tinta">Entrar</a>
                        <a href="{{ route('register') }}" class="site-button">Criar conta</a>
                    @endauth
                </div>
            </nav>
        </header>

        <main id="conteudo" class="mx-auto w-full max-w-5xl flex-1 px-6 py-12 md:py-16">
            {{ $slot }}
        </main>

        <footer class="border-t border-tinta/10">
            <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4 px-6 py-6 text-sm">
                <p>1º Prêmio Ceará Tem Design, realização da Associação Ceará Design.</p>
                <a href="{{ url('/') }}" class="site-link font-semibold text-tinta">Voltar ao site do prêmio</a>
            </div>
        </footer>
    </body>
</html>
