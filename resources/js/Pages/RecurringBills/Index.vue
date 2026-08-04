<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import MoneyInput from '@/Components/MoneyInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { PAYMENT_METHODS, formatBRL, formatCardLabel, formatDate, MONTHS } from '@/utils/format';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    bills: Array,
    upcoming: { type: Array, default: () => [] },
    paid: { type: Array, default: () => [] },
    periodSummary: {
        type: Object,
        default: () => ({
            current: { month: null, pending_count: 0, pending_amount: 0, paid_count: 0, paid_amount: 0 },
            next: { month: null, pending_count: 0, pending_amount: 0, paid_count: 0, paid_amount: 0 },
        }),
    },
    categories: Array,
    paymentCards: Array,
    bankAccounts: Array,
});

const page = usePage();
const userId = computed(() => page.props.auth.user.id);

const editing = ref(null);
const confirming = ref(null);
const showForm = ref(false);
const catalogExpanded = ref(false);

const form = useForm({
    description: '',
    category_id: '',
    estimated_amount: '',
    day_of_month: '10',
    payment_selection: 'pix',
    payment_method: 'pix',
    payment_card_id: null,
    bank_account_id: '',
    start_date: new Date().toISOString().slice(0, 10),
    end_date: '',
    propagate: 'none',
    propagate_from: new Date().toISOString().slice(0, 10),
});

const confirmForm = useForm({
    amount: '',
    date: '',
});

watch(
    () => form.payment_selection,
    (value) => {
        if (String(value).startsWith('card:')) {
            form.payment_method = 'card';
            form.payment_card_id = String(value).slice(5);
        } else {
            form.payment_method = value || null;
            form.payment_card_id = null;
        }
    },
    { immediate: true },
);

const monthLabel = (ym) => {
    if (!ym) return '';
    const [year, month] = String(ym).split('-');
    const found = MONTHS.find((m) => m.value === Number(month));
    return found ? `${found.label} ${year}` : ym;
};

const currentSummary = computed(() => props.periodSummary?.current ?? null);
const nextSummary = computed(() => props.periodSummary?.next ?? null);
const currentMonthKey = computed(() => currentSummary.value?.month ?? null);

const paidPercent = computed(() => {
    if (currentSummary.value?.paid_percent != null) {
        return currentSummary.value.paid_percent;
    }
    const paid = Number(currentSummary.value?.paid_amount ?? 0);
    const pending = Number(currentSummary.value?.pending_amount ?? 0);
    const total = paid + pending;
    return total > 0 ? Math.round((paid / total) * 100) : 0;
});

const currentMonthPending = computed(() => {
    const key = currentMonthKey.value;
    if (!key) return [];
    return props.upcoming
        .filter((item) => String(item.date).startsWith(key))
        .sort((a, b) => String(a.date).localeCompare(String(b.date)));
});

const scheduleByMonth = computed(() => {
    const groups = new Map();

    const ensure = (key) => {
        if (!groups.has(key)) {
            groups.set(key, {
                key,
                label: monthLabel(key),
                pending: [],
                paid: [],
                pending_amount: 0,
                paid_amount: 0,
            });
        }
        return groups.get(key);
    };

    for (const item of props.upcoming) {
        const key = String(item.date).slice(0, 7);
        const group = ensure(key);
        group.pending.push(item);
        group.pending_amount += Number(item.amount || 0);
    }

    for (const item of props.paid) {
        const key = String(item.date).slice(0, 7);
        const group = ensure(key);
        group.paid.push(item);
        group.paid_amount += Number(item.amount || 0);
    }

    return Array.from(groups.values())
        .map((g) => ({
            ...g,
            pending_amount: Math.round(g.pending_amount * 100) / 100,
            paid_amount: Math.round(g.paid_amount * 100) / 100,
            total_count: g.pending.length + g.paid.length,
        }))
        .sort((a, b) => a.key.localeCompare(b.key));
});

const hasSchedule = computed(() => scheduleByMonth.value.length > 0);

