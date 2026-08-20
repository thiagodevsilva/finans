<script setup>
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
import { computed } from 'vue';
import { formatBRL } from '@/utils/format';

ChartJS.register(ArcElement, BarElement, CategoryScale, LinearScale, Tooltip, Legend);

const props = defineProps({
    chart: {
        type: Object,
        required: true,
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

const isEmpty = computed(() => {
    const series = props.chart?.series ?? [];
    if (!series.length) return true;

    if (props.chart.chart_type === 'doughnut') {
        return series.every((row) => !row.total);
    }

    if (props.chart.id === 'monthly_flow') {
        return series.every((row) => !row.income && !row.expense && !row.investments);
    }

    if (props.chart.id === 'cash_movement') {
        return series.every(
            (row) => !row.income && !row.cash_expense && !row.card_payments && !row.investments,
        );
    }

    if (props.chart.id === 'by_member') {
        return series.every((row) => !row.total);
    }

    return false;
});

const doughnutData = computed(() => ({
    labels: (props.chart.series || []).map((c) => c.name),
    datasets: [
        {
            data: (props.chart.series || []).map((c) => c.total),
            backgroundColor: (props.chart.series || []).map((c) => c.color),
        },
    ],
}));

const barData = computed(() => {
    const series = props.chart.series || [];

    if (props.chart.id === 'by_member') {
        return {
            labels: series.map((c) => c.name),
            datasets: [
                {
                    label: 'Gastos',
                    data: series.map((c) => c.total),
                    backgroundColor: series.map((c) => c.color),
                },
            ],
        };
    }

    if (props.chart.id === 'cash_movement') {
        return {
            labels: series.map((m) => m.label),
            datasets: [
                {
                    label: 'Entradas',
                    data: series.map((m) => m.income),
                    backgroundColor: '#22c55e',
                },
                {
                    label: 'Gastos à vista',
                    data: series.map((m) => m.cash_expense),
                    backgroundColor: '#ef4444',
                },
                {
                    label: 'Pagamento de cartões',
                    data: series.map((m) => m.card_payments),
                    backgroundColor: '#f59e0b',
                },
                {
                    label: 'Investimentos',
                    data: series.map((m) => m.investments || 0),
                    backgroundColor: '#0d9488',
                },
            ],
        };
    }

    // monthly_flow
    return {
        labels: series.map((m) => m.label),
        datasets: [
            {
                label: 'Entradas',
                data: series.map((m) => m.income),
                backgroundColor: '#22c55e',
            },
            {
                label: 'Gastos',
                data: series.map((m) => m.expense),
                backgroundColor: '#ef4444',
            },
            {
                label: 'Investimentos',
                data: series.map((m) => m.investments || 0),
                backgroundColor: '#0d9488',
            },
        ],
    };
});

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    indexAxis: props.chart.id === 'by_member' ? 'y' : 'x',
    plugins: {
        legend: {
            position: 'bottom',
            display: props.chart.id !== 'by_member',
        },
        tooltip: {
            callbacks: {
                label(ctx) {
                    const value = ctx.parsed.y ?? ctx.parsed.x ?? ctx.parsed;
                    const num = typeof value === 'number' ? value : (value?.y ?? value?.x ?? 0);
                    const label = ctx.dataset.label ? `${ctx.dataset.label}: ` : '';
                    return `${label}${formatBRL(num)}`;
                },
            },
        },
    },
    scales: props.chart.chart_type === 'bar'
        ? (
            props.chart.id === 'by_member'
                ? {
                    x: {
                        ticks: {
                            callback(value) {
                                return formatBRL(value);
                            },
                        },
                    },
                    y: { ticks: { autoSkip: false } },
                }
                : {
                    x: { ticks: { maxRotation: 0 } },
                    y: {
                        ticks: {
                            callback(value) {
                                return formatBRL(value);
                            },
                        },
                    },
                }
        )
        : undefined,
}));

const heightClass = computed(() => (props.compact ? 'h-56' : 'h-72'));
</script>

<template>
    <div>
        <div v-if="!isEmpty" :class="heightClass">
            <Doughnut
                v-if="chart.chart_type === 'doughnut'"
                :data="doughnutData"
                :options="chartOptions"
            />
            <Bar
                v-else
                :data="barData"
                :options="chartOptions"
            />
        </div>
        <p v-else class="py-12 text-center text-sm text-horizon-500">
            Sem dados neste período.
        </p>

        <ul
            v-if="!isEmpty && chart.chart_type === 'doughnut'"
            class="mt-4 space-y-1 text-sm text-horizon-600"
        >
            <li
                v-for="item in chart.series"
                :key="item.name"
                class="flex justify-between gap-3"
            >
                <span class="min-w-0 truncate">{{ item.name }}</span>
                <span class="shrink-0 tabular-nums">{{ formatBRL(item.total) }}</span>
            </li>
        </ul>
    </div>
</template>
