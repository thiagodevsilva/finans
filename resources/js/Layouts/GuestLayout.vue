<script setup>
import AppMark from '@/Components/AppMark.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="grid min-h-screen overflow-x-hidden bg-white lg:grid-cols-2">
        <div class="flex flex-col justify-center px-6 py-10 sm:px-10 lg:px-16 xl:px-24">
            <Link :href="route('home')" class="mb-8 w-max text-sm text-horizon-500 hover:text-navy-700">
                ← Voltar ao início
            </Link>

            <Link :href="route('home')" class="mb-6 flex w-max items-center gap-3">
                <AppMark :size="44" />
                <span class="text-2xl font-bold tracking-tight text-navy-700 md:text-3xl">Levita</span>
            </Link>

            <div class="w-full max-w-md">
                <slot />
            </div>
        </div>

        <div class="relative hidden overflow-hidden bg-brand-500 lg:block">
            <img
                src="/images/capa-mulher.png"
                alt=""
                class="absolute bottom-0 left-1/2 h-[90%] w-auto max-w-none -translate-x-1/2 object-contain object-bottom select-none"
                draggable="false"
            />
        </div>
    </div>
</template>
