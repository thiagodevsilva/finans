<script setup>
import AppMark from '@/Components/AppMark.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    tickets: Object,
    filters: Object,
    statuses: Array,
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const flash = computed(() => page.props.flash);

const applyFilters = () => {
    const status = document.getElementById('admin-status-filter')?.value || '';
    const sla = document.getElementById('admin-sla-filter')?.value || '';
    router.get(
        route('admin.support-tickets.index'),
        {
            status: status || undefined,
            sla: sla || undefined,
        },
        { preserveState: true },
    );
};

const logout = () => {
    router.post(route('logout'));
};

const formatDate = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const slaClass = (status) => {
    if (status === 'breached' || status === 'missed') return 'bg-red-100 text-red-800';
    if (status === 'met') return 'bg-emerald-100 text-emerald-800';
    return 'bg-amber-100 text-amber-800';
};
</script>

<template>
    <Head title="Admin · Suporte" />

    <div class="min-h-screen bg-slate-50 text-navy-700">
        <header class="border-b border-horizon-100 bg-white">
            <div class="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-3">
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2">
                        <AppMark :size="32" />
                        <div>
                            <p class="text-sm font-bold">Levita Admin</p>
                            <p class="text-xs text-horizon-500">{{ user?.email }}</p>
                        </div>
                    </div>
                    <nav class="flex gap-3 text-sm">
                        <Link :href="route('admin.dashboard')" class="text-horizon-500 hover:text-navy-700">Painel</Link>
                        <Link :href="route('admin.support-tickets.index')" class="font-semibold text-navy-700">Suporte</Link>
                    </nav>
                </div>
                <button type="button" class="text-sm font-medium text-cta hover:underline" @click="logout">
                    Sair
                </button>
            </div>
        </header>

        <main class="mx-auto max-w-5xl space-y-4 px-4 py-5">
            <div v-if="flash?.success" class="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ flash.success }}
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2">
                <h1 class="text-lg font-bold sm:text-xl">Chamados de suporte</h1>
                <div class="flex flex-wrap gap-2">
                    <select
                        id="admin-status-filter"
                        class="rounded-xl border-horizon-200 py-1.5 text-xs text-navy-700 sm:text-sm"
                        :value="filters.status"
                        @change="applyFilters"
                    >
                        <option value="">Todos os status</option>
                        <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                    <select
                        id="admin-sla-filter"
                        class="rounded-xl border-horizon-200 py-1.5 text-xs text-navy-700 sm:text-sm"
                        :value="filters.sla"
                        @change="applyFilters"
                    >
                        <option value="">SLA: todos</option>
                        <option value="breached">Só atrasados</option>
                    </select>
                </div>
            </div>

            <section class="rounded-[16px] bg-white p-4 shadow-soft">
                <div v-if="!tickets?.data?.length" class="py-8 text-center text-sm text-horizon-500">
                    Nenhum chamado encontrado.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-horizon-100 text-xs font-semibold uppercase tracking-wide text-horizon-500">
                                <th class="px-2 py-2">Título</th>
                                <th class="px-2 py-2">Família</th>
                                <th class="px-2 py-2">Status</th>
                                <th class="px-2 py-2">SLA</th>
                                <th class="px-2 py-2">Aberto em</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in tickets.data"
                                :key="row.id"
                                class="cursor-pointer border-b border-horizon-50 last:border-0 hover:bg-amber-50/50"
                                @click="router.visit(route('admin.support-tickets.show', row.id))"
                            >
                                <td class="px-2 py-2.5 font-medium text-navy-700">{{ row.title }}</td>
                                <td class="px-2 py-2.5 text-horizon-600">
                                    <div>{{ row.family_name || '—' }}</div>
                                    <div class="text-xs">{{ row.author_email }}</div>
                                </td>
                                <td class="px-2 py-2.5">{{ row.status_label }}</td>
                                <td class="px-2 py-2.5">
                                    <span class="rounded-full px-2 py-0.5 text-xs" :class="slaClass(row.sla_status)">
                                        {{ row.sla_label }}
                                    </span>
                                </td>
                                <td class="px-2 py-2.5 text-horizon-600">{{ formatDate(row.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="tickets?.links?.length > 3" class="mt-4 flex flex-wrap gap-2">
                    <Link
                        v-for="link in tickets.links"
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
