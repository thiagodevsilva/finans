<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/Card.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import StatWidget from '@/Components/StatWidget.vue';
import TransactionList from '@/Components/TransactionList.vue';
import { formatBRL, MONTHS } from '@/utils/format';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    summary: Object,
    invoiceSummary: {
        type: Object,
        default: () => ({
            current: 0,
            future: 0,
        }),
    },
    recurringSummary: {
        type: Object,
        default: () => ({
            paid_amount: 0,
            pending_amount: 0,
            paid_count: 0,
            pending_count: 0,
            total_count: 0,
            paid_percent: 0,
        }),
    },
    filters: Object,
    recentTransactions: Array,
});

const mode = ref('money');

const years = computed(() => {
    const current = new Date().getFullYear();
    return [current, current - 1, current - 2];
});

const hasRecurring = computed(() => props.recurringSummary.total_count > 0);

const hasInvoices = computed(() =>
    props.invoiceSummary.current > 0 || props.invoiceSummary.future > 0,
);

const applyFilters = (event) => {
    const form = event.target;
    router.get(route('dashboard'), {
        month: form.month.value,
        year: form.year.value,
    }, { preserveState: true });
};
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout>
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2 sm:mb-6 sm:gap-4">
            <div class="min-w-0">
                <h1 class="text-lg font-bold text-navy-700 sm:text-2xl">Dashboard</h1>
                <p class="hidden text-sm text-horizon-500 sm:block">Resumo do mês selecionado</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <form class="flex gap-2" @change="applyFilters">
                    <select name="month" class="rounded-xl border-horizon-200 py-1.5 text-xs text-navy-700 sm:text-sm" :value="filters.month">
                        <option v-for="m in MONTHS" :key="m.value" :value="m.value">{{ m.label }}</option>
                    </select>
                    <select name="year" class="rounded-xl border-horizon-200 py-1.5 text-xs text-navy-700 sm:text-sm" :value="filters.year">
                        <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                    </select>
                </form>
                <Link :href="route('transactions.create')">
                    <PrimaryButton class="!px-3 !py-1.5 text-xs sm:!px-4 sm:!py-2.5 sm:text-sm">Nova</PrimaryButton>
                </Link>
            </div>
        </div>

        <div class="mb-4 rounded-[16px] bg-white px-4 py-3 shadow-soft sm:hidden">
            <div class="flex items-center justify-between gap-3 border-b border-horizon-100 pb-2.5">
                <span class="text-sm font-medium text-horizon-500">Saldo</span>
                <span
                    class="text-base font-bold tabular-nums"
                    :class="summary.balance >= 0 ? 'text-emerald-600' : 'text-red-600'"
                >
                    {{ formatBRL(summary.balance) }}
                </span>
            </div>
            <div class="mt-2.5 grid grid-cols-2 gap-3">
                <div>
                    <p class="text-xs font-medium text-horizon-500">Entradas</p>
                    <p class="text-sm font-bold tabular-nums text-emerald-600">{{ formatBRL(summary.income) }}</p>
                </div>
                <div class="text-right">
                    <p class="text-xs font-medium text-horizon-500">Saídas</p>
                    <p class="text-sm font-bold tabular-nums text-red-600">{{ formatBRL(summary.expense) }}</p>
                </div>
            </div>
            <p class="mt-2.5 border-t border-horizon-100 pt-2 text-xs font-medium text-horizon-600">
                Fluxo de caixa
                <span class="float-right font-semibold tabular-nums text-navy-700">{{ formatBRL(summary.cash_flow) }}</span>
            </p>
            <p
                v-if="summary.investments > 0"
                class="mt-1.5 text-xs font-medium text-teal-700"
            >
                Investimentos
                <span class="float-right font-semibold tabular-nums">{{ formatBRL(summary.investments) }}</span>
            </p>
        </div>

        <div class="mb-4 hidden gap-4 sm:mb-6 sm:grid sm:grid-cols-3">
            <StatWidget
                title="Saldo do mês"
                :value="formatBRL(summary.balance)"
                :tone="summary.balance >= 0 ? 'positive' : 'negative'"
            />
            <StatWidget title="Entradas" :value="formatBRL(summary.income)" tone="positive" />
            <StatWidget title="Saídas" :value="formatBRL(summary.expense)" tone="negative" />
        </div>

        <div class="mb-4 hidden rounded-[16px] bg-white px-4 py-2.5 shadow-soft sm:flex sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-medium text-horizon-600">Fluxo de caixa</p>
                <p class="text-xs text-horizon-500">Dinheiro que saiu da conta neste mês</p>
            </div>
            <p class="text-sm font-bold tabular-nums text-navy-700">{{ formatBRL(summary.cash_flow) }}</p>
        </div>

        <div
            v-if="summary.investments > 0"
            class="mb-4 hidden rounded-[16px] bg-teal-50 px-4 py-2.5 shadow-soft ring-1 ring-teal-100 sm:flex sm:items-center sm:justify-between"
        >
            <div>
                <p class="text-sm font-medium text-teal-800">Investimentos</p>
                <p class="text-xs text-teal-700/80">Aportes do mês (não entram nas Saídas de consumo)</p>
            </div>
            <p class="text-sm font-bold tabular-nums text-teal-900">{{ formatBRL(summary.investments) }}</p>
        </div>

        <div
            v-if="hasInvoices"
            class="mb-4 rounded-[16px] bg-white px-4 py-3 shadow-soft"
        >
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0 flex-1 space-y-1.5">
                    <p class="text-xs font-medium text-horizon-600 sm:text-sm">Faturas do cartão</p>
                    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 text-sm">
                        <span class="text-horizon-600">Fatura atual (a pagar)</span>
                        <span class="font-bold tabular-nums text-navy-700">{{ formatBRL(invoiceSummary.current) }}</span>
                    </div>
                    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 text-sm">
                        <span class="text-horizon-600">Faturas futuras</span>
                        <span class="font-bold tabular-nums text-navy-700">{{ formatBRL(invoiceSummary.future) }}</span>
                    </div>
                </div>
                <Link :href="route('payment-cards.index')" class="shrink-0 text-xs font-medium text-cta hover:underline sm:text-sm">
                    Ver
                </Link>
            </div>
        </div>

        <div
            v-if="hasRecurring"
            class="mb-4 rounded-[16px] bg-white px-4 py-3 shadow-soft"
        >
            <div class="flex items-center justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-xs font-medium text-horizon-600 sm:text-sm">Contas fixas</p>
                    <p v-if="mode === 'money'" class="mt-0.5 text-sm font-bold tabular-nums text-navy-700">
                        pago {{ formatBRL(recurringSummary.paid_amount) }}
                        <span class="font-medium text-horizon-600">· falta {{ formatBRL(recurringSummary.pending_amount) }}</span>
                    </p>
                    <p v-else class="mt-0.5 text-sm font-bold tabular-nums text-navy-700">
                        {{ recurringSummary.paid_percent }}% pagas
                        <span class="font-medium text-horizon-600">
                            ({{ recurringSummary.paid_count }}/{{ recurringSummary.total_count }})
                        </span>
                    </p>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <div class="segmented">
                        <button
                            type="button"
                            class="segmented-option !px-2.5 !py-1"
                            :class="mode === 'money' ? 'segmented-option-active' : 'segmented-option-idle'"
                            @click="mode = 'money'"
                        >
                            R$
                        </button>
                        <button
                            type="button"
                            class="segmented-option !px-2.5 !py-1"
                            :class="mode === 'percent' ? 'segmented-option-active' : 'segmented-option-idle'"
                            @click="mode = 'percent'"
                        >
                            %
                        </button>
                    </div>
                    <Link :href="route('recurring-bills.index')" class="text-xs font-medium text-cta hover:underline sm:text-sm">
                        Ver
                    </Link>
                </div>
            </div>
            <div v-if="mode === 'percent'" class="progress-track mt-2">
                <div
                    class="progress-fill"
                    :style="{ width: `${recurringSummary.paid_percent}%` }"
                />
            </div>
        </div>

        <h2 class="mb-2 text-base font-bold text-navy-700 sm:mb-3 sm:text-lg">Últimas transações</h2>
        <Card extra="!bg-transparent !shadow-none md:!bg-white md:shadow-soft">
            <TransactionList
                :transactions="recentTransactions"
                empty-message="Nenhuma transação neste mês."
            />
        </Card>
    </AppLayout>
</template>
