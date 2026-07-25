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
    defaults: {
        type: Object,
        default: () => ({}),
    },
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
    recurringBills: {
        type: Array,
        default: () => [],
    },
});

const isEdit = computed(() => !!props.transaction);

const initialType = () => props.transaction?.type || props.defaults?.type || 'expense';

const initialMethod = () => {
    if (props.transaction?.payment_method === 'card' && props.transaction?.payment_card_id) {
        return `card:${props.transaction.payment_card_id}`;
    }
    if (props.transaction?.payment_method) {
        return props.transaction.payment_method;
    }
    if (initialType() === 'transfer' || initialType() === 'investment') {
        return 'pix';
    }
    return 'cash';
};

const initialPaymentCardId = () => {
    if (props.transaction?.payment_card_id) {
        return props.transaction.payment_card_id;
    }
    if (initialType() === 'transfer' && props.defaults?.payment_card_id) {
        return props.defaults.payment_card_id;
    }
    return null;
};

const initialBillId = props.transaction?.recurring_bill_id || '';
const initialBillLabel = (() => {
    if (!initialBillId) return '';
    const fromList = props.recurringBills.find((b) => b.id === initialBillId);
    if (fromList) return fromList.description;
    return props.transaction?.recurring_bill?.description || 'Conta fixa';
})();

const form = useForm({
    type: initialType(),
    amount: props.transaction?.amount || '',
    description: props.transaction?.description || '',
    category_id: props.transaction?.category_id || '',
    date: props.transaction?.date?.slice?.(0, 10) || props.transaction?.date || new Date().toISOString().slice(0, 10),
    payment_selection: initialMethod(),
    payment_method: props.transaction?.payment_method || initialMethod(),
    payment_card_id: initialPaymentCardId(),
    bank_account_id: props.transaction?.bank_account_id || '',
    is_installment: false,
    total_amount: '',
    installments_count: '',
    installment_amount: '',
    recurring_transaction_id: '',
    recurring_bill_id: initialBillId,
});

if (
    form.type === 'transfer'
    && !form.payment_card_id
    && props.paymentCards.filter((c) => c.type === 'credit').length === 1
) {
    form.payment_card_id = props.paymentCards.find((c) => c.type === 'credit').id;
}

const recurringSearch = ref('');
const recurringOpen = ref(false);
const billSearch = ref(initialBillId ? initialBillLabel : '');
const billOpen = ref(false);
const billPickerOpen = ref(!initialBillId);
const installmentSource = ref(null);

const typeOptions = [
    { value: 'expense', label: 'Saída' },
    { value: 'income', label: 'Entrada' },
    { value: 'investment', label: 'Investimento' },
    { value: 'transfer', label: 'Pagamento de fatura' },
];

const creditCards = computed(() => props.paymentCards.filter((c) => c.type === 'credit'));

const invoicePaymentMethods = [
    ...PAYMENT_METHODS,
    { value: 'card', label: 'Cartão' },
];

const needsBankAccount = computed(() =>
    form.payment_method === 'pix' || form.payment_method === 'transfer',
);

const isInvestment = computed(() => form.type === 'investment');

const selectedCard = computed(() => {
    if (!form.payment_card_id) return null;
    return props.paymentCards.find((c) => c.id === form.payment_card_id) || null;
});

const isCreditCard = computed(() =>
    form.type === 'expense' && form.payment_method === 'card' && selectedCard.value?.type === 'credit',
);

const filteredPending = computed(() => {
    const q = recurringSearch.value.trim().toLowerCase();
    if (!q) return props.pendingRecurring;
    return props.pendingRecurring.filter((item) =>
        item.label.toLowerCase().includes(q) || item.description.toLowerCase().includes(q),
    );
});

const filteredBills = computed(() => {
    const q = billSearch.value.trim().toLowerCase();
    if (!q) return props.recurringBills;
    return props.recurringBills.filter((item) =>
        item.label.toLowerCase().includes(q) || item.description.toLowerCase().includes(q),
    );
});

const selectedPending = computed(() =>
    props.pendingRecurring.find((item) => item.id === form.recurring_transaction_id) || null,
);

const selectedBill = computed(() =>
    props.recurringBills.find((item) => item.id === form.recurring_bill_id) || null,
);

const linkedBillLabel = computed(() => {
    if (selectedBill.value) return selectedBill.value.description;
    if (form.recurring_bill_id && billSearch.value) return billSearch.value;
    return null;
});

