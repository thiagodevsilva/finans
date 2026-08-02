<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    show: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['dismiss']);

const dontShowAgain = ref(false);

const dismiss = () => {
    emit('dismiss', { dontShowAgain: dontShowAgain.value });
};
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 z-[90] flex items-center justify-center bg-navy-900/45 p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="support-notice-title"
        >
            <div class="w-full max-w-md rounded-[20px] bg-white p-6 shadow-2xl sm:p-8">
                <p class="text-xs font-semibold uppercase tracking-wide text-brand-600">Novidade</p>
                <h2 id="support-notice-title" class="mt-2 text-2xl font-bold text-navy-700">
                    Suporte disponível
                </h2>
                <p class="mt-3 text-sm leading-relaxed text-horizon-500">
                    Agora você pode abrir chamados direto no Levita. Sempre que precisar de ajuda,
                    tiver uma sugestão ou detectar algum erro, fale com a gente pelo menu Suporte.
                </p>
                <label class="mt-5 flex cursor-pointer items-start gap-2.5 text-sm text-navy-700">
                    <input
                        v-model="dontShowAgain"
                        type="checkbox"
                        class="mt-0.5 rounded border-horizon-300 text-brand-600 shadow-sm focus:ring-brand-500"
                    >
                    <span>Não exibir novamente</span>
                </label>
                <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        class="rounded-xl px-4 py-2.5 text-sm font-semibold text-horizon-600 hover:bg-horizon-100"
                        @click="dismiss"
                    >
                        Entendi
                    </button>
                    <Link
                        :href="route('support-tickets.index')"
                        class="inline-flex"
                        @click="dismiss"
                    >
                        <PrimaryButton type="button">
                            Ver suporte
                        </PrimaryButton>
                    </Link>
                </div>
            </div>
        </div>
    </Teleport>
</template>
