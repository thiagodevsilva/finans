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
    /** Versão compacta para o header mobile */
    compact: {
        type: Boolean,
        default: false,
    },
});

const { startPageTour, isTourActive, FIRST_SETUP_TOUR_ID } = useAppTour();

const resolvedId = computed(() => {
    const id = props.tourId || resolvePageTourId();
    // Ajuda nesta tela nunca dispara o tutorial completo
    return id === FIRST_SETUP_TOUR_ID ? null : id;
});

const canShow = computed(() => Boolean(resolvedId.value) && !isTourActive());

const start = () => {
    if (resolvedId.value) {
        startPageTour(resolvedId.value);
    }
};
</script>

<template>
    <button
        v-if="canShow"
        type="button"
        :class="compact
            ? 'rounded-xl bg-white px-3.5 py-2.5 text-[0.9625rem] font-semibold text-navy-700 shadow-soft'
            : 'inline-flex items-center gap-1.5 rounded-xl border border-horizon-200 bg-white px-3 py-1.5 text-xs font-semibold text-navy-700 shadow-sm hover:bg-horizon-50 sm:text-sm'"
        @click="start"
    >
        {{ compact ? 'Ajuda' : 'Ajuda nesta tela' }}
    </button>
</template>
