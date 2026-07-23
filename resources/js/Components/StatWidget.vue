<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    value: {
        type: String,
        required: true,
    },
    tone: {
        type: String,
        default: 'default', // default | positive | negative
    },
});
</script>

<template>
    <div class="relative z-[5] flex flex-grow flex-row items-center rounded-[20px] bg-white bg-clip-border shadow-soft">
        <div class="ml-[18px] flex h-[90px] w-auto flex-row items-center">
            <div class="rounded-full bg-lightPrimary p-3">
                <span
                    class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold"
                    :class="{
                        'bg-brand-100 text-brand-700': tone === 'default',
                        'bg-emerald-100 text-emerald-700': tone === 'positive',
                        'bg-red-100 text-red-600': tone === 'negative',
                    }"
                >
                    <slot name="icon">R$</slot>
                </span>
            </div>
        </div>
        <div class="ml-4 flex h-auto w-auto flex-col justify-center py-4 pr-4">
            <p class="font-dm text-sm font-medium text-horizon-500">{{ title }}</p>
            <h4
                class="text-xl font-bold tabular-nums"
                :class="{
                    'text-navy-700': tone === 'default',
                    'text-emerald-600': tone === 'positive',
                    'text-red-600': tone === 'negative',
                }"
            >
                {{ value }}
            </h4>
        </div>
    </div>
</template>
