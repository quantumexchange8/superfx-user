<script setup>
import {ref, watchEffect} from "vue";
import Button from "@/Components/Button.vue"
import Dialog from "primevue/dialog";
import {
    IconX,
    IconPhotoPlus,
    IconUpload,
    IconQuestionMark
} from "@tabler/icons-vue"
import InputError from '@/Components/InputError.vue';
import {useForm, usePage} from "@inertiajs/vue3";
import Skeleton from 'primevue/skeleton';
import FileUpload from "primevue/fileupload";
import { usePrimeVue } from 'primevue/config';
import PrimeButton from "primevue/button";
import Image from "primevue/image";
import Tag from 'primevue/tag';

const visible = ref(false);
const dialogType = ref('');
const kycVerification = ref();
const kyc_status = ref('');
const files = ref();
const isLoading = ref(false);

const getKycVerification = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get('/profile/getKycVerification');
        kycVerification.value = response.data.kycVerification;
        files.value = response.data.kycVerification;
        kyc_status.value = response.data.kyc_status;
    } catch (error) {
        console.error('Error getting kyc:', error);
    } finally {
        isLoading.value = false;
    }
};

getKycVerification();

const selectedKycVerification = ref(null);
const openDialog = (type, verification = null) => {
    dialogType.value = type;
    visible.value = true;

    if (type === 'view_kyc') {
        selectedKycVerification.value = verification;
    }
}

const form = useForm({
    kyc_verification: '',
});

const $primevue = usePrimeVue();

const onRemoveTemplatingFile = (removeFileCallback, index) => {
    removeFileCallback(index);
};

const onSelectedFiles = (event) => {
    files.value = event.files;
};

const formatSize = (bytes) => {
    const k = 1024;
    const dm = 3;
    const sizes = $primevue.config.locale.fileSizeTypes;

    if (bytes === 0) {
        return `0 ${sizes[0]}`;
    }

    const i = Math.floor(Math.log(bytes) / Math.log(k));
    const formattedSize = parseFloat((bytes / Math.pow(k, i)).toFixed(dm));

    return `${formattedSize} ${sizes[i]}`;
};

const submitForm = () => {
    form.kyc_verification = files.value;
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

const getSeverity = (status) => {
    switch (status) {
        case 'pending':
            return 'warning';

        case 'rejected':
            return 'danger';

        case 'approved':
            return 'primary';
    }
}
</script>

<template>
    <div class="p-4 md:py-6 md:px-8 flex flex-col gap-5 md:gap-2 md:justify-between items-end self-stretch rounded-2xl shadow-toast w-full">
        <div class="flex flex-col gap-1 items-start justify-center w-full">
            <span class="flex flex-row gap-2 text-gray-950 font-bold items-center">{{ $t('public.kyc_verification') }}
                <Tag
                    v-if="kyc_status !== 'unverified'"
                    class="right-[120px] -top-1 md:-top-7 md:right-20 max-h-6"
                    :severity="getSeverity(kyc_status)"
                    :value="$t(`public.${kyc_status}`)"
                />
                <IconQuestionMark v-else size="16" stroke-width="2" class="bg-gray-500 text-white rounded-full"/>
            </span>
            <span class="text-gray-500 text-xs">{{ $t('public.kyc_verification_caption') }}</span>
        </div>

        <div class="flex flex-col overflow-y-auto md:max-h-[160px] gap-2 items-center self-stretch">
            <div
                v-for="(verification, index) in kycVerification"
                :key="verification.id"
                class="px-3 py-4 flex gap-5 items-center self-stretch select-none md:min-h-[53px] cursor-pointer rounded-xl bg-gray-50 hover:bg-gray-100"
                @click="openDialog('view_kyc', verification)"
                >
                <Skeleton
                    v-if="isLoading"
                    width="4rem" height="3rem"
                ></Skeleton>
                <img
                    v-else
                    :src="verification.original_url ? verification.original_url : '/img/member/kyc_sample_illustration.png'"
                    class="w-14 max-h-10"
                    alt="kyc_verification"
                />
                <div class="truncate text-gray-950 font-medium w-full">
                    {{ verification.file_name || $t('public.image') + '.jpg' }}
                </div>
            </div>
        </div>

        <div class="flex justify-end w-full">
            <Button
                type="button"
                variant="primary-flat"
                @click="openDialog('submit_kyc')"
                :disabled="kyc_status === 'approved'"
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
                :src="selectedKycVerification?.original_url || '/img/member/kyc_sample_illustration.png'"
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

                <div
                    class="flex flex-col gap-3 md:gap-5 w-full"
                >
                    <FileUpload
                        name="demo[]"
                        multiple
                        accept="image/*"
                        @select="onSelectedFiles"
                    >
                        <template #header="{ chooseCallback, clearCallback, files }">
                            <div class="flex flex-wrap justify-between items-center flex-1 gap-4 m-1">
                                <div class="flex gap-2">
                                    <PrimeButton
                                        type="button"
                                        severity="secondary"
                                        size="small"
                                        @click="chooseCallback()"
                                        rounded
                                        outlined
                                        class="!p-2"
                                    >
                                        <IconPhotoPlus size="16" stroke-width="1.5" />
                                    </PrimeButton>
                                    <PrimeButton
                                        type="button"
                                        severity="danger"
                                        size="small"
                                        @click="clearCallback()"
                                        rounded
                                        outlined
                                        class="!p-2"
                                        :disabled="!files || files.length === 0"
                                    >
                                        <IconX size="16" stroke-width="1.5" />
                                    </PrimeButton>
                                </div>
                            </div>
                        </template>
                        <template #content="{ files, removeFileCallback }">
                            <div class="flex flex-col gap-3">
                                <div v-if="files.length > 0">
                                    <div class="flex overflow-x-auto items-center gap-4">
                                        <div
                                            v-for="(file, index) of files" :key="file.name + file.type + file.size"
                                            class="p-5 rounded-border w-full max-w-64 flex flex-col border border-surface items-center gap-4 relative"
                                        >
                                            <div class="absolute top-2 right-2">
                                                <PrimeButton
                                                    type="button"
                                                    severity="danger"
                                                    size="small"
                                                    @click="onRemoveTemplatingFile(removeFileCallback, index)"
                                                    rounded
                                                    text
                                                    class="!p-2"
                                                    :disabled="!files || files.length === 0"
                                                >
                                                    <IconX size="16" stroke-width="1.5" />
                                                </PrimeButton>
                                            </div>
                                            <div class="max-h-10 mt-5">
                                                <Image role="presentation" :alt="file.name" :src="file.objectURL" preview imageClass="w-48 object-contain h-10" />
                                            </div>
                                            <div class="flex flex-col gap-1 items-center self-stretch w-52">
                                                <span class="font-semibold text-center text-xs truncate w-full max-w-52">{{ file.name }}</span>
                                                <div class="text-xxs">{{ formatSize(file.size) }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <template #empty>
                            <div class="flex items-center justify-center flex-col gap-3 mt-3">
                                <div class="flex items-center justify-center p-3 text-surface-400 dark:text-surface-600 rounded-full border border-surface-400 dark:border-surface-600">
                                    <IconUpload size="24" stroke-width="1.5" />
                                </div>
                                <p class="text-sm">{{ $t('public.drag_and_drop_file') }}</p>
                                <InputError :message="form.errors.kyc_verification" />
                            </div>
                        </template>
                    </FileUpload>
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
