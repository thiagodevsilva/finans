<script setup>
import { paymentLabel } from '@/utils/format';
import { computed } from 'vue';

const props = defineProps({
    transaction: {
        type: Object,
        required: true,
    },
});

const label = computed(() => paymentLabel(props.transaction));
const color = computed(() => {
    if (props.transaction.type === 'income') {
        return props.transaction.bank_account?.color || null;
    }
    return props.transaction.payment_card?.color || null;
});
</script>

<template>
    <span
        class="inline-flex max-w-full items-center gap-1.5 truncate rounded-full px-2.5 py-0.5 text-xs font-medium"
        :class="color ? 'text-navy-700' : 'bg-horizon-100 text-horizon-600'"
        :style="color ? { backgroundColor: `${color}33` } : undefined"
    >
        <span
            v-if="color"
            class="h-1.5 w-1.5 shrink-0 rounded-full"
            :style="{ backgroundColor: color }"
        />
        {{ label }}
    </span>
</template>
