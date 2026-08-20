<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatBRL, formatDate, MONTHS } from '@/utils/format';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    payments: Object,
    cards: Array,
    filters: Object,
});

const years = computed(() => {
    const current = new Date().getFullYear();
    return [current, current - 1, current - 2];
});

const applyFilters = (event) => {
    const form = event.target.closest('form');
    router.get(route('credit-card-payments.index'), {
        month: form.month.value,
        year: form.year.value,
        payment_card_id: form.payment_card_id.value || undefined,
    }, { preserveState: true });
};
</script>

<template>
    <Head title="Pagamentos de fatura" />

    <AppLayout>
        <div class="mb-4 flex flex-col gap-2 sm:mb-6 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-navy-700 sm:text-2xl">Pagamentos de fatura</h1>
                <Link :href="route('payment-cards.index')" class="text-sm text-cta hover:underline">Voltar aos cartões</Link>
            </div>
            <Link :href="route('transactions.create', { type: 'transfer' })" class="text-sm font-semibold text-cta hover:underline">
                Novo pagamento
            </Link>
        </div>

        <form class="mb-4 flex flex-wrap gap-2" @change="applyFilters">
            <select name="month" class="rounded-xl border-horizon-200 text-sm text-navy-700" :value="filters.month">
                <option v-for="m in MONTHS" :key="m.value" :value="m.value">{{ m.label }}</option>
            </select>
            <select name="year" class="rounded-xl border-horizon-200 text-sm text-navy-700" :value="filters.year">
                <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
            </select>
            <select name="payment_card_id" class="rounded-xl border-horizon-200 text-sm text-navy-700" :value="filters.payment_card_id || ''">
                <option value="">Todos os cartões</option>
                <option v-for="card in cards" :key="card.id" :value="card.id">{{ card.name }}</option>
            </select>
        </form>

        <div v-if="!payments.data?.length" class="rounded-[16px] bg-white px-4 py-10 text-center text-sm text-horizon-500 shadow-soft">
            Nenhum pagamento neste filtro.
        </div>
        <ul v-else class="divide-y divide-horizon-100 rounded-[16px] bg-white px-4 shadow-soft">
            <li
                v-for="payment in payments.data"
                :key="payment.id"
                class="flex items-center justify-between gap-3 py-3 text-sm"
            >
                <div class="min-w-0">
                    <p class="truncate font-medium text-navy-700">{{ payment.description }}</p>
                    <p class="text-xs text-horizon-500">
                        {{ formatDate(payment.date) }}
                        <span v-if="payment.payment_card"> · {{ payment.payment_card.name }}</span>
                        <span v-if="payment.bank_account"> · {{ payment.bank_account.name }}</span>
                    </p>
                </div>
                <div class="flex shrink-0 items-center gap-3">
                    <p class="font-bold tabular-nums text-navy-700">{{ formatBRL(payment.amount) }}</p>
                    <Link
                        :href="route('transactions.edit', payment.id)"
                        class="text-xs font-semibold text-cta hover:underline"
                    >
                        Editar
                    </Link>
                </div>
            </li>
        </ul>

        <div v-if="payments.links?.length > 3" class="mt-4 flex flex-wrap gap-2">
            <Link
                v-for="link in payments.links"
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
