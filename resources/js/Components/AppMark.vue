<script setup>
defineProps({
    size: {
        type: Number,
        default: 40,
    },
    /** onBrand: mark on yellow background (white circle + navy glyph) */
    variant: {
        type: String,
        default: 'default', // default | onBrand
    },
});
</script>

<template>
    <!-- Cifrão com L no lugar do S -->
    <svg
        :width="size"
        :height="size"
        viewBox="0 0 40 40"
        fill="none"
        xmlns="http://www.w3.org/2000/svg"
        aria-hidden="true"
        class="shrink-0"
    >
        <circle
            cx="20"
            cy="20"
            r="20"
            :fill="variant === 'onBrand' ? '#ffffff' : '#ffc107'"
        />
        <g :fill="variant === 'onBrand' ? '#1b2559' : '#1b2559'">
            <!-- Traço vertical do cifrão -->
            <rect x="18.9" y="7" width="2.2" height="26" rx="1.1" />
            <!-- L -->
            <path d="M13 11h3.8v13.2H27a1.8 1.8 0 1 1 0 3.6H13.8A1.8 1.8 0 0 1 12 26V12.8c0-1 .8-1.8 1.8-1.8z" />
        </g>
    </svg>
</template>