watch(
    () => form.payment_selection,
    (value) => {
        if (form.type === 'transfer') return;
        if (form.type === 'investment') {
            form.payment_method = value;
            form.payment_card_id = null;
            if (value !== 'pix' && value !== 'transfer') {
                form.bank_account_id = '';
            }
            return;
        }
        if (String(value).startsWith('card:')) {
            form.payment_method = 'card';
            form.payment_card_id = String(value).slice(5);
        } else {
            form.payment_method = value;
            form.payment_card_id = null;
        }
        if (value !== 'pix' && value !== 'transfer') {
            form.bank_account_id = '';
        }
    },
    { immediate: true },
);

watch(
    () => form.payment_method,
    (method) => {
        if (form.type !== 'transfer') return;
        if (method !== 'pix' && method !== 'transfer') {
            form.bank_account_id = '';
        }
    },
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
        if (type !== 'expense') {
            form.is_installment = false;
            form.recurring_transaction_id = '';
            form.recurring_bill_id = '';
            recurringSearch.value = '';
            billSearch.value = '';
            billPickerOpen.value = true;
        }
        if (type === 'investment') {
            form.payment_card_id = null;
            if (form.payment_method === 'card' || String(form.payment_selection).startsWith('card:')) {
                form.payment_selection = 'cash';
                form.payment_method = 'cash';
            }
            if (!needsBankAccount.value) {
                form.bank_account_id = '';
            }
        }
        if (type === 'transfer') {
            form.category_id = '';
            form.payment_selection = 'cash';
            if (!['cash', 'pix', 'transfer', 'card'].includes(form.payment_method)) {
                form.payment_method = 'pix';
            }
            if (!needsBankAccount.value) {
                form.bank_account_id = '';
            }
            if (!form.payment_card_id && creditCards.value.length === 1) {
                form.payment_card_id = creditCards.value[0].id;
            }
        }
    },
);

