<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/Card.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TourDemoBanner from '@/Components/TourDemoBanner.vue';
import TourHelpButton from '@/Components/TourHelpButton.vue';
import TransactionList from '@/Components/TransactionList.vue';
import { useAppTour } from '@/Composables/useAppTour';
import { useTourDemo } from '@/Composables/useTourDemo';
import { TRANSACTIONS_TOUR_ID } from '@/tours/transactions';
import { MONTHS } from '@/utils/format';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted } from 'vue';

const props = defineProps({
    transactions: Object,
    categories: Array,
    filters: Object,
});

const page = usePage();
const userId = computed(() => page.props.auth.user.id);
const isOwner = computed(() => page.props.auth.user.is_owner);
const { startTour, resumeIfActive, isTourActive } = useAppTour();
const { isDemoTour, demoTransactionsList } = useTourDemo();

const showingDemo = computed(() => isDemoTour(TRANSACTIONS_TOUR_ID));

const listTransactions = computed(() =>
    showingDemo.value ? demoTransactionsList : props.transactions.data,
);

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

const canEdit = (tx) => {
    if (showingDemo.value) {
        return false;
    }
    return isOwner.value || tx.user_id === userId.value;
};

const destroy = (tx) => {
    if (showingDemo.value) {
        return;
    }
    if (!confirm('Excluir esta transação?')) return;
    router.delete(route('transactions.destroy', tx.id));
};

onMounted(() => {
    if (isTourActive()) {
        resumeIfActive();
        return;
    }

    const params = new URLSearchParams(window.location.search);
    if (params.get('tour') === TRANSACTIONS_TOUR_ID) {
        startTour(TRANSACTIONS_TOUR_ID);
    }
});
</script>

<template>
    <Head title="Transações" />

    <AppLayout>
        <TourDemoBanner :show="showingDemo" />

        <div
            class="mb-4 flex flex-col gap-3 sm:mb-6 sm:flex-row sm:items-center sm:justify-between"
            data-tour="tx-page"
        >
            <div>
                <h1 class="text-xl font-bold text-navy-700 sm:text-2xl">Transações</h1>
                <p class="text-sm text-horizon-500">Todas as movimentações da conta</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <TourHelpButton :tour-id="TRANSACTIONS_TOUR_ID" />
                <Link :href="route('transactions.create')" data-tour="tx-add">
                    <PrimaryButton>Adicionar</PrimaryButton>
                </Link>
            </div>
        </div>

        <form
            class="mb-4 flex flex-wrap gap-2 sm:mb-6 sm:gap-3"
            data-tour="tx-filters"
            @change="applyFilters"
        >
            <select name="month" class="rounded-xl border-horizon-200 text-sm text-navy-700" :value="filters.month">
                <option v-for="m in MONTHS" :key="m.value" :value="m.value">{{ m.label }}</option>
            </select>
            <select name="year" class="rounded-xl border-horizon-200 text-sm text-navy-700" :value="filters.year">
                <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
            </select>
            <select name="type" class="rounded-xl border-horizon-200 text-sm text-navy-700" :value="filters.type || ''">
                <option value="">Todos os tipos</option>
                <option value="income">Entradas</option>
                <option value="expense">Saídas</option>
                <option value="investment">Investimentos</option>
                <option value="transfer">Pagamentos de fatura</option>
            </select>
            <select name="category_id" class="rounded-xl border-horizon-200 text-sm text-navy-700" :value="filters.category_id || ''">
                <option value="">Todas as categorias</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
        </form>

        <Card extra="!bg-transparent !shadow-none md:!bg-white md:shadow-soft" data-tour="tx-list">
            <TransactionList
                :transactions="listTransactions"
                :show-actions="!showingDemo"
                :can-edit="canEdit"
                empty-message="Nenhuma transação encontrada."
                @destroy="destroy"
            />
        </Card>

        <div v-if="!showingDemo && transactions.links?.length > 3" class="mt-4 flex flex-wrap gap-2">
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
