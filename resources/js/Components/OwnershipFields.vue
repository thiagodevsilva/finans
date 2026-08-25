<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    form: { type: Object, required: true },
    members: { type: Array, default: () => [] },
    companies: { type: Array, default: () => [] },
    showShared: { type: Boolean, default: true },
    disabled: { type: Boolean, default: false },
});

const page = usePage();
const isOwner = computed(() => page.props.auth.user?.is_owner === true);
const authId = computed(() => page.props.auth.user?.id);

const memberCompanies = computed(() => {
    const ownerId = props.form.user_id || authId.value;
    return (props.companies || []).filter((c) => c.user_id === ownerId);
});

watch(
    () => props.form.is_shared,
    (shared) => {
        if (shared) {
            props.form.company_id = '';
        }
    },
);

watch(
    () => props.form.user_id,
    () => {
        if (props.form.company_id) {
            const ok = memberCompanies.value.some((c) => c.id === props.form.company_id);
            if (!ok) {
                props.form.company_id = '';
            }
        }
    },
);
</script>

<template>
    <div class="space-y-3 rounded-xl border border-horizon-200 bg-lightPrimary/60 p-3">
        <div v-if="isOwner && members.length > 1">
            <InputLabel for="owner_user_id" value="Em nome de" />
            <select
                id="owner_user_id"
                v-model="form.user_id"
                class="mt-1 block w-full rounded-md border-slate-300 text-sm"
                :disabled="disabled"
            >
                <option v-for="m in members" :key="m.id" :value="m.id">{{ m.name }}</option>
            </select>
            <InputError class="mt-1" :message="form.errors.user_id" />
        </div>

        <label v-if="showShared" class="flex items-center gap-2 text-sm text-navy-700">
            <input
                v-model="form.is_shared"
                type="checkbox"
                class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                :disabled="disabled"
            />
            Gasto / conta da família (só aparece na visão Família)
        </label>
        <InputError v-if="showShared" class="mt-1" :message="form.errors.is_shared" />

        <div v-if="!form.is_shared && memberCompanies.length">
            <InputLabel for="company_id" value="CNPJ (opcional)" />
            <select
                id="company_id"
                v-model="form.company_id"
                class="mt-1 block w-full rounded-md border-slate-300 text-sm"
                :disabled="disabled"
            >
                <option value="">Pessoal (sem CNPJ)</option>
                <option v-for="c in memberCompanies" :key="c.id" :value="c.id">
                    {{ c.name }}
                </option>
            </select>
            <InputError class="mt-1" :message="form.errors.company_id" />
        </div>
    </div>
</template>
