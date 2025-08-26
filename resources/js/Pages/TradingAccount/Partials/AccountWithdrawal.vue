<script setup>
import InputLabel from "@/Components/InputLabel.vue";
import InputNumber from 'primevue/inputnumber';
import {useForm} from "@inertiajs/vue3";
import Button from "@/Components/Button.vue"
import InputError from "@/Components/InputError.vue";
import Dropdown from "primevue/dropdown";
import {ref, watch, computed} from "vue";
import {transactionFormat} from "@/Composables/index.js";

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

const getOptions = async () => {
    try {
        const response = await axios.get('/getWithdrawalPaymentAccounts?type=withdrawal');
        walletOptions.value = response.data.payment_accounts;
        selectedPaymentAccount.value = walletOptions.value[0];
        cryptoOptions.value = response.data.crypto_options;
    } catch (error) {
        console.error('Error fetching wallets:', error);
    }
};

getOptions();

watch(selectedPaymentAccount, (newWallet) => {
    selectedPaymentAccount.value = newWallet;

    const matchingOption = cryptoOptions.value.find(
        (option) => option.type === selectedPaymentAccount.value.payment_account_type.toUpperCase()
    );

    if (matchingOption) {
        selectedCryptoOption.value = matchingOption;
    } else {
        selectedCryptoOption.value = null;
    }
})

const form = useForm({
    account_id: props.account.id,
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
                :disabled="form.processing"
            >
                {{ $t('public.confirm') }}
            </Button>
        </div>
    </form>
</template>
