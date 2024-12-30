<script setup>
import {ref, watchEffect} from "vue";
import Button from "@/Components/Button.vue"
import Dialog from "primevue/dialog";
import {
    IconX
} from "@tabler/icons-vue"
import InputError from '@/Components/InputError.vue';
import {KycFemale, KycMale} from "@/Components/Icons/solid.jsx";
import {useForm, usePage} from "@inertiajs/vue3";
import Skeleton from 'primevue/skeleton';

const visible = ref(false);
const dialogType = ref('');
const kycVerification = ref();
const isLoading = ref(false);

const getKycVerification = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get('/profile/getKycVerification');
        kycVerification.value = response.data.kycVerification;
    } catch (error) {
        console.error('Error getting kyc:', error);
    } finally {
        isLoading.value = false;
    }
};

getKycVerification();

const openDialog = (type) => {
    dialogType.value = type;
    visible.value = true;
}

const form = useForm({
    kyc_verification: '',
});

const selectedKycVerification = ref(null);
const selectedKycVerificationName = ref(null);
const handleKycVerification = (event) => {
    const kycVerificationInput = event.target;
    const file = kycVerificationInput.files[0];

    if (file) {
        // Display the selected image
        const reader = new FileReader();
        reader.onload = () => {
            selectedKycVerification.value = reader.result;
        };
        reader.readAsDataURL(file);
        selectedKycVerificationName.value = file.name;
        form.kyc_verification = event.target.files[0];
    } else {
        selectedKycVerification.value = null;
    }
};

const removeKycVerification = () => {
    selectedKycVerification.value = null;
    form.kyc_verification = '';
};

const submitForm = () => {
    form.post(route('profile.updateKyc'), {
        onSuccess: () => {
            visible.value = false;
        }
    })
}

watchEffect(() => {
    if (usePage().props.toast !== null) {
        getKycVerification();
    }
});
</script>

<template>
    <div class="p-4 md:py-6 md:px-8 flex flex-col gap-5 md:gap-0 md:justify-between items-end self-stretch rounded-2xl shadow-toast w-full">
        <div class="flex flex-col gap-1 items-start justify-center w-full">
            <span class="text-gray-950 font-bold">{{ $t('public.kyc_verification') }}</span>
            <span class="text-gray-500 text-xs">{{ $t('public.kyc_verification_caption') }}</span>
        </div>

        <div class="flex flex-col gap-5 md:gap-8 items-center self-stretch">
            <div
                class="px-3 py-6 flex gap-5 items-center self-stretch select-none md:min-h-[106px] cursor-pointer rounded-xl bg-gray-50 hover:bg-gray-100"
                @click="openDialog('view_kyc')"
            >
                <Skeleton
                    v-if="isLoading"
                    width="4rem" height="3rem"
                ></Skeleton>
                <img
                    v-else
                    :src="kycVerification ? kycVerification.original_url : '/img/member/kyc_sample_illustration.png'"
                    class="w-14"
                    alt="kyc_verification"
                />
                <div class="truncate text-gray-950 font-medium w-full">
                    {{ kycVerification ? kycVerification.file_name : $t('public.image') + '.jpg' }}
                </div>
            </div>
        </div>

        <div class="flex justify-end w-full">
            <Button
                type="button"
                variant="primary-flat"
                :disabled="!!$page.props.auth.user.kyc_approved_at"
                @click="openDialog('submit_kyc')"
            >
                {{ $t('public.submit_again') }}
            </Button>
        </div>
    </div>

    <Dialog
        v-model:visible="visible"
        modal
        :header="$t(`public.${dialogType}`)"
        class="dialog-xs"
        :class="{
            'sm:dialog-lg': dialogType === 'view_kyc',
            'sm:dialog-md': dialogType === 'submit_kyc',
        }"
    >
        <template v-if="dialogType === 'view_kyc'">
            <img
                :src="kycVerification ? kycVerification.original_url : '/img/member/kyc_sample_illustration.png'"
                class="w-full"
                alt="kyc_verification"
            />
        </template>

        <template v-if="dialogType === 'submit_kyc'">
            <div class="flex flex-col items-center gap-5 self-stretch">
                <div class="flex flex-col gap-1 items-start self-stretch">
                    <span class="font-bold text-gray-950">{{ $t('public.kyc_guideline') }}</span>
                    <span class="text-xs text-gray-500">{{ $t('public.kyc_guideline_desc') }}</span>
                </div>

                <div class="flex items-center gap-5 self-stretch">
                    <div class="flex justify-center bg-primary-600 w-full pt-2.5">
                        <KycFemale />
                    </div>
                    <div class="flex justify-center bg-logo w-full pt-2.5">
                        <KycMale />
                    </div>
                </div>

                <div class="flex flex-col items-center self-stretch">
                    <div class="text-gray-950 font-semibold text-sm self-stretch">
                        {{ $t('public.upload_here') }}
                    </div>
                    <div class="flex flex-col gap-3 items-start self-stretch">
                        <span class="text-xs text-gray-500">{{ $t('public.kyc_caption') }}</span>
                        <div class="flex flex-col gap-3">
                            <input
                                ref="kycVerificationInput"
                                id="kyc_verification"
                                type="file"
                                class="hidden"
                                accept="image/*"
                                @change="handleKycVerification"
                            />
                            <Button
                                type="button"
                                variant="primary-tonal"
                                @click="$refs.kycVerificationInput.click()"
                            >
                                {{ $t('public.browse') }}
                            </Button>
                            <InputError :message="form.errors.kyc_verification" />
                        </div>
                        <div
                            v-if="selectedKycVerification"
                            class="relative w-full py-3 pl-4 flex justify-between rounded-xl bg-gray-50"
                        >
                            <div class="inline-flex items-center gap-3">
                                <img :src="selectedKycVerification" alt="Selected Image" class="max-w-full h-9 object-contain rounded" />
                                <div class="text-sm text-gray-950">
                                    {{ selectedKycVerificationName }}
                                </div>
                            </div>
                            <Button
                                type="button"
                                variant="gray-text"
                                @click="removeKycVerification"
                                pill
                                iconOnly
                            >
                                <IconX class="text-gray-700 w-5 h-5" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex justify-end items-center pt-10 md:pt-7 gap-4 self-stretch">
                <Button
                    variant="primary-flat"
                    :disabled="form.processing"
                    @click.prevent="submitForm"
                >
                    {{ $t('public.submit') }}
                </Button>
            </div>
        </template>
    </Dialog>
</template>
