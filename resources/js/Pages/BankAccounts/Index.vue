<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useAppTour } from '@/Composables/useAppTour';
import { FIRST_SETUP_TOUR_ID } from '@/tours/firstSetup';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    bankAccounts: Array,
});

const page = usePage();
const userId = computed(() => page.props.auth.user.id);
const { resumeIfActive, startTour, isTourActive } = useAppTour();

onMounted(() => {
    if (isTourActive()) {
        resumeIfActive();
        return;
    }
    const params = new URLSearchParams(window.location.search);
    if (params.get('tour') === FIRST_SETUP_TOUR_ID) {
        startTour(FIRST_SETUP_TOUR_ID);
    }
});

const editing = ref(null);

const form = useForm({
    name: '',
    color: '#2563eb',
});

const startEdit = (bankAccount) => {
    editing.value = bankAccount.id;
    form.name = bankAccount.name;
    form.color = bankAccount.color;
    form.clearErrors();
};

const cancelEdit = () => {
    editing.value = null;
    form.reset();
    form.color = '#2563eb';
};

const submit = () => {
    if (editing.value) {
        form.put(route('bank-accounts.update', editing.value), {
            onSuccess: () => cancelEdit(),
        });
    } else {
        form.post(route('bank-accounts.store'), {
            onSuccess: () => {
                form.reset();
                form.color = '#2563eb';
            },
        });
    }
};

const destroy = (bankAccount) => {
    if (!confirm(`Excluir a conta "${bankAccount.name}"?`)) return;
    router.delete(route('bank-accounts.destroy', bankAccount.id));
};
</script>

<template>
    <Head title="Contas" />

    <AppLayout>
        <div class="mb-6" data-tour="ba-page">
            <h1 class="text-2xl font-bold text-navy-700">Contas</h1>
            <p class="text-sm text-horizon-500">
                Contas bancárias opcionais para vincular entradas e, se quiser, cartões
            </p>
        </div>

        <form
            class="mb-8 grid max-w-2xl gap-3 rounded-[20px] bg-white p-5 shadow-soft sm:grid-cols-4"
            @submit.prevent="submit"
        >
            <div class="sm:col-span-2" data-tour="ba-name">
                <InputLabel value="Nome" />
                <TextInput class="mt-1 block w-full" v-model="form.name" placeholder="Ex.: Nubank Conta" required />
                <InputError class="mt-1" :message="form.errors.name" />
            </div>
            <div data-tour="ba-color">
                <InputLabel value="Cor" />
                <input type="color" v-model="form.color" class="mt-1 h-10 w-full cursor-pointer rounded-xl border border-horizon-200" />
                <InputError class="mt-1" :message="form.errors.color" />
            </div>
            <div class="flex items-end gap-2" data-tour="ba-submit">
                <PrimaryButton :disabled="form.processing">{{ editing ? 'Salvar' : 'Adicionar' }}</PrimaryButton>
                <button v-if="editing" type="button" class="text-sm text-horizon-600 underline" @click="cancelEdit">Cancelar</button>
            </div>
        </form>

        <div v-if="!bankAccounts.length" class="rounded-[20px] bg-white px-5 py-12 text-center text-horizon-500 shadow-soft">
            Nenhuma conta cadastrada. Você pode lançar entradas sem conta.
        </div>

        <div v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="bankAccount in bankAccounts"
                :key="bankAccount.id"
                class="flex items-center justify-between gap-3 rounded-[20px] bg-white p-4 shadow-soft"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <span class="h-4 w-4 shrink-0 rounded-full" :style="{ backgroundColor: bankAccount.color }" />
                    <div class="min-w-0">
                        <p class="truncate font-semibold text-navy-700">{{ bankAccount.name }}</p>
                        <p class="text-xs text-horizon-500">
                            {{ bankAccount.user_id === userId ? 'Minha' : bankAccount.user?.name }}
                        </p>
                    </div>
                </div>
                <div v-if="bankAccount.can_edit" class="flex shrink-0 gap-3 text-sm">
                    <button type="button" class="text-cta hover:underline" @click="startEdit(bankAccount)">Editar</button>
                    <button type="button" class="text-red-600 hover:underline" @click="destroy(bankAccount)">Excluir</button>
                </div>
            </article>
        </div>
    </AppLayout>
</template>
