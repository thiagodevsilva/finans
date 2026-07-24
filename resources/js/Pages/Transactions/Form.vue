<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { PAYMENT_METHODS, formatCardLabel, formatBRL } from '@/utils/format';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    transaction: Object,
    categories: Array,
    paymentCards: {
        type: Array,
        default: () => [],
    },
    bankAccounts: {
        type: Array,
        default: () => [],
    },
    pendingRecurring: {
        type: Array,
        default: () => [],
    },
});

const isEdit = computed(() => !!props.transaction);

const initialMethod = () => {
    if (props.transaction?.payment_method === 'card' && props.transaction?.payment_card_id) {
        return `card:${props.transaction.payment_card_id}`;
    }
    return props.transaction?.payment_method || 'cash';
};

const form = useForm({
    type: props.transaction?.type || 'expense',
    amount: props.transaction?.amount || '',
    description: props.transaction?.description || '',
    category_id: props.transaction?.category_id || '',
    date: props.transaction?.date?.slice?.(0, 10) || props.transaction?.date || new Date().toISOString().slice(0, 10),
    payment_selection: initialMethod(),
    payment_method: props.transaction?.payment_method || 'cash',
    payment_card_id: props.transaction?.payment_card_id || null,
    bank_account_id: props.transaction?.bank_account_id || '',
    is_installment: false,
    total_amount: '',
    installments_count: '',
    installment_amount: '',
    recurring_transaction_id: '',
});

const recurringSearch = ref('');
const recurringOpen = ref(false);
const installmentSource = ref(null);

const selectedCard = computed(() => {
    if (form.payment_method !== 'card' || !form.payment_card_id) return null;
    return props.paymentCards.find((c) => c.id === form.payment_card_id) || null;
});

const isCreditCard = computed(() => selectedCard.value?.type === 'credit');

const filteredRecurring = computed(() => {
    const q = recurringSearch.value.trim().toLowerCase();
    if (!q) return props.pendingRecurring;
    return props.pendingRecurring.filter((item) =>
        item.label.toLowerCase().includes(q) || item.description.toLowerCase().includes(q),
    );
});

const selectedRecurring = computed(() =>
    props.pendingRecurring.find((item) => item.id === form.recurring_transaction_id) || null,
);

watch(
    () => form.payment_selection,
    (value) => {
        if (String(value).startsWith('card:')) {
            form.payment_method = 'card';
            form.payment_card_id = String(value).slice(5);
        } else {
            form.payment_method = value;
            form.payment_card_id = null;
        }
    },
    { immediate: true },
);

watch(isCreditCard, (credit) => {
    if (!credit) {
        form.is_installment = false;
        form.total_amount = '';
        form.installments_count = '';
        form.installment_amount = '';
        installmentSource.value = null;
    }
});

watch(
    () => form.type,
    (type) => {
        if (type === 'income') {
            form.is_installment = false;
            form.recurring_transaction_id = '';
            recurringSearch.value = '';
        }
    },
);

watch(
    () => form.is_installment,
    (on) => {
        if (on) {
            form.recurring_transaction_id = '';
            recurringSearch.value = '';
        } else {
            form.total_amount = '';
            form.installments_count = '';
            form.installment_amount = '';
            installmentSource.value = null;
        }
    },
);

const roundMoney = (n) => Math.round(Number(n) * 100) / 100;

watch(
    () => [form.installments_count, form.installment_amount, form.total_amount],
    ([count, parcel, total]) => {
        if (!form.is_installment) return;
        const n = Number(count);
        if (!n || n < 2) return;

        if (installmentSource.value === 'parcel' && parcel !== '' && parcel != null) {
            form.total_amount = roundMoney(n * Number(parcel)).toFixed(2);
            return;
        }

        if (installmentSource.value === 'total' && total !== '' && total != null) {
            form.installment_amount = roundMoney(Number(total) / n).toFixed(2);
        }
    },
);

const selectRecurring = (item) => {
    form.recurring_transaction_id = item.id;
    form.description = item.description;
    form.category_id = item.category_id;
    form.amount = item.amount;
    form.date = item.date;
    recurringSearch.value = item.label;

    if (item.payment_method === 'card' && item.payment_card_id) {
        form.payment_selection = `card:${item.payment_card_id}`;
    } else if (item.payment_method) {
        form.payment_selection = item.payment_method;
    }

    form.is_installment = false;
};