const categoryBreakdown = computed(() => {
    const key = currentMonthKey.value;
    if (!key) return [];

    const map = new Map();

    const add = (item, kind) => {
        const cat = item.category;
        const catId = cat?.id ?? 'none';
        if (!map.has(catId)) {
            map.set(catId, {
                id: catId,
                name: cat?.name ?? 'Sem categoria',
                color: cat?.color ?? '#94a3b8',
                pending: 0,
                paid: 0,
                total: 0,
            });
        }
        const entry = map.get(catId);
        const amount = Number(item.amount || 0);
        if (kind === 'pending') {
            entry.pending += amount;
        } else {
            entry.paid += amount;
        }
        entry.total = entry.pending + entry.paid;
    };

    for (const item of props.upcoming) {
        if (String(item.date).startsWith(key)) add(item, 'pending');
    }
    for (const item of props.paid) {
        if (String(item.date).startsWith(key)) add(item, 'paid');
    }

    return Array.from(map.values())
        .map((e) => ({
            ...e,
            pending: Math.round(e.pending * 100) / 100,
            paid: Math.round(e.paid * 100) / 100,
            total: Math.round(e.total * 100) / 100,
        }))
        .sort((a, b) => b.total - a.total);
});

const maxCategoryTotal = computed(() =>
    Math.max(...categoryBreakdown.value.map((c) => c.total), 1),
);

const expandedMonths = ref({});

watch(
    currentMonthKey,
    (key) => {
        if (key && expandedMonths.value[key] === undefined) {
            expandedMonths.value[key] = true;
        }
    },
    { immediate: true },
);

const isMonthExpanded = (key) => expandedMonths.value[key] ?? false;

const toggleMonth = (key) => {
    expandedMonths.value[key] = !isMonthExpanded(key);
};

const resetForm = () => {
    editing.value = null;
    showForm.value = false;
    form.reset();
    form.day_of_month = '10';
    form.payment_selection = 'pix';
    form.payment_method = 'pix';
    form.start_date = new Date().toISOString().slice(0, 10);
    form.propagate = 'none';
    form.propagate_from = new Date().toISOString().slice(0, 10);
};

const openCreateForm = () => {
    editing.value = null;
    form.reset();
    form.day_of_month = '10';
    form.payment_selection = 'pix';
    form.payment_method = 'pix';
    form.start_date = new Date().toISOString().slice(0, 10);
    form.propagate = 'none';
    form.propagate_from = new Date().toISOString().slice(0, 10);
    form.clearErrors();
    showForm.value = true;
};

const startEdit = (bill) => {
    editing.value = bill.id;
    form.description = bill.description;
    form.category_id = bill.category_id;
    form.estimated_amount = bill.estimated_amount;
    form.day_of_month = String(bill.day_of_month);
    form.payment_selection = bill.payment_method === 'card' && bill.payment_card_id
        ? `card:${bill.payment_card_id}`
        : (bill.payment_method || 'pix');
    form.bank_account_id = bill.bank_account_id || '';
    form.start_date = bill.start_date;
    form.end_date = bill.end_date || '';
    form.propagate = 'none';
    form.propagate_from = new Date().toISOString().slice(0, 10);
    form.clearErrors();
    showForm.value = true;
};

const submit = () => {
    if (!form.bank_account_id) form.bank_account_id = null;
    if (!form.end_date) form.end_date = null;
    if (!editing.value) {
        form.propagate = 'none';
        form.propagate_from = null;
    } else if (form.propagate !== 'from_date') {
        form.propagate_from = null;
    }

    if (editing.value) {
        form.put(route('recurring-bills.update', editing.value), {
            onSuccess: () => resetForm(),
        });
    } else {
        form.post(route('recurring-bills.store'), {
            onSuccess: () => resetForm(),
        });
    }
};

const destroy = (bill) => {
    if (!confirm(`Excluir "${bill.description}"? Contas sem histórico serão removidas.`)) return;
    router.delete(route('recurring-bills.destroy', bill.id));
};

const openConfirm = (item) => {
    confirming.value = item;
    confirmForm.amount = item.amount;
    confirmForm.date = item.date;
    confirmForm.clearErrors();
};

const closeConfirm = () => {
    confirming.value = null;
    confirmForm.clearErrors();
};

const submitConfirm = () => {
    if (!confirming.value) return;
    confirmForm.post(route('recurring-transactions.confirm', confirming.value.id), {
        onSuccess: () => {
            confirming.value = null;
        },
    });
};

const skip = (item) => {
    if (!confirm('Pular este lançamento no mês?')) return;
    router.post(route('recurring-transactions.skip', item.id));
};
</script>

