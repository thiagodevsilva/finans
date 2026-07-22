<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Levita — Finanças da Família</title>
    <meta name="description" content="Organize as finanças da sua família em um só lugar. Conta compartilhada para casais e famílias.">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css'])
</head>
<body class="font-sans antialiased text-navy-700 bg-white overflow-x-hidden">
    <header>
        <nav class="bg-brand-500">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
                <a href="{{ route('home') }}" class="flex items-center gap-2">
                    <img src="{{ asset('images/logo.png') }}" alt="Levita" class="h-10 w-auto">
                    <span class="text-xl font-bold tracking-tight text-white">Levita</span>
                </a>
                <div class="flex items-center gap-3">
                    @auth
                        <a href="{{ route('dashboard') }}" class="rounded-xl bg-cta px-4 py-2 text-sm font-semibold text-white hover:bg-cta-dark">Ir ao app</a>
                    @else
                        <a href="{{ route('login') }}" class="rounded-xl border border-white px-4 py-2 text-sm font-semibold text-white hover:bg-white/10">Entrar</a>
                        <a href="{{ route('register') }}" class="rounded-xl bg-cta px-4 py-2 text-sm font-semibold text-white hover:bg-cta-dark">Criar conta</a>
                    @endauth
                </div>
            </div>
        </nav>
    </header>

    <section class="overflow-hidden bg-brand-500">
        <div class="mx-auto grid max-w-6xl md:min-h-[520px] md:grid-cols-2">
            <div class="flex flex-col justify-center px-4 py-14 text-white md:py-16">
                <h1 class="text-4xl font-bold leading-tight md:text-5xl">Finanças da família, sem complicação</h1>
                <p class="mt-4 text-lg text-white/90">
                    O Levita é o espaço compartilhado para você e sua família registrarem entradas, saídas e categorias no mesmo ambiente.
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('register') }}" class="rounded-xl bg-cta px-6 py-3 text-base font-semibold text-white hover:bg-cta-dark">Começar grátis</a>
                    <a href="{{ route('login') }}" class="rounded-xl border border-white px-6 py-3 text-base font-semibold text-white hover:bg-white/10">Já tenho conta</a>
                </div>
            </div>
            <div class="relative hidden min-h-[320px] md:block">
                <img
                    src="{{ asset('images/capa-mulher.png') }}"
                    alt="Levita"
                    class="absolute bottom-0 left-1/2 h-full max-h-[520px] w-auto max-w-none -translate-x-1/2 object-contain object-bottom"
                    draggable="false"
                >
            </div>
        </div>
    </section>

    <section class="border-b border-[#e5e5e5] py-16">
        <div class="mx-auto grid max-w-6xl items-center gap-10 px-4 md:grid-cols-2">
            <div>
                <img src="{{ asset('images/saiba.png') }}" alt="Categorias e gráficos" class="mx-auto max-h-72">
            </div>
            <div>
                <h2 class="text-3xl font-bold">Saibam juntos para onde vai o dinheiro</h2>
                <p class="mt-4 text-slate-600">Categorize gastos, acompanhe o mês e veja quem lançou cada movimentação — tudo na mesma conta familiar.</p>
            </div>
        </div>
    </section>

    <section class="border-b border-[#e5e5e5] py-16">
        <div class="mx-auto grid max-w-6xl items-center gap-10 px-4 md:grid-cols-2">
            <div class="order-2 md:order-1">
                <h2 class="text-3xl font-bold">Conta compartilhada de verdade</h2>
                <p class="mt-4 text-slate-600">O dono da conta convida dependentes (como o cônjuge). Todos visualizam e lançam; só o dono gerencia categorias e membros.</p>
            </div>
            <div class="order-1 md:order-2">
                <img src="{{ asset('images/juros.png') }}" alt="Controle familiar" class="mx-auto max-h-72">
            </div>
        </div>
    </section>

    <section class="py-16">
        <div class="mx-auto max-w-6xl px-4 text-center">
            <h2 class="text-3xl font-bold">Por que Levita?</h2>
            <div class="mt-12 grid gap-10 md:grid-cols-3">
                <div>
                    <img src="{{ asset('images/facil.png') }}" alt="" class="mx-auto h-20">
                    <h3 class="mt-4 text-xl font-semibold">Fácil</h3>
                    <p class="mt-2 text-slate-600">Interface limpa em português, pensada para o dia a dia da família.</p>
                </div>
                <div>
                    <img src="{{ asset('images/economize.png') }}" alt="" class="mx-auto h-20">
                    <h3 class="mt-4 text-xl font-semibold">Organizado</h3>
                    <p class="mt-2 text-slate-600">Dashboard, filtros por mês e relatórios com gráficos.</p>
                </div>
                <div>
                    <img src="{{ asset('images/suporte.png') }}" alt="" class="mx-auto h-20">
                    <h3 class="mt-4 text-xl font-semibold">Compartilhado</h3>
                    <p class="mt-2 text-slate-600">Owner e dependentes no mesmo ambiente, com permissões claras.</p>
                </div>
            </div>
        </div>
    </section>

    <footer class="bg-slate-900 py-10 text-slate-300">
        <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-4 md:flex-row">
            <p class="font-semibold text-white">Levita</p>
            <p class="text-sm">Finanças da família · {{ date('Y') }}</p>
        </div>
    </footer>
</body>
</html>
