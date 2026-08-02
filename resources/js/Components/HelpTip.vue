<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

defineProps({
    text: { type: String, required: true },
    label: { type: String, default: 'Ajuda' },
});

const open = ref(false);
const root = ref(null);

const toggle = () => {
    open.value = !open.value;
};

const onDocClick = (event) => {
    if (!root.value?.contains(event.target)) {
        open.value = false;
    }
};

onMounted(() => document.addEventListener('click', onDocClick));
onBeforeUnmount(() => document.removeEventListener('click', onDocClick));
</script>

<template>
    <span ref="root" class="relative inline-flex align-middle">
        <button
            type="button"
            class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-horizon-300 text-[11px] font-bold leading-none text-horizon-600 hover:border-horizon-400 hover:bg-horizon-50 hover:text-navy-700"
            :aria-label="label"
            :aria-expanded="open"
            @click.stop="toggle"
        >
            ?
        </button>
        <span
            v-if="open"
            role="tooltip"
            class="absolute left-1/2 top-full z-30 mt-2 w-64 -translate-x-1/2 rounded-xl border border-horizon-200 bg-white px-3 py-2 text-left text-xs font-normal leading-relaxed text-horizon-600 shadow-soft sm:w-72"
        >
            {{ text }}
        </span>
    </span>
</template>
