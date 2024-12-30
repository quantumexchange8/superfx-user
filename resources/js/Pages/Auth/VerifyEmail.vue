<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { usePage } from "@inertiajs/vue3";
import Button from '@/Components/Button.vue';
import { ref, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    status: {
        type: String,
    },
});

const user = usePage().props.auth.user;
const form = useForm({});

const submitted = ref(false);
const countdown = ref(60);
let interval;
const startCountdown = () => {
    countdown.value = 60;
    interval = setInterval(() => {
        if (countdown.value > 0) {
            countdown.value -= 1;
        } else {
            clearInterval(interval);
            submitted.value = false;
        }
    }, 1000);
};

const submit = () => {
    form.post(route('verification.send'), {
        onSuccess: () => {
            submitted.value = true;
            startCountdown();
        },
    });
};

const logout = () => {
    form.post(route('logout'));
};

const verificationLinkSent = computed(() => props.status === 'verification-link-sent');
</script>

<template>
    <GuestLayout>
        <Head title="Email Verification" />

        <div class="w-full flex flex-col items-center justify-center gap-5 pt-8 md:pt-0">
            <div class="flex flex-col items-start gap-3 self-stretch">
                <div class="self-stretch text-center text-gray-950 text-xl font-semibold">{{ $t('public.email_verification') }}</div>
                <div class="flex flex-col self-stretch text-center text-gray-500 gap-1">{{ $t('public.email_verification_message') }}
                    <span class="text-gray-900 font-medium">
                        {{ user.email }}
                    </span>
                </div>
            </div>
            <div class="flex flex-col items-center justify-center gap-6 self-stretch">
                <Button size="base" variant="primary-flat" class="w-full" :disabled="form.processing" @click.prevent="logout">{{ $t('public.log_out') }}</Button>
                <div class="flex justify-between items-center self-stretch">
                    <div class="text-gray-700 text-sm font-medium">{{ $t('public.not_receive_email') }}</div>
                    <div
                        v-if="!submitted"
                        class="text-right text-sm text-primary-500 font-semibold"
                        :class="{
                            'opacity-25 pointer-events-none cursor-not-allowed': form.processing,
                            'cursor-pointer': !form.processing
                        }"
                        @click.prevent="submit"
                    >
                        {{ $t('public.resend') }}
                    </div>
                    <div v-else class="text-gray-300 text-right text-sm font-semibold">{{ $t('public.resend_in') }} {{ countdown }}s</div>
                </div>
            </div>
        </div>
    </GuestLayout>
</template>
