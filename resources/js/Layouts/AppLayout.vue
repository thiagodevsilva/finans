<script setup>
import AppMark from '@/Components/AppMark.vue';
import { useAppTour } from '@/Composables/useAppTour';
import { computed, ref } from 'vue';
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
    mobileOpen.value = false;
    restartOnboarding();
};
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
                    <AppMark :size="36" />
                    <span class="font-dm text-[22px] font-bold tracking-tight text-navy-700">Levita</span>
                </Link>
                <button type="button" class="text-horizon-500 xl:hidden" @click="mobileOpen = false">✕</button>
            </div>
            <p class="mx-8 mt-1 truncate text-sm text-horizon-500">{{ account?.name }}</p>
            <div class="mb-6 mt-8 h-px bg-horizon-200" />

            <nav class="mb-auto pt-1">
                <Link
                    v-for="link in links"
                    :key="link.route"
                    :href="route(link.route)"
                    class="relative mb-2 flex items-center px-8 py-2 hover:cursor-pointer"
                    :data-tour="link.tour || undefined"
                    @click="mobileOpen = false"
                >
                    <span
                        class="text-sm"
                        :class="isActive(link.match) ? 'font-bold text-brand-600' : 'font-medium text-horizon-500'"
                    >
                        {{ link.name }}
                    </span>
                    <div
                        v-if="isActive(link.match)"
                        class="absolute right-0 top-1 h-8 w-1 rounded-lg bg-brand-500"
                    />
                </Link>
            </nav>

            <div class="mx-6 rounded-[20px] bg-lightPrimary p-4">
                <p class="text-sm font-bold text-navy-700">{{ user?.name }}</p>
                <p class="text-xs text-horizon-500">{{ user?.is_owner ? 'Dono da conta' : 'Dependente' }}</p>
                <div class="mt-3 flex flex-col gap-1 text-sm">
                    <button
                        type="button"
                        class="text-left font-medium text-cta hover:underline"
                        @click="replayGuide"
                    >
                        Refazer guia
                    </button>
                    <Link :href="route('profile.edit')" class="font-medium text-cta hover:underline">Perfil</Link>
                    <Link :href="route('logout')" method="post" as="button" class="text-left font-medium text-horizon-600 hover:underline">
                        Sair
                    </Link>
                </div>
            </div>
        </aside>

        <div v-if="mobileOpen" class="fixed inset-0 z-40 bg-navy-900/40 xl:hidden" @click="mobileOpen = false" />

        <div class="flex min-w-0 flex-1 flex-col xl:ml-0">
            <header class="sticky top-0 z-30 flex items-center justify-between bg-lightPrimary/80 px-4 py-3 backdrop-blur xl:hidden">
                <button type="button" class="rounded-xl bg-white px-3 py-2 text-sm font-semibold text-navy-700 shadow-soft" @click="mobileOpen = true">
                    Menu
                </button>
                <span class="flex items-center gap-2 font-bold text-navy-700">
                    <AppMark :size="28" />
                    Levita
                </span>
                <span class="w-14" />
            </header>

            <main class="mx-auto w-full max-w-7xl flex-1 px-3 pb-8 pt-2 sm:px-6 lg:px-8 xl:pt-6">
                <div v-if="flash?.success" class="mb-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ flash.success }}
                </div>
                <div v-if="flash?.error" class="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800">
                    {{ flash.error }}
                </div>
                <slot />
            </main>
        </div>
    </div>
</template>
