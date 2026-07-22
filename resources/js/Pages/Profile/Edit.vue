<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});
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
                <UpdatePasswordForm class="max-w-xl" />
            </div>

            <div class="bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:rounded-lg sm:p-8">
                <DeleteUserForm class="max-w-xl" />
            </div>
        </div>
    </AppLayout>
</template>
