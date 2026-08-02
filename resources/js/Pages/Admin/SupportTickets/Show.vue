<script setup>
import AppMark from '@/Components/AppMark.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    ticket: Object,
    statuses: Array,
    canReply: Boolean,
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const flash = computed(() => page.props.flash);

const replyForm = useForm({ body: '' });
const statusForm = useForm({
    status: props.ticket.status,
    closed_reason: props.ticket.closed_reason || '',
});
const closeForm = useForm({ closed_reason: '' });
const showCloseModal = ref(false);

watch(
    () => props.ticket.status,
    (value) => {
        statusForm.status = value;
        statusForm.closed_reason = props.ticket.closed_reason || '';
    },
);

const submitReply = () => {
    replyForm.post(route('admin.support-tickets.replies.store', props.ticket.id), {
        preserveScroll: true,
        onSuccess: () => replyForm.reset(),
    });
};

const submitStatus = () => {
    if (statusForm.status === 'closed' && !statusForm.closed_reason) {
        showCloseModal.value = true;
        return;
    }

    statusForm.patch(route('admin.support-tickets.update', props.ticket.id), {
        preserveScroll: true,
    });
};

const submitClose = () => {
    closeForm.post(route('admin.support-tickets.close', props.ticket.id), {
        onSuccess: () => {
            showCloseModal.value = false;
        },
    });
};

