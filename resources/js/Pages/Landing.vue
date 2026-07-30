<script setup>
import AppMark from '@/Components/AppMark.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const year = new Date().getFullYear();
const appUrl = computed(() => page.props.app?.url || '');
const appName = computed(() => page.props.app?.name || 'Levita');

const seoTitle = 'App de finanças gratuito e simples para famílias e casais';
const seoDescription =
    'Levita é um app de finanças gratuito e simples: controle de gastos do dia a dia, orçamento compartilhado para solteiros, casais e famílias — sem planilha e sem mensalidade.';
const seoKeywords = [
    'app de finanças gratuito',
    'app financeiro grátis',
    'controle financeiro grátis',
    'controle financeiro simples',
    'finanças pessoais grátis',
    'finanças da família',
    'orçamento familiar grátis',
    'controle de gastos',
    'gastos do dia a dia',
    'organizar finanças',
    'app de orçamento',
    'finanças casal',
    'controle financeiro familiar',
    'gestão financeira doméstica',
    'Levita',
].join(', ');

const ogImage = computed(() => `${appUrl.value}/images/og-image.png`);
const canonical = computed(() => `${appUrl.value}/`);
const fullTitle = computed(() => `${seoTitle} | ${appName.value}`);

const jsonLd = computed(() =>
    JSON.stringify({
        '@context': 'https://schema.org',
        '@graph': [
            {
                '@type': 'WebSite',
                name: appName.value,
                url: canonical.value,
                inLanguage: 'pt-BR',
                description: seoDescription,
            },
            {
                '@type': 'SoftwareApplication',
                name: appName.value,
                applicationCategory: 'FinanceApplication',
                operatingSystem: 'Web',
                isAccessibleForFree: true,
                offers: {
                    '@type': 'Offer',
                    price: '0',
                    priceCurrency: 'BRL',
                    availability: 'https://schema.org/InStock',
                },
                description: seoDescription,
                inLanguage: 'pt-BR',
                url: canonical.value,
                image: ogImage.value,
                featureList: [
                    '100% gratuito para começar',
                    'Simples de usar, em português',
                    'Controle de finanças da família',
                    'Gastos do dia a dia e orçamento compartilhado',
                    'Cartões, contas bancárias e contas fixas',
                    'Categorias e relatórios',
                ],
            },
            {
                '@type': 'Organization',
                name: appName.value,
                url: canonical.value,
                logo: `${appUrl.value}/apple-touch-icon.png`,
            },
            {
                '@type': 'FAQPage',
                mainEntity: [
                    {
                        '@type': 'Question',
                        name: 'O Levita é um app de finanças gratuito?',
                        acceptedAnswer: {
                            '@type': 'Answer',
                            text: 'Sim. O Levita é gratuito para começar: você cria a conta, registra gastos e organiza o orçamento da família sem mensalidade.',
                        },
                    },
                    {
                        '@type': 'Question',
                        name: 'O Levita é simples de usar?',
                        acceptedAnswer: {
                            '@type': 'Answer',
                            text: 'Foi feito para ser simples: interface limpa em português, lançamentos rápidos e visão clara do mês — sem planilha confusa.',
                        },
                    },
                    {
                        '@type': 'Question',
                        name: 'Serve para casais e famílias?',
                        acceptedAnswer: {
                            '@type': 'Answer',
                            text: 'Sim. Dá para usar sozinho ou convidar quem divide a casa. Todos registram gastos no mesmo ambiente, com papéis claros.',
                        },
                    },
                ],
            },
        ],
    }),
);
</script>

