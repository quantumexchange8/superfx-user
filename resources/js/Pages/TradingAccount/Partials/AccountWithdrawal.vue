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

const props = defineProps({
    account: Object,
})

const walletOptions = ref([]);
const loadPaymentAccounts = ref(false);
const selectedPaymentAccount = ref();
const cryptoOptions = ref([]);
const selectedCryptoOption = ref();
const {formatAmount} = transactionFormat()
const emit = defineEmits(['update:visible'])

const getWithdrawalPaymentAccounts = async (type) => {
    loadPaymentAccounts.value = true;
    try {
        const response = await axios.get(`/getWithdrawalPaymentAccounts?payment_platform=${selectedPlatform.value}&payment_account_type=${type}`);
        walletOptions.value = response.data.payment_accounts;
        selectedPaymentAccount.value = walletOptions.value[0];
        cryptoOptions.value = response.data.crypto_options;
        selectedCryptoOption.value = response.data.crypto_options[0];
    } catch (error) {
        console.error('Error fetching wallets:', error);
    } finally {
        loadPaymentAccounts.value = false;
    }
};

const form = useForm({
    account_id: props.account.id,
    payment_platform: '',
    payment_platform_type: '',
    amount: 0,
    fee: 0,
    payment_account_id: '',
})

const finalAmount = computed(() => {
    const fee = props.account?.category === 'cent'
        ? Number(selectedCryptoOption.value.fee) * props.account.balance_multiplier
        : Number(selectedCryptoOption.value.fee);

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
    form.payment_platform = selectedPlatform.value;
    form.payment_platform_type = selectedPlatform.value === 'bank' ? selectedPaymentGateway.value : selectedCryptoNetwork.value;
    form.payment_account_id = selectedPaymentAccount.value.id;
    form.fee = Number(selectedCryptoOption.value?.fee ?? 0);
    form.post(route('account.withdrawal_from_account'), {
        onSuccess: () => {
            closeDialog();
        }
    });
}

const closeDialog = () => {
    emit('update:visible', false)
};

const selectedPlatform = ref('');
const depositOptions = [
    'bank',
    'crypto'
];

// crypto
const selectedCryptoNetwork = ref('');
const cryptoNetworks = ref([
    'erc20',
    'trc20'
]);

const paymentGateways = ref([]);
const selectedPaymentGateway = ref();
const loadingPaymentGateways = ref(false);

const getPaymentGateways = async () => {
    loadingPaymentGateways.value = true;
    try {
        const response = await axios.get(`/getPaymentGateways?platform=${selectedPlatform.value}`);
        paymentGateways.value = response.data.payment_gateways;
    } catch (error) {
        console.error('Error changing locale:', error);
    } finally {
        loadingPaymentGateways.value = false;
    }
};

watch(selectedPlatform, () => {
    getPaymentGateways();
});

//bank
watch(selectedPaymentGateway, (newPaymentGateway) => {
    selectedPaymentGateway.value = null;
    getWithdrawalPaymentAccounts(newPaymentGateway);
})

//crypto
watch(selectedCryptoNetwork, (newCryptoNetwork) => {
    selectedCryptoNetwork.value = null;
    getWithdrawalPaymentAccounts(newCryptoNetwork);
})
</script>

<template>
    <form>
        <div class="flex flex-col items-center gap-8 self-stretch md:gap-10">
            <div class="flex flex-col items-center gap-5 self-stretch">
                <div class="flex flex-col justify-center items-center py-4 px-8 gap-2 self-stretch bg-gray-200">
                    <span class="w-full text-gray-500 text-center text-xs font-medium">#{{ account.meta_login }} - {{ $t('public.current_account_balance') }}</span>
                    <span class="w-full text-gray-950 text-center text-xl font-semibold">$ {{ formatAmount(account.balance) }}</span>
                </div>

                <div class="flex flex-col items-start gap-1 self-stretch">
                    <InputLabel for="accountType" :value="$t('public.platform_placeholder')" />
                    <SelectChipGroup
                        :items="depositOptions"
                        v-model="selectedPlatform"
                    />
                    <InputError :message="form.errors.payment_platform" />
                </div>

                <div
                    v-if="selectedPlatform === 'bank'"
                    class="flex flex-col items-start gap-1 self-stretch"
                >
                    <InputLabel
                        for="accountType"
                        :value="$t('public.platform_placeholder')"
                    />
                    <Skeleton
                        v-if="loadingPaymentGateways"
                        width="9rem"
                        height="2.75rem"
                    />
                    <SelectChipGroup
                        v-else
                        v-model="selectedPaymentGateway"
                        :items="paymentGateways"
                        value-key="id"
                    >
                        <template #option="{ item }">
                            {{ item.name }}
                        </template>
                    </SelectChipGroup>
                    <InputError :message="form.errors.payment_platform_type" />
                </div>

                <!-- Crypto Options-->
                <div
                    v-if="selectedPlatform ==='crypto'"
                    class="flex flex-col items-start gap-1 self-stretch"
                >
                    <InputLabel for="accountType" :value="$t('public.platform_placeholder')" />
                    <SelectChipGroup
                        :items="cryptoNetworks"
                        v-model="selectedCryptoNetwork"
                    >
                        <template #option="{ item }">
                            <div class="uppercase">{{ item }}</div>
                        </template>
                    </SelectChipGroup>
                    <InputError :message="form.errors.payment_platform_type" />
                </div>

                <!-- input fields -->
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
                    <span class="self-stretch text-gray-500 text-xs">{{ $t('public.minimum_amount') }}: ${{ formatAmount(account.category === 'cent' ? 50 * account.balance_multiplier : 50, 0) }}</span>
                    <InputError :message="form.errors.amount" />
                </div>

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
                        :disabled="walletOptions.length === 0"
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
                    <span v-if="walletOptions.length === 0" class="self-stretch text-gray-500 text-xs">{{ loadPaymentAccounts ? $t('public.loading_caption') : $t('public.no_payment_account')}}</span>
                    <span v-else class="self-stretch text-gray-500 text-xs">{{ selectedPaymentAccount.account_no }}</span>
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
                            ${{ formatAmount(account.category === 'cent' ? (selectedCryptoOption.fee ?? 0) * account.balance_multiplier : (selectedCryptoOption.fee ?? 0)) }}
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
                class="w-full md:w-[120px]"
                @click.prevent="closeDialog()"
                :disabled="form.processing"
            >
                {{ $t('public.cancel') }}
            </Button>
            <Button
                variant="primary-flat"
                class="w-full md:w-[120px]"
                @click.prevent="submitForm"
                :disabled="form.processing || walletOptions.length === 0"
            >
                {{ $t('public.confirm') }}
            </Button>
        </div>
    </form>
</template>
