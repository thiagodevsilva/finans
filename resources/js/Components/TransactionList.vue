<script setup>
import PaymentBadge from '@/Components/PaymentBadge.vue';
import { formatBRL, formatDate } from '@/utils/format';
import { Link } from '@inertiajs/vue3';

defineProps({
    transactions: {
        type: Array,
        default: () => [],
    },
    emptyMessage: {
        type: String,
        default: 'Nenhuma transação encontrada.',
    },
    showActions: {
        type: Boolean,
        default: false,
    },
    canEdit: {
        type: Function,
        default: () => false,
    },
});

const emit = defineEmits(['destroy']);
</script>

<template>
    <!-- Mobile cards -->
    <div class="space-y-3 md:hidden">
        <article
            v-for="tx in transactions"
            :key="tx.id"
            class="rounded-[16px] bg-white p-4 shadow-soft"
        >
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <p class="truncate font-semibold text-navy-700">{{ tx.description }}</p>
                    <p class="mt-0.5 text-xs text-horizon-500">{{ formatDate(tx.date) }} · {{ tx.user?.name }}</p>
                </div>
                <p
                    class="shrink-0 text-sm font-bold"
                    :class="tx.type === 'income' ? 'text-emerald-600' : 'text-red-600'"
                >
                    {{ tx.type === 'income' ? '+' : '-' }}{{ formatBRL(tx.amount) }}
                </p>
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center gap-1.5 text-xs text-navy-700">
                    <span class="h-2 w-2 rounded-full" :style="{ backgroundColor: tx.category?.color }" />
                    {{ tx.category?.name }}
                </span>
                <PaymentBadge :transaction="tx" />
            </div>
            <div v-if="showActions && canEdit(tx)" class="mt-3 flex gap-4 border-t border-horizon-100 pt-3 text-sm">
                <Link :href="route('transactions.edit', tx.id)" class="font-medium text-cta hover:underline">Editar</Link>
                <button type="button" class="font-medium text-red-600 hover:underline" @click="emit('destroy', tx)">
                    Excluir
                </button>
            </div>
        </article>
        <p v-if="!transactions.length" class="rounded-[16px] bg-white px-4 py-10 text-center text-sm text-horizon-500 shadow-soft">
            {{ emptyMessage }}
        </p>
    </div>

    <!-- Desktop table -->
    <div class="hidden overflow-x-auto md:block">
        <table class="min-w-full divide-y divide-horizon-100 text-sm">
            <thead class="text-left text-horizon-500">
                <tr>
                    <th class="px-5 py-4 font-medium">Data</th>
                    <th class="px-5 py-4 font-medium">Descrição</th>
                    <th class="px-5 py-4 font-medium">Categoria</th>
                    <th class="px-5 py-4 font-medium">Pagamento / Conta</th>
                    <th class="px-5 py-4 font-medium">Quem</th>
                    <th class="px-5 py-4 font-medium text-right">Valor</th>
                    <th v-if="showActions" class="px-5 py-4 font-medium" />
                </tr>
            </thead>
            <tbody class="divide-y divide-horizon-100">
                <tr v-for="tx in transactions" :key="tx.id">
                    <td class="whitespace-nowrap px-5 py-3 text-navy-700">{{ formatDate(tx.date) }}</td>
                    <td class="px-5 py-3 text-navy-700">{{ tx.description }}</td>
                    <td class="px-5 py-3">
                        <span class="inline-flex items-center gap-2 text-navy-700">
                            <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: tx.category?.color }" />
                            {{ tx.category?.name }}
                        </span>
                    </td>
                    <td class="px-5 py-3">
                        <PaymentBadge :transaction="tx" />
                    </td>
                    <td class="px-5 py-3 text-horizon-600">{{ tx.user?.name }}</td>
                    <td
                        class="px-5 py-3 text-right font-bold"
                        :class="tx.type === 'income' ? 'text-emerald-600' : 'text-red-600'"
                    >
                        {{ tx.type === 'income' ? '+' : '-' }}{{ formatBRL(tx.amount) }}
                    </td>
                    <td v-if="showActions" class="whitespace-nowrap px-5 py-3 text-right">
                        <template v-if="canEdit(tx)">
                            <Link :href="route('transactions.edit', tx.id)" class="text-cta hover:underline">Editar</Link>
                            <button type="button" class="ms-3 text-red-600 hover:underline" @click="emit('destroy', tx)">
                                Excluir
                            </button>
                        </template>
                    </td>
                </tr>
                <tr v-if="!transactions.length">
                    <td :colspan="showActions ? 7 : 6" class="px-5 py-10 text-center text-horizon-500">
                        {{ emptyMessage }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