<template>
    <Head :title="seoTitle">
        <meta head-key="description" name="description" :content="seoDescription" />
        <meta head-key="keywords" name="keywords" :content="seoKeywords" />
        <meta head-key="robots" name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1" />
        <meta head-key="author" name="author" :content="appName" />
        <link head-key="canonical" rel="canonical" :href="canonical" />

        <meta head-key="og:locale" property="og:locale" content="pt_BR" />
        <meta head-key="og:type" property="og:type" content="website" />
        <meta head-key="og:site_name" property="og:site_name" :content="appName" />
        <meta head-key="og:title" property="og:title" :content="fullTitle" />
        <meta head-key="og:description" property="og:description" :content="seoDescription" />
        <meta head-key="og:url" property="og:url" :content="canonical" />
        <meta head-key="og:image" property="og:image" :content="ogImage" />
        <meta head-key="og:image:alt" property="og:image:alt" content="Levita — app de finanças gratuito e simples para famílias" />

        <meta head-key="twitter:card" name="twitter:card" content="summary_large_image" />
        <meta head-key="twitter:title" name="twitter:title" :content="fullTitle" />
        <meta head-key="twitter:description" name="twitter:description" :content="seoDescription" />
        <meta head-key="twitter:image" name="twitter:image" :content="ogImage" />

        <component :is="'script'" type="application/ld+json" v-text="jsonLd" />
    </Head>

    <div class="overflow-x-hidden bg-white font-sans text-navy-700 antialiased">
        <header>
            <nav class="bg-brand-500">
                <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
                    <Link :href="route('home')" class="flex items-center gap-3">
                        <AppMark :size="44" variant="onBrand" />
                        <span class="text-2xl font-bold tracking-tight text-white md:text-3xl">Levita</span>
                    </Link>
                    <div class="flex items-center gap-3">
                        <Link
                            v-if="user"
                            :href="route('dashboard')"
                            class="rounded-xl bg-cta px-4 py-2 text-sm font-semibold text-white hover:bg-cta-dark"
                        >
                            Ir ao app
                        </Link>
                        <template v-else>
                            <Link
                                :href="route('login')"
                                class="rounded-xl border border-white px-4 py-2 text-sm font-semibold text-white hover:bg-white/10"
                            >
                                Entrar
                            </Link>
                            <Link
                                :href="route('register')"
                                class="rounded-xl bg-cta px-4 py-2 text-sm font-semibold text-white hover:bg-cta-dark"
                            >
                                Criar conta
                            </Link>
                        </template>
                    </div>
                </div>
            </nav>
        </header>

        <section class="overflow-hidden bg-brand-500">
            <div class="mx-auto grid max-w-6xl md:min-h-[520px] md:grid-cols-2">
                <div class="flex flex-col justify-center px-4 py-14 text-white md:py-16">
                    <h1 class="text-4xl font-bold leading-tight md:text-5xl">
                        App de finanças gratuito e simples para a sua família
                    </h1>
                    <p class="mt-4 text-lg text-white/90">
                        Organize o orçamento sem planilha e sem mensalidade. Controle gastos do dia a dia com quem divide a casa — em poucos minutos.
                    </p>
                    <p class="mt-4 inline-flex w-max max-w-full rounded-full bg-cta px-4 py-1.5 text-sm font-semibold text-white md:text-base">
                        Grátis · Simples · Para solteiros, casais e famílias
                    </p>
                    <div class="mt-8 flex flex-wrap items-center gap-3">
                        <Link
                            :href="route('register')"
                            class="rounded-xl border border-white px-6 py-3 text-base font-semibold text-white hover:bg-white/10"
                        >
                            Começar agora (É grátis)
                        </Link>
                        <Link
                            :href="route('login')"
                            class="rounded-xl border border-white px-6 py-3 text-base font-semibold text-white hover:bg-white/10"
                        >
                            Já tenho conta
                        </Link>
                    </div>
                </div>
                <div class="relative hidden min-h-[320px] md:block">
                    <img
                        src="/images/capa-mulher.png"
                        alt="Levita — app de finanças gratuito para famílias"
                        class="absolute bottom-0 left-1/2 h-[min(100%,520px)] w-auto max-w-none -translate-x-1/2 object-contain object-bottom"
                        style="aspect-ratio: 1024 / 1536;"
                        draggable="false"
                        width="1024"
                        height="1536"
                        fetchpriority="high"
                    >
                </div>
            </div>
        </section>

        <section class="border-b border-[#e5e5e5] py-16">
            <div class="mx-auto grid max-w-6xl items-center gap-10 px-4 md:grid-cols-2">
                <div class="overflow-hidden rounded-2.5xl bg-gradient-to-br from-lightPrimary to-brand-50 shadow-soft transition duration-300 hover:shadow-lg">
                    <img
                        src="/images/saiba.png"
                        alt="Controle de gastos e categorias no Levita"
                        class="aspect-[4/3] w-full object-cover transition duration-500 hover:scale-[1.02]"
                        loading="lazy"
                        width="1536"
                        height="1024"
                    >
                </div>
                <div>
                    <h2 class="text-3xl font-bold">Chega de se perguntar para onde o dinheiro foi</h2>
                    <p class="mt-4 text-slate-600">
                        Categorize os gastos da casa, acompanhe a evolução do mês em tempo real e saiba exatamente quem lançou cada despesa — tudo reunido em um só painel.
                    </p>
                </div>
            </div>
        </section>

        <section class="border-b border-[#e5e5e5] py-16">
            <div class="mx-auto grid max-w-6xl items-center gap-10 px-4 md:grid-cols-2">
                <div class="order-2 md:order-1">
                    <h2 class="text-3xl font-bold">Planejamento financeiro pensado a dois ou para toda a família</h2>
                    <p class="mt-4 text-slate-600">
                        Convide o seu cônjuge ou familiares para somar. Todo mundo participa registrando os gastos do dia a dia, enquanto você mantém o controle total das configurações.
                    </p>
                </div>
                <div class="order-1 overflow-hidden rounded-2.5xl bg-gradient-to-br from-lightPrimary to-brand-50 shadow-soft transition duration-300 hover:shadow-lg md:order-2">
                    <img
                        src="/images/juros.png"
                        alt="Orçamento familiar compartilhado no Levita"
                        class="aspect-[4/3] w-full object-cover transition duration-500 hover:scale-[1.02]"
                        loading="lazy"
                        width="1536"
                        height="1024"
                    >
                </div>
            </div>
        </section>

        <section class="py-16">
            <div class="mx-auto max-w-6xl px-4 text-center">
                <h2 class="text-3xl font-bold">Por que escolher um app de finanças gratuito e simples?</h2>
                <div class="mt-12 grid gap-10 md:grid-cols-3">
                    <div class="group">
                        <div class="mx-auto overflow-hidden rounded-2.5xl bg-gradient-to-br from-lightPrimary to-brand-50 shadow-soft transition duration-300 group-hover:shadow-lg">
                            <img
                                src="/images/facil.png"
                                alt="Interface simples do Levita"
                                class="aspect-square w-full object-cover transition duration-500 group-hover:scale-[1.03]"
                                loading="lazy"
                                width="1024"
                                height="1024"
                            >
                        </div>
                        <h3 class="mt-4 text-xl font-semibold">Simples de usar</h3>
                        <p class="mt-2 text-slate-600">Interface limpa, intuitiva e em português. Sem planilhas confusas ou funções desnecessárias.</p>
                    </div>
                    <div class="group">
                        <div class="mx-auto overflow-hidden rounded-2.5xl bg-gradient-to-br from-lightPrimary to-brand-50 shadow-soft transition duration-300 group-hover:shadow-lg">
                            <img
                                src="/images/economize.png"
                                alt="Visão clara do orçamento no Levita"
                                class="aspect-square w-full object-cover transition duration-500 group-hover:scale-[1.03]"
                                loading="lazy"
                                width="1024"
                                height="1024"
                            >
                        </div>
                        <h3 class="mt-4 text-xl font-semibold">Visão clara do mês</h3>
                        <p class="mt-2 text-slate-600">Gráficos práticos e relatórios diretos para você entender para onde o orçamento está indo com um olhar.</p>
                    </div>
                    <div class="group">
                        <div class="mx-auto overflow-hidden rounded-2.5xl bg-gradient-to-br from-lightPrimary to-brand-50 shadow-soft transition duration-300 group-hover:shadow-lg">
                            <img
                                src="/images/suporte.png"
                                alt="Finanças em família no Levita"
                                class="aspect-square w-full object-cover transition duration-500 group-hover:scale-[1.03]"
                                loading="lazy"
                                width="1024"
                                height="1024"
                            >
                        </div>
                        <h3 class="mt-4 text-xl font-semibold">Feito para colaborar</h3>
                        <p class="mt-2 text-slate-600">Acesso simultâneo para quem mora com você, com permissões claras e seguras para cada perfil — e sem cobrar por isso.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="bg-brand-500">
            <div class="mx-auto flex max-w-6xl flex-col items-start justify-between gap-6 px-4 py-14 md:flex-row md:items-center">
                <div class="max-w-xl text-white">
                    <h2 class="text-2xl font-bold leading-tight md:text-3xl">
                        Menos dúvida no fim do mês. Mais paz no dia a dia.
                    </h2>
                    <p class="mt-3 text-base text-white/90 md:text-lg">
                        Comece grátis e traga quem divide a casa com você. Em poucos minutos o orçamento da família ganha clareza.
                    </p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <Link
                        v-if="user"
                        :href="route('dashboard')"
                        class="rounded-xl bg-cta px-6 py-3 text-base font-semibold text-white hover:bg-cta-dark"
                    >
                        Ir ao app
                    </Link>
                    <template v-else>
                        <Link
                            :href="route('register')"
                            class="rounded-xl border border-white px-6 py-3 text-base font-semibold text-white hover:bg-white/10"
                        >
                            Começar agora (É grátis)
                        </Link>
                        <Link
                            :href="route('login')"
                            class="rounded-xl border border-white px-6 py-3 text-base font-semibold text-white hover:bg-white/10"
                        >
                            Já tenho conta
                        </Link>
                    </template>
                </div>
            </div>
        </section>

        <footer class="bg-navy-900 text-horizon-300">
            <div class="mx-auto grid max-w-6xl gap-10 px-4 py-12 md:grid-cols-[1.4fr_1fr_1fr]">
                <div>
                    <Link :href="route('home')" class="inline-flex items-center gap-2.5">
                        <AppMark :size="36" />
                        <span class="text-xl font-bold tracking-tight text-white">Levita</span>
                    </Link>
                    <p class="mt-4 max-w-sm text-sm leading-relaxed text-horizon-400">
                        App de finanças gratuito e simples para a família: gastos do dia a dia, orçamento compartilhado e tranquilidade no mês — sem planilha bagunçada.
                    </p>
                </div>

                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-white">Produto</p>
                    <ul class="mt-4 space-y-2 text-sm">
                        <li>
                            <Link :href="route('home')" class="hover:text-brand-400">Início</Link>
                        </li>
                        <li v-if="user">
                            <Link :href="route('dashboard')" class="hover:text-brand-400">Abrir o app</Link>
                        </li>
                        <template v-else>
                            <li>
                                <Link :href="route('register')" class="hover:text-brand-400">Criar conta grátis</Link>
                            </li>
                            <li>
                                <Link :href="route('login')" class="hover:text-brand-400">Entrar</Link>
                            </li>
                        </template>
                    </ul>
                </div>

                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-white">Para quem é</p>
                    <ul class="mt-4 space-y-2 text-sm text-horizon-400">
                        <li>Solteiros organizando o mês</li>
                        <li>Casais que dividem as contas</li>
                        <li>Famílias no dia a dia</li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-white/10">
                <div class="mx-auto flex max-w-6xl flex-col gap-2 px-4 py-6 text-sm text-horizon-500 md:flex-row md:items-center md:justify-between">
                    <p>© {{ year }} Levita. Feito para a vida financeira em sintonia.</p>
                    <p>Controle de gastos · Orçamento familiar · Grátis para começar</p>
                </div>
            </div>
        </footer>
    </div>
</template>
