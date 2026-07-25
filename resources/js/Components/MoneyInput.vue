<script setup>
import { ref, watch } from 'vue';

const model = defineModel({
    default: '',
});

defineProps({
    id: String,
    required: Boolean,
    disabled: Boolean,
    placeholder: {
        type: String,
        default: '0,00',
    },
});

const input = ref(null);
const display = ref('');

const toCents = (value) => {
    if (value === '' || value === null || value === undefined) {
        return null;
    }

    if (typeof value === 'number') {
        return Number.isNaN(value) ? null : Math.round(value * 100);
    }

    const raw = String(value).trim();
    if (!raw) {
        return null;
    }

    // pt-BR mascarado: 1.234,56 — senão valor do form: 1234.56
    const normalized = raw.includes(',')
        ? raw.replace(/\./g, '').replace(',', '.')
        : raw;

    const n = Number.parseFloat(normalized);
    return Number.isNaN(n) ? null : Math.round(n * 100);
};

const formatCents = (cents) => (cents / 100).toLocaleString('pt-BR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const syncFromModel = (value) => {
    const cents = toCents(value);
    display.value = cents === null ? '' : formatCents(cents);
};

watch(() => model.value, syncFromModel, { immediate: true });

const onInput = (event) => {
    const digits = String(event.target.value).replace(/\D/g, '').replace(/^0+(?=\d)/, '').slice(0, 12);

    if (!digits) {
        display.value = '';
        model.value = '';
        return;
    }

    const cents = Number.parseInt(digits, 10);
    display.value = formatCents(cents);
    model.value = (cents / 100).toFixed(2);
};

const onFocus = (event) => {
    event.target.select();
};

defineExpose({ focus: () => input.value?.focus() });
</script>

<template>
    <div class="relative">
        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm font-medium text-horizon-500">
            R$
        </span>
        <input
            :id="id"
            ref="input"
            type="text"
            inputmode="numeric"
            autocomplete="off"
            :required="required"
            :disabled="disabled"
            :placeholder="placeholder"
            :value="display"
            class="block w-full rounded-md border-gray-300 pl-10 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            @input="onInput"
            @focus="onFocus"
        />
    </div>
</template>
