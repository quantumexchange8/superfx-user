<script setup>
import Button from "@/Components/Button.vue";
import {ref, watch} from "vue";
import InputLabel from "@/Components/InputLabel.vue";
import {
    IconLoader2,
} from "@tabler/icons-vue";
import InputNumber from "primevue/inputnumber";
import InputError from "@/Components/InputError.vue";
import Dialog from "primevue/dialog";
import {transactionFormat} from "@/Composables/index.js";
import toast from "@/Composables/toast.js";
import { trans } from "laravel-vue-i18n";
import SelectChipGroup from "@/Components/SelectChipGroup.vue";
import Skeleton from "primevue/skeleton";
import Dropdown from "primevue/dropdown";
import Loader from "@/Components/Loader.vue";
import {usePage} from "@inertiajs/vue3";
import InputText from "primevue/inputtext";

const props = defineProps({
    account: Object,
    methods: Array,
});

const visible = ref(false);
const isLoading = ref(false);

const form = ref({
    meta_login: props.account.meta_login,
    payment_method: '',
    payment_gateway: '',
    amount: 0,
    txn_fee: '',
    min_amount: '',
    max_amount: '',
    chinese_name: '',
});

const selectedMethod = ref();

const paymentGateways = ref([]);
const selectedPaymentGateway = ref();
const loadingPaymentGateways = ref(false);

const getPaymentGateways = async () => {
    loadingPaymentGateways.value = true;
    try {
        const response = await axios.get(`/getPaymentGateways?slug=${selectedMethod.value.slug}`);
        paymentGateways.value = response.data.payment_gateways;

        paymentGateways.value = response.data.payment_gateways.filter(
            gateway => gateway.can_deposit
        );

        if (paymentGateways.value.length === 1) {
            selectedPaymentGateway.value = paymentGateways.value[0].id;
        }
    } catch (error) {
        console.error('Error get payment gateways:', error);
    } finally {
        loadingPaymentGateways.value = false;
    }
};

// Get Payment Gateway
watch(selectedMethod, () => {
    selectedPaymentGateway.value = null;
    steps.value = [];
    getPaymentGateways();
})

const selectedPaymentGatewaySlug = ref('');

// Get Payment Gateway Fee
watch(selectedPaymentGateway, (newVal) => {
    if (newVal) {
        getFee();
        const gateway = paymentGateways.value.find(pg => pg.id === newVal);

        if (selectedMethod.value.type === 'bank') {
            selectedPaymentGatewaySlug.value = gateway ? gateway.methods[0].slug : null;
        }
    }
})

const txnFee = ref(null);
const minAmount = ref(null);
const maxAmount = ref(null);
const exchangeRate = ref(null);
const loadingFee = ref(false);

const steps = ref([]);

const getFee = async () => {
    loadingFee.value = true;
    try {
        const response = await axios.get(`/getPaymentMethodRule?payment_method_id=${selectedMethod.value.id}&payment_gateway_id=${selectedPaymentGateway.value}&type=deposit`);

        txnFee.value = response.data.fee;
        minAmount.value = response.data.minAmount;
        maxAmount.value = response.data.maxAmount;
        exchangeRate.value = response.data.conversionRate;

        const stepsArray = [];
        if (selectedMethod.value.type !== 'crypto') {
            stepsArray.push(trans('public.deposit_info_message_1', { conversionRate: exchangeRate.value }));
        }
        stepsArray.push(trans('public.deposit_info_message_2', { minAmount: props.account.minimum_deposit > 0 ? props.account.minimum_deposit : (minAmount.value < 50 ? 50 : Math.round(minAmount.value)) }));
        stepsArray.push(trans('public.deposit_info_message_3', { maxAmount: Math.floor(maxAmount.value) }));

        if (Number(txnFee.value) > 0) {
            stepsArray.push(trans('public.deposit_info_message_4', { txnFee: formatAmount(txnFee.value) }));
        } else {
            stepsArray.push(trans('public.deposit_no_fee'));
        }

        steps.value = stepsArray;
        calculateFinalAmount();

    } catch (error) {
        console.error('Error get payment fee:', error);
    } finally {
        loadingFee.value = false;
    }
};

const finalAmount = ref(0);

watch(
    () => form.value.amount,
    () => {
        calculateFinalAmount()
    }
);

const calculateFinalAmount = () => {
    finalAmount.value = Math.abs(Number(form.value.amount || 0) - Number(txnFee.value || 0))
}

const {formatAmount} = transactionFormat();
const errors = ref({});

