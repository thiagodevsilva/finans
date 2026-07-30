<script setup>
defineProps({
    show: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div
        v-if="show"
        class="mb-4 rounded-[16px] border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950"
        role="status"
    >
        <p class="font-semibold">Modo demonstração</p>
        <p class="mt-0.5 text-amber-900/80">
            Os dados desta tela são exemplos para o tutorial. Nada será salvo.
        </p>
    </div>
</template>
