<script setup>
defineProps({
    extra: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div
        class="relative z-[5] flex flex-col rounded-[20px] bg-white bg-clip-border shadow-soft"
        :class="extra"
    >
        <slot />
    </div>
</template>
