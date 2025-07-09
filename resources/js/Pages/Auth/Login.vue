<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import {Link, useForm, usePage} from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Button from '@/Components/Button.vue';
import {watchEffect} from "vue";
import {useConfirm} from "primevue/useconfirm";
import {trans} from "laravel-vue-i18n";
import {Inertia} from "@inertiajs/inertia";

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};

const confirm = useConfirm();

watchEffect(() => {
    if (usePage().props.user_status !== null) {
        confirm.require({
            group: 'headless-gray',
            header: trans('public.account_deactivated'),
            message: {
                text: trans('public.account_deactivated_desc'),
            },
            acceptButton: trans('public.alright'),
        });
    }
});
</script>

<template>
    <GuestLayout title="Forex Broker | Online Currency Trading with SuperFin (SF)">
        <div v-if="status" class="mb-4 font-medium text-sm text-green-600">
            {{ status }}
        </div>

        <div class="w-full flex flex-col justify-center items-center gap-8">
            <div class="w-full flex flex-col items-center gap-6 self-stretch">
                <div class="rounded-lg flex items-center justify-center">
                    <ApplicationLogo class="w-16 h-16 fill-white" />

                </div>
                <div class="w-full flex flex-col items-start gap-3 self-stretch">
                    <div class="self-stretch text-center text-gray-950 text-xl font-semibold">{{ $t('public.login_header') }}</div>
                    <div class="self-stretch text-center text-gray-500">{{ $t('public.login_header_caption') }}</div>
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
                            autofocus
                            :placeholder="$t('public.enter_your_email')"
                            :invalid="!!form.errors.email"
                            autocomplete="username"
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
                            v-model="form.password"
                            toggleMask
                            placeholder="••••••••"
                            :invalid="!!form.errors.password"
                            :feedback="false"
                        />

                        <InputError :message="form.errors.password" />
                    </div>
                </div>
                <div class="flex justify-between items-center self-stretch">
                    <label class="flex items-center cursor-pointer gap-2">
                        <Checkbox name="remember" v-model:checked="form.remember" />
                        <span class="text-sm text-gray-600 font-medium">{{ $t('public.remember_me') }}</span>
                    </label>

                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="text-right text-sm text-primary-500 font-semibold"
                    >
                        {{ $t('public.forgot_your_password') }}
                    </Link>

                </div>
                <Button
                    variant="primary-flat"
                    size="base"
                    class="w-full"
                    :disabled="form.processing"
                >
                    {{ $t('public.sign_in') }}
                </Button>
                <div class="text-sm text-gray-700">
                    {{ $t('public.dont_have_an_account') }}
                    <Link
                        :href="route('register')"
                        class="text-right text-sm text-primary-500 font-semibold"
                    >
                        {{ $t('public.register') }}
                    </Link>
                </div>
            </form>
        </div>
    </GuestLayout>
</template>
