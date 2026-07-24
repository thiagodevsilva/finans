<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { formatBRL, formatDate } from '@/utils/format';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    cards: Array,
    brands: Array,
    types: Array,
    bankAccounts: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const userId = computed(() => page.props.auth.user.id);

const editing = ref(null);
const payingInvoice = ref(null);

const form = useForm({
    name: '',
    brand: 'visa',
    type: 'credit',
    last_four: '',
    color: '#ffc107',
    bank_account_id: '',
    closing_day: 10,
    due_day: 17,
});

const payForm = useForm({
    bank_account_id: '',
    amount: '',
    date: new Date().toISOString().slice(0, 10),
    description: '',
});

watch(
    () => form.type,
    (type) => {
        if (type === 'debit') {
            form.closing_day = null;
            form.due_day = null;
        } else if (!form.closing_day) {
            form.closing_day = 10;
            form.due_day = 17;
        }
    },
);

const startEdit = (card) => {
    editing.value = card.id;
    form.name = card.name;
    form.brand = card.brand;
    form.type = card.type || 'credit';
    form.last_four = card.last_four || '';
    form.color = card.color;
    form.bank_account_id = card.bank_account_id || '';
    form.closing_day = card.closing_day || 10;
    form.due_day = card.due_day || 17;
    form.clearErrors();
};

const cancelEdit = () => {
    editing.value = null;
    form.reset();
    form.brand = 'visa';
    form.type = 'credit';
    form.color = '#ffc107';
    form.bank_account_id = '';
    form.closing_day = 10;
    form.due_day = 17;
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
            onSuccess: () => {
                form.reset();
                form.brand = 'visa';
                form.type = 'credit';
                form.color = '#ffc107';
                form.bank_account_id = '';
                form.closing_day = 10;
                form.due_day = 17;
            },
        });
    }
};

const destroy = (card) => {
    if (!confirm(`Excluir o cartão "${card.name}"?`)) return;
    router.delete(route('payment-cards.destroy', card.id));
};

const openPay = (invoice, card) => {
    payingInvoice.value = { ...invoice, card_name: card.name, card_id: card.id };
    payForm.bank_account_id = card.bank_account_id || '';
    payForm.amount = invoice.remaining > 0 ? invoice.remaining : invoice.total;
    payForm.date = new Date().toISOString().slice(0, 10);
    payForm.description = '';
    payForm.clearErrors();
};

const submitPay = () => {
    if (!payingInvoice.value) return;
    payForm.post(route('credit-card-invoices.pay', payingInvoice.value.id), {
        onSuccess: () => {
            payingInvoice.value = null;
            payForm.reset();
            payForm.date = new Date().toISOString().slice(0, 10);
        },
    });
};
</script>

