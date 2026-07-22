<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/Card.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { formatBRL, formatDate, MONTHS } from '@/utils/format';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    transactions: Object,
    categories: Array,
    filters: Object,
});

const page = usePage();
const userId = computed(() => page.props.auth.user.id);
const isOwner = computed(() => page.props.auth.user.is_owner);

const years = computed(() => {
    const current = new Date().getFullYear();
    return [current, current - 1, current - 2];
});

const applyFilters = (event) => {
    const form = event.target.closest('form');
    router.get(route('transactions.index'), {
        month: form.month.value,
        year: form.year.value,
        type: form.type.value || undefined,
        category_id: form.category_id.value || undefined,
    }, { preserveState: true });
};

const canEdit = (tx) => isOwner.value || tx.user_id === userId.value;

const destroy = (tx) => {
    if (!confirm('Excluir esta transação?')) return;
    router.delete(route('transactions.destroy', tx.id));
};
</script>

<template>
    <Head title="Transações" />

    <AppLayout>
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Transações</h1>
                <p class="text-sm text-slate-500">Todas as movimentações da conta</p>
            </div>
            <Link :href="route('transactions.create')">
                <PrimaryButton>Adicionar</PrimaryButton>
            </Link>
        </div>

        <form class="mb-6 flex flex-wrap gap-3" @change="applyFilters">
            <select name="month" class="rounded-md border-slate-300 text-sm" :value="filters.month">
                <option v-for="m in MONTHS" :key="m.value" :value="m.value">{{ m.label }}</option>
            </select>
            <select name="year" class="rounded-md border-slate-300 text-sm" :value="filters.year">
                <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
            </select>
            <select name="type" class="rounded-md border-slate-300 text-sm" :value="filters.type || ''">
                <option value="">Todos os tipos</option>
                <option value="income">Entradas</option>
                <option value="expense">Saídas</option>
            </select>
            <select name="category_id" class="rounded-md border-slate-300 text-sm" :value="filters.category_id || ''">
                <option value="">Todas as categorias</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
        </form>

        <Card>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-horizon-100 text-sm">
                    <thead class="text-left text-horizon-500">
                        <tr>
                            <th class="px-5 py-4 font-medium">Data</th>
                            <th class="px-5 py-4 font-medium">Descrição</th>
                            <th class="px-5 py-4 font-medium">Categoria</th>
                            <th class="px-5 py-4 font-medium">Quem lançou</th>
                            <th class="px-5 py-4 font-medium text-right">Valor</th>
                            <th class="px-5 py-4 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-horizon-100">
                        <tr v-for="tx in transactions.data" :key="tx.id">
                            <td class="whitespace-nowrap px-5 py-3 text-navy-700">{{ formatDate(tx.date) }}</td>
                            <td class="px-5 py-3 text-navy-700">{{ tx.description }}</td>
                            <td class="px-5 py-3 text-navy-700">{{ tx.category?.name }}</td>
                            <td class="px-5 py-3 text-horizon-600">{{ tx.user?.name }}</td>
                            <td class="px-5 py-3 text-right font-bold" :class="tx.type === 'income' ? 'text-emerald-600' : 'text-red-600'">
                                {{ tx.type === 'income' ? '+' : '-' }}{{ formatBRL(tx.amount) }}
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-right">
                                <template v-if="canEdit(tx)">
                                    <Link :href="route('transactions.edit', tx.id)" class="text-cta hover:underline">Editar</Link>
                                    <button type="button" class="ms-3 text-red-600 hover:underline" @click="destroy(tx)">Excluir</button>
                                </template>
                            </td>
                        </tr>
                        <tr v-if="!transactions.data.length">
                            <td colspan="6" class="px-5 py-8 text-center text-horizon-500">Nenhuma transação encontrada.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <div v-if="transactions.links?.length > 3" class="mt-4 flex flex-wrap gap-2">
            <Link
                v-for="link in transactions.links"
                :key="link.label"
                :href="link.url || '#'"
                class="rounded border px-3 py-1 text-sm"
                :class="link.active ? 'border-cta bg-cta text-white' : 'border-slate-200 bg-white text-slate-700'"
                v-html="link.label"
                :preserve-scroll="true"
            />
        </div>
    </AppLayout>
</template>
