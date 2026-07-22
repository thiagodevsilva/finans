<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    members: Array,
    canManage: Boolean,
});

const page = usePage();
const currentUserId = computed(() => page.props.auth.user.id);

const form = useForm({
    name: '',
    email: '',
    password: '',
});

const submit = () => {
    form.post(route('members.store'), {
        onSuccess: () => form.reset(),
    });
};

const destroy = (member) => {
    if (!confirm(`Remover ${member.name} da conta?`)) return;
    router.delete(route('members.destroy', member.id));
};

const roleLabel = (role) => (role === 'owner' ? 'Dono' : 'Dependente');
</script>

<template>
    <Head title="Dependentes" />

    <AppLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-900">Dependentes</h1>
            <p class="text-sm text-slate-500">Membros da conta familiar</p>
        </div>

        <form v-if="canManage" class="mb-8 grid max-w-3xl gap-3 rounded-lg bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:grid-cols-4" @submit.prevent="submit">
            <div>
                <InputLabel value="Nome" />
                <TextInput class="mt-1 block w-full" v-model="form.name" required />
                <InputError class="mt-1" :message="form.errors.name" />
            </div>
            <div>
                <InputLabel value="E-mail" />
                <TextInput type="email" class="mt-1 block w-full" v-model="form.email" required />
                <InputError class="mt-1" :message="form.errors.email" />
            </div>
            <div>
                <InputLabel value="Senha temporária" />
                <TextInput type="password" class="mt-1 block w-full" v-model="form.password" required />
                <InputError class="mt-1" :message="form.errors.password" />
            </div>
            <div class="flex items-end">
                <PrimaryButton :disabled="form.processing">Adicionar</PrimaryButton>
            </div>
        </form>

        <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Nome</th>
                        <th class="px-4 py-3 font-medium">E-mail</th>
                        <th class="px-4 py-3 font-medium">Perfil</th>
                        <th class="px-4 py-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="member in members" :key="member.id">
                        <td class="px-4 py-3 font-medium text-slate-800">{{ member.name }}</td>
                        <td class="px-4 py-3">{{ member.email }}</td>
                        <td class="px-4 py-3">{{ roleLabel(member.role) }}</td>
                        <td class="px-4 py-3 text-right">
                            <button
                                v-if="canManage && member.id !== currentUserId"
                                type="button"
                                class="text-red-600 hover:underline"
                                @click="destroy(member)"
                            >
                                Remover
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
