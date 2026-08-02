<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    ticket: Object,
    canReply: Boolean,
    canClose: Boolean,
});

const replyForm = useForm({ body: '' });
const closeForm = useForm({ closed_reason: '' });
const showCloseModal = ref(false);

const submitReply = () => {
    replyForm.post(route('support-tickets.replies.store', props.ticket.id), {
        preserveScroll: true,
        onSuccess: () => replyForm.reset(),
    });
};

const submitClose = () => {
    closeForm.post(route('support-tickets.close', props.ticket.id), {
        onSuccess: () => {
            showCloseModal.value = false;
        },
    });
};

const formatDate = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>

<template>
    <Head :title="ticket.title" />

    <AppLayout>
        <div class="mb-6">
            <Link :href="route('support-tickets.index')" class="text-sm text-cta hover:underline">← Voltar</Link>
            <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">{{ ticket.title }}</h1>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ ticket.status_label }} · aberto em {{ formatDate(ticket.created_at) }}
                        <span v-if="ticket.author_name"> · {{ ticket.author_name }}</span>
                    </p>
                </div>
                <button
                    v-if="canClose"
                    type="button"
                    class="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
                    @click="showCloseModal = true"
                >
                    Fechar chamado
                </button>
            </div>
        </div>

        <div class="max-w-3xl space-y-4">
            <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h2 class="text-sm font-semibold text-slate-700">Descrição</h2>
                <p class="mt-2 whitespace-pre-wrap text-sm text-slate-800">{{ ticket.description }}</p>

                <div v-if="ticket.attachments?.length" class="mt-4">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Prints</h3>
                    <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                        <a
                            v-for="att in ticket.attachments"
                            :key="att.id"
                            :href="att.url"
                            target="_blank"
                            class="block overflow-hidden rounded-md border border-slate-200"
                        >
                            <img :src="att.url" :alt="att.original_name" class="h-28 w-full object-cover" />
                            <p class="truncate px-2 py-1 text-xs text-slate-500">{{ att.original_name }}</p>
                        </a>
                    </div>
                </div>
            </section>

            <section
                v-if="ticket.status === 'closed'"
                class="rounded-lg bg-slate-50 p-4 text-sm text-slate-700 ring-1 ring-slate-200"
            >
                <p class="font-semibold">Chamado fechado</p>
                <p class="mt-1">{{ ticket.closed_reason }}</p>
                <p class="mt-1 text-xs text-slate-500">
                    {{ ticket.closed_by_name }} · {{ formatDate(ticket.closed_at) }}
                </p>
            </section>

            <section class="space-y-3">
                <h2 class="text-sm font-semibold text-slate-700">Conversa</h2>

                <div
                    v-if="!ticket.replies?.length"
                    class="rounded-lg bg-white px-4 py-6 text-center text-sm text-slate-500 shadow-sm ring-1 ring-slate-200"
                >
                    Ainda sem respostas.
                </div>

                <div
                    v-for="reply in ticket.replies"
                    :key="reply.id"
                    class="rounded-lg p-4 shadow-sm ring-1"
                    :class="reply.is_staff
                        ? 'bg-amber-50 ring-amber-100'
                        : 'bg-white ring-slate-200'"
                >
                    <div class="flex items-center justify-between gap-2 text-xs text-slate-500">
                        <span class="font-semibold text-slate-700">
                            {{ reply.author_name }}
                            <span v-if="reply.is_staff" class="ml-1 font-normal text-amber-700">(Equipe Levita)</span>
                        </span>
                        <span>{{ formatDate(reply.created_at) }}</span>
                    </div>
                    <p class="mt-2 whitespace-pre-wrap text-sm text-slate-800">{{ reply.body }}</p>
                </div>
            </section>

            <form
                v-if="canReply"
                class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-slate-200"
                @submit.prevent="submitReply"
            >
                <InputLabel value="Sua mensagem" />
                <textarea
                    v-model="replyForm.body"
                    rows="4"
                    required
                    class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                />
                <InputError class="mt-1" :message="replyForm.errors.body" />
                <div class="mt-3">
                    <PrimaryButton :disabled="replyForm.processing">Enviar</PrimaryButton>
                </div>
            </form>
        </div>

        <div
            v-if="showCloseModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-navy-900/40 p-4"
            @click.self="showCloseModal = false"
        >
            <div class="w-full max-w-md rounded-lg bg-white p-5 shadow-lg">
                <h3 class="text-lg font-bold text-slate-900">Fechar chamado</h3>
                <p class="mt-1 text-sm text-slate-500">Informe o motivo do fechamento.</p>
                <form class="mt-4 space-y-3" @submit.prevent="submitClose">
                    <div>
                        <InputLabel value="Justificativa" />
                        <textarea
                            v-model="closeForm.closed_reason"
                            rows="3"
                            required
                            class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                        />
                        <InputError class="mt-1" :message="closeForm.errors.closed_reason" />
                    </div>
                    <div class="flex justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-md px-3 py-2 text-sm text-slate-600 hover:bg-slate-50"
                            @click="showCloseModal = false"
                        >
                            Cancelar
                        </button>
                        <PrimaryButton :disabled="closeForm.processing">Confirmar fechamento</PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
