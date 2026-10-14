@php
    /*
     | Site público do 1º Prêmio Ceará Tem Design (P26). Textos do edital (§1–§8); datas,
     | categorias e faixas vêm do banco. Identidade visual da Associação Ceará Design.
     */
    $date = fn ($value, string $format = 'j \d\e F') => $value?->locale('pt_BR')->translatedFormat($format);

    // Descrições dos critérios (§5).
    $criteriaText = [
        'Conceito e criatividade' => 'Qualidade da ideia, da abordagem e das soluções apresentadas.',
        'Qualidade do projeto' => 'Qualidade técnica, execução e coerência do resultado.',
        'Adequação' => 'O quanto a solução responde ao seu propósito, público e contexto.',
        'Relevância' => 'Contribuição, impacto ou diferencial apresentado pelo projeto.',
    ];

    $scale = [1 => 'Insuficiente', 2 => 'Regular', 3 => 'Bom', 4 => 'Muito bom', 5 => 'Excelente'];
    $maxScore = $edition ? $edition->judges_per_entry * 20 : 60;
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="1º Prêmio Ceará Tem Design 2026, da Associação Ceará Design: reconhecer, valorizar e dar visibilidade ao design feito no Ceará. Inscrições gratuitas.">
        <title>1º Prêmio Ceará Tem Design 2026</title>
        <link rel="icon" href="{{ asset('images/associacao-ceara-design.png') }}">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="site bg-white font-texto text-grafite antialiased">
        <a href="#conteudo" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:bg-tinta focus:px-4 focus:py-2 focus:text-white">Ir para o conteúdo</a>

        <header class="border-b border-tinta/10 bg-white">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-x-8 gap-y-4 px-6 py-5">
                <a href="{{ route('home') }}" class="flex items-center gap-4" aria-label="Prêmio Ceará Tem Design — início">
                    <img src="{{ asset('images/associacao-ceara-design.png') }}" alt="Associação Ceará Design" width="122" height="52" class="h-14 w-auto">
                </a>
                <nav aria-label="Seções" class="order-3 w-full md:order-none md:w-auto">
                    <ul class="flex flex-wrap gap-x-6 gap-y-2 text-[0.95rem] font-semibold text-tinta">
                        <li><a href="#sobre" class="site-link">Sobre</a></li>
                        <li><a href="#categorias" class="site-link">Categorias</a></li>
                        <li><a href="#avaliacao" class="site-link">Avaliação</a></li>
                        <li><a href="#datas" class="site-link">Datas</a></li>
                        <li><a href="#como-participar" class="site-link">Como participar</a></li>
                        @if ($galleryOpen)
                            <li><a href="{{ route('gallery.index') }}" class="site-link">Galeria</a></li>
                        @endif
                        @if ($resultsPublished)
                            <li><a href="{{ route('results.index') }}" class="site-link">Premiados</a></li>
                        @endif
                    </ul>
                </nav>
                <div class="flex items-center gap-5 text-[0.95rem] font-semibold">
                    @auth
                        <a href="{{ route('participant.dashboard') }}" class="site-button">Área do participante</a>
                    @else
                        <a href="{{ route('login') }}" class="site-link text-tinta">Entrar</a>
                        <a href="{{ route('register') }}" class="site-button">Inscrever projeto</a>
                    @endauth
                </div>
            </div>
        </header>

        <main id="conteudo">
            {{-- Capa: o acento laranja do logo como marca do prêmio --}}
            <section class="relative overflow-hidden bg-amarelo" aria-labelledby="titulo">
                <div class="mx-auto grid max-w-6xl gap-10 px-6 pb-20 pt-16 md:grid-cols-[1fr_auto] md:items-end md:pb-28 md:pt-24">
                    <div class="relative z-10 max-w-3xl">
                        <p class="font-display text-xl font-medium text-tinta">1ª edição, 2026</p>
                        <h1 id="titulo" class="mt-4 font-display text-[3.4rem] font-bold uppercase leading-[0.88] tracking-tight text-tinta sm:text-7xl lg:text-[7.5rem]">
                            Prêmio<br>Ceará tem<br>design
                        </h1>
                        <p class="mt-8 max-w-xl text-xl leading-8 text-tinta">
                            Para reconhecer, valorizar e dar visibilidade ao design feito no Ceará.
                            Porque o Ceará tem design — e queremos mostrar.
                        </p>

                        @if ($edition)
                            <p class="mt-8 text-lg text-tinta">
                                Inscrições gratuitas de {{ $date($edition->registration_starts_at) }}
                                a {{ $date($edition->registration_ends_at, 'j \d\e F \d\e Y') }}.
                            </p>
                        @endif

                        <div class="mt-8 flex flex-wrap items-center gap-6">
                            @if ($registrationOpen)
                                <a href="{{ route('register') }}" class="site-button site-button--grande">Inscrever projeto</a>
                            @endif
                            @if ($resultsPublished)
                                <a href="{{ route('results.index') }}" class="site-button site-button--grande">Ver os premiados</a>
                            @elseif ($votingOpen)
                                <a href="{{ route('gallery.index') }}" class="site-button site-button--grande">Ver a galeria e votar</a>
                            @endif
                            <a href="#categorias" class="site-link text-lg font-semibold text-tinta">Conhecer as categorias</a>
                        </div>
                    </div>

                    <svg class="pointer-events-none absolute -right-6 -top-4 h-40 w-auto text-laranja sm:-right-10 sm:top-6 sm:h-72 md:static md:h-[30rem] lg:h-[34rem]" viewBox="0 0 120 200" aria-hidden="true" focusable="false">
                        <path fill="currentColor" d="M86 6c18 0 30 13 26 33L53 184c-4 10-15 14-24 10-9-4-13-14-10-24L62 26C66 13 74 6 86 6Z"/>
                    </svg>
                </div>
            </section>

            {{-- §1 --}}
            <section id="sobre" class="site-section" aria-labelledby="sobre-titulo">
                <div class="mx-auto grid max-w-6xl gap-10 px-6 md:grid-cols-[18rem_1fr]">
                    <h2 id="sobre-titulo" class="site-h2">Sobre o prêmio</h2>
                    <div class="site-prose">
                        <p>A Associação Ceará Design realiza a primeira edição do Prêmio Ceará Tem Design, criado para reconhecer, valorizar e dar visibilidade ao design feito no Ceará.</p>
                        <p>O prêmio reúne profissionais, estudantes, empresas, organizações e instituições e busca mostrar a diversidade e a qualidade da produção cearense.</p>
                        <p>Entendemos o design como um campo amplo e diverso. Por isso, as categorias do prêmio representam grandes áreas de atuação e procuram acolher diferentes formas de pensar, criar e fazer design.</p>
                        <p>Mais do que estabelecer primeiros ou segundos lugares, o Ceará Tem Design reconhece níveis de excelência, representados pelas distinções Ouro, Prata, Bronze e Menção Honrosa.</p>
                        <p>Queremos também começar a construir um panorama do design que vem sendo pensado, criado e realizado no Ceará.</p>
                    </div>
                </div>
            </section>

            {{-- §3 --}}
            <section id="categorias" class="site-section border-t border-tinta/10" aria-labelledby="categorias-titulo">
                <div class="mx-auto max-w-6xl px-6">
                    <div class="grid gap-6 md:grid-cols-[18rem_1fr]">
                        <h2 id="categorias-titulo" class="site-h2">Categorias</h2>
                        <p class="site-lead">Na inscrição, você escolhe a categoria que melhor representa o projeto. Cada participante pode inscrever um projeto por categoria.</p>
                    </div>

                    <ul class="mt-14 grid gap-x-10 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($categories as $category)
                            <li class="border-t-2 border-tinta pt-5">
                                <p class="font-display text-lg font-bold text-laranja-escuro">{{ $category->code }}</p>
                                <h3 class="mt-1 font-display text-2xl font-bold leading-tight text-tinta">{{ $category->name }}</h3>
                                <p class="mt-3 leading-7">{{ $category->description }}</p>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>

            {{-- §5 e §6 --}}
            <section id="avaliacao" class="site-section bg-tinta text-white" aria-labelledby="avaliacao-titulo">
                <div class="mx-auto max-w-6xl px-6">
                    <div class="grid gap-6 md:grid-cols-[18rem_1fr]">
                        <h2 id="avaliacao-titulo" class="site-h2 text-white">Avaliação</h2>
                        <p class="site-lead text-white/85">
                            Cada projeto é avaliado individualmente por {{ $edition?->judges_per_entry ?? 3 }} jurados convidados, com experiência relacionada à área avaliada.
                            Cada jurado dá uma nota de 1 a 5 para cada um dos quatro critérios, até 20 pontos.
                            A nota final é a soma das avaliações, com no máximo {{ $maxScore }} pontos.
                        </p>
                    </div>

                    <div class="mt-14 grid gap-12 lg:grid-cols-[3fr_2fr]">
                        <div>
                            <h3 class="font-display text-2xl font-bold">Critérios</h3>
                            <dl class="mt-6 grid gap-6 sm:grid-cols-2">
                                @foreach ($defaultCriteria as $criterion)
                                    <div class="border-l-4 border-laranja pl-4">
                                        <dt class="font-semibold text-white">{{ $criterion->name }}</dt>
                                        <dd class="mt-1 text-white/80">{{ $criteriaText[$criterion->name] ?? '' }}</dd>
                                    </div>
                                @endforeach
                            </dl>

                            @if ($exportCategory)
                                <p class="mt-10 max-w-2xl text-white/80">
                                    A <strong class="text-white">{{ $exportCategory->name }}</strong> segue o mesmo sistema de notas, avaliando:
                                    {{ $exportCategory->criteria->pluck('name')->map(fn ($name) => mb_strtolower($name))->join(', ', ' e ') }}.
                                </p>
                            @endif
                        </div>

                        <div>
                            <h3 class="font-display text-2xl font-bold">Notas</h3>
                            <ol class="mt-6 divide-y divide-white/15 border-y border-white/15">
                                @foreach ($scale as $value => $label)
                                    <li class="flex items-baseline gap-5 py-3">
                                        <span class="w-6 font-display text-2xl font-bold text-amarelo">{{ $value }}</span>
                                        <span>{{ $label }}</span>
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    </div>

                    <div class="mt-20">
                        <h3 class="font-display text-2xl font-bold">Premiação</h3>
                        <p class="mt-3 max-w-2xl text-white/80">
                            O prêmio reconhece níveis de excelência: não há primeiro, segundo ou terceiro lugar. Uma mesma categoria pode ter mais de um Ouro, Prata, Bronze ou Menção Honrosa,
                            e uma distinção pode não ser concedida se nenhum projeto alcançar a pontuação.
                        </p>

                        @if ($edition)
                            @php
                                $bands = [
                                    ['Ouro', $edition->band_gold_min, $maxScore, 'bg-ouro', 'Troféu, certificado e divulgação institucional'],
                                    ['Prata', $edition->band_silver_min, $edition->band_gold_min - 1, 'bg-prata', 'Troféu, certificado e divulgação institucional'],
                                    ['Bronze', $edition->band_bronze_min, $edition->band_silver_min - 1, 'bg-bronze', 'Troféu, certificado e divulgação institucional'],
                                    ['Menção Honrosa', $edition->band_honorable_min, $edition->band_bronze_min - 1, 'bg-white/60', 'Certificado e divulgação institucional'],
                                ];
                            @endphp
                            <ul class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                @foreach ($bands as [$name, $from, $to, $color, $prize])
                                    <li class="bg-white/5 p-5">
                                        <span class="block h-2 w-12 {{ $color }}" aria-hidden="true"></span>
                                        <p class="mt-4 font-display text-2xl font-bold">{{ $name }}</p>
                                        <p class="mt-1 font-display text-lg text-amarelo">{{ $from }} a {{ $to }} pontos</p>
                                        <p class="mt-3 text-sm text-white/75">{{ $prize }}</p>
                                    </li>
                                @endforeach
                            </ul>
                            <p class="mt-6 text-sm text-white/70">Projetos com menos de {{ $edition->band_honorable_min }} pontos não recebem distinção. A premiação não envolve pagamento em dinheiro.</p>
                        @endif
                    </div>
                </div>
            </section>

            {{-- §7 --}}
            <section id="datas" class="site-section" aria-labelledby="datas-titulo">
                <div class="mx-auto max-w-6xl px-6">
                    <div class="grid gap-6 md:grid-cols-[18rem_1fr]">
                        <h2 id="datas-titulo" class="site-h2">Datas</h2>
                        <p class="site-lead">Se alguma data mudar, a organização avisa pelos canais oficiais do prêmio.</p>
                    </div>

                    @if ($edition)
                        @php
                            $timeline = [
                                ['Lançamento do edital', '28 de setembro'],
                                ['Inscrições', $date($edition->registration_starts_at).' a '.$date($edition->registration_ends_at)],
                                ['Avaliação dos projetos', $date($edition->judging_starts_at).' a '.$date($edition->judging_ends_at)],
                                ['Divulgação do resultado', $date($edition->results_announce_on)],
                                ['Cerimônia de premiação', $date($edition->ceremony_on)],
                            ];
                        @endphp
                        <ol class="mt-14 grid gap-8 sm:grid-cols-2 lg:grid-cols-5">
                            @foreach ($timeline as [$label, $when])
                                <li class="border-t-4 border-laranja pt-4">
                                    <p class="font-display text-2xl font-bold leading-tight text-tinta">{{ $when }}</p>
                                    <p class="mt-2">{{ $label }}</p>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>
            </section>

            {{-- §2 e §4 --}}
            <section id="como-participar" class="site-section border-t border-tinta/10" aria-labelledby="participar-titulo">
                <div class="mx-auto max-w-6xl px-6">
                    <div class="grid gap-6 md:grid-cols-[18rem_1fr]">
                        <h2 id="participar-titulo" class="site-h2">Como participar</h2>
                        <p class="site-lead">A inscrição é gratuita e feita pelo formulário oficial do prêmio, neste site.</p>
                    </div>

                    <div class="mt-14 grid gap-14 lg:grid-cols-2">
                        <div class="space-y-10">
                            <div>
                                <h3 class="site-h3">Quem pode participar</h3>
                                <ul class="site-list mt-4">
                                    <li>Profissionais de design</li>
                                    <li>Escritórios, estúdios, equipes e coletivos</li>
                                    <li>Estudantes</li>
                                    <li>Empresas</li>
                                    <li>Instituições públicas e privadas</li>
                                    <li>Organizações da sociedade civil</li>
                                </ul>
                                <p class="mt-4">O projeto deve ter relação com o Ceará por meio da autoria, da equipe, da organização responsável, do local de desenvolvimento ou de realização.</p>
                            </div>
                            @if ($edition)
                                <div>
                                    <h3 class="site-h3">Período dos projetos</h3>
                                    <p class="mt-4">
                                        Por ser a primeira edição, o período é ampliado: são aceitos projetos concluídos, lançados, publicados ou implementados
                                        entre {{ $date($edition->project_period_starts_on, 'j \d\e F \d\e Y') }} e {{ $date($edition->project_period_ends_on, 'j \d\e F \d\e Y') }}.
                                        Quando necessário, a organização pode pedir informações para confirmar o período ou a relação do projeto com o Ceará.
                                    </p>
                                </div>
                            @endif
                        </div>

                        <div>
                            <h3 class="site-h3">Passo a passo</h3>
                            <ol class="mt-6 space-y-6">
                                <li class="site-step">
                                    <span class="site-step__n">1</span>
                                    <div><p class="font-semibold text-tinta">Crie sua conta</p><p>Como pessoa física (CPF) ou jurídica (CNPJ).</p></div>
                                </li>
                                <li class="site-step">
                                    <span class="site-step__n">2</span>
                                    <div><p class="font-semibold text-tinta">Escolha a categoria</p><p>Um projeto por categoria.</p></div>
                                </li>
                                <li class="site-step">
                                    <span class="site-step__n">3</span>
                                    <div>
                                        <p class="font-semibold text-tinta">Conte sobre o projeto</p>
                                        <p>Nome, autoria e principais créditos, breve apresentação, contexto e solução desenvolvida, principais resultados ou diferenciais, imagens e, quando necessários, links ou vídeos.</p>
                                    </div>
                                </li>
                                <li class="site-step">
                                    <span class="site-step__n">4</span>
                                    <div><p class="font-semibold text-tinta">Envie a inscrição</p><p>Não é preciso enviar documentos nem comprovantes nesta etapa; a organização pode pedir depois, se necessário.</p></div>
                                </li>
                            </ol>
                            @if ($registrationOpen)
                                <a href="{{ route('register') }}" class="site-button site-button--grande mt-10">Inscrever projeto</a>
                            @endif
                        </div>
                    </div>
                </div>
            </section>

            {{-- §8 --}}
            <section id="regras" class="site-section bg-amarelo/25" aria-labelledby="regras-titulo">
                <div class="mx-auto grid max-w-6xl gap-10 px-6 md:grid-cols-[18rem_1fr]">
                    <h2 id="regras-titulo" class="site-h2">Regras gerais</h2>
                    <ul class="site-list site-prose">
                        <li>Ao se inscrever, você confirma que as informações são verdadeiras, que tem autorização para inscrever o projeto e para usar as imagens e materiais enviados, e que informou corretamente a autoria e os principais créditos.</li>
                        <li>O uso de inteligência artificial não impede a participação. Quando a IA generativa tiver participação relevante na criação ou produção do projeto, o uso deve ser informado na inscrição.</li>
                        <li>A inscrição não transfere para a Associação Ceará Design os direitos autorais do projeto.</li>
                        <li>Você autoriza o uso das imagens, informações e créditos enviados para avaliação, divulgação, exposição, registro e memória institucional do prêmio.</li>
                        <li>Os dados pessoais são usados para a organização, a avaliação e a comunicação do prêmio, de acordo com a legislação.</li>
                        <li>Informações falsas, problemas de autoria, violação de direitos de terceiros ou descumprimento das regras podem levar à desclassificação.</li>
                        <li>As notas dos jurados são finais quanto ao mérito. Pedidos de revisão são analisados somente em caso de erro na inscrição, na soma das notas ou na aplicação das regras do edital.</li>
                    </ul>
                </div>
            </section>

            {{-- Parceiros, diretoria e avaliadores (P26, P59, P61): aparecem quando houver conteúdo cadastrado. --}}
            @if ($partners->isNotEmpty() || $boardMembers->isNotEmpty())
                <section id="parceiros" class="site-section" aria-labelledby="parceiros-titulo">
                    <div class="mx-auto max-w-6xl space-y-16 px-6">
                        @if ($partners->isNotEmpty())
                            <div>
                                <h2 id="parceiros-titulo" class="site-h2">Parceiros</h2>
                                <ul class="mt-10 flex flex-wrap items-center gap-10">
                                    @foreach ($partners as $partner)
                                        <li>{{ $partner->name }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        @if ($boardMembers->isNotEmpty())
                            <div>
                                <h2 class="site-h2">Diretoria</h2>
                                <ul class="mt-10 grid gap-8 sm:grid-cols-3">
                                    @foreach ($boardMembers as $member)
                                        <li><p class="font-semibold text-tinta">{{ $member->name }}</p><p>{{ $member->role_title }}</p></li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </section>
            @endif

            @if ($judges->isNotEmpty())
                <section id="avaliadores" class="site-section border-t border-tinta/10" aria-labelledby="avaliadores-titulo">
                    <div class="mx-auto max-w-6xl px-6">
                        <h2 id="avaliadores-titulo" class="site-h2">Avaliadores</h2>
                        <ul class="mt-10 grid gap-10 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($judges as $judge)
                                <li><p class="font-display text-xl font-bold text-tinta">{{ $judge->name }}</p><p class="mt-2">{{ $judge->bio }}</p></li>
                            @endforeach
                        </ul>
                    </div>
                </section>
            @endif
        </main>

        <footer class="border-t border-tinta/10 bg-white">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-6 px-6 py-10 text-sm">
                <div class="flex items-center gap-5">
                    <img src="{{ asset('images/associacao-ceara-design.png') }}" alt="Associação Ceará Design" width="122" height="52" class="h-10 w-auto">
                    <p>Realização da Associação Ceará Design.<br>Fortaleza, Ceará.</p>
                </div>
                <ul class="flex flex-wrap gap-x-6 gap-y-2 font-semibold">
                    <li><a href="https://www.cearadesign.org.br" class="site-link" rel="noopener">cearadesign.org.br</a></li>
                    <li><a href="https://www.instagram.com/cearatemdesign" class="site-link" rel="noopener">@cearatemdesign</a></li>
                </ul>
            </div>
        </footer>
    </body>
</html>
