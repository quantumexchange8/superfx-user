<script setup>
import Button from "@/Components/Button.vue";
import {ref, computed} from "vue";
import InputLabel from "@/Components/InputLabel.vue";
import {
    IconLoader2,
    IconCircleCheckFilled,
    IconInfoOctagonFilled
} from "@tabler/icons-vue";
import InputNumber from "primevue/inputnumber";
import InputError from "@/Components/InputError.vue";
import Dialog from "primevue/dialog";
import {transactionFormat} from "@/Composables/index.js";
import toast from "@/Composables/toast.js";
import { trans } from "laravel-vue-i18n";


const props = defineProps({
    account: Object,
    conversionRate: Number,
});

const maxAmount = ref();
const visible = ref(false);
const isLoading = ref(false);
const cryptoOptions = ref([]);
const selectedCryptoOption = ref();

const form = ref({
    meta_login: props.account.meta_login,
    payment_platform: '',
    cryptoType: '',
    amount: 0,
    // fee: 0,
});

const selectedPlatform = ref('');
const depositOptions = ref(['bank', 'crypto']);
const selectPlatform = (type) => {
    selectedPlatform.value = type;
    maxAmount.value = type === 'bank' ? formatAmount(4000000000/props.conversionRate) : formatAmount(1000000);
}

const getFee = async () => {
    try {
        const response = await axios.get('/getPaymentAccounts?type=deposit');
        cryptoOptions.value = response.data.crypto_options;
        selectedCryptoOption.value = cryptoOptions.value[0];
    } catch (error) {
        console.error('Error changing locale:', error);
    }
};

getFee();

const selectCryptoOption = (type) => {
    const selectedOption = cryptoOptions.value.find(option => option.type === type);
    if (selectedOption) {
        selectedCryptoOption.value = selectedOption;
    }
};

const {formatAmount} = transactionFormat();
const errors = ref({});

const steps = computed(() => {
    const stepsArray = [];

    if (selectedPlatform.value === 'bank') {
        stepsArray.push(trans('public.deposit_info_message_1', { conversionRate: formatAmount(props.conversionRate) }));
        stepsArray.push(trans('public.deposit_info_message_2'));
        stepsArray.push(trans('public.deposit_info_message_3', { maxAmount: maxAmount.value }));
    }
    else {
        stepsArray.push(trans('public.crypto_deposit_info_message_1'));
        stepsArray.push(trans('public.crypto_deposit_info_message_2', { maxAmount: maxAmount.value }));
    }

     return stepsArray;
});

// const finalAmount = computed(() => {
//     return form.value.amount + selectedCryptoOption.value.fee;
// });

const submitForm = async () => {
    isLoading.value = true;
    try {
        form.value.payment_platform = selectedPlatform.value;

        if (form.value.payment_platform === 'crypto') {
            form.value.cryptoType = selectedCryptoOption.value.type;
            // form.value.fee = selectedCryptoOption.value.fee;
        } else {
            form.value.cryptoType = null;
        }

        // Send POST request with form data
        const response = await axios.post(route('account.deposit_to_account'), form.value);

        if (response.data.success) {
            closeDialog();

            toast.add({
                title: response.data.toast_title,
                message: response.data.toast_message,
                type: response.data.toast_type,
            });

            form.value = {
                meta_login: props.account.meta_login,
                payment_platform: '',
                cryptoType: '',
                amount: 0,
                // fee: 0,
            };

            window.location.href(response.data.payment_url);
        } else {
            toast.add({
                title: response.data.toast_title,
                message: response.data.toast_message,
                type: response.data.toast_type,
            });
        }
    } catch (error) {
        if (error.response && error.response.status === 422) {
            // Validation errors
            errors.value = error.response.data.errors;
        }
    } finally {
        isLoading.value = false;
    }
};

const closeDialog = () => {
    visible.value = false;
}
</script>

