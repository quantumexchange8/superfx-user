<script setup>
import InputLabel from "@/Components/InputLabel.vue";
import InputNumber from 'primevue/inputnumber';
import {useForm} from "@inertiajs/vue3";
import Button from "@/Components/Button.vue"
import InputError from "@/Components/InputError.vue";
import Dropdown from "primevue/dropdown";
import {ref, watch, computed} from "vue";
import {transactionFormat} from "@/Composables/index.js";
import Skeleton from "primevue/skeleton";
import SelectChipGroup from "@/Components/SelectChipGroup.vue";
import {IconLoader2} from "@tabler/icons-vue";
import {trans} from "laravel-vue-i18n";
import Loader from "@/Components/Loader.vue";

const props = defineProps({
    account: Object,
})

const walletOptions = ref([]);
const loadPaymentAccounts = ref(false);
const selectedPaymentAccount = ref();
const {formatAmount} = transactionFormat()
const emit = defineEmits(['update:visible'])

const getOptions = async () => {
    try {
        const response = await axios.get('/getWithdrawalPaymentAccounts?type=withdrawal');
        walletOptions.value = response.data.payment_accounts;
        selectedPaymentAccount.value = walletOptions.value[0];
    } catch (error) {
        console.error('Error fetching wallets:', error);
    }
};

getOptions();

watch(selectedPaymentAccount, (newWallet) => {
    selectedPaymentAccount.value = newWallet;

    getPaymentGateways();
});

const paymentGateways = ref([]);
const selectedPaymentGateway = ref();
const loadingPaymentGateways = ref(false);

