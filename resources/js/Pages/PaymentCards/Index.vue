<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    cards: Array,
    brands: Array,
});

const page = usePage();
const userId = computed(() => page.props.auth.user.id);

const editing = ref(null);

const form = useForm({
    name: '',
    brand: 'visa',
    last_four: '',
    color: '#ffc107',
});

const startEdit = (card) => {
    editing.value = card.id;
    form.name = card.name;
    form.brand = card.brand;
    form.last_four = card.last_four;
    form.color = card.color;
    form.clearErrors();
};

const cancelEdit = () => {
    editing.value = null;
    form.reset();
    form.brand = 'visa';
    form.color = '#ffc107';
};

const submit = () => {
    if (editing.value) {
        form.put(route('payment-cards.update', editing.value), {
            onSuccess: () => cancelEdit(),
        });
    } else {
        form.post(route('payment-cards.store'), {
            onSuccess: () => {
                form.reset();
                form.brand = 'visa';
                form.color = '#ffc107';
            },
        });
    }
};

const destroy = (card) => {
    if (!confirm(`Excluir o cartão "${card.name}"?`)) return;
    router.delete(route('payment-cards.destroy', card.id));
};
</script>

<template>
    <Head title="Cartões" />

    <AppLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-navy-700">Cartões</h1>
            <p class="text-sm text-horizon-500">Cadastre cartões para usar como forma de pagamento nos lançamentos</p>
        </div>

        <form
            class="mb-8 grid max-w-3xl gap-3 rounded-[20px] bg-white p-5 shadow-soft sm:grid-cols-2 lg:grid-cols-5"
            @submit.prevent="submit"
        >
            <div class="lg:col-span-2">
                <InputLabel value="Apelido" />
                <TextInput class="mt-1 block w-full" v-model="form.name" placeholder="Ex.: Nubank Roxinho" required />
                <InputError class="mt-1" :message="form.errors.name" />
            </div>
            <div>
                <InputLabel value="Bandeira" />
                <select v-model="form.brand" class="mt-1 block w-full rounded-xl border-horizon-200 text-sm text-navy-700" required>
                    <option v-for="b in brands" :key="b.value" :value="b.value">{{ b.label }}</option>
                </select>
                <InputError class="mt-1" :message="form.errors.brand" />
            </div>
            <div>
                <InputLabel value="Final" />
                <TextInput
                    class="mt-1 block w-full"
                    v-model="form.last_four"
                    maxlength="4"
                    pattern="[0-9]{4}"
                    placeholder="1234"
                    required
                />
                <InputError class="mt-1" :message="form.errors.last_four" />
            </div>
            <div>
                <InputLabel value="Cor" />
                <input type="color" v-model="form.color" class="mt-1 h-10 w-full cursor-pointer rounded-xl border border-horizon-200" />
                <InputError class="mt-1" :message="form.errors.color" />
            </div>
            <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-5">
                <PrimaryButton :disabled="form.processing">{{ editing ? 'Salvar' : 'Adicionar cartão' }}</PrimaryButton>
                <button v-if="editing" type="button" class="text-sm text-horizon-600 underline" @click="cancelEdit">Cancelar</button>
            </div>
        </form>

        <div v-if="!cards.length" class="rounded-[20px] bg-white px-5 py-12 text-center text-horizon-500 shadow-soft">
            Nenhum cartão cadastrado ainda.
        </div>

        <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="card in cards"
                :key="card.id"
                class="relative overflow-hidden rounded-[20px] p-5 text-white shadow-soft"
                :style="{ background: `linear-gradient(135deg, ${card.color} 0%, #1b2559 100%)` }"
            >
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-white/80">{{ card.brand_label }}</p>
                        <h2 class="mt-1 text-lg font-bold">{{ card.name }}</h2>
                    </div>
                    <span
                        class="rounded-full bg-white/20 px-2.5 py-0.5 text-xs font-medium"
                        :class="card.user_id === userId ? 'ring-1 ring-white/50' : ''"
                    >
                        {{ card.user_id === userId ? 'Meu' : card.user?.name }}
                    </span>
                </div>

                <p class="mt-8 font-mono text-xl tracking-[0.2em]">•••• {{ card.last_four }}</p>

                <div v-if="card.can_edit" class="mt-5 flex gap-3 text-sm">
                    <button type="button" class="font-medium text-white/90 underline hover:text-white" @click="startEdit(card)">
                        Editar
                    </button>
                    <button type="button" class="font-medium text-white/90 underline hover:text-white" @click="destroy(card)">
                        Excluir
                    </button>
                </div>
            </article>
        </div>
    </AppLayout>
</template>
