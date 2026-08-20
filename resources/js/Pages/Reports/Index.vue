<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/Card.vue';
import ReportChart from '@/Components/ReportChart.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { MONTHS } from '@/utils/format';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    charts: Object,
    catalog: Array,
    pinnedChartId: {
        type: String,
        default: null,
    },
    filters: Object,
});

const page = usePage();

const years = computed(() => {
    const current = new Date().getFullYear();
    return [current, current - 1, current - 2];
});

const applyFilters = (event) => {
    const form = event.target.closest('form');
    router.get(route('reports.index'), {
        month: form.month.value,
        year: form.year.value,
    }, { preserveState: true });
};

const isPinned = (chartId) => props.pinnedChartId === chartId
    || page.props.auth.user?.pinned_dashboard_chart === chartId;

const togglePin = (chartId) => {
    const next = isPinned(chartId) ? null : chartId;
    router.put(route('dashboard.pinned-chart'), {
        chart_id: next,
    }, {
        preserveScroll: true,
        preserveState: true,
    });
};
</script>

<template>
    <Head title="Relatórios" />

    <AppLayout>
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-navy-700">Relatórios</h1>
                <p class="text-sm text-horizon-500">
                    Gráficos com os dados da conta. Fixe um no dashboard para ver todo dia.
                </p>
            </div>
            <form class="flex flex-wrap gap-3" @change="applyFilters">
                <select name="month" class="rounded-xl border-horizon-200 text-sm text-navy-700" :value="filters.month">
                    <option v-for="m in MONTHS" :key="m.value" :value="m.value">{{ m.label }}</option>
                </select>
                <select name="year" class="rounded-xl border-horizon-200 text-sm text-navy-700" :value="filters.year">
                    <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                </select>
            </form>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <Card
                v-for="meta in catalog"
                :key="meta.id"
                extra="p-5"
            >
                <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <h2 class="font-bold text-navy-700">{{ meta.title }}</h2>
                        <p class="mt-0.5 text-xs text-horizon-500">{{ meta.description }}</p>
                    </div>
                    <SecondaryButton
                        type="button"
                        class="!px-3 !py-1.5 text-xs shrink-0"
                        @click="togglePin(meta.id)"
                    >
                        {{ isPinned(meta.id) ? 'Fixado no dash' : 'Fixar no dash' }}
                    </SecondaryButton>
                </div>
                <ReportChart v-if="charts[meta.id]" :chart="charts[meta.id]" />
            </Card>
        </div>
    </AppLayout>
</template>
