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
    compact: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div
        class="relative z-[5] flex flex-grow flex-row items-center rounded-[16px] bg-white bg-clip-border shadow-soft sm:rounded-[20px]"
        :class="compact ? 'min-w-0' : ''"
    >
        <div
            class="flex w-auto flex-row items-center"
            :class="compact ? 'ml-2.5 h-14 sm:ml-[18px] sm:h-[90px]' : 'ml-[18px] h-[90px]'"
        >
            <div class="rounded-full bg-lightPrimary" :class="compact ? 'p-1.5 sm:p-3' : 'p-3'">
                <span
                    class="flex items-center justify-center rounded-full font-bold"
                    :class="[
                        compact ? 'h-6 w-6 text-[10px] sm:h-8 sm:w-8 sm:text-sm' : 'h-8 w-8 text-sm',
                        {
                            'bg-brand-100 text-brand-700': tone === 'default',
                            'bg-emerald-100 text-emerald-700': tone === 'positive',
                            'bg-red-100 text-red-600': tone === 'negative',
                        },
                    ]"
                >
                    <slot name="icon">R$</slot>
                </span>
            </div>
        </div>
        <div
            class="ml-2 flex h-auto w-auto min-w-0 flex-col justify-center sm:ml-4"
            :class="compact ? 'py-2 pr-2 sm:py-4 sm:pr-4' : 'py-4 pr-4'"
        >
            <p
                class="font-dm font-medium text-horizon-500"
                :class="compact ? 'truncate text-[10px] leading-tight sm:text-sm' : 'text-sm'"
            >
                {{ title }}
            </p>
            <h4
                class="font-bold"
                :class="[
                    compact ? 'truncate text-sm sm:text-xl' : 'text-xl',
                    {
                        'text-navy-700': tone === 'default',
                        'text-emerald-600': tone === 'positive',
                        'text-red-600': tone === 'negative',
                    },
                ]"
            >
                {{ value }}
            </h4>
        </div>
    </div>
</template>
