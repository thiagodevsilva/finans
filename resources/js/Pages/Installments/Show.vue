<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatBRL, formatDate } from '@/utils/format';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    plan: Object,
});

const cancelFuture = () => {
    if (!confirm('Cancelar parcelas futuras desta compra?')) return;
    router.delete(route('installment-plans.destroy', props.plan.id));
};
</script>

<template>
    <Head :title="plan.description" />

    <AppLayout>
        <div class="mb-6">
            <Link :href="route('transactions.index')" class="text-sm text-cta hover:underline">Voltar</Link>
            <h1 class="mt-2 text-2xl font-bold text-navy-700">{{ plan.description }}</h1>
            <p class="text-sm text-horizon-500">
                Compra em {{ plan.installments_count }}x no {{ plan.payment_card?.name }}
            </p>
        </div>

        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <div class="rounded-[20px] bg-white p-5 shadow-soft">
                <p class="text-xs text-horizon-500">Valor total</p>
                <p class="mt-1 text-xl font-bold text-navy-700">{{ formatBRL(plan.total_amount) }}</p>
            </div>
            <div class="rounded-[20px] bg-white p-5 shadow-soft">
                <p class="text-xs text-horizon-500">Progresso</p>
                <p class="mt-1 text-xl font-bold text-navy-700">{{ plan.paid_count }}/{{ plan.installments_count }}</p>
            </div>
            <div class="rounded-[20px] bg-white p-5 shadow-soft">
                <p class="text-xs text-horizon-500">Restante</p>
                <p class="mt-1 text-xl font-bold text-navy-700">{{ formatBRL(plan.remaining_amount) }}</p>
            </div>
        </div>

        <div class="rounded-[20px] bg-white shadow-soft">
            <div class="border-b border-horizon-100 px-5 py-4">
                <h2 class="font-bold text-navy-700">Parcelas</h2>
                <p class="text-sm text-horizon-500">
                    Compra em {{ formatDate(plan.purchase_date) }} · Categoria {{ plan.category?.name }}
                </p>
            </div>
            <ul class="divide-y divide-horizon-100">
                <li v-for="item in plan.installments" :key="item.id" class="flex items-center justify-between px-5 py-3 text-sm">
                    <div>
                        <p class="font-medium text-navy-700">{{ item.installment_number }}/{{ plan.installments_count }}</p>
                        <p class="text-horizon-500">{{ formatDate(item.date) }}</p>
                    </div>
                    <p class="font-bold text-navy-700">{{ formatBRL(item.amount) }}</p>
                </li>
            </ul>
        </div>

        <div v-if="plan.can_edit" class="mt-6">
            <button type="button" class="text-sm font-medium text-red-600 underline" @click="cancelFuture">
                Cancelar parcelas futuras
            </button>
        </div>
    </AppLayout>
</template>
