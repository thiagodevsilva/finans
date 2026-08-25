<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    companies: { type: Array, default: () => [] },
    members: { type: Array, default: () => [] },
});

const page = usePage();
const isOwner = computed(() => page.props.auth.user?.is_owner === true);
const editingId = ref(null);

const form = useForm({
    name: '',
    cnpj: '',
    user_id: page.props.auth.user?.id || '',
});

const resetForm = () => {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    form.user_id = page.props.auth.user?.id || '';
};

const startEdit = (company) => {
    editingId.value = company.id;
    form.name = company.name;
    form.cnpj = company.cnpj_formatted || company.cnpj;
    form.user_id = company.user_id;
    form.clearErrors();
};

const submit = () => {
    if (editingId.value) {
        form.put(route('companies.update', editingId.value), {
            preserveScroll: true,
            onSuccess: () => resetForm(),
        });
        return;
    }

    form.post(route('companies.store'), {
        preserveScroll: true,
        onSuccess: () => resetForm(),
    });
};

const remove = (company) => {
    if (!confirm(`Remover o CNPJ ${company.name}?`)) {
        return;
    }
    form.delete(route('companies.destroy', company.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="CNPJs" />

    <AppLayout>
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-navy-700">CNPJs</h1>
                <p class="mt-1 text-sm text-horizon-500">
                    Cadastre CNPJs para marcar gastos e contas como PJ. É só uma tag — o caixa continua por pessoa.
                </p>
            </div>

            <form class="rounded-[20px] bg-white p-5 shadow-soft" @submit.prevent="submit">
                <h2 class="mb-4 text-lg font-bold text-navy-700">
                    {{ editingId ? 'Editar CNPJ' : 'Novo CNPJ' }}
                </h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="name" value="Nome / razão social" />
                        <TextInput id="name" v-model="form.name" class="mt-1 block w-full" required />
                        <InputError class="mt-1" :message="form.errors.name" />
                    </div>
                    <div>
                        <InputLabel for="cnpj" value="CNPJ" />
                        <TextInput id="cnpj" v-model="form.cnpj" class="mt-1 block w-full" placeholder="00.000.000/0000-00" required />
                        <InputError class="mt-1" :message="form.errors.cnpj" />
                    </div>
                    <div v-if="isOwner">
                        <InputLabel for="user_id" value="Dono do CNPJ" />
                        <select
                            id="user_id"
                            v-model="form.user_id"
                            class="mt-1 block w-full rounded-xl border-horizon-200 text-sm"
                            :disabled="!!editingId"
                        >
                            <option v-for="m in members" :key="m.id" :value="m.id">{{ m.name }}</option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.user_id" />
                    </div>
                </div>
                <div class="mt-4 flex gap-2">
                    <PrimaryButton :disabled="form.processing">
                        {{ editingId ? 'Salvar' : 'Cadastrar' }}
                    </PrimaryButton>
                    <SecondaryButton v-if="editingId" type="button" @click="resetForm">Cancelar</SecondaryButton>
                </div>
            </form>

            <div class="rounded-[20px] bg-white p-5 shadow-soft">
                <h2 class="mb-4 text-lg font-bold text-navy-700">Cadastrados</h2>
                <ul v-if="companies.length" class="divide-y divide-horizon-100">
                    <li
                        v-for="company in companies"
                        :key="company.id"
                        class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <p class="font-semibold text-navy-700">{{ company.name }}</p>
                            <p class="text-sm text-horizon-500">
                                {{ company.cnpj_formatted }}
                                <span v-if="company.user"> · {{ company.user.name }}</span>
                            </p>
                        </div>
                        <div v-if="company.can_edit" class="flex gap-2">
                            <SecondaryButton type="button" class="!px-3 !py-1.5 text-xs" @click="startEdit(company)">
                                Editar
                            </SecondaryButton>
                            <button
                                type="button"
                                class="rounded-xl px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50"
                                @click="remove(company)"
                            >
                                Remover
                            </button>
                        </div>
                    </li>
                </ul>
                <p v-else class="text-sm text-horizon-500">Nenhum CNPJ cadastrado ainda.</p>
            </div>
        </div>
    </AppLayout>
</template>
