<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/Card.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import StatWidget from '@/Components/StatWidget.vue';
import { formatBRL, formatDate, MONTHS } from '@/utils/format';
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
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-navy-700">Dashboard</h1>
                <p class="text-sm text-horizon-500">Resumo do mês selecionado</p>
            </div>
            <Link :href="route('transactions.create')">
                <PrimaryButton>Nova transação</PrimaryButton>
            </Link>
        </div>

        <form class="mb-6 flex flex-wrap gap-3" @change="applyFilters">
            <select name="month" class="rounded-xl border-horizon-200 text-sm text-navy-700" :value="filters.month">
                <option v-for="m in MONTHS" :key="m.value" :value="m.value">{{ m.label }}</option>
            </select>
            <select name="year" class="rounded-xl border-horizon-200 text-sm text-navy-700" :value="filters.year">
                <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
            </select>
        </form>

        <div class="mb-8 grid gap-4 sm:grid-cols-3">
            <StatWidget
                title="Saldo do mês"
                :value="formatBRL(summary.balance)"
                :tone="summary.balance >= 0 ? 'positive' : 'negative'"
            />
            <StatWidget title="Entradas" :value="formatBRL(summary.income)" tone="positive" />
            <StatWidget title="Saídas" :value="formatBRL(summary.expense)" tone="negative" />
        </div>

        <h2 class="mb-3 text-lg font-bold text-navy-700">Últimas transações</h2>
        <Card>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-horizon-100 text-sm">
                    <thead class="text-left text-horizon-500">
                        <tr>
                            <th class="px-5 py-4 font-medium">Data</th>
                            <th class="px-5 py-4 font-medium">Descrição</th>
                            <th class="px-5 py-4 font-medium">Categoria</th>
                            <th class="px-5 py-4 font-medium">Quem</th>
                            <th class="px-5 py-4 font-medium text-right">Valor</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-horizon-100">
                        <tr v-for="tx in recentTransactions" :key="tx.id">
                            <td class="whitespace-nowrap px-5 py-3 text-navy-700">{{ formatDate(tx.date) }}</td>
                            <td class="px-5 py-3 text-navy-700">{{ tx.description }}</td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center gap-2 text-navy-700">
                                    <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: tx.category?.color }" />
                                    {{ tx.category?.name }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-horizon-600">{{ tx.user?.name }}</td>
                            <td
                                class="px-5 py-3 text-right font-bold"
                                :class="tx.type === 'income' ? 'text-emerald-600' : 'text-red-600'"
                            >
                                {{ tx.type === 'income' ? '+' : '-' }}{{ formatBRL(tx.amount) }}
                            </td>
                        </tr>
                        <tr v-if="!recentTransactions.length">
                            <td colspan="5" class="px-5 py-10 text-center text-horizon-500">Nenhuma transação neste mês.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
    </AppLayout>
</template>