watch(
    () => form.is_installment,
    (on) => {
        if (on) {
            form.recurring_transaction_id = '';
            form.recurring_bill_id = '';
            recurringSearch.value = '';
            billSearch.value = '';
            billPickerOpen.value = true;
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

const selectPending = (item) => {
    form.recurring_transaction_id = item.id;
    form.recurring_bill_id = '';
    form.description = item.description;
    form.category_id = item.category_id;
    form.amount = item.amount;
    form.date = item.date;
    recurringSearch.value = item.label;
    form.is_installment = false;
    billSearch.value = '';
    billPickerOpen.value = true;

    if (item.payment_method === 'card' && item.payment_card_id) {
        form.payment_selection = `card:${item.payment_card_id}`;
    } else if (item.payment_method) {
        form.payment_selection = item.payment_method;
    }
};

const clearPending = () => {
    form.recurring_transaction_id = '';
    recurringSearch.value = '';
};

const selectBill = (item) => {
    form.recurring_bill_id = item.id;
    form.recurring_transaction_id = '';
    recurringSearch.value = '';
    billSearch.value = item.description;
    billPickerOpen.value = false;
    billOpen.value = false;

    if (!isEdit.value) {
        form.description = item.description;
        form.category_id = item.category_id;
        form.amount = item.estimated_amount;
    }
};

const clearBill = () => {
    form.recurring_bill_id = '';
    billSearch.value = '';
    billPickerOpen.value = true;
};

const onPendingSearchInput = () => {
    if (!form.recurring_transaction_id) return;
    const selected = props.pendingRecurring.find((item) => item.id === form.recurring_transaction_id);
    if (!selected || recurringSearch.value !== selected.label) {
        form.recurring_transaction_id = '';
    }
};

const onBillSearchInput = () => {
    if (!form.recurring_bill_id) return;
    const selected = props.recurringBills.find((item) => item.id === form.recurring_bill_id);
    if (!selected || billSearch.value !== selected.description) {
        form.recurring_bill_id = '';
    }
};

const submit = () => {
    if (form.type === 'income') {
        form.payment_method = null;
        form.payment_card_id = null;
        form.payment_selection = 'cash';
        form.is_installment = false;
        form.recurring_transaction_id = null;
        form.recurring_bill_id = null;
        if (!form.bank_account_id) form.bank_account_id = null;
    } else if (form.type === 'transfer') {
        form.category_id = null;
        form.description = null;
        form.is_installment = false;
        form.recurring_transaction_id = null;
        form.recurring_bill_id = null;
        if (!needsBankAccount.value) form.bank_account_id = null;
    } else if (form.type === 'investment') {
        form.payment_card_id = null;
        form.is_installment = false;
        form.recurring_transaction_id = null;
        form.recurring_bill_id = null;
        form.total_amount = null;
        form.installments_count = null;
        form.installment_amount = null;
        if (!needsBankAccount.value) form.bank_account_id = null;
    } else {
        if (!needsBankAccount.value) form.bank_account_id = null;
        if (!form.recurring_transaction_id) form.recurring_transaction_id = null;
        if (!form.recurring_bill_id) form.recurring_bill_id = null;
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
                <div class="segmented mt-2">
                    <label
                        v-for="opt in typeOptions"
                        :key="opt.value"
                        class="segmented-option"
                        :class="form.type === opt.value ? 'segmented-option-active' : 'segmented-option-idle'"
                    >
                        <input v-model="form.type" type="radio" class="sr-only" :value="opt.value" />
                        {{ opt.label }}
                    </label>
                </div>
                <InputError class="mt-2" :message="form.errors.type" />
            </div>

            <template v-if="form.type === 'transfer'">
                <div>
                    <InputLabel value="Cartão da fatura" />
                    <select v-model="form.payment_card_id" class="mt-1 block w-full rounded-md border-slate-300" required>
                        <option value="" disabled>Selecione</option>
                        <option v-for="card in creditCards" :key="card.id" :value="card.id">
                            {{ formatCardLabel(card) }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.payment_card_id" />
                </div>
                <div>
                    <InputLabel value="Forma de pagamento" />
                    <select v-model="form.payment_method" class="mt-1 block w-full rounded-md border-slate-300" required>
                        <option v-for="m in invoicePaymentMethods" :key="m.value" :value="m.value">{{ m.label }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.payment_method" />
                    <p class="mt-1 text-xs text-horizon-500">
                        Pode ser PIX, transferência, dinheiro ou até outro cartão.
                    </p>
                </div>
                <div v-if="needsBankAccount">
                    <InputLabel value="Conta bancária" />
                    <select v-model="form.bank_account_id" class="mt-1 block w-full rounded-md border-slate-300" required>
                        <option value="" disabled>Selecione</option>
                        <option v-for="account in bankAccounts" :key="account.id" :value="account.id">
                            {{ account.name }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.bank_account_id" />
                </div>
                <div>
                    <InputLabel for="amount" value="Valor (R$)" />
                    <TextInput id="amount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" v-model="form.amount" required />
                    <InputError class="mt-2" :message="form.errors.amount" />
                </div>
                <div>
                    <InputLabel for="date" value="Data" />
                    <TextInput id="date" type="date" class="mt-1 block w-full" v-model="form.date" required />
                    <InputError class="mt-2" :message="form.errors.date" />
                </div>
                <p class="text-xs text-horizon-500">
                    Descrição e categoria são padronizadas (Pagamento de fatura · cartão). Pagamentos parciais no mesmo mês são permitidos.
                </p>
                <p class="text-xs text-horizon-500">
                    PIX, transferência e dinheiro contam como saída de caixa. Pagamento com outro cartão não.
                </p>
            </template>

            <template v-else-if="isInvestment">
                <div>
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
                    <p class="mt-2 text-sm font-medium text-teal-800">Investimento</p>
                    <p class="mt-1 text-xs text-horizon-500">Categoria padrão do sistema (automática).</p>
                    <InputError class="mt-2" :message="form.errors.category_id" />
                </div>
                <div>
                    <InputLabel value="Forma de pagamento" />
                    <select v-model="form.payment_selection" class="mt-1 block w-full rounded-md border-slate-300" required>
                        <option v-for="m in PAYMENT_METHODS" :key="m.value" :value="m.value">{{ m.label }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.payment_method" />
                </div>
                <div v-if="needsBankAccount">
                    <InputLabel value="Conta bancária (opcional)" />
                    <select v-model="form.bank_account_id" class="mt-1 block w-full rounded-md border-slate-300">
                        <option value="">Sem conta</option>
                        <option v-for="account in bankAccounts" :key="account.id" :value="account.id">
                            {{ account.name }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.bank_account_id" />
                </div>
                <div>
                    <InputLabel for="date" value="Data" />
                    <TextInput id="date" type="date" class="mt-1 block w-full" v-model="form.date" required />
                    <InputError class="mt-2" :message="form.errors.date" />
                </div>
                <p class="text-xs text-horizon-500">
                    Conta como saída de caixa, mas não entra nas Saídas de consumo — fica no montante de Investimentos.
                </p>
            </template>

            <template v-else>
                <div v-if="form.type === 'expense' && !isEdit && pendingRecurring.length" class="relative">
                    <InputLabel value="Pagar conta fixa pendente (opcional)" />
                    <TextInput
                        type="search"
                        class="mt-1 block w-full"
                        v-model="recurringSearch"
                        placeholder="Buscar pendente ou vencida…"
                        autocomplete="off"
                        @focus="recurringOpen = true"
                        @blur="recurringOpen = false"
                        @input="onPendingSearchInput"
                    />
                    <p v-if="selectedPending" class="mt-1 text-xs text-horizon-500">
                        Selecionada: {{ selectedPending.description }}
                        <button type="button" class="ml-1 text-cta underline" @click="clearPending">limpar</button>
                    </p>
                    <ul
                        v-if="recurringOpen"
                        class="absolute z-10 mt-1 max-h-48 w-full overflow-auto rounded-md border border-slate-200 bg-white shadow-lg"
                    >
                        <li v-if="!filteredPending.length" class="px-3 py-2 text-sm text-horizon-500">Nenhuma encontrada.</li>
                        <li
                            v-for="item in filteredPending"
                            :key="item.id"
                            class="cursor-pointer border-b border-slate-100 px-3 py-2 text-sm last:border-0 hover:bg-amber-50"
                            @mousedown.prevent="selectPending(item); recurringOpen = false"
                        >
                            <span class="font-medium text-navy-700">{{ item.description }}</span>
                            <span class="mt-0.5 block text-xs text-horizon-500">
                                {{ item.date.split('-').reverse().join('/') }} · {{ formatBRL(item.amount) }} ·
                                <span :class="item.overdue ? 'text-red-600' : 'text-amber-700'">
                                    {{ item.overdue ? 'vencida' : 'pendente' }}
                                </span>
                            </span>
                        </li>
                    </ul>
                    <InputError class="mt-2" :message="form.errors.recurring_transaction_id" />
                </div>

                <div
                    v-if="form.type === 'expense' && recurringBills.length && !form.is_installment && !form.recurring_transaction_id"
                    class="relative"
                >
                    <InputLabel :value="isEdit ? 'Conta fixa' : 'Vincular conta fixa (opcional)'" />
                    <template v-if="form.recurring_bill_id && !billPickerOpen">
                        <p class="mt-2 text-sm text-horizon-600">
                            Vinculada: <span class="font-medium text-navy-700">{{ linkedBillLabel }}</span>
                            <button type="button" class="ml-1 text-cta underline" @click="clearBill">limpar</button>
                        </p>
                    </template>
                    <template v-else>
                        <TextInput
                            type="search"
                            class="mt-1 block w-full"
                            v-model="billSearch"
                            placeholder="Buscar conta fixa cadastrada…"
                            autocomplete="off"
                            @focus="billOpen = true"
                            @blur="billOpen = false"
                            @input="onBillSearchInput"
                        />
                        <ul
                            v-if="billOpen"
                            class="absolute z-10 mt-1 max-h-48 w-full overflow-auto rounded-md border border-slate-200 bg-white shadow-lg"
                        >
                            <li v-if="!filteredBills.length" class="px-3 py-2 text-sm text-horizon-500">Nenhuma encontrada.</li>
                            <li
                                v-for="item in filteredBills"
                                :key="item.id"
                                class="cursor-pointer border-b border-slate-100 px-3 py-2 text-sm last:border-0 hover:bg-amber-50"
                                @mousedown.prevent="selectBill(item)"
                            >
                                {{ item.label }}
                            </li>
                        </ul>
                    </template>
                    <InputError class="mt-2" :message="form.errors.recurring_bill_id" />
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

                    <div v-if="needsBankAccount" class="mt-3">
                        <InputLabel value="Conta bancária (opcional)" />
                        <select v-model="form.bank_account_id" class="mt-1 block w-full rounded-md border-slate-300">
                            <option value="">Sem conta</option>
                            <option v-for="account in bankAccounts" :key="account.id" :value="account.id">
                                {{ account.name }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.bank_account_id" />
                    </div>

                    <label
                        v-if="isCreditCard && !isEdit && !form.recurring_transaction_id"
                        class="mt-3 flex items-center gap-2 text-sm text-navy-700"
                    >
                        <input v-model="form.is_installment" type="checkbox" class="rounded border-slate-300 text-brand-500 focus:ring-brand-500" />
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
                    </div>
                </div>

                <div>
                    <InputLabel for="date" :value="form.is_installment ? 'Data da compra' : 'Data'" />
                    <TextInput id="date" type="date" class="mt-1 block w-full" v-model="form.date" required />
                    <InputError class="mt-2" :message="form.errors.date" />
                </div>
            </template>

            <PrimaryButton :disabled="form.processing">{{ isEdit ? 'Salvar' : 'Criar' }}</PrimaryButton>
        </form>
    </AppLayout>
</template>
