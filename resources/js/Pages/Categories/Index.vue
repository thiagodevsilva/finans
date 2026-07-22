<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    categories: Array,
    canManage: Boolean,
});

const editing = ref(null);

const form = useForm({
    name: '',
    color: '#ffc107',
    icon: '',
});

const startEdit = (category) => {
    editing.value = category.id;
    form.name = category.name;
    form.color = category.color;
    form.icon = category.icon || '';
    form.clearErrors();
};

const cancelEdit = () => {
    editing.value = null;
    form.reset();
    form.color = '#ffc107';
};

const submit = () => {
    if (editing.value) {
        form.put(route('categories.update', editing.value), {
            onSuccess: () => cancelEdit(),
        });
    } else {
        form.post(route('categories.store'), {
            onSuccess: () => {
                form.reset();
                form.color = '#ffc107';
            },
        });
    }
};

const destroy = (category) => {
    if (!confirm(`Excluir a categoria "${category.name}"?`)) return;
    router.delete(route('categories.destroy', category.id));
};
</script>

<template>
    <Head title="Categorias" />

    <AppLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-900">Categorias</h1>
            <p class="text-sm text-slate-500">Organização dos lançamentos da conta</p>
        </div>

        <form v-if="canManage" class="mb-8 grid max-w-2xl gap-3 rounded-lg bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:grid-cols-4" @submit.prevent="submit">
            <div class="sm:col-span-2">
                <InputLabel value="Nome" />
                <TextInput class="mt-1 block w-full" v-model="form.name" required />
                <InputError class="mt-1" :message="form.errors.name" />
            </div>
            <div>
                <InputLabel value="Cor" />
                <input type="color" v-model="form.color" class="mt-1 h-10 w-full cursor-pointer rounded border border-slate-300" />
                <InputError class="mt-1" :message="form.errors.color" />
            </div>
            <div class="flex items-end gap-2">
                <PrimaryButton :disabled="form.processing">{{ editing ? 'Salvar' : 'Criar' }}</PrimaryButton>
                <button v-if="editing" type="button" class="text-sm text-slate-600 underline" @click="cancelEdit">Cancelar</button>
            </div>
        </form>

        <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-slate-200">
            <ul class="divide-y divide-slate-100">
                <li v-for="category in categories" :key="category.id" class="flex items-center justify-between px-4 py-3">
                    <div class="flex items-center gap-3">
                        <span class="h-4 w-4 rounded-full" :style="{ backgroundColor: category.color }" />
                        <span class="font-medium text-slate-800">{{ category.name }}</span>
                    </div>
                    <div v-if="canManage" class="space-x-3 text-sm">
                        <button type="button" class="text-cta hover:underline" @click="startEdit(category)">Editar</button>
                        <button type="button" class="text-red-600 hover:underline" @click="destroy(category)">Excluir</button>
                    </div>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