const submitForm = async () => {
    isLoading.value = true;
    try {
        form.value.payment_method = selectedMethod.value;
        form.value.payment_gateway = selectedPaymentGateway.value;
        form.value.txn_fee = txnFee.value;
        form.value.min_amount = minAmount.value;
        form.value.max_amount = maxAmount.value;

        // Send POST request with form data
        const response = await axios.post(route('account.deposit_to_account'), form.value);

        if (response.data.success) {
            window.location.assign(response.data.payment_url);
        }

        toast.add({
            title: response.data.toast_title,
            message: response.data.toast_message,
            type: response.data.toast_type,
        });

        closeDialog();

        form.value = {
            meta_login: props.account.meta_login,
            payment_platform: '',
            cryptoType: '',
            amount: 0,
            payment_gateway: ''
        };
    } catch (error) {
        if (error.response && error.response.status === 422) {
            // Validation errors
            errors.value = error.response.data.errors;
        }

        if (error.response && error.response.status === 400) {
            toast.add({
                title: error.response.data.toast_title,
                message: error.response.data.toast_message,
                type: error.response.data.toast_type,
            });

            closeDialog();
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
        <form>
            <div class="flex flex-col items-center gap-5 self-stretch">
                <div class="flex flex-col justify-center items-center py-4 px-8 gap-2 self-stretch bg-gray-200">
                    <span class="text-gray-500 text-center text-xs font-medium">#{{ account.meta_login }} - {{ $t('public.current_account_balance') }}</span>
                    <span class="text-gray-950 text-center text-xl font-semibold">$ {{ account.balance ?? 0 }}</span>
                </div>
                <div class="flex flex-col items-start gap-1 self-stretch">
                    <InputLabel
                        for="accountType"
                        :value="$t('public.select_payment')"
                    />
                    <Dropdown
                        v-model="selectedMethod"
                        :options="methods"
                        optionLabel="name"
                        :placeholder="$t('public.select_payment')"
                        class="w-full"
                        scroll-height="236px"
                        :invalid="!!errors.payment_method"
                    >
                        <template #value="slotProps">
                            <div
                                v-if="slotProps.value"
                                class="flex items-center gap-2"
                            >
                                <img
                                    :src="`/img/payment/${slotProps.value.slug}.png`"
                                    alt="Logo"
                                    class="w-5 h-5 object-cover"
                                />
                                {{ slotProps.value.name }}
                            </div>
                            <span v-else>
                                {{ slotProps.placeholder }}
                            </span>
                        </template>
                        <template #option="slotProps">
                            <div
                                class="flex items-center gap-2"
                            >
                                <img
                                    :src="`/img/payment/${slotProps.option.slug}.png`"
                                    alt="Logo"
                                    class="w-5 h-5 object-cover"
                                />
                                {{ slotProps.option.name }}
                            </div>
                        </template>
                    </Dropdown>
                    <InputError v-if="errors.payment_method" :message="errors.payment_method[0]" />
                </div>

                <div
                    v-if="selectedMethod"
                    class="flex flex-col items-start gap-1 self-stretch"
                >
                    <InputLabel
                        for="accountType"
                        :value="$t('public.platform_placeholder')"
                    />
                    <Skeleton
                        v-if="loadingPaymentGateways"
                        width="10rem"
                        height="2.75rem"
                    />
                    <SelectChipGroup
                        v-else
                        v-model="selectedPaymentGateway"
                        :items="paymentGateways.map(pg => ({
                            ...pg,
                            disabled: pg.status === 'inactive',
                        }))"
                        value-key="id"
                    >
                        <template #option="{ item }">
                            {{ item.name }}
                        </template>
                    </SelectChipGroup>
                    <InputError v-if="errors.payment_platform" :message="errors.payment_platform[0]" />
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
                    <span class="self-stretch text-gray-500 text-xs">{{ $t('public.minimum_amount') }}: ${{ formatAmount( account.group === 'PRIME' ? account.minimum_deposit : (minAmount < 50 ? 50 : Math.round(minAmount)),0) }}</span>
                    <InputError v-if="errors.amount" :message="errors.amount[0]" />
                </div>

                <div
                    v-if="selectedPaymentGatewaySlug === 'hypay' && usePage().props.auth.user.chinese_name === null"
                    class="flex flex-col items-start gap-1 self-stretch"
                >
                    <InputLabel for="name" :value="$t('public.chinese_name')" />
                    <div class="relative w-full">
                        <InputText
                            id="chinese_name"
                            type="text"
                            class="block w-full"
                            v-model="form.chinese_name"
                            :placeholder="$t('public.chinese_name')"
                            :invalid="!!errors.chinese_name"
                        />
                    </div>
                    <span class="self-stretch text-gray-500 text-xs">{{ $t('public.require_chinese_name') }}</span>
                    <InputError v-if="errors.chinese_name" :message="errors.chinese_name[0]" />
                </div>

                <div class="flex flex-col items-center self-stretch">
                    <div class="flex justify-center items-start gap-3 self-stretch">
                        <div class="flex flex-col items-start gap-1 flex-grow">
                            <span v-if="steps.length" class="self-stretch text-gray-950 text-sm font-semibold">{{ $t('public.deposit_info_header') }}</span>
                            <div v-if="loadingFee" class="flex flex-col items-center justify-center w-full py-5">
                                <Loader />
                            </div>
                            <div v-else class="flex flex-col items-start gap-1 flex-grow">
                                <div v-for="(step, index) in steps" :key="index" class="self-stretch text-gray-500 text-xs">
                                    {{ index + 1 }}. {{ step }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div
                    v-if="txnFee > 0 && selectedPaymentGateway"
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
                            ${{ formatAmount(txnFee ?? 0) }}
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
                </div>
            </div>
            <div
                v-if="selectedMethod && selectedMethod.status === 'active'"
                class="flex justify-end items-center pt-5 gap-4 self-stretch sm:pt-7"
            >
                <Button
                    type="submit"
                    variant="primary-flat"
                    @click.prevent="submitForm('deposit')"
                    :disabled="isLoading"
                >
                    <IconLoader2 v-if="isLoading" class="animate-spin w-4" />
                    {{ $t('public.deposit_now') }}
                </Button>
            </div>
        </form>
    </Dialog>
</template>
