<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Password from 'primevue/password';
import {Head, Link, useForm} from '@inertiajs/vue3';
import ApplicationLogo from "@/Components/ApplicationLogo.vue";
import InputText from "primevue/inputtext";
import Button from "@/Components/Button.vue";

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    token: {
        type: String,
        required: true,
    },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head :title="$t('public.choose_a_password')" />

        <div class="w-full flex flex-col justify-center items-center gap-8">
            <div class="w-full flex flex-col items-center gap-6 self-stretch">
                <div class="w-full flex flex-col items-start gap-3 self-stretch">
                    <div class="self-stretch text-center text-gray-950 text-xl font-semibold">{{ $t('public.choose_a_password') }}</div>
                    <div class="self-stretch text-center text-gray-500">{{ $t('public.register_step_2_desc_2') }}</div>
                </div>
            </div>

            <form @submit.prevent="submit" class="flex flex-col items-center gap-6 self-stretch">
                <div class="flex flex-col items-start gap-5 self-stretch">
                    <div class="flex flex-col items-start gap-1 self-stretch">
                        <InputLabel for="email" :value="$t('public.email')" :invalid="!!form.errors.email" />

                        <InputText
                            id="email"
                            type="email"
                            class="block w-full"
                            v-model="form.email"
                            :placeholder="$t('public.enter_your_email')"
                            :invalid="!!form.errors.email"
                            disabled
                        />

                        <InputError :message="form.errors.email" />
                    </div>

                    <div class="flex flex-col items-start gap-1 self-stretch">
                        <InputLabel
                            for="password"
                            :value="$t('public.password')"
                            :invalid="!!form.errors.password"
                        />

                        <Password
                            :invalid="!!form.errors.password"
                            toggleMask
                            :feedback="false"
                            v-model="form.password"
                            autocomplete="new-password"
                        />
                        <InputError :message="form.errors.password" />
                        <span class="text-xs text-gray-500">{{ $t('public.password_desc') }}</span>
                    </div>
                </div>

                <div class="flex flex-col items-start gap-1 self-stretch">
                    <InputLabel for="password_confirmation" :value="$t('public.confirm_password')" />
                    <Password
                        :invalid="!!form.errors.password_confirmation"
                        toggleMask
                        :feedback="false"
                        v-model="form.password_confirmation"
                        autocomplete="new-password"
                    />
                </div>

                <Button
                    variant="primary-flat"
                    size="base"
                    class="w-full"
                    :disabled="form.processing"
                >
                    {{ $t('public.reset_password') }}
                </Button>
                <div class="text-sm text-gray-500">
                    <Link
                        :href="route('login')"
                        class="text-right text-sm font-semibold"
                    >
                        {{ $t('public.back_to_login') }}
                    </Link>
                </div>
            </form>
        </div>
    </GuestLayout>
</template>
