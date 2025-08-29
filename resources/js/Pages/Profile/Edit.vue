<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import ChangeProfilePhoto from "@/Pages/Profile/Partials/ChangeProfilePhoto.vue";
import KycVerfication from "@/Pages/Profile/Partials/KycVerfication.vue";
import PaymentAccount from "@/Pages/Profile/PaymentAccount/PaymentAccount.vue";

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    paymentAccountsCount: Number,
    user: Object
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
                <UpdateProfileInformationForm
                    :user="user"
                />
                <UpdatePasswordForm />
            </div>

            <PaymentAccount
                :paymentAccountsCount="paymentAccountsCount"
            />
        </div>
    </AuthenticatedLayout>
</template>
