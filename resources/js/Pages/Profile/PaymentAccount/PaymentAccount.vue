<script setup>
import AddPaymentAccount from "@/Pages/Profile/PaymentAccount/AddPaymentAccount.vue";
import {ref, watchEffect} from "vue";
import PaymentAccountAction from "@/Pages/Profile/PaymentAccount/PaymentAccountAction.vue";
import Tag from "primevue/tag";
import Skeleton from "primevue/skeleton";
import {usePage} from "@inertiajs/vue3";
import Empty from "@/Components/Empty.vue";
import {NoAssetMaster} from "@/Components/Icons/solid.jsx";

const props = defineProps({
    paymentAccountsCount: Number,
})

const paymentAccounts = ref([]);
const isLoading = ref(false);

const getResults = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get('/profile/getPaymentAccounts');
        paymentAccounts.value = response.data.paymentAccounts;
    } catch (error) {
        console.error('Error changing locale:', error);
    } finally {
        isLoading.value = false;
    }
};

getResults();

const tooltipText = ref('copy')
const copiedText = ref('')

function copyToClipboard(text) {
    const textToCopy = text;
    copiedText.value = text;

    const textArea = document.createElement('textarea');
    document.body.appendChild(textArea);

    textArea.value = textToCopy;
    textArea.select();

    try {
        const successful = document.execCommand('copy');

        tooltipText.value = 'copied';
        setTimeout(() => {
            tooltipText.value = 'copy';
        }, 1500);
    } catch (err) {
        console.error('Copy to clipboard failed:', err);
    }

    document.body.removeChild(textArea);
}

watchEffect(() => {
    if (usePage().props.toast !== null) {
        getResults();
    }
});
</script>

<template>
    <div class="p-4 md:py-6 md:px-8 flex flex-col gap-5 md:gap-8 items-center self-stretch rounded-2xl shadow-toast w-full">
        <div class="flex flex-col md:flex-row gap-3 md:justify-between w-full">
            <div class="flex flex-col gap-1 items-start justify-center w-full">
                <span class="text-gray-950 font-bold">{{ $t('public.payment_account') }}</span>
                <span class="text-gray-500 text-xs">{{ $t('public.payment_account_caption') }}</span>
            </div>
            <div class="w-full flex justify-end">
                <AddPaymentAccount />
            </div>
        </div>

        <div v-if="paymentAccountsCount === 0">
            <Empty
                :title="$t('public.no_payment_account')"
                :message="$t('public.no_payment_account_desc')"
            />
        </div>
        <div
            v-else
            class="w-full"
        >
            <div
                v-if="isLoading"
                class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-3 md:gap-5 w-full"
            >
                <div
                    v-for="(paymentAccount, index) in paymentAccountsCount"
                    class="flex flex-col gap-3 border border-gray-300 rounded-md p-3 md:p-5 w-full"
                >
                    <div class="flex gap-3 items-start self-stretch">
                        <div class="min-w-12 h-12 rounded-md bg-primary-300 flex items-center justify-center text-white font-bold">
                            PA
                        </div>
                        <div class="flex flex-col w-full">
                            <Skeleton width="5rem" height="1rem" borderRadius="2rem"></Skeleton>
                            <Skeleton width="9rem" height="1.5rem" borderRadius="2rem" class="mt-1"></Skeleton>
                        </div>
                    </div>
                    <div class="border-t border-gray-300 pt-2">
                        <Skeleton width="15rem" height="0.8rem" borderRadius="2rem" class="my-1.5"></Skeleton>
                    </div>
                </div>
            </div>

            <div v-else class="w-full">
                <div
                    class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-3 md:gap-5 w-full"
                >
                    <div
                        v-for="paymentAccount in paymentAccounts"
                        class="flex flex-col gap-3 border border-gray-300 rounded-md p-3 md:p-5 w-full"
                    >
                        <div class="flex gap-3 items-start self-stretch">
                            <div class="min-w-12 h-12 rounded-md bg-primary-300 flex items-center justify-center text-white font-bold">
                                <div v-if="paymentAccount.payment_platform === 'crypto'">
                                    {{ paymentAccount.payment_account_type.slice(0, 3).toUpperCase() }}
                                </div>
                                <div v-else>
                                    {{ paymentAccount.bank_code.slice(0, 2).toUpperCase() }}
                                </div>
                            </div>
                            <div class="flex flex-col w-full">
                                <span class="text-xs text-gray-600 uppercase">{{ $t(`public.${paymentAccount.payment_platform}`) }}</span>
                                <span class="font-semibold text-gray-950">{{ paymentAccount.payment_account_name }}</span>
                            </div>
                            <PaymentAccountAction
                                :paymentAccount="paymentAccount"
                            />
                        </div>
                        <div class="border-t border-gray-300 pt-2 relative">
                            <Tag
                                v-if="tooltipText === 'copied' && copiedText === paymentAccount.account_no"
                                class="absolute w-fit -top-4 left-1 !bg-gray-950 !text-white"
                                :value="$t(`public.${tooltipText}`)"
                            ></Tag>
                            <span @click="copyToClipboard(paymentAccount.account_no)" class="text-sm text-gray-600 break-words select-none cursor-pointer">{{ paymentAccount.account_no }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
