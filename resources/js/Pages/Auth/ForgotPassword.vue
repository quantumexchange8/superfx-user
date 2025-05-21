<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import {Head, useForm} from '@inertiajs/vue3';
import Button from "@/Components/Button.vue";
import InputText from "primevue/inputtext";

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head :title="$t('public.forgot_password')" />
        <div class="w-full flex flex-col justify-center items-center gap-8">
            <div class="w-full flex flex-col items-center gap-6 self-stretch">
                <div class="w-full flex flex-col items-start gap-3 self-stretch">
                    <div class="self-stretch text-center text-gray-950 text-xl font-semibold">{{ $t('public.forgot_password') }}</div>
                    <div class="self-stretch text-center text-gray-500">{{ $t('public.forgot_password_caption') }}</div>
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
                            autocomplete="email"
                        />

                        <InputError :message="form.errors.email" />
                        <div v-if="status" class="font-medium text-center text-xs text-success-500">
                            {{ status }}
                        </div>
                    </div>
                </div>

                <Button
                    variant="primary-flat"
                    size="base"
                    class="w-full"
                    :disabled="form.processing"
                >
                    {{ $t('public.send_reset_password_link') }}
                </Button>
            </form>
        </div>
    </GuestLayout>
</template>
