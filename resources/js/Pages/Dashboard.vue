<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import BalanceCheckinModal from '@/Components/BalanceCheckinModal.vue';
import Card from '@/Components/Card.vue';
import HelpTip from '@/Components/HelpTip.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TourDemoBanner from '@/Components/TourDemoBanner.vue';
import TransactionList from '@/Components/TransactionList.vue';
import WelcomeOnboardingModal from '@/Components/WelcomeOnboardingModal.vue';
import { useAppTour } from '@/Composables/useAppTour';
import { useTourDemo } from '@/Composables/useTourDemo';
import { DASHBOARD_TOUR_ID } from '@/tours/dashboard';
import { FIRST_SETUP_TOUR_ID } from '@/tours/firstSetup';
import { formatBRL, MONTHS } from '@/utils/format';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    summary: Object,
    balanceMeta: {
        type: Object,
        default: () => ({
            has_anchor: false,
            needs_initial: false,
            needs_monthly_checkin: false,
            as_of_date: null,
            previous_month_balance: null,
            needs_stale_recalc: false,
            suggested_balance: null,
            stale_recalc_mode: null,
        }),
    },
    recurringSummary: {
        type: Object,
        default: () => ({
            paid_amount: 0,
            pending_amount: 0,
            total_amount: 0,
            paid_count: 0,
            pending_count: 0,
            total_count: 0,
            paid_percent: 0,
        }),
    },
    filters: Object,
    recentTransactions: Array,
});

const page = usePage();
const showWelcome = ref(false);
const balanceModalMode = ref(null); // initial | monthly | update | null
const balanceSuggestedAmount = ref(null);
const { startFirstSetup, startTour, resumeIfActive, skipOnboarding, isTourActive } = useAppTour();
const { isDemoTour, demoDashboardData } = useTourDemo();

const showingDemo = computed(() => isDemoTour(DASHBOARD_TOUR_ID));
const isOwner = computed(() => page.props.auth.user?.is_owner === true);

const summary = computed(() =>
    showingDemo.value ? demoDashboardData.summary : props.summary,
);
const balanceMeta = computed(() =>
    showingDemo.value ? demoDashboardData.balanceMeta : props.balanceMeta,
);
const recurringSummary = computed(() =>
    showingDemo.value ? demoDashboardData.recurringSummary : props.recurringSummary,
);
const recentTransactions = computed(() =>
    showingDemo.value ? demoDashboardData.recentTransactions : props.recentTransactions,
);

const years = computed(() => {
    const current = new Date().getFullYear();
    return [current, current - 1, current - 2];
});

const hasRecurring = computed(() =>
    showingDemo.value || recurringSummary.value.total_count > 0,
);

const balanceDisplay = computed(() => {
    if (summary.value.balance == null) return '—';
    return formatBRL(summary.value.balance);
});

const balanceToneClass = computed(() => {
    if (summary.value.balance == null) return 'text-navy-700';
    return summary.value.balance >= 0 ? 'text-emerald-600' : 'text-red-600';
});

const showBalanceModal = computed(() => balanceModalMode.value != null);

const showStaleBanner = computed(() =>
    !showingDemo.value
    && !showWelcome.value
    && balanceMeta.value.needs_stale_recalc
    && !balanceMeta.value.needs_initial
    && !balanceMeta.value.needs_monthly_checkin
    && balanceModalMode.value == null,
);

const monthBalanceHelp =
    'Só saídas de dinheiro (PIX, débito, dinheiro etc.) e investimentos. Compras no crédito e no cartão benefício entram nos gastos do mês, mas não neste saldo.';

const staleHelp =
    'Você lançou, alterou ou excluiu movimentações com data anterior à referência do saldo atual. A sugestão parte do saldo do fim do mês anterior e soma as movimentações de caixa deste mês.';

const staleBannerTitle = computed(() => {
    if (balanceMeta.value.stale_recalc_mode === 'confirm') {
        return 'Confirme a referência do saldo de caixa';
    }

    return 'Lançamentos anteriores à data de referência do saldo foram alterados ou excluídos';
});

const staleBannerBody = computed(() => {
    if (!isOwner.value) {
        return 'Peça ao responsável da conta para atualizar a referência do saldo de caixa.';
    }

    if (balanceMeta.value.stale_recalc_mode === 'confirm') {
        return 'O saldo exibido já está correto. Confirme a referência para gravar esse valor e evitar avisos futuros.';
    }

    return 'O saldo exibido ainda não reflete a referência salva. Revise o valor sugerido antes de confirmar.';
});

const staleBannerAction = computed(() =>
    balanceMeta.value.stale_recalc_mode === 'confirm' ? 'Confirmar referência' : 'Recalcular',
);

const openUpdateBalance = (suggested = null) => {
    balanceSuggestedAmount.value = suggested;
    balanceModalMode.value = 'update';
};

