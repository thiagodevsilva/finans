<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { useAppTour } from '@/Composables/useAppTour';
import { Head } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const { restartOnboarding } = useAppTour();
</script>

<template>
    <Head title="Perfil" />

    <AppLayout>
        <h1 class="mb-6 text-2xl font-bold text-slate-900">Perfil</h1>

        <div class="max-w-3xl space-y-6">
            <div class="bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:rounded-lg sm:p-8">
                <UpdateProfileInformationForm
                    :must-verify-email="mustVerifyEmail"
                    :status="status"
                    class="max-w-xl"
                />
            </div>

            <div class="bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:rounded-lg sm:p-8">
                <h2 class="text-lg font-medium text-navy-700">Tutorial do app</h2>
                <p class="mt-1 text-sm text-horizon-500">
                    <span class="font-medium text-navy-700">Refazer tutorial</span>
                    reinicia o passeio completo (contas e cartões). Em cada tela, use
                    <span class="font-medium text-navy-700">Ajuda</span>
                    para um tutorial curto só daquela página.
                </p>
                <PrimaryButton type="button" class="mt-4" @click="restartOnboarding">
                    Refazer tutorial
                </PrimaryButton>
            </div>

            <div class="bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:rounded-lg sm:p-8">
                <UpdatePasswordForm class="max-w-xl" />
            </div>

            <div class="bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:rounded-lg sm:p-8">
                <DeleteUserForm class="max-w-xl" />
            </div>
        </div>
    </AppLayout>
</template>
