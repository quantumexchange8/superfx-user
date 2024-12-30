<script setup>
import InputLabel from "@/Components/InputLabel.vue";
import InputNumber from 'primevue/inputnumber';
import {useForm} from "@inertiajs/vue3";
import Button from "@/Components/Button.vue"
import InputError from "@/Components/InputError.vue";
import Dropdown from "primevue/dropdown";
import {ref, watch} from "vue";
import {transactionFormat} from "@/Composables/index.js";

const props = defineProps({
    rebateWallet: Object,
})

const transferOptions = ref([]);
const transferAmount = ref(0);
const {formatAmount} = transactionFormat()
const emit = defineEmits(['update:visible'])

const getOptions = async () => {
    try {
        const response = await axios.get('/account/getOptions');
        transferOptions.value = response.data.transferOptions;
    } catch (error) {
        console.error('Error changing locale:', error);
    }
};

getOptions();

const form = useForm({
    wallet_id: props.rebateWallet.id,
    amount: 0,
    meta_login: '',
})

watch(transferOptions, (newAccount) => {
    transferAmount.value = newAccount[0].value
    form.meta_login = newAccount[0].name
})

const toggleFullAmount = () => {
    if (form.amount) {
        form.amount = 0;
    } else {
        form.amount = Number(props.rebateWallet.balance);
    }
};

const submitForm = () => {
    form.post(route('dashboard.walletTransfer'), {
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
                <div class="flex flex-col justify-center items-center py-4 px-8 gap-2 self-stretch bg-logo">
                    <span class="w-full text-gray-100 text-center text-xs font-medium">{{ $t('public.available_rebate_balance') }}</span>
                    <span class="w-full text-white text-center text-xl font-semibold">$ {{ formatAmount(rebateWallet.balance) }}</span>
                </div>

                <!-- input fields -->
                <div class="flex flex-col items-start gap-1 self-stretch">
                    <InputLabel for="receiving_wallet" :value="$t('public.transfer_to')" />
                    <Dropdown
                        v-model="transferAmount"
                        :options="transferOptions"
                        optionLabel="name"
                        optionValue="value"
                        :placeholder="$t('public.select')"
                        class="w-full"
                        scroll-height="236px"
                        :invalid="!!form.errors.meta_login"
                        :disabled="!transferOptions.length"
                    />
                    <InputError :message="form.errors.meta_login" />
                    <span class="self-stretch text-gray-500 text-xs">{{ $t('public.balance') }}: $ {{ transferOptions.length ? formatAmount(transferAmount, 0) : $t('public.loading_caption')}}</span>
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
                    <span class="self-stretch text-gray-500 text-xs">{{ $t('public.minimum_amount') }}: ${{ formatAmount(30,0) }}</span>
                    <InputError :message="form.errors.amount" />
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
                :disabled="form.processing || !transferOptions.length"
            >
                {{ $t('public.confirm') }}
            </Button>
        </div>
    </form>
</template>
