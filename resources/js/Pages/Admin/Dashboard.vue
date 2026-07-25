<script setup>
import AppMark from '@/Components/AppMark.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';
import {
    Chart as ChartJS,
    BarElement,
    CategoryScale,
    LinearScale,
    Tooltip,
    Legend,
} from 'chart.js';

ChartJS.register(BarElement, CategoryScale, LinearScale, Tooltip, Legend);

const props = defineProps({
    onlineCount: Number,
    totalUsers: Number,
    users: Object,
    signupsChart: Object,
    filters: Object,
    onlineWindowMinutes: Number,
});

const page = usePage();
const user = computed(() => page.props.auth.user);

const chartData = computed(() => ({
    labels: props.signupsChart.labels,
    datasets: [
        {
            label: 'Novos usuários',
            data: props.signupsChart.data,
            backgroundColor: '#ffc107',
            borderRadius: 4,
        },
    ],
}));

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
    },
    scales: {
        x: {
            ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 8 },
            grid: { display: false },
        },
        y: {
            beginAtZero: true,
            ticks: { precision: 0 },
        },
    },
};

const applyDays = (event) => {
    router.get(route('admin.dashboard'), { days: event.target.value }, { preserveState: true });
};

const logout = () => {
    router.post(route('logout'));
};
</script>

<template>
    <Head title="Admin · Levita" />

    <div class="min-h-screen bg-slate-50 text-navy-700">
        <header class="border-b border-horizon-100 bg-white">
            <div class="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-3">
                <div class="flex items-center gap-2">
                    <AppMark :size="32" />
                    <div>
                        <p class="text-sm font-bold">Levita Admin</p>
                        <p class="text-xs text-horizon-500">{{ user?.email }}</p>
                    </div>
                </div>
                <button type="button" class="text-sm font-medium text-cta hover:underline" @click="logout">
                    Sair
                </button>
            </div>
        </header>

        <main class="mx-auto max-w-5xl space-y-4 px-4 py-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h1 class="text-lg font-bold sm:text-xl">Painel</h1>
                <select
                    class="rounded-xl border-horizon-200 py-1.5 text-xs text-navy-700 sm:text-sm"
                    :value="filters.days"
                    @change="applyDays"
                >
                    <option :value="7">7 dias</option>
                    <option :value="30">30 dias</option>
                    <option :value="90">90 dias</option>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-[16px] bg-white px-4 py-3 shadow-soft">
                    <p class="text-xs font-medium text-horizon-500">Online agora</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-emerald-600">{{ onlineCount }}</p>
                    <p class="mt-0.5 text-[11px] text-horizon-500">Últimos {{ onlineWindowMinutes }} min</p>
                </div>
                <div class="rounded-[16px] bg-white px-4 py-3 shadow-soft">
                    <p class="text-xs font-medium text-horizon-500">Usuários</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums">{{ totalUsers }}</p>
                    <p class="mt-0.5 text-[11px] text-horizon-500">Exceto admin</p>
                </div>
            </div>

            <section class="rounded-[16px] bg-white p-4 shadow-soft">
                <h2 class="mb-3 text-sm font-bold">Entrada de usuários</h2>
                <div class="h-56 sm:h-72">
                    <Bar :data="chartData" :options="chartOptions" />
                </div>
            </section>

            <section class="rounded-[16px] bg-white p-4 shadow-soft">
                <h2 class="mb-3 text-sm font-bold">Usuários</h2>

                <div v-if="!users?.data?.length" class="py-8 text-center text-sm text-horizon-500">
                    Nenhum usuário cadastrado ainda.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-horizon-100 text-xs font-semibold uppercase tracking-wide text-horizon-500">
                                <th class="px-2 py-2 font-semibold">Nome</th>
                                <th class="px-2 py-2 font-semibold">E-mail</th>
                                <th class="px-2 py-2 font-semibold">Família</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in users.data"
                                :key="row.id"
                                class="border-b border-horizon-50 last:border-0"
                            >
                                <td class="px-2 py-2.5 font-medium text-navy-700">{{ row.name }}</td>
                                <td class="px-2 py-2.5 text-horizon-600">{{ row.email }}</td>
                                <td class="px-2 py-2.5 text-horizon-600">
                                    {{ row.family_name || '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="users?.links?.length > 3"
                    class="mt-4 flex flex-wrap gap-2"
                >
                    <Link
                        v-for="link in users.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        class="rounded-lg border px-2.5 py-1 text-xs"
                        :class="link.active
                            ? 'border-cta bg-cta text-white'
                            : 'border-horizon-200 bg-white text-navy-700'"
                        :preserve-scroll="true"
                        v-html="link.label"
                    />
                </div>
            </section>
        </main>
    </div>
</template>
