<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    tickets: Object,
    filters: Object,
    statuses: Array,
});

const applyFilter = (event) => {
    router.get(
        route('support-tickets.index'),
        { status: event.target.value || undefined },
        { preserveState: true },
    );
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
</script>

<template>
    <Head title="Suporte" />

    <AppLayout>
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Suporte</h1>
                <p class="text-sm text-slate-500">Sugestões e pedidos de ajuda</p>
            </div>
            <Link :href="route('support-tickets.create')">
                <PrimaryButton type="button">Abrir chamado</PrimaryButton>
            </Link>
        </div>

        <div class="mb-4">
            <label class="mr-2 text-sm text-slate-600" for="status-filter">Status</label>
            <select
                id="status-filter"
                class="rounded-md border-slate-300 text-sm"
                :value="filters.status"
                @change="applyFilter"
            >
                <option value="">Todos</option>
                <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
        </div>

        <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Título</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Autor</th>
                        <th class="px-4 py-3 font-medium">Aberto em</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-if="!tickets?.data?.length">
                        <td colspan="4" class="px-4 py-8 text-center text-slate-500">
                            Nenhum chamado ainda.
                        </td>
                    </tr>
                    <tr
                        v-for="ticket in tickets.data"
                        :key="ticket.id"
                        class="cursor-pointer hover:bg-amber-50/40"
                        @click="router.visit(route('support-tickets.show', ticket.id))"
                    >
                        <td class="px-4 py-3 font-medium text-slate-800">
                            {{ ticket.title }}
                            <span
                                v-if="ticket.awaiting_response"
                                class="ml-2 rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800"
                            >
                                Aguardando
                            </span>
                        </td>
                        <td class="px-4 py-3">{{ ticket.status_label }}</td>
                        <td class="px-4 py-3">{{ ticket.author_name }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ formatDate(ticket.created_at) }}</td>
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
                :class="link.active ? 'border-brand-500 bg-brand-500 text-navy-900' : 'border-slate-200 bg-white text-slate-700'"
                :preserve-scroll="true"
                v-html="link.label"
            />
        </div>
    </AppLayout>
</template>