const logout = () => {
    router.post(route('logout'));
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

const slaClass = (status) => {
    if (status === 'breached' || status === 'missed') return 'bg-red-100 text-red-800';
    if (status === 'met') return 'bg-emerald-100 text-emerald-800';
    return 'bg-amber-100 text-amber-800';
};
</script>

<template>
    <Head :title="`Admin · ${ticket.title}`" />

    <div class="min-h-screen bg-slate-50 text-navy-700">
        <header class="border-b border-horizon-100 bg-white">
            <div class="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-3">
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2">
                        <AppMark :size="32" />
                        <div>
                            <p class="text-sm font-bold">Levita Admin</p>
                            <p class="text-xs text-horizon-500">{{ user?.email }}</p>
                        </div>
                    </div>
                    <nav class="flex gap-3 text-sm">
                        <Link :href="route('admin.dashboard')" class="text-horizon-500 hover:text-navy-700">Painel</Link>
                        <Link :href="route('admin.support-tickets.index')" class="font-semibold text-navy-700">Suporte</Link>
                    </nav>
                </div>
                <button type="button" class="text-sm font-medium text-cta hover:underline" @click="logout">
                    Sair
                </button>
            </div>
        </header>

        <main class="mx-auto max-w-5xl space-y-4 px-4 py-5">
            <div v-if="flash?.success" class="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ flash.success }}
            </div>

            <div>
                <Link :href="route('admin.support-tickets.index')" class="text-sm text-cta hover:underline">← Voltar</Link>
                <h1 class="mt-2 text-xl font-bold">{{ ticket.title }}</h1>
                <p class="mt-1 text-sm text-horizon-500">
                    {{ ticket.family_name }} · {{ ticket.author_name }} ({{ ticket.author_email }})
                </p>
                <div class="mt-2 flex flex-wrap gap-2 text-xs">
                    <span class="rounded-full bg-slate-100 px-2 py-0.5">{{ ticket.status_label }}</span>
                    <span class="rounded-full px-2 py-0.5" :class="slaClass(ticket.sla_status)">
                        {{ ticket.sla_label }}
                    </span>
                    <span class="text-horizon-500">Prazo SLA: {{ formatDate(ticket.sla_due_at) }}</span>
                    <span v-if="ticket.first_responded_at" class="text-horizon-500">
                        1ª resposta: {{ formatDate(ticket.first_responded_at) }}
                    </span>
                </div>
            </div>

            <section class="rounded-[16px] bg-white p-4 shadow-soft">
                <h2 class="text-sm font-bold">Descrição</h2>
                <p class="mt-2 whitespace-pre-wrap text-sm">{{ ticket.description }}</p>

                <div v-if="ticket.attachments?.length" class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3">
                    <a
                        v-for="att in ticket.attachments"
                        :key="att.id"
                        :href="att.url"
                        target="_blank"
                        class="overflow-hidden rounded-md border border-horizon-100"
                    >
                        <img :src="att.url" :alt="att.original_name" class="h-28 w-full object-cover" />
                        <p class="truncate px-2 py-1 text-xs text-horizon-500">{{ att.original_name }}</p>
                    </a>
                </div>
            </section>

            <section
                v-if="ticket.status === 'closed'"
                class="rounded-[16px] bg-slate-100 p-4 text-sm"
            >
                <p class="font-semibold">Fechado</p>
                <p class="mt-1">{{ ticket.closed_reason }}</p>
                <p class="mt-1 text-xs text-horizon-500">
                    {{ ticket.closed_by_name }} · {{ formatDate(ticket.closed_at) }}
                </p>
            </section>

            <section class="rounded-[16px] bg-white p-4 shadow-soft">
                <h2 class="mb-3 text-sm font-bold">Atualizar status</h2>
                <form class="flex flex-wrap items-end gap-3" @submit.prevent="submitStatus">
                    <div>
                        <InputLabel value="Status" />
                        <select
                            v-model="statusForm.status"
                            class="mt-1 rounded-md border-horizon-200 text-sm"
                        >
                            <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                    </div>
                    <div v-if="statusForm.status === 'closed'" class="min-w-[220px] flex-1">
                        <InputLabel value="Justificativa" />
                        <input
                            v-model="statusForm.closed_reason"
                            type="text"
                            class="mt-1 w-full rounded-md border-horizon-200 text-sm"
                            required
                        />
                        <InputError class="mt-1" :message="statusForm.errors.closed_reason" />
                    </div>
                    <PrimaryButton :disabled="statusForm.processing">Salvar</PrimaryButton>
                    <button
                        v-if="ticket.status !== 'closed'"
                        type="button"
                        class="rounded-md border border-slate-300 px-3 py-2 text-sm"
                        @click="showCloseModal = true"
                    >
                        Fechar com motivo
                    </button>
                </form>
            </section>

            <section class="space-y-3">
                <h2 class="text-sm font-bold">Conversa</h2>
                <div
                    v-for="reply in ticket.replies"
                    :key="reply.id"
                    class="rounded-[16px] p-4 shadow-soft"
                    :class="reply.is_staff ? 'bg-amber-50' : 'bg-white'"
                >
                    <div class="flex justify-between text-xs text-horizon-500">
                        <span class="font-semibold text-navy-700">
                            {{ reply.author_name }}
                            <span v-if="reply.is_staff">(Equipe)</span>
                        </span>
                        <span>{{ formatDate(reply.created_at) }}</span>
                    </div>
                    <p class="mt-2 whitespace-pre-wrap text-sm">{{ reply.body }}</p>
                </div>
                <p v-if="!ticket.replies?.length" class="text-sm text-horizon-500">Sem mensagens ainda.</p>
            </section>

            <form
                v-if="canReply"
                class="rounded-[16px] bg-white p-4 shadow-soft"
                @submit.prevent="submitReply"
            >
                <InputLabel value="Resposta da equipe" />
                <textarea
                    v-model="replyForm.body"
                    rows="4"
                    required
                    class="mt-1 block w-full rounded-md border-horizon-200 text-sm"
                />
                <InputError class="mt-1" :message="replyForm.errors.body" />
                <div class="mt-3">
                    <PrimaryButton :disabled="replyForm.processing">Enviar resposta</PrimaryButton>
                </div>
            </form>
        </main>

        <div
            v-if="showCloseModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-navy-900/40 p-4"
            @click.self="showCloseModal = false"
        >
            <div class="w-full max-w-md rounded-lg bg-white p-5 shadow-lg">
                <h3 class="text-lg font-bold">Fechar chamado</h3>
                <form class="mt-4 space-y-3" @submit.prevent="submitClose">
                    <div>
                        <InputLabel value="Justificativa" />
                        <textarea
                            v-model="closeForm.closed_reason"
                            rows="3"
                            required
                            class="mt-1 block w-full rounded-md border-horizon-200 text-sm"
                        />
                        <InputError class="mt-1" :message="closeForm.errors.closed_reason" />
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" class="text-sm text-horizon-600" @click="showCloseModal = false">
                            Cancelar
                        </button>
                        <PrimaryButton :disabled="closeForm.processing">Fechar</PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
