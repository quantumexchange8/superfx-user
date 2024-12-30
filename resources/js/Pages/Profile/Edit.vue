<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';
import ChangeProfilePhoto from "@/Pages/Profile/Partials/ChangeProfilePhoto.vue";
import KycVerfication from "@/Pages/Profile/Partials/KycVerfication.vue";
import CryptoWallet from "@/Pages/Profile/Partials/CryptoWallet.vue";

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
    <AuthenticatedLayout :title="$t('public.my_profile')">
        <div class="flex flex-col items-center gap-5 self-stretch">
            <div class="flex flex-col md:flex-row justify-center items-center gap-5 self-stretch">
                <ChangeProfilePhoto />
                <KycVerfication />
            </div>

            <div class="flex flex-col md:flex-row justify-center items-center gap-5 self-stretch">
                <UpdateProfileInformationForm />
                <UpdatePasswordForm />
            </div>

            <div class="flex flex-col md:flex-row justify-center items-center gap-5 self-stretch">
                <CryptoWallet />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
