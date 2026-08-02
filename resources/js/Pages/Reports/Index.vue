<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/Card.vue';
import { formatBRL, MONTHS } from '@/utils/format';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Bar, Doughnut } from 'vue-chartjs';
import {
    Chart as ChartJS,
    ArcElement,
    BarElement,
    CategoryScale,
    LinearScale,
    Tooltip,
    Legend,
} from 'chart.js';

ChartJS.register(ArcElement, BarElement, CategoryScale, LinearScale, Tooltip, Legend);

const props = defineProps({
    byCategory: Array,
    monthly: Array,
    filters: Object,
});

const years = computed(() => {
    const current = new Date().getFullYear();
    return [current, current - 1, current - 2];
});

const pieData = computed(() => ({
    labels: props.byCategory.map((c) => c.name),
    datasets: [
        {
            data: props.byCategory.map((c) => c.total),
            backgroundColor: props.byCategory.map((c) => c.color),
        },
    ],
}));

const barData = computed(() => ({
    labels: props.monthly.map((m) => m.label),
    datasets: [
        {
            label: 'Entradas',
            data: props.monthly.map((m) => m.income),
            backgroundColor: '#22c55e',
        },
        {
            label: 'Saídas',
            data: props.monthly.map((m) => m.expense),
            backgroundColor: '#ef4444',
        },
        {
            label: 'Investimentos',
            data: props.monthly.map((m) => m.investments || 0),
            backgroundColor: '#0d9488',
        },
    ],
}));

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'bottom' },
    },
};

const applyFilters = (event) => {
    const form = event.target.closest('form');
    router.get(route('reports.index'), {
        month: form.month.value,
        year: form.year.value,
    }, { preserveState: true });
};
</script>

<template>
    <Head title="Relatórios" />

    <AppLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-900">Relatórios</h1>
            <p class="text-sm text-slate-500">Distribuição de gastos e evolução mensal</p>
        </div>

        <form class="mb-6 flex flex-wrap gap-3" @change="applyFilters">
            <select name="month" class="rounded-md border-slate-300 text-sm" :value="filters.month">
                <option v-for="m in MONTHS" :key="m.value" :value="m.value">{{ m.label }}</option>
            </select>
            <select name="year" class="rounded-md border-slate-300 text-sm" :value="filters.year">
                <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
            </select>
        </form>

        <div class="grid gap-6 lg:grid-cols-2">
            <Card extra="p-5">
                <h2 class="mb-4 font-bold text-navy-700">Gastos por categoria</h2>
                <div v-if="byCategory.length" class="h-72">
                    <Doughnut :data="pieData" :options="chartOptions" />
                </div>
                <p v-else class="py-16 text-center text-horizon-500">Sem gastos no período.</p>
                <ul v-if="byCategory.length" class="mt-4 space-y-1 text-sm text-horizon-600">
                    <li v-for="item in byCategory" :key="item.name" class="flex justify-between">
                        <span>{{ item.name }}</span>
                        <span>{{ formatBRL(item.total) }}</span>
                    </li>
                </ul>
            </Card>

            <Card extra="p-5">
                <h2 class="mb-4 font-bold text-navy-700">Entradas × saídas × investimentos (6 meses)</h2>
                <div class="h-72">
                    <Bar :data="barData" :options="chartOptions" />
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
