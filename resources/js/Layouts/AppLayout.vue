<script setup>
import AppMark from '@/Components/AppMark.vue';
import TourHelpButton from '@/Components/TourHelpButton.vue';
import { useAppTour } from '@/Composables/useAppTour';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => page.props.auth.user);
const account = computed(() => page.props.auth.account);
const flash = computed(() => page.props.flash);
const mobileOpen = ref(false);
const { restartOnboarding } = useAppTour();

const links = [
    { name: 'Dashboard', route: 'dashboard', match: 'dashboard' },
    { name: 'Transações', route: 'transactions.index', match: 'transactions.*' },
    { name: 'Contas fixas', route: 'recurring-bills.index', match: 'recurring-bills.*' },
    { name: 'Contas', route: 'bank-accounts.index', match: 'bank-accounts.*', tour: 'nav-bank-accounts' },
    { name: 'Cartões', route: 'payment-cards.index', match: 'payment-cards.*', tour: 'nav-payment-cards' },
    { name: 'Categorias', route: 'categories.index', match: 'categories.*' },
    { name: 'Dependentes', route: 'members.index', match: 'members.*' },
    { name: 'Relatórios', route: 'reports.index', match: 'reports.*' },
];

const isActive = (match) => route().current(match);

const replayGuide = () => {
    restartOnboarding();
};

const onTourSidebar = (event) => {
    mobileOpen.value = Boolean(event.detail?.open);
};

onMounted(() => {
    window.addEventListener('levita:tour-sidebar', onTourSidebar);
});

onUnmounted(() => {
    window.removeEventListener('levita:tour-sidebar', onTourSidebar);
});
</script>

<template>
    <div class="flex min-h-screen w-full bg-lightPrimary">
        <!-- Sidebar estilo Horizon (branco) + acento Levita -->
        <aside
            class="fixed z-50 flex h-full w-[280px] flex-col bg-white pb-6 shadow-2xl shadow-white/5 transition-transform duration-200 xl:static xl:translate-x-0"
            :class="mobileOpen ? 'translate-x-0' : '-translate-x-full xl:translate-x-0'"
        >
            <div class="mx-8 mt-10 flex items-center justify-between">
                <Link :href="route('dashboard')" class="flex items-center gap-2.5">
                    <AppMark :size="40" />
                    <span class="font-dm text-[24px] font-bold tracking-tight text-navy-700">Levita</span>
                </Link>
                <button type="button" class="text-[0.9625rem] text-horizon-500 xl:hidden" @click="mobileOpen = false">✕</button>
            </div>
            <p class="mx-8 mt-1 truncate text-[0.9625rem] text-horizon-500">{{ account?.name }}</p>
            <div class="mb-6 mt-8 h-px bg-horizon-200" />

            <nav class="mb-auto pt-1">
                <Link
                    v-for="link in links"
                    :key="link.route"
                    :href="route(link.route)"
                    class="relative mb-2 flex items-center px-8 py-2.5 hover:cursor-pointer"
                    :data-tour="link.tour || undefined"
                    @click="mobileOpen = false"
                >
                    <span
                        class="text-[0.9625rem]"
                        :class="isActive(link.match) ? 'font-bold text-brand-600' : 'font-medium text-horizon-500'"
                    >
                        {{ link.name }}
                    </span>
                    <div
                        v-if="isActive(link.match)"
                        class="absolute right-0 top-1.5 h-8 w-1 rounded-lg bg-brand-500"
                    />
                </Link>
            </nav>

            <div class="mx-6 rounded-[20px] bg-lightPrimary p-4">
                <p class="text-[0.9625rem] font-bold text-navy-700">{{ user?.name }}</p>
                <p class="text-[0.825rem] text-horizon-500">{{ user?.is_owner ? 'Dono da conta' : 'Dependente' }}</p>
                <div class="mt-3 flex flex-col gap-2">
                    <button
                        type="button"
                        class="rounded-xl bg-cta px-3 py-2.5 text-center text-[0.9625rem] font-semibold text-white hover:bg-cta-dark"
                        @click="replayGuide"
                    >
                        Refazer tutorial
                    </button>
                    <Link :href="route('profile.edit')" class="text-[0.9625rem] font-medium text-cta hover:underline">Perfil</Link>
                    <Link :href="route('logout')" method="post" as="button" class="text-left text-[0.9625rem] font-medium text-horizon-600 hover:underline">
                        Sair
                    </Link>
                </div>
            </div>
        </aside>

        <div v-if="mobileOpen" class="fixed inset-0 z-40 bg-navy-900/40 xl:hidden" @click="mobileOpen = false" />

        <div class="flex min-w-0 flex-1 flex-col xl:ml-0">
            <header class="sticky top-0 z-30 flex items-center justify-between bg-lightPrimary/80 px-4 py-3 backdrop-blur xl:hidden">
                <button
                    type="button"
                    class="rounded-xl bg-white px-3.5 py-2.5 text-[0.9625rem] font-semibold text-navy-700 shadow-soft"
                    @click="mobileOpen = true"
                >
                    Menu
                </button>
                <span class="flex items-center gap-2 text-[0.9625rem] font-bold text-navy-700">
                    <AppMark :size="30" />
                    Levita
                </span>
                <div class="flex min-w-[4rem] justify-end">
                    <TourHelpButton compact />
                </div>
            </header>

            <main class="mx-auto w-full max-w-7xl flex-1 px-3 pb-8 pt-2 sm:px-6 lg:px-8 xl:pt-6">
                <div v-if="flash?.success" class="mb-4 rounded-xl bg-emerald-50 px-4 py-3 text-[0.9625rem] text-emerald-800">
                    {{ flash.success }}
                </div>
                <div v-if="flash?.error" class="mb-4 rounded-xl bg-red-50 px-4 py-3 text-[0.9625rem] text-red-800">
                    {{ flash.error }}
                </div>
                <slot />
            </main>
        </div>
    </div>
</template>
