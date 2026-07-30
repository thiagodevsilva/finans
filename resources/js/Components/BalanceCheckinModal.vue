<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import MoneyInput from '@/Components/MoneyInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { formatBRL } from '@/utils/format';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    mode: {
        type: String,
        default: 'initial', // initial | monthly | update
    },
    previousMonthBalance: { type: Number, default: null },
});

const emit = defineEmits(['close']);

const today = () => new Date().toISOString().slice(0, 10);

const form = useForm({
    amount: '',
    as_of_date: today(),
    source: 'initial',
});

const keepForm = useForm({});

const title = computed(() => {
    if (props.mode === 'monthly') return 'Saldo do novo mês';
    if (props.mode === 'update') return 'Atualizar saldo';
    return 'Informe o saldo atual';
});

const description = computed(() => {
    if (props.mode === 'monthly') {
        return 'É o primeiro acesso deste mês. Você pode manter o saldo calculado do fim do mês anterior ou informar um novo valor.';
    }
    if (props.mode === 'update') {
        return 'Informe o saldo de caixa (contas + dinheiro) na data escolhida. Os lançamentos depois dessa data serão recalculados automaticamente.';
    }
    return 'Para começar o controle de caixa, informe quanto a família tem hoje somando contas e dinheiro. Compras no crédito não entram aqui até você pagar a fatura.';
});

const closeable = computed(() => props.mode === 'update');

watch(
    () => [props.show, props.mode],
    ([show]) => {
        if (!show) return;
        form.clearErrors();
        form.amount = '';
        form.as_of_date = today();
        form.source = props.mode === 'initial' ? 'initial' : (props.mode === 'monthly' ? 'monthly_update' : 'manual');
    },
);

const submitUpdate = () => {
    form.post(route('balance-anchors.store'), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
};

const keepPrevious = () => {
    keepForm.post(route('balance-anchors.keep'), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
};

const onClose = () => {
    if (closeable.value) emit('close');
};
</script>

<template>
    <Modal :show="show" max-width="md" :closeable="closeable" @close="onClose">
        <div class="p-6">
            <h2 class="text-lg font-bold text-navy-700">{{ title }}</h2>
            <p class="mt-2 text-sm text-horizon-600">{{ description }}</p>

            <div
                v-if="mode === 'monthly' && previousMonthBalance != null"
                class="mt-4 rounded-xl bg-horizon-50 px-4 py-3"
            >
                <p class="text-xs font-medium text-horizon-500">Saldo calculado no fim do mês anterior</p>
                <p class="mt-0.5 text-xl font-bold tabular-nums text-navy-700">
                    {{ formatBRL(previousMonthBalance) }}
                </p>
                <SecondaryButton
                    class="mt-3"
                    :disabled="keepForm.processing"
                    @click="keepPrevious"
                >
                    Manter este valor
                </SecondaryButton>
            </div>

            <form class="mt-5 space-y-4" @submit.prevent="submitUpdate">
                <div v-if="mode === 'monthly'">
                    <p class="text-sm font-medium text-navy-700">Ou atualizar com outro valor</p>
                </div>
                <div>
                    <InputLabel for="balance_amount" value="Saldo (R$)" />
                    <MoneyInput id="balance_amount" class="mt-1" v-model="form.amount" required />
                    <InputError class="mt-2" :message="form.errors.amount" />
                </div>
                <div>
                    <InputLabel for="balance_as_of" value="Data de referência" />
                    <input
                        id="balance_as_of"
                        v-model="form.as_of_date"
                        type="date"
                        class="mt-1 block w-full rounded-md border-slate-300"
                        required
                    >
                    <InputError class="mt-2" :message="form.errors.as_of_date" />
                    <p class="mt-1 text-xs text-horizon-500">
                        Saldo no fim deste dia. Lançamentos posteriores entram no recalculo.
                    </p>
                </div>
                <div class="flex justify-end gap-2">
                    <SecondaryButton v-if="closeable" type="button" @click="onClose">
                        Cancelar
                    </SecondaryButton>
                    <PrimaryButton :disabled="form.processing">
                        {{ mode === 'initial' ? 'Salvar saldo' : 'Atualizar saldo' }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </Modal>
</template>
