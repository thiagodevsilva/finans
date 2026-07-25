<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';

defineProps({
    show: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['accept', 'skip']);
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 z-[90] flex items-center justify-center bg-navy-900/45 p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="welcome-onboarding-title"
        >
            <div class="w-full max-w-md rounded-[20px] bg-white p-6 shadow-2xl sm:p-8">
                <p class="text-xs font-semibold uppercase tracking-wide text-brand-600">Primeiro acesso</p>
                <h2 id="welcome-onboarding-title" class="mt-2 text-2xl font-bold text-navy-700">
                    Bem-vindo ao Levita
                </h2>
                <p class="mt-3 text-sm leading-relaxed text-horizon-500">
                    Um passeio rápido pelas telas de contas e cartões. Você não precisa cadastrar nada agora —
                    pode só conhecer o fluxo e criar depois, no seu tempo.
                </p>
                <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        class="rounded-xl px-4 py-2.5 text-sm font-semibold text-horizon-600 hover:bg-horizon-100"
                        @click="$emit('skip')"
                    >
                        Agora não
                    </button>
                    <PrimaryButton type="button" @click="$emit('accept')">
                        Sim, mostrar
                    </PrimaryButton>
                </div>
            </div>
        </div>
    </Teleport>
</template>
