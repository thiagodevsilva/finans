<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/Card.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import StatWidget from '@/Components/StatWidget.vue';
import TransactionList from '@/Components/TransactionList.vue';
import { formatBRL, MONTHS } from '@/utils/format';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    summary: Object,
    filters: Object,
    recentTransactions: Array,
});

const years = computed(() => {
    const current = new Date().getFullYear();
    return [current, current - 1, current - 2];
});

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

        <!-- Mobile: resumo legível em uma faixa -->
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
        </div>

        <!-- Desktop: widgets -->
        <div class="mb-8 hidden gap-4 sm:grid sm:grid-cols-3">
            <StatWidget
                title="Saldo do mês"
                :value="formatBRL(summary.balance)"
                :tone="summary.balance >= 0 ? 'positive' : 'negative'"
            />
            <StatWidget title="Entradas" :value="formatBRL(summary.income)" tone="positive" />
            <StatWidget title="Saídas" :value="formatBRL(summary.expense)" tone="negative" />
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
