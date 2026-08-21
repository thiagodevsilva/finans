<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/Card.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TourDemoBanner from '@/Components/TourDemoBanner.vue';
import TransactionList from '@/Components/TransactionList.vue';
import { useAppTour } from '@/Composables/useAppTour';
import { useTourDemo } from '@/Composables/useTourDemo';
import { TRANSACTIONS_TOUR_ID } from '@/tours/transactions';
import { MONTHS, PAYMENT_METHODS, formatBRL } from '@/utils/format';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    transactions: Object,
    categories: Array,
    filters: Object,
    filterSummary: {
        type: Object,
        default: () => ({ count: 0, signed_total: 0 }),
    },
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

const summaryCount = computed(() => {
    if (showingDemo.value) {
        return listTransactions.value.length;
    }
    return props.filterSummary?.count ?? 0;
});

const summaryTotal = computed(() => {
    if (showingDemo.value) {
        return listTransactions.value.reduce((sum, tx) => {
            const amount = Number(tx.amount) || 0;
            if (tx.type === 'income') return sum + amount;
            if (tx.type === 'expense' || tx.type === 'investment' || tx.type === 'transfer') {
                return sum - amount;
            }
            return sum;
        }, 0);
    }
    return Number(props.filterSummary?.signed_total ?? 0);
});

const summaryToneClass = computed(() => {
    if (summaryTotal.value > 0) return 'text-emerald-600';
    if (summaryTotal.value < 0) return 'text-red-600';
    return 'text-navy-700';
});

const summaryTotalLabel = computed(() => {
    const value = summaryTotal.value;
    if (value > 0) return `+${formatBRL(value)}`;
    if (value < 0) return `-${formatBRL(Math.abs(value))}`;
    return formatBRL(0);
});

const years = computed(() => {
    const current = new Date().getFullYear();
    return [current, current - 1, current - 2];
});

const paymentMethodOptions = [
    ...PAYMENT_METHODS,
    { value: 'card', label: 'Cartão' },
];

const selectedPaymentMethods = ref([...(props.filters.payment_methods || [])]);
const paymentMenuOpen = ref(false);
const paymentMenuRef = ref(null);

watch(
    () => props.filters.payment_methods,
    (value) => {
        selectedPaymentMethods.value = [...(value || [])];
    },
);

const paymentFilterLabel = computed(() => {
    const selected = selectedPaymentMethods.value;
    if (!selected.length) {
        return 'Formas de pagamento';
    }
    if (selected.length === 1) {
        return paymentMethodOptions.find((o) => o.value === selected[0])?.label || '1 forma';
    }
    return `${selected.length} formas`;
});

const closePaymentMenu = (event) => {
    if (!paymentMenuRef.value?.contains(event.target)) {
        paymentMenuOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', closePaymentMenu);

    if (isTourActive()) {
        resumeIfActive();
        return;
    }

    const params = new URLSearchParams(window.location.search);
    if (params.get('tour') === TRANSACTIONS_TOUR_ID) {
        startTour(TRANSACTIONS_TOUR_ID);
    }
});

onUnmounted(() => {
    document.removeEventListener('click', closePaymentMenu);
});

const currentFilters = (form) => ({
    month: form.month.value,
    year: form.year.value,
    type: form.type.value || undefined,
    category_id: form.category_id.value || undefined,
    payment_methods: selectedPaymentMethods.value.length
        ? selectedPaymentMethods.value
        : undefined,
});

const applyFilters = (event) => {
    const form = event.target.closest('form');
    router.get(route('transactions.index'), currentFilters(form), { preserveState: true });
};

const onFormChange = (event) => {
    if (event.target.type === 'checkbox') {
        return;
    }
    applyFilters(event);
};

const applyPaymentFilters = () => {
    const form = document.querySelector('[data-tour="tx-filters"]');
    if (!form) return;
    router.get(route('transactions.index'), currentFilters(form), { preserveState: true });
};

const togglePaymentMethod = (value) => {
    const set = new Set(selectedPaymentMethods.value);
    if (set.has(value)) {
        set.delete(value);
    } else {
        set.add(value);
    }
    selectedPaymentMethods.value = [...set];
    applyPaymentFilters();
};

const clearPaymentMethods = () => {
    selectedPaymentMethods.value = [];
    applyPaymentFilters();
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
                <Link :href="route('transactions.create')" data-tour="tx-add">
                    <PrimaryButton>Adicionar</PrimaryButton>
                </Link>
            </div>
        </div>

        <form
            class="mb-4 flex flex-wrap items-start gap-2 sm:mb-6 sm:gap-3"
            data-tour="tx-filters"
            @change="onFormChange"
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

            <div ref="paymentMenuRef" class="relative">
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-horizon-200 bg-white px-3 py-2 text-sm text-navy-700"
                    :class="selectedPaymentMethods.length ? 'border-cta/40 bg-cta/5 font-medium text-cta' : ''"
                    @click.stop="paymentMenuOpen = !paymentMenuOpen"
                >
                    {{ paymentFilterLabel }}
                    <span class="text-horizon-400" aria-hidden="true">▾</span>
                </button>
                <div
                    v-if="paymentMenuOpen"
                    class="absolute left-0 z-20 mt-1 min-w-[14rem] rounded-xl border border-horizon-200 bg-white p-2 shadow-soft"
                    @click.stop
                >
                    <p class="px-2 pb-1 text-xs font-medium text-horizon-500">Selecionar uma ou mais</p>
                    <label
                        v-for="option in paymentMethodOptions"
                        :key="option.value"
                        class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-navy-700 hover:bg-horizon-50"
                    >
                        <input
                            type="checkbox"
                            class="rounded border-horizon-300 text-cta focus:ring-cta"
                            :checked="selectedPaymentMethods.includes(option.value)"
                            @change="togglePaymentMethod(option.value)"
                        >
                        <span>{{ option.label }}</span>
                    </label>
                    <button
                        v-if="selectedPaymentMethods.length"
                        type="button"
                        class="mt-1 w-full rounded-lg px-2 py-1.5 text-left text-xs font-semibold text-cta hover:bg-cta/5"
                        @click="clearPaymentMethods"
                    >
                        Limpar formas
                    </button>
                </div>
            </div>
        </form>

        <div
            v-if="summaryCount > 0"
            class="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-[16px] bg-white px-4 py-3 shadow-soft"
            data-tour="tx-filter-summary"
        >
            <p class="text-sm text-horizon-600">
                {{ summaryCount }} {{ summaryCount === 1 ? 'lançamento' : 'lançamentos' }} no filtro
            </p>
            <p class="text-sm font-bold tabular-nums" :class="summaryToneClass">
                Soma: {{ summaryTotalLabel }}
            </p>
        </div>

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