<template>
    <Head title="Cartões" />

    <AppLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-navy-700">Cartões</h1>
            <p class="text-sm text-horizon-500">Cadastre cartões, ciclo de fatura e registre pagamentos sem duplicar gastos</p>
        </div>

        <form
            class="mb-8 grid max-w-4xl gap-3 rounded-[20px] bg-white p-5 shadow-soft sm:grid-cols-2 lg:grid-cols-3"
            @submit.prevent="submit"
        >
            <div class="lg:col-span-2">
                <InputLabel value="Apelido" />
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
            <div>
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
            </template>
            <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-3">
                <PrimaryButton :disabled="form.processing">{{ editing ? 'Salvar' : 'Adicionar cartão' }}</PrimaryButton>
                <button v-if="editing" type="button" class="text-sm text-horizon-600 underline" @click="cancelEdit">Cancelar</button>
            </div>
        </form>

        <div v-if="payingInvoice" class="mb-8 max-w-xl rounded-[20px] bg-white p-5 shadow-soft">
            <h2 class="text-lg font-bold text-navy-700">Pagar fatura · {{ payingInvoice.card_name }}</h2>
            <p class="mt-1 text-sm text-horizon-500">
                Vence em {{ formatDate(payingInvoice.due_date) }} · Restante {{ formatBRL(payingInvoice.remaining) }}
            </p>
            <p class="mt-2 text-xs text-horizon-500">
                Este lançamento é uma transferência e não entra no total de gastos do mês.
            </p>
            <form class="mt-4 space-y-3" @submit.prevent="submitPay">
                <div>
                    <InputLabel value="Conta bancária" />
                    <select v-model="payForm.bank_account_id" class="mt-1 block w-full rounded-xl border-horizon-200 text-sm" required>
                        <option value="" disabled>Selecione</option>
                        <option v-for="account in bankAccounts" :key="account.id" :value="account.id">{{ account.name }}</option>
                    </select>
                    <InputError class="mt-1" :message="payForm.errors.bank_account_id" />
                </div>
                <div>
                    <InputLabel value="Valor (R$)" />
                    <TextInput type="number" step="0.01" min="0.01" class="mt-1 block w-full" v-model="payForm.amount" required />
                    <InputError class="mt-1" :message="payForm.errors.amount" />
                </div>
                <div>
                    <InputLabel value="Data" />
                    <TextInput type="date" class="mt-1 block w-full" v-model="payForm.date" required />
                    <InputError class="mt-1" :message="payForm.errors.date" />
                </div>
                <div class="flex gap-2">
                    <PrimaryButton :disabled="payForm.processing">Confirmar pagamento</PrimaryButton>
                    <button type="button" class="text-sm text-horizon-600 underline" @click="payingInvoice = null">Cancelar</button>
                </div>
            </form>
        </div>

        <div v-if="!cards.length" class="rounded-[20px] bg-white px-5 py-12 text-center text-horizon-500 shadow-soft">
            Nenhum cartão cadastrado ainda.
        </div>

        <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="card in cards"
                :key="card.id"
                class="relative overflow-hidden rounded-[20px] p-5 text-white shadow-soft"
                :style="{ background: `linear-gradient(135deg, ${card.color} 0%, #1b2559 100%)` }"
            >
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-white/80">
                            {{ card.brand_label }} · {{ card.type_label }}
                        </p>
                        <h2 class="mt-1 text-lg font-bold">{{ card.name }}</h2>
                    </div>
                    <span
                        class="rounded-full bg-white/20 px-2.5 py-0.5 text-xs font-medium"
                        :class="card.user_id === userId ? 'ring-1 ring-white/50' : ''"
                    >
                        {{ card.user_id === userId ? 'Meu' : card.user?.name }}
                    </span>
                </div>

                <p class="mt-8 font-mono text-xl tracking-[0.2em]">
                    <template v-if="card.last_four">•••• {{ card.last_four }}</template>
                    <template v-else>{{ card.type_label }}</template>
                </p>
                <p v-if="card.bank_account" class="mt-2 text-sm text-white/80">
                    Conta: {{ card.bank_account.name }}
                </p>
                <p v-if="card.type === 'credit'" class="mt-1 text-sm text-white/80">
                    Fecha dia {{ card.closing_day }} · Vence dia {{ card.due_day }}
                </p>

                <div v-if="card.type === 'credit' && card.invoices?.length" class="mt-4 space-y-2">
                    <div
                        v-for="invoice in card.invoices"
                        :key="invoice.id"
                        class="rounded-xl bg-white/15 px-3 py-2 text-sm"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span>{{ invoice.status_label }} · {{ formatDate(invoice.due_date) }}</span>
                            <span class="font-semibold">{{ formatBRL(invoice.remaining) }}</span>
                        </div>
                        <button
                            v-if="invoice.remaining > 0"
                            type="button"
                            class="mt-1 text-xs font-medium underline"
                            @click="openPay(invoice, card)"
                        >
                            Pagar fatura
                        </button>
                    </div>
                </div>

                <div v-if="card.can_edit" class="mt-5 flex gap-3 text-sm">
                    <button type="button" class="font-medium text-white/90 underline hover:text-white" @click="startEdit(card)">
                        Editar
                    </button>
                    <button type="button" class="font-medium text-white/90 underline hover:text-white" @click="destroy(card)">
                        Excluir
                    </button>
                </div>
            </article>
        </div>
    </AppLayout>
</template>