<template>
    <Head title="Contas fixas" />

    <AppLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-navy-700">Contas fixas</h1>
            <p class="text-sm text-horizon-500">Água, luz, internet e outras contas que se repetem todo mês</p>
        </div>

        <div class="lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(260px,300px)] lg:items-start lg:gap-6">
            <div class="min-w-0">
                <!-- Dashboard do mês -->
                <section class="mb-4 rounded-[16px] bg-white px-4 py-4 shadow-soft sm:mb-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-horizon-500 sm:text-sm">
                                Este mês · {{ monthLabel(currentSummary?.month) }}
                            </p>
                            <p class="mt-1 text-sm font-bold tabular-nums text-navy-700 sm:text-base">
                                {{ paidPercent }}% pagas
                                <span class="font-medium text-horizon-600">
                                    ({{ formatBRL(currentSummary?.paid_amount) }} / {{ formatBRL(currentSummary?.total_amount ?? (Number(currentSummary?.paid_amount ?? 0) + Number(currentSummary?.pending_amount ?? 0))) }})
                                </span>
                            </p>
                        </div>
                        <div class="text-right text-sm">
                            <p class="tabular-nums text-navy-700">
                                <span class="font-bold">{{ currentSummary?.pending_count ?? 0 }}</span>
                                <span class="text-horizon-500"> a pagar</span>
                                <span class="ml-1 font-semibold">{{ formatBRL(currentSummary?.pending_amount) }}</span>
                            </p>
                            <p class="mt-0.5 tabular-nums text-emerald-700">
                                <span class="font-bold">{{ currentSummary?.paid_count ?? 0 }}</span>
                                <span class="text-horizon-500"> pagas</span>
                                <span class="ml-1 font-semibold">{{ formatBRL(currentSummary?.paid_amount) }}</span>
                            </p>
                        </div>
                    </div>
                    <div class="progress-track mt-3">
                        <div class="progress-fill" :style="{ width: `${paidPercent}%` }" />
                    </div>
                    <p v-if="nextSummary?.month" class="mt-3 text-xs text-horizon-500">
                        Próximo mês · {{ monthLabel(nextSummary.month) }}:
                        <span class="font-medium text-navy-700">{{ nextSummary.pending_count }} previstas</span>
                        ({{ formatBRL(nextSummary.pending_amount) }})
                    </p>
                </section>

                <!-- Adicionar sutil -->
                <div class="mb-4 sm:mb-5">
                    <button
                        type="button"
                        class="inline-flex items-center rounded-xl border border-cta/30 bg-cta/5 px-3 py-1.5 text-xs font-semibold text-cta hover:bg-cta/10 sm:text-sm"
                        @click="openCreateForm"
                    >
                        + Nova conta fixa
                    </button>
                </div>

                <!-- Próximos vencimentos (mês atual) -->
                <section v-if="currentMonthPending.length" class="mb-4 sm:mb-5">
                    <h2 class="mb-2 text-base font-bold text-navy-700 sm:text-lg">Próximos vencimentos</h2>
                    <div class="divide-y divide-horizon-100 overflow-hidden rounded-[16px] bg-white shadow-soft">
                        <div
                            v-for="item in currentMonthPending"
                            :key="item.id"
                            class="flex items-center justify-between gap-2 px-4 py-3"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-navy-700">{{ item.description }}</p>
                                <p class="truncate text-xs text-horizon-500">
                                    Vence {{ formatDate(item.date) }} · {{ formatBRL(item.amount) }}
                                    <span
                                        v-if="item.category"
                                        class="ml-1 inline-flex items-center gap-1"
                                    >
                                        ·
                                        <span
                                            class="inline-block h-2 w-2 shrink-0 rounded-full"
                                            :style="{ backgroundColor: item.category.color }"
                                        />
                                        {{ item.category.name }}
                                    </span>
                                </p>
                            </div>
                            <div v-if="item.can_edit" class="flex shrink-0 gap-2 text-xs sm:text-sm">
                                <button
                                    type="button"
                                    class="rounded-lg bg-cta/10 px-2.5 py-1.5 font-semibold text-cta hover:bg-cta/15"
                                    @click="openConfirm(item)"
                                >
                                    Confirmar
                                </button>
                                <button
                                    type="button"
                                    class="px-1 py-1.5 font-medium text-horizon-600 hover:underline"
                                    @click="skip(item)"
                                >
                                    Pular
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Por categoria -->
                <section v-if="categoryBreakdown.length" class="mb-4 sm:mb-5">
                    <h2 class="mb-2 text-base font-bold text-navy-700 sm:text-lg">Por categoria</h2>
                    <p class="mb-3 text-xs text-horizon-500 sm:text-sm">
                        Totais do mês atual (pagas + a pagar)
                    </p>
                    <div class="space-y-3 rounded-[16px] bg-white px-4 py-3 shadow-soft">
                        <div v-for="cat in categoryBreakdown" :key="cat.id" class="min-w-0">
                            <div class="flex items-center justify-between gap-2 text-sm">
                                <span class="flex min-w-0 items-center gap-2 truncate font-medium text-navy-700">
                                    <span
                                        class="inline-block h-2.5 w-2.5 shrink-0 rounded-full"
                                        :style="{ backgroundColor: cat.color }"
                                    />
                                    <span class="truncate">{{ cat.name }}</span>
                                </span>
                                <span class="shrink-0 font-semibold tabular-nums text-navy-700">
                                    {{ formatBRL(cat.total) }}
                                </span>
                            </div>
                            <div class="mt-1.5 flex h-1.5 overflow-hidden rounded-full bg-horizon-100">
                                <div
                                    class="h-full bg-emerald-500"
                                    :style="{ width: `${(cat.paid / maxCategoryTotal) * 100}%` }"
                                />
                                <div
                                    class="h-full bg-amber-400"
                                    :style="{ width: `${(cat.pending / maxCategoryTotal) * 100}%` }"
                                />
                            </div>
                            <p class="mt-1 text-[11px] text-horizon-500 sm:text-xs">
                                <span v-if="cat.paid > 0" class="text-emerald-700">{{ formatBRL(cat.paid) }} pagas</span>
                                <span v-if="cat.paid > 0 && cat.pending > 0"> · </span>
                                <span v-if="cat.pending > 0" class="text-amber-700">{{ formatBRL(cat.pending) }} a pagar</span>
                            </p>
                        </div>
                    </div>
                </section>

                <!-- A pagar por mês -->
                <section data-tour="recurring-to-pay">
                    <div class="mb-3">
                        <h2 class="text-base font-bold text-navy-700 sm:text-lg">A pagar</h2>
                        <p class="text-xs text-horizon-500 sm:text-sm">
                            Previsto deste mês e dos próximos — gerado ao abrir a tela
                        </p>
                    </div>

                    <div v-if="!hasSchedule" class="rounded-[20px] bg-white px-5 py-8 text-center text-sm text-horizon-500 shadow-soft">
                        Nenhuma conta fixa prevista no horizonte.
                    </div>

                    <div v-else class="space-y-3">
                        <article
                            v-for="group in scheduleByMonth"
                            :key="group.key"
                            class="overflow-hidden rounded-[16px] bg-white shadow-soft"
                        >
                            <button
                                type="button"
                                class="flex w-full flex-wrap items-center justify-between gap-2 border-b border-horizon-100 px-4 py-3 text-left hover:bg-horizon-50/50"
                                :class="!isMonthExpanded(group.key) ? 'border-b-0' : ''"
                                @click="toggleMonth(group.key)"
                            >
                                <div class="min-w-0">
                                    <h3 class="text-sm font-bold text-navy-700 sm:text-base">{{ group.label }}</h3>
                                    <p class="text-[11px] text-horizon-500 sm:text-xs">
                                        {{ group.total_count }} conta{{ group.total_count === 1 ? '' : 's' }}
                                        <span v-if="group.pending.length">
                                            · {{ group.pending.length }} a pagar ({{ formatBRL(group.pending_amount) }})
                                        </span>
                                        <span v-if="group.paid.length">
                                            · {{ group.paid.length }} paga{{ group.paid.length === 1 ? '' : 's' }} ({{ formatBRL(group.paid_amount) }})
                                        </span>
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-center gap-2">
                                    <p
                                        v-if="group.pending.length"
                                        class="text-sm font-bold tabular-nums text-navy-700"
                                    >
                                        {{ formatBRL(group.pending_amount) }}
                                    </p>
                                    <svg
                                        class="h-4 w-4 text-horizon-400 transition-transform"
                                        :class="isMonthExpanded(group.key) ? 'rotate-180' : ''"
                                        viewBox="0 0 20 20"
                                        fill="currentColor"
                                        aria-hidden="true"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                </div>
                            </button>

                            <div v-show="isMonthExpanded(group.key)">
                                <div v-if="group.pending.length" class="divide-y divide-horizon-100">
                                    <div
                                        v-for="item in group.pending"
                                        :key="item.id"
                                        class="flex items-center justify-between gap-2 px-4 py-3"
                                    >
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-semibold text-navy-700">{{ item.description }}</p>
                                            <p class="truncate text-xs text-horizon-500">
                                                Vence {{ formatDate(item.date) }} · {{ formatBRL(item.amount) }}
                                                <span
                                                    v-if="item.category"
                                                    class="ml-1 inline-flex items-center gap-1"
                                                >
                                                    ·
                                                    <span
                                                        class="inline-block h-2 w-2 shrink-0 rounded-full"
                                                        :style="{ backgroundColor: item.category.color }"
                                                    />
                                                    {{ item.category.name }}
                                                </span>
                                            </p>
                                        </div>
                                        <div v-if="item.can_edit" class="flex shrink-0 gap-2 text-xs sm:text-sm">
                                            <button
                                                type="button"
                                                class="rounded-lg bg-cta/10 px-2.5 py-1.5 font-semibold text-cta hover:bg-cta/15"
                                                @click="openConfirm(item)"
                                            >
                                                Confirmar
                                            </button>
                                            <button
                                                type="button"
                                                class="px-1 py-1.5 font-medium text-horizon-600 hover:underline"
                                                @click="skip(item)"
                                            >
                                                Pular
                                            </button>
                                        </div>
                                        <span v-else class="shrink-0 rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700">
                                            Pendente
                                        </span>
                                    </div>
                                </div>

                                <div v-if="group.paid.length" :class="group.pending.length ? 'border-t border-horizon-100' : ''">
                                    <p class="bg-emerald-50/60 px-4 py-2 text-[11px] font-semibold uppercase tracking-wide text-emerald-700">
                                        Pagas · {{ group.paid.length }} · {{ formatBRL(group.paid_amount) }}
                                    </p>
                                    <div class="divide-y divide-horizon-100">
                                        <div
                                            v-for="item in group.paid"
                                            :key="item.id"
                                            class="flex items-center justify-between gap-2 px-4 py-2.5"
                                        >
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate text-sm font-medium text-navy-700">{{ item.description }}</p>
                                                <p class="truncate text-xs text-horizon-500">
                                                    {{ formatDate(item.date) }} · {{ formatBRL(item.amount) }}
                                                    <span
                                                        v-if="item.category"
                                                        class="ml-1 inline-flex items-center gap-1"
                                                    >
                                                        ·
                                                        <span
                                                            class="inline-block h-2 w-2 shrink-0 rounded-full"
                                                            :style="{ backgroundColor: item.category.color }"
                                                        />
                                                        {{ item.category.name }}
                                                    </span>
                                                </p>
                                            </div>
                                            <span class="shrink-0 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700">
                                                Paga
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>
                </section>
            </div>

            <!-- Sidebar: cadastradas (desktop) -->
            <aside class="hidden lg:block">
                <div class="sticky top-4 rounded-[16px] bg-white shadow-soft">
                    <div class="border-b border-horizon-100 px-4 py-3">
                        <h2 class="text-base font-bold text-navy-700">Cadastradas</h2>
                        <p class="text-xs text-horizon-500">{{ bills.length }} conta{{ bills.length === 1 ? '' : 's' }}</p>
                    </div>
                    <div v-if="!bills.length" class="px-4 py-6 text-center text-sm text-horizon-500">
                        Nenhuma conta fixa cadastrada.
                    </div>
                    <div v-else class="divide-y divide-horizon-100">
                        <article
                            v-for="bill in bills"
                            :key="bill.id"
                            class="px-4 py-3"
                        >
                            <div class="min-w-0">
                                <h3 class="truncate text-sm font-semibold text-navy-700">{{ bill.description }}</h3>
                                <p class="mt-0.5 truncate text-xs text-horizon-500">
                                    Dia {{ bill.day_of_month }} · {{ formatBRL(bill.estimated_amount) }}
                                </p>
                                <p class="mt-0.5 truncate text-xs text-horizon-500">
                                    <span
                                        v-if="bill.category"
                                        class="inline-flex items-center gap-1"
                                    >
                                        <span
                                            class="inline-block h-2 w-2 shrink-0 rounded-full"
                                            :style="{ backgroundColor: bill.category.color }"
                                        />
                                        {{ bill.category.name }}
                                    </span>
                                    <span v-if="!bill.active"> · Inativa</span>
                                </p>
                                <p class="mt-1 text-[11px] text-horizon-400">
                                    {{ bill.user_id === userId ? 'Minha' : bill.user?.name }}
                                </p>
                            </div>
                            <div v-if="bill.can_edit && bill.active" class="mt-2 flex gap-3 text-xs">
                                <button type="button" class="font-medium text-cta hover:underline" @click="startEdit(bill)">
                                    Editar
                                </button>
                                <button type="button" class="font-medium text-red-600 hover:underline" @click="destroy(bill)">
                                    Excluir
                                </button>
                            </div>
                        </article>
                    </div>
                </div>
            </aside>
        </div>

        <!-- Cadastradas colapsável (mobile) -->
        <section class="mt-6 lg:hidden">
            <button
                type="button"
                class="flex w-full items-center justify-between rounded-[16px] bg-white px-4 py-3 shadow-soft"
                @click="catalogExpanded = !catalogExpanded"
            >
                <div>
                    <h2 class="text-base font-bold text-navy-700">Cadastradas</h2>
                    <p class="text-xs text-horizon-500">{{ bills.length }} conta{{ bills.length === 1 ? '' : 's' }}</p>
                </div>
                <svg
                    class="h-5 w-5 text-horizon-400 transition-transform"
                    :class="catalogExpanded ? 'rotate-180' : ''"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    aria-hidden="true"
                >
                    <path
                        fill-rule="evenodd"
                        d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                        clip-rule="evenodd"
                    />
                </svg>
            </button>
            <div v-show="catalogExpanded" class="mt-2 overflow-hidden rounded-[16px] bg-white shadow-soft">
                <div v-if="!bills.length" class="px-5 py-8 text-center text-sm text-horizon-500">
                    Nenhuma conta fixa cadastrada.
                </div>
                <div v-else class="divide-y divide-horizon-100">
                    <article
                        v-for="bill in bills"
                        :key="bill.id"
                        class="flex items-center justify-between gap-3 px-4 py-3"
                    >
                        <div class="min-w-0 flex-1">
                            <h3 class="truncate text-sm font-semibold text-navy-700">{{ bill.description }}</h3>
                            <p class="truncate text-xs text-horizon-500">
                                Dia {{ bill.day_of_month }} · {{ formatBRL(bill.estimated_amount) }}
                                <span v-if="bill.category"> · {{ bill.category.name }}</span>
                            </p>
                        </div>
                        <div v-if="bill.can_edit && bill.active" class="flex shrink-0 gap-2 text-xs">
                            <button type="button" class="font-medium text-cta hover:underline" @click="startEdit(bill)">
                                Editar
                            </button>
                            <button type="button" class="font-medium text-red-600 hover:underline" @click="destroy(bill)">
                                Excluir
                            </button>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <!-- Modal: cadastro / edição -->
        <Modal :show="showForm" max-width="2xl" @close="resetForm">
            <form class="p-5 sm:p-6" @submit.prevent="submit">
                <h2 class="text-lg font-bold text-navy-700">
                    {{ editing ? 'Editar conta fixa' : 'Nova conta fixa' }}
                </h2>
                <p class="mt-1 text-sm text-horizon-500">
                    {{ editing ? 'Alterações no cadastro não afetam pagamentos já confirmados.' : 'A conta será repetida todo mês no dia escolhido.' }}
                </p>
                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <InputLabel value="Descrição" />
                        <TextInput class="mt-1 block w-full" v-model="form.description" placeholder="Ex.: Internet" required />
                        <InputError class="mt-1" :message="form.errors.description" />
                    </div>
                    <div>
                        <InputLabel value="Valor estimado (R$)" />
                        <MoneyInput class="mt-1" v-model="form.estimated_amount" required />
                        <InputError class="mt-1" :message="form.errors.estimated_amount" />
                    </div>
                    <div>
                        <InputLabel value="Dia do vencimento" />
                        <TextInput type="number" min="1" max="31" class="mt-1 block w-full" v-model="form.day_of_month" required />
                        <InputError class="mt-1" :message="form.errors.day_of_month" />
                    </div>
                    <div>
                        <InputLabel value="Categoria" />
                        <select v-model="form.category_id" class="mt-1 block w-full rounded-xl border-horizon-200 text-sm" required>
                            <option value="" disabled>Selecione</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.category_id" />
                    </div>
                    <div>
                        <InputLabel value="Forma de pagamento (opcional)" />
                        <select v-model="form.payment_selection" class="mt-1 block w-full rounded-xl border-horizon-200 text-sm">
                            <optgroup label="Geral">
                                <option v-for="m in PAYMENT_METHODS" :key="m.value" :value="m.value">{{ m.label }}</option>
                            </optgroup>
                            <optgroup v-if="paymentCards.length" label="Cartões">
                                <option v-for="card in paymentCards" :key="card.id" :value="`card:${card.id}`">
                                    {{ formatCardLabel(card) }}
                                </option>
                            </optgroup>
                        </select>
                        <InputError class="mt-1" :message="form.errors.payment_method || form.errors.payment_card_id" />
                    </div>
                    <div>
                        <InputLabel value="Início" />
                        <TextInput type="date" class="mt-1 block w-full" v-model="form.start_date" required />
                        <InputError class="mt-1" :message="form.errors.start_date" />
                    </div>
                    <div>
                        <InputLabel value="Fim (opcional)" />
                        <TextInput type="date" class="mt-1 block w-full" v-model="form.end_date" />
                        <InputError class="mt-1" :message="form.errors.end_date" />
                    </div>
                    <div v-if="editing" class="sm:col-span-2 space-y-2 rounded-xl border border-horizon-200 bg-horizon-50/50 p-3">
                        <InputLabel value="Ao salvar, atualizar lançamentos pendentes?" />
                        <div class="space-y-2 text-sm text-navy-700">
                            <label class="flex items-center gap-2">
                                <input v-model="form.propagate" type="radio" value="none" class="text-brand-500 focus:ring-brand-500" />
                                Só o cadastro
                            </label>
                            <label class="flex items-center gap-2">
                                <input v-model="form.propagate" type="radio" value="open" class="text-brand-500 focus:ring-brand-500" />
                                Atualizar todos os pendentes em aberto
                            </label>
                            <label class="flex items-center gap-2">
                                <input v-model="form.propagate" type="radio" value="from_date" class="text-brand-500 focus:ring-brand-500" />
                                Atualizar a partir de uma data
                            </label>
                        </div>
                        <div v-if="form.propagate === 'from_date'" class="pt-1">
                            <InputLabel value="A partir de" />
                            <TextInput type="date" class="mt-1 block w-full max-w-xs" v-model="form.propagate_from" required />
                            <InputError class="mt-1" :message="form.errors.propagate_from" />
                        </div>
                        <InputError class="mt-1" :message="form.errors.propagate" />
                    </div>
                </div>
                <div class="mt-5 flex justify-end gap-2">
                    <SecondaryButton type="button" @click="resetForm">Cancelar</SecondaryButton>
                    <PrimaryButton :disabled="form.processing">
                        {{ editing ? 'Salvar' : 'Adicionar conta fixa' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- Modal: confirmar pagamento -->
        <Modal :show="!!confirming" max-width="md" @close="closeConfirm">
            <div class="p-5 sm:p-6">
                <h2 class="text-lg font-bold text-navy-700">Confirmar pagamento</h2>
                <p class="mt-1 text-sm text-horizon-600">
                    {{ confirming?.description }} · previsto {{ formatBRL(confirming?.amount) }}
                </p>
                <form class="mt-5 space-y-4" @submit.prevent="submitConfirm">
                    <div>
                        <InputLabel value="Valor real (R$)" />
                        <MoneyInput class="mt-1" v-model="confirmForm.amount" required />
                        <InputError class="mt-1" :message="confirmForm.errors.amount" />
                    </div>
                    <div>
                        <InputLabel value="Data do pagamento" />
                        <TextInput type="date" class="mt-1 block w-full" v-model="confirmForm.date" required />
                        <InputError class="mt-1" :message="confirmForm.errors.date" />
                    </div>
                    <div class="flex justify-end gap-2 pt-1">
                        <SecondaryButton type="button" @click="closeConfirm">Cancelar</SecondaryButton>
                        <PrimaryButton :disabled="confirmForm.processing">Confirmar pagamento</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AppLayout>
</template>
