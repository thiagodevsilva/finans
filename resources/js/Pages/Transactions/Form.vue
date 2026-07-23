<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { PAYMENT_METHODS } from '@/utils/format';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    transaction: Object,
    categories: Array,
    paymentCards: {
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
});

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

const submit = () => {
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
                <select v-model="form.type" class="mt-1 block w-full rounded-md border-slate-300">
                    <option value="expense">Saída</option>
                    <option value="income">Entrada</option>
                </select>
                <InputError class="mt-2" :message="form.errors.type" />
            </div>

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
                <select v-model="form.category_id" class="mt-1 block w-full rounded-md border-slate-300" required>
                    <option value="" disabled>Selecione</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <InputError class="mt-2" :message="form.errors.category_id" />
            </div>

            <div>
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
                            {{ card.name }} •••• {{ card.last_four }}{{ card.user?.name ? ` (${card.user.name})` : '' }}
                        </option>
                    </optgroup>
                </select>
                <InputError class="mt-2" :message="form.errors.payment_method || form.errors.payment_card_id" />
                <p v-if="!paymentCards.length" class="mt-1 text-xs text-horizon-500">
                    Sem cartões? Cadastre em
                    <Link :href="route('payment-cards.index')" class="text-cta hover:underline">Cartões</Link>.
                </p>
            </div>

            <div>
                <InputLabel for="date" value="Data" />
                <TextInput id="date" type="date" class="mt-1 block w-full" v-model="form.date" required />
                <InputError class="mt-2" :message="form.errors.date" />
            </div>

            <PrimaryButton :disabled="form.processing">{{ isEdit ? 'Salvar' : 'Criar' }}</PrimaryButton>
        </form>
    </AppLayout>
</template>