const clearRecurring = () => {
    form.recurring_transaction_id = '';
    recurringSearch.value = '';
};

const submit = () => {
    if (form.type === 'income') {
        form.payment_method = null;
        form.payment_card_id = null;
        form.payment_selection = 'cash';
        form.is_installment = false;
        form.recurring_transaction_id = null;
        if (!form.bank_account_id) {
            form.bank_account_id = null;
        }
    } else {
        form.bank_account_id = null;
        if (!form.recurring_transaction_id) {
            form.recurring_transaction_id = null;
        }
        if (!form.is_installment) {
            form.total_amount = null;
            form.installments_count = null;
            form.installment_amount = null;
        }
    }

    if (isEdit.value) {
        form.put(route('transactions.update', props.transaction.id));
    } else {
        form.post(route('transactions.store'));
    }
};
</script>

<template>
    <Head :title="isEdit ? 'Editar transação' : 'Nova transação'" />

    <AppLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-900">{{ isEdit ? 'Editar transação' : 'Nova transação' }}</h1>
            <Link :href="route('transactions.index')" class="text-sm text-cta hover:underline">Voltar</Link>
        </div>

        <form class="max-w-xl space-y-4 rounded-lg bg-white p-6 shadow-sm ring-1 ring-slate-200" @submit.prevent="submit">
            <div>
                <InputLabel value="Tipo" />
                <select v-model="form.type" class="mt-1 block w-full rounded-md border-slate-300" :disabled="isEdit">
                    <option value="expense">Saída</option>
                    <option value="income">Entrada</option>
                </select>
                <InputError class="mt-2" :message="form.errors.type" />
            </div>

            <div v-if="form.type === 'expense' && !isEdit && pendingRecurring.length" class="relative">
                <InputLabel value="Conta fixa (opcional)" />
                <TextInput
                    type="search"
                    class="mt-1 block w-full"
                    v-model="recurringSearch"
                    placeholder="Buscar conta fixa pendente ou vencida…"
                    autocomplete="off"
                    @focus="recurringOpen = true"
                    @blur="recurringOpen = false"
                    @input="form.recurring_transaction_id = ''"
                />
                <p v-if="selectedRecurring" class="mt-1 text-xs text-horizon-500">
                    Selecionada: {{ selectedRecurring.description }} · {{ selectedRecurring.date.split('-').reverse().join('/') }}
                    <button type="button" class="ml-1 text-cta underline" @click="clearRecurring">limpar</button>
                </p>
                <ul
                    v-if="recurringOpen"
                    class="absolute z-10 mt-1 max-h-48 w-full overflow-auto rounded-md border border-slate-200 bg-white shadow-lg"
                >
                    <li v-if="!filteredRecurring.length" class="px-3 py-2 text-sm text-horizon-500">
                        Nenhuma conta fixa encontrada.
                    </li>
                    <li
                        v-for="item in filteredRecurring"
                        :key="item.id"
                        class="cursor-pointer border-b border-slate-100 px-3 py-2 text-sm last:border-0 hover:bg-amber-50"
                        @mousedown.prevent="selectRecurring(item); recurringOpen = false"
                    >
                        <span class="font-medium text-navy-700">{{ item.description }}</span>
                        <span class="mt-0.5 block text-xs text-horizon-500">
                            {{ item.date.split('-').reverse().join('/') }}
                            · {{ formatBRL(item.amount) }}
                            ·
                            <span :class="item.overdue ? 'text-red-600' : 'text-amber-700'">
                                {{ item.overdue ? 'vencida' : 'pendente' }}
                            </span>
                        </span>
                    </li>
                </ul>
                <InputError class="mt-2" :message="form.errors.recurring_transaction_id" />
            </div>

            <div v-if="!form.is_installment">
                <InputLabel for="amount" value="Valor (R$)" />
                <TextInput id="amount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" v-model="form.amount" required />
                <InputError class="mt-2" :message="form.errors.amount" />
            </div>

            <div>
                <InputLabel for="description" value="Descrição" />
                <TextInput id="description" type="text" class="mt-1 block w-full" v-model="form.description" required />
                <InputError class="mt-2" :message="form.errors.description" />
            </div>

            <div>
                <InputLabel value="Categoria" />
                <select v-model="form.category_id" class="mt-1 block w-full rounded-md border-slate-300" required>
                    <option value="" disabled>Selecione</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <InputError class="mt-2" :message="form.errors.category_id" />
            </div>

            <div v-if="form.type === 'income'">
                <InputLabel value="Conta (opcional)" />
                <select v-model="form.bank_account_id" class="mt-1 block w-full rounded-md border-slate-300">
                    <option value="">Sem conta</option>
                    <option v-for="account in bankAccounts" :key="account.id" :value="account.id">
                        {{ account.name }}
                    </option>
                </select>
                <InputError class="mt-2" :message="form.errors.bank_account_id" />
                <p class="mt-1 text-xs text-horizon-500">
                    Cadastre contas em
                    <Link :href="route('bank-accounts.index')" class="text-cta hover:underline">Contas</Link>.
                </p>
            </div>

            <div v-else>
                <InputLabel value="Forma de pagamento" />
                <select v-model="form.payment_selection" class="mt-1 block w-full rounded-md border-slate-300" required>
                    <optgroup label="Geral">
                        <option v-for="m in PAYMENT_METHODS" :key="m.value" :value="m.value">{{ m.label }}</option>
                    </optgroup>
                    <optgroup v-if="paymentCards.length" label="Cartões">
                        <option
                            v-for="card in paymentCards"
                            :key="card.id"
                            :value="`card:${card.id}`"
                        >
                            {{ formatCardLabel(card) }}{{ card.user?.name ? ` (${card.user.name})` : '' }}
                        </option>
                    </optgroup>
                </select>
                <InputError class="mt-2" :message="form.errors.payment_method || form.errors.payment_card_id" />
                <p v-if="!paymentCards.length" class="mt-1 text-xs text-horizon-500">
                    Sem cartões? Cadastre em
                    <Link :href="route('payment-cards.index')" class="text-cta hover:underline">Cartões</Link>.
                </p>

                <label
                    v-if="isCreditCard && !isEdit && !form.recurring_transaction_id"
                    class="mt-3 flex items-center gap-2 text-sm text-navy-700"
                >
                    <input v-model="form.is_installment" type="checkbox" class="rounded border-slate-300 text-primary focus:ring-primary" />
                    Compra parcelada
                </label>
                <InputError class="mt-2" :message="form.errors.is_installment" />
            </div>

            <div v-if="form.is_installment" class="space-y-4 rounded-lg bg-amber-50/60 p-4 ring-1 ring-amber-100">
                <div>
                    <InputLabel for="total_amount" value="Valor total (R$)" />
                    <TextInput
                        id="total_amount"
                        type="number"
                        step="0.01"
                        min="0.01"
                        class="mt-1 block w-full"
                        v-model="form.total_amount"
                        required
                        @input="installmentSource = 'total'"
                    />
                    <InputError class="mt-2" :message="form.errors.total_amount" />
                </div>
                <div>
                    <InputLabel for="installments_count" value="Quantidade de parcelas" />
                    <TextInput
                        id="installments_count"
                        type="number"
                        min="2"
                        max="48"
                        class="mt-1 block w-full"
                        v-model="form.installments_count"
                        required
                    />
                    <InputError class="mt-2" :message="form.errors.installments_count" />
                </div>
                <div>
                    <InputLabel for="installment_amount" value="Valor da parcela (R$)" />
                    <TextInput
                        id="installment_amount"
                        type="number"
                        step="0.01"
                        min="0.01"
                        class="mt-1 block w-full"
                        v-model="form.installment_amount"
                        @input="installmentSource = 'parcel'"
                    />
                    <InputError class="mt-2" :message="form.errors.installment_amount" />
                    <p class="mt-1 text-xs text-horizon-500">
                        Preencha quantidade + parcela para calcular o total, ou quantidade + total para calcular a parcela.
                    </p>
                </div>
            </div>

            <div>
                <InputLabel for="date" :value="form.is_installment ? 'Data da compra' : 'Data'" />
                <TextInput id="date" type="date" class="mt-1 block w-full" v-model="form.date" required />
                <InputError class="mt-2" :message="form.errors.date" />
            </div>

            <PrimaryButton :disabled="form.processing">{{ isEdit ? 'Salvar' : 'Criar' }}</PrimaryButton>
        </form>
    </AppLayout>
</template>