const getPaymentGateways = async () => {
    loadingPaymentGateways.value = true;
    try {
        const paymentAccType = selectedPaymentAccount.value.payment_platform === 'bank' ? 'bank' : selectedPaymentAccount.value.payment_account_type;

        const response = await axios.get(`/getPaymentGateways?slug=${paymentAccType}`);
        paymentGateways.value = response.data.payment_gateways;

        paymentGateways.value = response.data.payment_gateways.filter(
            gateway => gateway.can_withdraw
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

watch(selectedPaymentGateway, () => {
    getFee()
});

const txnFee = ref(null);
const minAmount = ref(null);
const maxAmount = ref(null);
const exchangeRate = ref(null);
const loadingFee = ref(false);
const steps = ref([]);

const getFee = async () => {
    loadingFee.value = true;
    try {
        const paymentAccType = selectedPaymentAccount.value.payment_platform === 'bank' ? 'bank' : selectedPaymentAccount.value.payment_account_type;

        const response = await axios.get(`/getWithdrawalCondition?payment_gateway_id=${selectedPaymentGateway.value}&type=${paymentAccType}`);

        txnFee.value = response.data.fee;
        minAmount.value = response.data.minAmount;
        maxAmount.value = response.data.maxAmount;
        exchangeRate.value = response.data.conversionRate;

        const stepsArray = [];
        if (selectedPaymentAccount.value.payment_platform !== 'crypto') {
            stepsArray.push(trans('public.withdrawal_info_message_1', { conversionRate: exchangeRate.value }));
        }
        stepsArray.push(trans('public.withdrawal_info_message_2', { minAmount: props.account.minimum_deposit > 0 ? props.account.minimum_deposit : (minAmount.value < 50 ? 50 : Math.round(minAmount.value)) }));
        stepsArray.push(trans('public.withdrawal_info_message_3', { maxAmount: Math.floor(maxAmount.value) }));

        if (Number(txnFee.value) > 0) {
            stepsArray.push(trans('public.withdrawal_info_message_4', { txnFee: formatAmount(txnFee.value) }));
        } else {
            stepsArray.push(trans('public.deposit_no_fee'));
        }

        steps.value = stepsArray;
    } catch (error) {
        console.error('Error get payment fee:', error);
    } finally {
        loadingFee.value = false;
    }
};

const form = useForm({
    account_id: props.account.id,
    amount: 0,
    fee: 0,
    payment_account_id: '',
    payment_gateway_id: '',
    min_amount: 50,
})

const finalAmount = computed(() => {
    const fee = props.account?.category === 'cent'
        ? Number(txnFee.value) * props.account.balance_multiplier
        : Number(txnFee.value);

    return form.amount - fee < 0 ? 0 : form.amount - fee;
});

const toggleFullAmount = () => {
    if (form.amount) {
        form.amount = 0;
    } else {
        form.amount = Number(props.account.balance);
    }
};

const submitForm = () => {
    form.payment_account_id = selectedPaymentAccount.value.id;
    form.payment_gateway_id = selectedPaymentGateway.value;
    form.fee = Number(txnFee.value ?? 0);
    form.min_amount = Number(minAmount.value < 50 ? 50 : minAmount.value);

    form.post(route('account.withdrawal_from_account'), {
        onSuccess: () => {
            closeDialog();
            form.reset();
        }
    });
}

const closeDialog = () => {
    emit('update:visible', false)
}
</script>

<template>
    <form>
        <div class="flex flex-col items-center gap-8 self-stretch md:gap-10">
            <div class="flex flex-col items-center gap-5 self-stretch">
                <div class="flex flex-col justify-center items-center py-4 px-8 gap-2 self-stretch bg-gray-200">
                    <span class="w-full text-gray-500 text-center text-xs font-medium">#{{ account.meta_login }} - {{ $t('public.current_account_balance') }}</span>
                    <span class="w-full text-gray-950 text-center text-xl font-semibold">$ {{ formatAmount(account.balance) }}</span>
                </div>

                <!-- input fields -->
                <div class="flex flex-col items-start gap-1 self-stretch">
                    <InputLabel for="receiving_wallet" :value="$t('public.receiving_wallet')" />
                    <Dropdown
                        v-model="selectedPaymentAccount"
                        :options="walletOptions"
                        :placeholder="$t('public.receiving_wallet_placeholder')"
                        class="w-full"
                        scroll-height="236px"
                        :invalid="!!form.errors.payment_account_id"
                        :loading="loadPaymentAccounts"
                    >
                        <template #value="slotProps">
                            <div v-if="slotProps.value" class="flex items-center">
                                <div>{{ slotProps.value.payment_account_name }}</div>
                            </div>
                            <span v-else>{{ slotProps.placeholder }}</span>
                        </template>
                        <template #option="slotProps">
                            <div class="flex items-center w-[262px] md:max-w-[236px]">
                                {{ slotProps.option.payment_account_name }}
                            </div>
                        </template>
                    </Dropdown>
                    <InputError :message="form.errors.payment_account_id" />
                    <span class="self-stretch text-gray-500 text-xs">{{ walletOptions.length ? selectedPaymentAccount.account_no : $t('public.loading_caption')}}</span>
                </div>

                <div
                    v-if="selectedPaymentAccount"
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
                    <InputError v-if="form.errors.payment_gateway_id" :message="form.errors.payment_gateway_id" />
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
                            :invalid="!!form.errors.amount"
                        />
                        <div
                            class="absolute right-4 top-1/2 transform -translate-y-1/2 cursor-pointer text-sm font-semibold"
                            :class="{
                                    'text-primary-500': !form.amount,
                                    'text-error-500': form.amount,
                                }"
                            @click="toggleFullAmount"
                        >
                            {{ form.amount ? $t('public.clear') : $t('public.full_amount') }}
                        </div>
                    </div>
                    <span class="self-stretch text-gray-500 text-xs">{{ $t('public.minimum_amount') }}: ${{ formatAmount(account.category === 'cent' ? (minAmount < 50 ? 50 : Math.round(minAmount)) * account.balance_multiplier : (minAmount < 50 ? 50 : Math.round(minAmount)), 0) }}</span>
                    <InputError :message="form.errors.amount" />
                </div>

                <div class="flex flex-col items-center self-stretch">
                    <div class="flex justify-center items-start gap-3 self-stretch">
                        <div class="flex flex-col items-start gap-1 flex-grow">
                            <span v-if="steps.length" class="self-stretch text-gray-950 text-sm font-semibold">{{ $t('public.withdrawal_info_header') }}</span>
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
                    v-if="walletOptions.length && selectedPaymentAccount.payment_platform === 'crypto'"
                    class="flex flex-col items-start self-stretch pt-5 border-t border-gray-20"
                >
                    <div class="flex justify-between items-start gap-1 self-stretch">
                        <span class="text-xs text-gray-500">
                            {{ $t('public.withdrawal_amount') }} :
                        </span>
                        <span class="col-span-1 text-right text-gray-500 text-sm">
                            ${{ formatAmount(form.amount) }}
                        </span>
                    </div>
                    <div class="flex justify-between items-start gap-1 self-stretch">
                        <span class="text-xs text-gray-500">
                            {{ $t('public.withdrawal_fee') }} :
                        </span>
                        <span class="col-span-1 text-right text-gray-500 text-sm">
                            ${{ formatAmount(account.category === 'cent' ? (txnFee ?? 0) * account.balance_multiplier : (txnFee ?? 0)) }}
                        </span>
                    </div>
                    <div class="flex justify-between items-start gap-1 self-stretch">
                        <span class="text-sm text-gray-950 font-semibold">
                            {{ $t('public.final_amount_to_receive') }} :
                        </span>
                        <span class="col-span-1 text-right text-gray-950 text-sm font-semibold">
                            ${{ formatAmount(finalAmount) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <div class="flex justify-end items-center pt-5 gap-4 self-stretch sm:pt-7">
            <Button
                type="button"
                variant="gray-tonal"
                class="w-full"
                @click.prevent="closeDialog()"
                :disabled="form.processing"
            >
                {{ $t('public.cancel') }}
            </Button>
            <Button
                variant="primary-flat"
                class="flex gap-2 w-full"
                @click.prevent="submitForm"
                :disabled="form.processing || loadingFee || loadPaymentAccounts || loadingPaymentGateways"
            >
                <IconLoader2 v-if="form.processing" size="16" class="animate-spin w-4" />
                {{ $t('public.confirm') }}
            </Button>
        </div>
    </form>
</template>
