<script setup>
import { useAppTour } from '@/Composables/useAppTour';
import { resolvePageTourId } from '@/tours/registry';
import { computed } from 'vue';

const props = defineProps({
    /** Força um tour específico; senão resolve pela rota atual */
    tourId: {
        type: String,
        default: null,
    },
});

const { startTour, isTourActive } = useAppTour();

const resolvedId = computed(() => props.tourId || resolvePageTourId());

const canShow = computed(() => Boolean(resolvedId.value) && !isTourActive());

const start = () => {
    if (resolvedId.value) {
        startTour(resolvedId.value);
    }
};
</script>

<template>
    <button
        v-if="canShow"
        type="button"
        class="inline-flex items-center gap-1.5 rounded-xl border border-horizon-200 bg-white px-3 py-1.5 text-xs font-semibold text-navy-700 shadow-sm hover:bg-horizon-50 sm:text-sm"
        @click="start"
    >
        Ajuda nesta tela
    </button>
</template>
