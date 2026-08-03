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

const currentSummary = computed(() => props.periodSummary?.current ?? null);
const nextSummary = computed(() => props.periodSummary?.next ?? null);

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
    showForm.value = true;
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
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-navy-700">Contas fixas</h1>
                <p class="text-sm text-horizon-500">Água, luz, internet e outras contas que se repetem todo mês</p>
            </div>
            <PrimaryButton v-if="!showForm" type="button" @click="openCreateForm">Cadastrar conta fixa</PrimaryButton>
        </div>

        <form
            v-if="showForm"
            class="mb-8 grid max-w-4xl gap-3 rounded-[20px] bg-white p-5 shadow-soft sm:grid-cols-2"
            @submit.prevent="submit"
        >
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
            <div v-if="editing" class="sm:col-span-2 space-y-2 rounded-xl border border-horizon-200 bg-white p-3">
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
            <div class="flex items-end gap-2 sm:col-span-2">
                <PrimaryButton :disabled="form.processing">{{ editing ? 'Salvar' : 'Adicionar conta fixa' }}</PrimaryButton>
                <button type="button" class="text-sm text-horizon-600 underline" @click="resetForm">Cancelar</button>
            </div>
        </form>

        <section class="mb-6 sm:mb-8">
            <h2 class="mb-2 text-base font-bold text-navy-700 sm:mb-3 sm:text-lg">Cadastradas</h2>
            <div v-if="!bills.length" class="rounded-[20px] bg-white px-5 py-8 text-center text-sm text-horizon-500 shadow-soft">
                Nenhuma conta fixa cadastrada.
            </div>
            <div v-else class="divide-y divide-horizon-100 overflow-hidden rounded-[16px] bg-white shadow-soft sm:grid sm:grid-cols-2 sm:gap-3 sm:divide-y-0 sm:bg-transparent sm:shadow-none">
                <article
                    v-for="bill in bills"
                    :key="bill.id"
                    class="flex items-center justify-between gap-3 px-3 py-2.5 sm:rounded-[20px] sm:bg-white sm:p-5 sm:shadow-soft"
                >
                    <div class="min-w-0 flex-1">
                        <div class="flex items-baseline gap-2">
                            <h3 class="truncate text-sm font-semibold text-navy-700 sm:text-base sm:font-bold">{{ bill.description }}</h3>
                            <span class="shrink-0 text-[11px] text-horizon-500 sm:hidden">dia {{ bill.day_of_month }}</span>
                        </div>
                        <p class="truncate text-xs text-horizon-500 sm:text-sm">
                            <span class="hidden sm:inline">Todo dia {{ bill.day_of_month }} · </span>{{ formatBRL(bill.estimated_amount) }}
                            <span class="sm:hidden"> · {{ bill.category?.name }}</span>
                        </p>
                        <p class="mt-0.5 hidden text-xs text-horizon-500 sm:block">
                            {{ bill.category?.name }}
                            <span v-if="!bill.active"> · Inativa</span>
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2 sm:flex-col sm:items-end sm:gap-3">
                        <span class="hidden text-xs text-horizon-500 sm:inline">
                            {{ bill.user_id === userId ? 'Minha' : bill.user?.name }}
                        </span>
                        <div v-if="bill.can_edit && bill.active" class="flex gap-2 text-xs sm:gap-3 sm:text-sm">
                            <button type="button" class="font-medium text-cta hover:underline" @click="startEdit(bill)">Editar</button>
                            <button type="button" class="font-medium text-red-600 hover:underline" @click="destroy(bill)">Excluir</button>
                        </div>
                    </div>
                </article>
            </div>
        </section>

        <section data-tour="recurring-to-pay">
            <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
                <div>
                    <h2 class="text-base font-bold text-navy-700 sm:text-lg">A pagar</h2>
                    <p class="text-xs text-horizon-500 sm:text-sm">
                        Previsto deste mês e dos próximos — gerado ao abrir a tela
                    </p>
                </div>
            </div>

            <div
                v-if="currentSummary || nextSummary"
                class="mb-4 grid gap-3 sm:grid-cols-2"
            >
                <div class="rounded-[16px] bg-white px-4 py-3 shadow-soft">
                    <p class="text-xs font-medium text-horizon-500">
                        Este mês · {{ monthLabel(currentSummary?.month) }}
                    </p>
                    <div class="mt-2 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                        <p class="text-sm text-navy-700">
                            <span class="font-bold tabular-nums">{{ currentSummary?.pending_count ?? 0 }}</span>
                            <span class="text-horizon-500"> a pagar</span>
                            <span class="ml-1 font-semibold tabular-nums text-navy-700">{{ formatBRL(currentSummary?.pending_amount) }}</span>
                        </p>
                        <p class="text-sm text-emerald-700">
                            <span class="font-bold tabular-nums">{{ currentSummary?.paid_count ?? 0 }}</span>
                            <span class="text-horizon-500"> pagas</span>
                            <span class="ml-1 font-semibold tabular-nums">{{ formatBRL(currentSummary?.paid_amount) }}</span>
                        </p>
                    </div>
                </div>
                <div class="rounded-[16px] bg-white px-4 py-3 shadow-soft">
                    <p class="text-xs font-medium text-horizon-500">
                        Próximo mês · {{ monthLabel(nextSummary?.month) }}
                    </p>
                    <div class="mt-2 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                        <p class="text-sm text-navy-700">
                            <span class="font-bold tabular-nums">{{ nextSummary?.pending_count ?? 0 }}</span>
                            <span class="text-horizon-500"> previstas</span>
                            <span class="ml-1 font-semibold tabular-nums text-navy-700">{{ formatBRL(nextSummary?.pending_amount) }}</span>
                        </p>
                        <p v-if="(nextSummary?.paid_count ?? 0) > 0" class="text-sm text-emerald-700">
                            <span class="font-bold tabular-nums">{{ nextSummary?.paid_count ?? 0 }}</span>
                            <span class="text-horizon-500"> pagas</span>
                            <span class="ml-1 font-semibold tabular-nums">{{ formatBRL(nextSummary?.paid_amount) }}</span>
                        </p>
                    </div>
                </div>
            </div>

            <div v-if="!hasSchedule" class="rounded-[20px] bg-white px-5 py-8 text-center text-sm text-horizon-500 shadow-soft">
                Nenhuma conta fixa prevista no horizonte.
            </div>

            <div v-else class="space-y-4">
                <article
                    v-for="group in scheduleByMonth"
                    :key="group.key"
                    class="overflow-hidden rounded-[16px] bg-white shadow-soft"
                >
                    <header class="flex flex-wrap items-center justify-between gap-2 border-b border-horizon-100 px-4 py-3">
                        <div>
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
                        <p
                            v-if="group.pending.length"
                            class="shrink-0 text-sm font-bold tabular-nums text-navy-700"
                        >
                            {{ formatBRL(group.pending_amount) }}
                        </p>
                    </header>

                    <div v-if="group.pending.length" class="divide-y divide-horizon-100">
                        <div
                            v-for="item in group.pending"
                            :key="item.id"
                            class="flex items-center justify-between gap-2 px-4 py-3"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-navy-700">{{ item.description }}</p>
                                <p class="truncate text-[11px] text-horizon-500 sm:text-xs">
                                    Vence {{ formatDate(item.date) }} · {{ formatBRL(item.amount) }}
                                    <span class="hidden sm:inline"> · {{ item.category?.name }}</span>
                                </p>
                            </div>
                            <div v-if="item.can_edit" class="flex shrink-0 gap-2 text-xs sm:gap-3 sm:text-sm">
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
                                    <p class="truncate text-[11px] text-horizon-500 sm:text-xs">
                                        {{ formatDate(item.date) }} · {{ formatBRL(item.amount) }}
                                        <span class="hidden sm:inline"> · {{ item.category?.name }}</span>
                                    </p>
                                </div>
                                <span class="shrink-0 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700">
                                    Paga
                                </span>
                            </div>
                        </div>
                    </div>
                </article>
            </div>
        </section>

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