<template>
    <Button
        type="button"
        variant="gray-outlined"
        size="sm"
        class="w-full"
        @click="visible = true"
        :disabled="account.status === 'pending'"
    >
        {{ $t('public.deposit') }}
    </Button>

    <Dialog
        v-model:visible="visible"
        :header="$t('public.deposit')"
        modal
        class="dialog-xs sm:dialog-sm"
    >
        <div class="flex flex-col items-center gap-5 self-stretch">
            <div class="flex flex-col justify-center items-center py-4 px-8 gap-2 self-stretch bg-gray-200">
                <span class="text-gray-500 text-center text-xs font-medium">#{{ account.meta_login }} - {{ $t('public.current_account_balance') }}</span>
                <span class="text-gray-950 text-center text-xl font-semibold">$ {{ account.balance ?? 0 }}</span>
            </div>
            <div class="flex flex-col items-start gap-2 self-stretch">
                <InputLabel for="accountType" :value="$t('public.platform_placeholder')" />
                <div class="grid grid-cols-2 items-start gap-3 self-stretch">
                    <div
                        v-for="platform in depositOptions"
                        :key="platform"
                        @click="selectPlatform(platform)"
                        class="group col-span-1 items-start py-3 px-4 gap-1 self-stretch rounded-lg border shadow-input transition-colors duration-300 select-none cursor-pointer"
                        :class="{
                            'bg-primary-50 border-primary-500': selectedPlatform === platform,
                            'bg-white border-gray-300 hover:bg-primary-50 hover:border-primary-500': selectedPlatform !== platform,
                        }"
                    >
                        <div class="flex items-center gap-3 self-stretch">
                            <span
                                class="flex-grow text-sm font-semibold transition-colors duration-300 group-hover:text-primary-700"
                                :class="{
                                    'text-primary-700': selectedPlatform === platform,
                                    'text-gray-950': selectedPlatform !== platform
                                }"
                            >
                                {{ $t(`public.${platform}`) }}
                            </span>
                            <IconCircleCheckFilled v-if="selectedPlatform === platform" size="20" stroke-width="1.25" color="#06D001" />
                        </div>
                    </div>
                </div>
                <InputError v-if="errors.payment_platform" :message="errors.payment_platform[0]" />
            </div>

            <!-- Crypto Options-->
            <div
                v-if="selectedPlatform ==='crypto'"
                class="flex flex-col items-start gap-2 self-stretch"
            >
                <InputLabel for="accountType" :value="$t('public.platform_placeholder')" />
                <div class="grid grid-cols-2 items-start gap-3 self-stretch">
                    <div
                        v-for="network in cryptoOptions"
                        :key="network.type"
                        @click="selectCryptoOption(network.type)"
                        class="group col-span-1 items-start py-3 px-4 gap-1 self-stretch rounded-lg border shadow-input transition-colors duration-300 select-none cursor-pointer"
                        :class="{
                            'bg-primary-50 border-primary-500': selectedCryptoOption.type === network.type,
                            'bg-white border-gray-300 hover:bg-primary-50 hover:border-primary-500': selectedCryptoOption.type !== network.type,
                        }"
                    >
                        <div class="flex items-center gap-3 self-stretch">
                            <span
                                class="flex-grow text-sm font-semibold transition-colors duration-300 group-hover:text-primary-700"
                                :class="{
                                    'text-primary-700': selectedCryptoOption.type === network.type,
                                    'text-gray-950': selectedCryptoOption.type !== network.type
                                }"
                            >
                                {{ network.type }}
                            </span>
                            <IconCircleCheckFilled v-if="selectedCryptoOption.type === network.type" size="20" stroke-width="1.25" color="#06D001" />
                        </div>
                    </div>
                </div>
                <InputError v-if="errors.cryptoType" :message="errors.cryptoType[0]" />
            </div>

            <div class="flex flex-col items-start gap-1 self-stretch">
                <InputLabel for="amount" :value="$t('public.amount')" />
                <div class="relative w-full">
                    <InputNumber
                        v-model="form.amount"
                        inputId="currency-us"
                        prefix="$ "
                        class="w-full"
                        inputClass="py-3 px-4"
                        :min="0"
                        :step="100"
                        :minFractionDigits="2"
                        fluid
                        autofocus
                        :invalid="!!errors.amount"
                    />
                </div>
                <span class="self-stretch text-gray-500 text-xs">{{ $t('public.minimum_amount') }}: ${{ formatAmount( account.group === 'PRIME' ? account.minimum_deposit : 50,0) }}</span>
                <InputError v-if="errors.amount" :message="errors.amount[0]" />
            </div>
            <div class="flex flex-col items-center self-stretch">
                <div v-if="selectedPlatform" class="flex justify-center items-start gap-3 self-stretch">
                    <div class="flex flex-col items-start gap-1 flex-grow">
                        <span class="self-stretch text-gray-950 text-sm font-semibold">{{ $t('public.deposit_info_header') }}</span>
                        <span v-for="(step, index) in steps" :key="index" class="self-stretch text-gray-500 text-xs">
                            {{ index + 1 }}. {{ step }}
                        </span>
                    </div>
                </div>
            </div>
            <!-- <div
                v-if="selectedPlatform ==='crypto'"
                class="flex flex-col items-end justify-end self-stretch pt-5 border-t border-gray-200"
            >
                <div class="flex justify-between items-start gap-1 self-stretch">
                    <span class="text-xs text-gray-500">
                        {{ $t('public.deposit_amount') }} :
                    </span>
                    <span class="col-span-1 text-right text-gray-500 text-sm">
                        ${{ formatAmount(form.amount) }}
                    </span>
                </div>
                <div class="flex justify-between items-start gap-1 self-stretch">
                    <span class="text-xs text-gray-500">
                        {{ $t('public.deposit_fee') }} :
                    </span>
                    <span class="col-span-1 text-right text-gray-500 text-sm">
                        ${{ formatAmount(selectedCryptoOption.fee ?? 0) }}
                    </span>
                </div>
                <div class="flex justify-between items-start gap-1 self-stretch">
                    <span class="text-sm text-gray-950 font-semibold">
                        {{ $t('public.final_amount_to_pay') }} :
                    </span>
                    <span class="col-span-1 text-right text-gray-950 text-sm font-semibold">
                        ${{ formatAmount(finalAmount) }}
                    </span>
                </div>
            </div> -->
        </div>
        <div class="flex justify-end items-center pt-5 gap-4 self-stretch sm:pt-7">
            <Button
                type="button"
                variant="primary-flat"
                @click.prevent="submitForm('deposit')"
                :disabled="isLoading"
            >
                <IconLoader2 v-if="isLoading" class="animate-spin w-4" />
                {{ $t('public.deposit_now') }}
            </Button>
        </div>
    </Dialog>
</template>