const openStaleRecalc = () => {
    openUpdateBalance(balanceMeta.value.suggested_balance);
};

const dismissStaleRecalc = () => {
    router.post(route('balance-anchors.dismiss-stale'), {}, { preserveScroll: true });
};

const closeBalanceModal = () => {
    if (balanceModalMode.value === 'update') {
        balanceModalMode.value = null;
        balanceSuggestedAmount.value = null;
    }
};

const syncBalanceModals = () => {
    if (!isOwner.value || showingDemo.value || showWelcome.value) return;

    if (balanceMeta.value.needs_initial) {
        balanceModalMode.value = 'initial';
        balanceSuggestedAmount.value = null;
        return;
    }

    if (balanceMeta.value.needs_monthly_checkin) {
        balanceModalMode.value = 'monthly';
        balanceSuggestedAmount.value = null;
        return;
    }

    if (balanceModalMode.value === 'initial' || balanceModalMode.value === 'monthly') {
        balanceModalMode.value = null;
    }
};

watch(
    () => [balanceMeta.value.needs_initial, balanceMeta.value.needs_monthly_checkin, showWelcome.value],
    () => syncBalanceModals(),
    { immediate: true },
);

const applyFilters = (event) => {
    const form = event.target.closest('form');
    router.get(route('dashboard'), {
        month: form.month.value,
        year: form.year.value,
    }, { preserveState: true });
};

const acceptWelcome = () => {
    showWelcome.value = false;
    startFirstSetup('dash-nav-contas');
};

const skipWelcome = () => {
    showWelcome.value = false;
    skipOnboarding();
};

