<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const form = useForm({
    title: '',
    description: '',
    attachments: [],
});

const fileInput = ref(null);
const previews = ref([]);

const onFilesChange = (event) => {
    const files = Array.from(event.target.files || []).slice(0, 5);
    form.attachments = files;
    previews.value = files.map((file) => ({
        name: file.name,
        size: file.size,
        url: URL.createObjectURL(file),
    }));
};

const submit = () => {
    form.post(route('support-tickets.store'), {
        forceFormData: true,
    });
};

const formatSize = (bytes) => {
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};
</script>

<template>
    <Head title="Abrir chamado" />

    <AppLayout>
        <div class="mb-6">
            <Link :href="route('support-tickets.index')" class="text-sm text-cta hover:underline">← Voltar</Link>
            <h1 class="mt-2 text-2xl font-bold text-slate-900">Abrir chamado</h1>
            <p class="text-sm text-slate-500">Conte o que precisa — sugestão ou ajuda</p>
        </div>

        <form class="max-w-xl space-y-4 rounded-lg bg-white p-6 shadow-sm ring-1 ring-slate-200" @submit.prevent="submit">
            <div>
                <InputLabel value="Título" />
                <TextInput class="mt-1 block w-full" v-model="form.title" required maxlength="255" />
                <InputError class="mt-1" :message="form.errors.title" />
            </div>

            <div>
                <InputLabel value="Descrição" />
                <textarea
                    v-model="form.description"
                    rows="6"
                    required
                    class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                />
                <InputError class="mt-1" :message="form.errors.description" />
            </div>

            <div>
                <InputLabel value="Prints (opcional, até 5 imagens · máx. 6 MB cada)" />
                <input
                    ref="fileInput"
                    type="file"
                    accept="image/jpeg,image/png,image/webp,image/gif"
                    multiple
                    class="mt-1 block w-full text-sm text-slate-600"
                    @change="onFilesChange"
                />
                <InputError class="mt-1" :message="form.errors.attachments" />
                <InputError
                    v-for="(msg, key) in form.errors"
                    :key="key"
                    class="mt-1"
                    :message="String(key).startsWith('attachments.') ? msg : null"
                />

                <ul v-if="previews.length" class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
                    <li v-for="(p, i) in previews" :key="i" class="rounded-md border border-slate-200 p-2">
                        <img :src="p.url" :alt="p.name" class="h-24 w-full rounded object-cover" />
                        <p class="mt-1 truncate text-xs text-slate-500">{{ p.name }}</p>
                        <p class="text-xs text-slate-400">{{ formatSize(p.size) }}</p>
                    </li>
                </ul>
            </div>

            <div class="flex gap-3">
                <PrimaryButton :disabled="form.processing">Enviar chamado</PrimaryButton>
                <Link :href="route('support-tickets.index')" class="text-sm text-slate-600 hover:underline self-center">
                    Cancelar
                </Link>
            </div>
        </form>
    </AppLayout>
</template>
