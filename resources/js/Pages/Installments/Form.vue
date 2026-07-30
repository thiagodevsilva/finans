<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import MoneyInput from '@/Components/MoneyInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { formatCardLabel } from '@/utils/format';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    categories: Array,
    paymentCards: Array,
});

const form = useForm({
    description: '',
    category_id: '',
    payment_card_id: '',
    total_amount: '',
    installments_count: '2',
    purchase_date: new Date().toISOString().slice(0, 10),
    first_installment_date: new Date().toISOString().slice(0, 10),
});

const submit = () => form.post(route('installment-plans.store'));
</script>

<template>
    <Head title="Compra parcelada" />

    <AppLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-navy-700">Compra parcelada</h1>
            <p class="text-sm text-horizon-500">O mês filtrado mostra só a parcela daquele período</p>
            <Link :href="route('transactions.index')" class="text-sm text-cta hover:underline">Voltar</Link>
        </div>

        <form class="max-w-xl space-y-4 rounded-[20px] bg-white p-6 shadow-soft" @submit.prevent="submit">
            <div>
                <InputLabel value="Descrição" />
                <TextInput class="mt-1 block w-full" v-model="form.description" required />
                <InputError class="mt-1" :message="form.errors.description" />
            </div>
            <div>
                <InputLabel value="Valor total (R$)" />
                <MoneyInput class="mt-1" v-model="form.total_amount" required />
                <InputError class="mt-1" :message="form.errors.total_amount" />
            </div>
            <div>
                <InputLabel value="Número de parcelas" />
                <TextInput type="number" min="2" max="48" class="mt-1 block w-full" v-model="form.installments_count" required />
                <InputError class="mt-1" :message="form.errors.installments_count" />
            </div>
            <div>
                <InputLabel value="Categoria" />
                <select v-model="form.category_id" class="mt-1 block w-full rounded-xl border-horizon-200 text-sm" required>
                    <option value="" disabled>Selecione</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <InputError class="mt-1" :message="form.errors.category_id" />
            </div>
            <div>
                <InputLabel value="Cartão de crédito" />
                <select v-model="form.payment_card_id" class="mt-1 block w-full rounded-xl border-horizon-200 text-sm" required>
                    <option value="" disabled>Selecione</option>
                    <option v-for="card in paymentCards" :key="card.id" :value="card.id">{{ formatCardLabel(card) }}</option>
                </select>
                <InputError class="mt-1" :message="form.errors.payment_card_id" />
            </div>
            <div>
                <InputLabel value="Data da compra" />
                <TextInput type="date" class="mt-1 block w-full" v-model="form.purchase_date" required />
                <InputError class="mt-1" :message="form.errors.purchase_date" />
            </div>
            <div>
                <InputLabel value="Data da 1ª parcela" />
                <TextInput type="date" class="mt-1 block w-full" v-model="form.first_installment_date" required />
                <InputError class="mt-1" :message="form.errors.first_installment_date" />
            </div>
            <PrimaryButton :disabled="form.processing">Cadastrar parcelas</PrimaryButton>
        </form>
    </AppLayout>
</template>