onMounted(() => {
    if (isTourActive()) {
        resumeIfActive();
        return;
    }

    const params = new URLSearchParams(window.location.search);
    const tourParam = params.get('tour');

    if (tourParam === FIRST_SETUP_TOUR_ID) {
        startFirstSetup();
        return;
    }

    if (tourParam === DASHBOARD_TOUR_ID) {
        startTour(DASHBOARD_TOUR_ID);
        return;
    }

    const status = page.props.auth.user?.onboarding_status;
    const forceWelcome = params.get('welcome') === '1';

    if (status == null || forceWelcome) {
        showWelcome.value = true;
    }
});
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout>
        <TourDemoBanner :show="showingDemo" />

        <div class="mb-3 flex flex-nowrap items-center justify-between gap-2 sm:mb-6 sm:gap-4" data-tour="dash-page">
            <div class="min-w-0 shrink">
                <h1 class="truncate text-base font-bold text-navy-700 sm:text-2xl">Dashboard</h1>
                <p class="hidden text-sm text-horizon-500 sm:block">Resumo do mês selecionado</p>
            </div>
            <div class="flex shrink-0 flex-nowrap items-center gap-1.5 sm:gap-2">
                <form class="flex shrink-0 gap-1.5 sm:gap-2" data-tour="dash-period" @change="applyFilters">
                    <select name="month" class="max-w-[5.5rem] rounded-xl border-horizon-200 py-1.5 text-xs text-navy-700 sm:max-w-none sm:text-sm" :value="filters.month">
                        <option v-for="m in MONTHS" :key="m.value" :value="m.value">{{ m.label }}</option>
                    </select>
                    <select name="year" class="rounded-xl border-horizon-200 py-1.5 text-xs text-navy-700 sm:text-sm" :value="filters.year">
                        <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                    </select>
                </form>
                <Link :href="route('transactions.create')" data-tour="dash-new">
                    <PrimaryButton class="!px-3 !py-1.5 text-xs sm:!px-4 sm:!py-2.5 sm:text-sm">Nova</PrimaryButton>
                </Link>
            </div>
        </div>

        <div
            v-if="showStaleBanner"
            class="mb-4 rounded-[16px] border border-amber-200 bg-amber-50 px-4 py-3 shadow-soft"
            data-tour="dash-stale-recalc"
        >
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-navy-700">
                        {{ staleBannerTitle }}
                        <HelpTip class="ml-1" :text="staleHelp" label="Sobre recalcular o saldo" />
                    </p>
                    <p class="mt-1 text-xs text-horizon-600">
                        {{ staleBannerBody }}
                    </p>
                    <p
                        v-if="isOwner && balanceMeta.suggested_balance != null"
                        class="mt-2 text-sm text-horizon-600"
                    >
                        Sugestão:
                        <span class="font-semibold tabular-nums text-navy-700">
                            {{ formatBRL(balanceMeta.suggested_balance) }}
                        </span>
                    </p>
                </div>
                <div v-if="isOwner" class="flex shrink-0 flex-wrap gap-2">
                    <SecondaryButton class="!px-3 !py-1.5 text-xs" type="button" @click="dismissStaleRecalc">
                        Agora não
                    </SecondaryButton>
                    <PrimaryButton class="!px-3 !py-1.5 text-xs" type="button" @click="openStaleRecalc">
                        {{ staleBannerAction }}
                    </PrimaryButton>
                </div>
            </div>
        </div>

        <div data-tour="dash-stats" class="mb-4 sm:mb-6">
            <div class="rounded-[16px] bg-white px-4 py-4 shadow-soft sm:px-6 sm:py-5">
                <div class="flex flex-nowrap items-start justify-between gap-2 sm:gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-horizon-500">Saldo</p>
                        <p
                            class="mt-1 text-lg font-bold tabular-nums leading-tight tracking-tight sm:text-3xl lg:text-4xl"
                            :class="balanceToneClass"
                            data-tour="dash-balance"
                        >
                            {{ balanceDisplay }}
                        </p>
                        <p class="mt-2 text-xs text-horizon-500 sm:text-sm">
                            Saldo do mês
                            <HelpTip class="ml-1" :text="monthBalanceHelp" label="Sobre o saldo do mês" />
                            <span
                                class="ml-1 font-semibold tabular-nums"
                                :class="summary.month_balance >= 0 ? 'text-emerald-600' : 'text-red-600'"
                            >
                                {{ formatBRL(summary.month_balance) }}
                            </span>
                        </p>
                        <SecondaryButton
                            v-if="isOwner && !showingDemo"
                            class="mt-2 !px-2.5 !py-1 text-xs sm:hidden"
                            type="button"
                            @click="openUpdateBalance()"
                        >
                            Atualizar saldo
                        </SecondaryButton>
                    </div>
                    <div class="shrink-0 text-right">
                        <SecondaryButton
                            v-if="isOwner && !showingDemo"
                            class="mb-2 hidden !px-2 !py-1 text-xs sm:inline-flex sm:!px-3 sm:!py-1.5 sm:text-sm"
                            type="button"
                            data-tour="dash-balance-update"
                            @click="openUpdateBalance()"
                        >
                            Atualizar saldo
                        </SecondaryButton>
                        <div class="space-y-0.5 sm:space-y-1">
                            <p class="text-xs text-horizon-500 sm:text-sm">
                                Entradas
                                <span class="ml-1 font-semibold tabular-nums text-emerald-600">
                                    {{ formatBRL(summary.income) }}
                                </span>
                            </p>
                            <p class="text-xs text-horizon-500 sm:text-sm">
                                <span class="sm:hidden">Crédito</span>
                                <span class="hidden sm:inline">Gastos no crédito</span>
                                <span class="ml-1 font-semibold tabular-nums text-red-600">
                                    {{ formatBRL(summary.expense_credit) }}
                                </span>
                            </p>
                            <p class="text-xs text-horizon-500 sm:text-sm">
                                <span class="sm:hidden">Débito</span>
                                <span class="hidden sm:inline">Gastos no débito</span>
                                <span class="ml-1 font-semibold tabular-nums text-red-600">
                                    {{ formatBRL(summary.expense_debit) }}
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="hasRecurring"
            class="mb-4 rounded-[16px] bg-white px-4 py-3 shadow-soft"
            data-tour="dash-recurring"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-medium text-horizon-600 sm:text-sm">Contas fixas</p>
                    <p class="mt-0.5 text-sm font-bold tabular-nums text-navy-700">
                        {{ recurringSummary.paid_percent }}% pagas
                        <span class="font-medium text-horizon-600">
                            ({{ formatBRL(recurringSummary.paid_amount) }} / {{ formatBRL(recurringSummary.total_amount) }})
                        </span>
                    </p>
                </div>
                <Link
                    :href="route('recurring-bills.index')"
                    class="inline-flex shrink-0 items-center rounded-xl border border-cta/30 bg-cta/5 px-3 py-1.5 text-xs font-semibold text-cta hover:bg-cta/10 sm:text-sm"
                >
                    Ver contas fixas
                </Link>
            </div>
            <div class="progress-track mt-2">
                <div
                    class="progress-fill"
                    :style="{ width: `${recurringSummary.paid_percent}%` }"
                />
            </div>
        </div>

        <h2 class="mb-2 text-base font-bold text-navy-700 sm:mb-3 sm:text-lg">Últimas transações</h2>
        <Card extra="!bg-transparent !shadow-none md:!bg-white md:shadow-soft" data-tour="dash-recent">
            <TransactionList
                :transactions="recentTransactions"
                empty-message="Nenhuma transação neste mês."
            />
        </Card>

        <WelcomeOnboardingModal
            :show="showWelcome"
            @accept="acceptWelcome"
            @skip="skipWelcome"
        />

        <BalanceCheckinModal
            :show="showBalanceModal"
            :mode="balanceModalMode || 'initial'"
            :previous-month-balance="balanceMeta.previous_month_balance"
            :suggested-amount="balanceSuggestedAmount"
            @close="closeBalanceModal"
        />
    </AppLayout>
</template>
