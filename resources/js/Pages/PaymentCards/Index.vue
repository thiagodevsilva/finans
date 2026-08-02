<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { formatBRL, formatDate } from '@/utils/format';
import { useAppTour } from '@/Composables/useAppTour';
import { FIRST_SETUP_TOUR_ID } from '@/tours/firstSetup';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

const props = defineProps({
    cards: Array,
    recentPayments: {
        type: Array,
        default: () => [],
    },
    brands: Array,
    types: Array,
    bankAccounts: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const userId = computed(() => page.props.auth.user.id);
const { resumeIfActive, startTour, isTourActive } = useAppTour();

const editing = ref(null);
const showForm = ref(false);
const formPanel = ref(null);

const form = useForm({
    name: '',
    brand: 'visa',
    type: 'credit',
    last_four: '',
    color: '#ffc107',
    bank_account_id: '',
    closing_day: '10',
    due_day: '17',
});

watch(
    () => form.type,
    (type) => {
        if (type !== 'credit') {
            form.closing_day = null;
            form.due_day = null;
        } else if (!form.closing_day) {
            form.closing_day = '10';
            form.due_day = '17';
        }
    },
);

const resetFormDefaults = () => {
    form.reset();
    form.brand = 'visa';
    form.type = 'credit';
    form.color = '#ffc107';
    form.bank_account_id = '';
    form.closing_day = '10';
    form.due_day = '17';
    form.clearErrors();
};

const focusForm = async () => {
    await nextTick();
    formPanel.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    formPanel.value?.querySelector('input, select')?.focus();
};

const openCreateForm = () => {
    editing.value = null;
    resetFormDefaults();
    showForm.value = true;
    focusForm();
};

const startEdit = (card) => {
    editing.value = card.id;
    form.name = card.name;
    form.brand = card.brand;
    form.type = card.type || 'credit';
    form.last_four = card.last_four || '';
    form.color = card.color;
    form.bank_account_id = card.bank_account_id || '';
    form.closing_day = String(card.closing_day || 10);
    form.due_day = String(card.due_day || 17);
    form.clearErrors();
    showForm.value = true;
    focusForm();
};

const cancelEdit = () => {
    editing.value = null;
    showForm.value = false;
    resetFormDefaults();
};

const submit = () => {
    if (!form.bank_account_id) {
        form.bank_account_id = null;
    }

    if (form.type === 'debit') {
        form.closing_day = null;
        form.due_day = null;
    }

    if (editing.value) {
        form.put(route('payment-cards.update', editing.value), {
            onSuccess: () => cancelEdit(),
        });
    } else {
        form.post(route('payment-cards.store'), {
            onSuccess: () => cancelEdit(),
        });
    }
};

const destroy = (card) => {
    if (!confirm(`Excluir o cartão "${card.name}"?`)) return;
    router.delete(route('payment-cards.destroy', card.id));
};

const payInvoiceHref = route('transactions.create', { type: 'transfer' });

onMounted(async () => {
    const params = new URLSearchParams(window.location.search);
    if (params.get('tour') === FIRST_SETUP_TOUR_ID) {
        openCreateForm();
        await nextTick();
    }

    if (isTourActive()) {
        resumeIfActive();
        return;
    }

    if (params.get('tour') === FIRST_SETUP_TOUR_ID) {
        startTour(FIRST_SETUP_TOUR_ID);
    }
});
</script>

<template>
    <Head title="Cartões" />

    <AppLayout>
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-navy-700">Cartões</h1>
                <p class="text-sm text-horizon-500">Cadastre cartões e acompanhe pagamentos de fatura</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <Link :href="payInvoiceHref" class="text-sm font-semibold text-cta hover:underline">
                    Pagar fatura
                </Link>
                <PrimaryButton v-if="!showForm" type="button" @click="openCreateForm">
                    Adicionar cartão
                </PrimaryButton>
            </div>
        </div>

        <form
            v-if="showForm"
            ref="formPanel"
            data-tour="pc-form"
            class="mb-8 grid max-w-4xl scroll-mt-4 gap-3 rounded-[20px] bg-white p-5 shadow-soft sm:grid-cols-2 lg:grid-cols-3"
            @submit.prevent="submit"
        >
            <div class="lg:col-span-3">
                <h2 class="text-base font-bold text-navy-700">
                    {{ editing ? 'Editar cartão' : 'Novo cartão' }}
                </h2>
            </div>
            <div class="lg:col-span-2" data-tour="pc-name">
                <InputLabel value="Nome do cartão" />
                <TextInput class="mt-1 block w-full" v-model="form.name" placeholder="Ex.: Nubank Roxinho" required />
                <InputError class="mt-1" :message="form.errors.name" />
            </div>
            <div>
                <InputLabel value="Tipo" />
                <select v-model="form.type" class="mt-1 block w-full rounded-xl border-horizon-200 text-sm text-navy-700" required>
                    <option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option>
                </select>
                <InputError class="mt-1" :message="form.errors.type" />
            </div>
            <div>
                <InputLabel value="Bandeira" />
                <select v-model="form.brand" class="mt-1 block w-full rounded-xl border-horizon-200 text-sm text-navy-700" required>
                    <option v-for="b in brands" :key="b.value" :value="b.value">{{ b.label }}</option>
                </select>
                <InputError class="mt-1" :message="form.errors.brand" />
            </div>
            <div>
                <InputLabel value="Final (opcional)" />
                <TextInput
                    class="mt-1 block w-full"
                    v-model="form.last_four"
                    maxlength="4"
                    inputmode="numeric"
                    placeholder="1234"
                />
                <InputError class="mt-1" :message="form.errors.last_four" />
            </div>
            <div data-tour="pc-bank-account">
                <InputLabel value="Conta (opcional)" />
                <select v-model="form.bank_account_id" class="mt-1 block w-full rounded-xl border-horizon-200 text-sm text-navy-700">
                    <option value="">Sem conta</option>
                    <option v-for="account in bankAccounts" :key="account.id" :value="account.id">
                        {{ account.name }}
                    </option>
                </select>
                <InputError class="mt-1" :message="form.errors.bank_account_id" />
            </div>
            <div>
                <InputLabel value="Cor" />
                <input type="color" v-model="form.color" class="mt-1 h-10 w-full cursor-pointer rounded-xl border border-horizon-200" />
                <InputError class="mt-1" :message="form.errors.color" />
            </div>
            <template v-if="form.type === 'credit'">
                <div
                    data-tour="pc-credit-days"
                    class="grid gap-3 sm:col-span-2 sm:grid-cols-2 lg:col-span-2"
                >
                    <div>
                        <InputLabel value="Dia de fechamento" />
                        <TextInput class="mt-1 block w-full" type="number" min="1" max="31" v-model="form.closing_day" required />
                        <InputError class="mt-1" :message="form.errors.closing_day" />
                    </div>
                    <div>
                        <InputLabel value="Dia de vencimento" />
                        <TextInput class="mt-1 block w-full" type="number" min="1" max="31" v-model="form.due_day" required />
                        <InputError class="mt-1" :message="form.errors.due_day" />
                    </div>
                </div>
            </template>
            <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-3" data-tour="pc-submit">
                <PrimaryButton :disabled="form.processing">{{ editing ? 'Salvar' : 'Adicionar cartão' }}</PrimaryButton>
                <button type="button" class="text-sm text-horizon-600 underline" @click="cancelEdit">Cancelar</button>
            </div>
        </form>

        <div v-if="!cards.length" class="rounded-[20px] bg-white px-5 py-12 text-center text-horizon-500 shadow-soft">
            Nenhum cartão cadastrado ainda.
            <button
                v-if="!showForm"
                type="button"
                class="mt-2 block w-full text-cta hover:underline"
                @click="openCreateForm"
            >
                Adicionar o primeiro cartão
            </button>
        </div>

        <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="card in cards"
                :key="card.id"
                class="relative flex min-h-[180px] flex-col justify-between overflow-hidden rounded-[20px] p-5 text-white shadow-soft"
                :style="{ background: `linear-gradient(135deg, ${card.color} 0%, #1b2559 100%)` }"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-white/75">
                            {{ card.brand_label }} · {{ card.type_label }}
                        </p>
                        <h2 class="mt-2 truncate text-xl font-bold leading-tight sm:text-2xl">{{ card.name }}</h2>
                    </div>
                    <span
                        class="shrink-0 rounded-full bg-white/20 px-2.5 py-0.5 text-xs font-medium"
                        :class="card.user_id === userId ? 'ring-1 ring-white/50' : ''"
                    >
                        {{ card.user_id === userId ? 'Meu' : card.user?.name }}
                    </span>
                </div>

                <div>
                    <p class="mt-6 font-mono text-lg tracking-[0.25em] sm:text-xl">
                        <template v-if="card.last_four">•••• {{ card.last_four }}</template>
                        <template v-else>{{ card.type_label }}</template>
                    </p>
                    <p v-if="card.bank_account" class="mt-2 text-xs text-white/80">
                        Conta: {{ card.bank_account.name }}
                    </p>
                    <p v-if="card.type === 'credit'" class="mt-1 text-xs text-white/80">
                        Fecha dia {{ card.closing_day }} · Vence dia {{ card.due_day }}
                    </p>
                </div>

                <div v-if="card.can_edit" class="mt-4 flex gap-3 text-sm">
                    <button type="button" class="font-medium text-white/90 underline hover:text-white" @click="startEdit(card)">
                        Editar
                    </button>
                    <button type="button" class="font-medium text-white/90 underline hover:text-white" @click="destroy(card)">
                        Excluir
                    </button>
                </div>
            </article>
        </div>

        <section class="mt-8">
            <div class="mb-3 flex items-center justify-between gap-2">
                <h2 class="text-base font-bold text-navy-700 sm:text-lg">Últimos pagamentos</h2>
                <Link :href="route('credit-card-payments.index')" class="text-sm font-medium text-cta hover:underline">
                    Ver todos
                </Link>
            </div>
            <div v-if="!recentPayments.length" class="rounded-[16px] bg-white px-4 py-8 text-center text-sm text-horizon-500 shadow-soft">
                Nenhum pagamento de fatura ainda.
                <Link :href="payInvoiceHref" class="mt-1 block text-cta hover:underline">Registrar pagamento</Link>
            </div>
            <ul v-else class="divide-y divide-horizon-100 rounded-[16px] bg-white px-4 shadow-soft">
                <li
                    v-for="payment in recentPayments"
                    :key="payment.id"
                    class="flex items-center justify-between gap-3 py-3 text-sm first:pt-3 last:pb-3"
                >
                    <div class="min-w-0">
                        <p class="truncate font-medium text-navy-700">{{ payment.description }}</p>
                        <p class="text-xs text-horizon-500">
                            {{ formatDate(payment.date) }}
                            <span v-if="payment.payment_card"> · {{ payment.payment_card.name }}</span>
                            <span v-if="payment.bank_account"> · {{ payment.bank_account.name }}</span>
                        </p>
                    </div>
                    <p class="shrink-0 font-bold tabular-nums text-navy-700">{{ formatBRL(payment.amount) }}</p>
                </li>
            </ul>
        </section>
    </AppLayout>
</template>
