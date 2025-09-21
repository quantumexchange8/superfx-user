<script setup>
import Button from "@/Components/Button.vue";
import {SwitchHorizontal01Icon} from "@/Components/Icons/outline.jsx";
import {ref} from "vue";
import Dialog from "primevue/dialog";
import InputLabel from "@/Components/InputLabel.vue";
import Dropdown from "primevue/dropdown";
import InputError from "@/Components/InputError.vue";
import InputNumber from "primevue/inputnumber";
import Tag from "primevue/tag";
import {generalFormat, transactionFormat} from "@/Composables/index.js";
import toast from "@/Composables/toast.js";
import {useForm} from "@inertiajs/vue3";

const props = defineProps({
    account: Object,
});

const visible = ref(false);
const {formatAmount} = transactionFormat()
const { formatRgbaColor } = generalFormat();

const openDialog = () => {
    visible.value = true;
    getAccounts();
}

const form = useForm({
    from_login: props.account.meta_login,
    to_login: '',
    amount: null
})

const accounts = ref([]);
const selectedAccount = ref()
const loadingAccounts = ref(false);

const getAccounts = async () => {
    loadingAccounts.value = true;

    try {
        const response = await axios.get(
            `/getTradingAccounts?login=${props.account.meta_login}`
        );

        // All groups from API
        accounts.value = response.data.accounts;
        selectedAccount.value = accounts.value[0];

    } catch (error) {
        console.error('Error getting accounts:', error);
    } finally {
        loadingAccounts.value = false;
    }
};

const toggleFullAmount = () => {
    if (form.amount) {
        form.amount = null;
    } else {
        form.amount = props.account.balance;
    }
};

const submitForm = async () => {
    form.to_login = selectedAccount?.value?.meta_login;

    form.post(route('account.internal_transfer'), {
        onSuccess: () => {
            closeDialog();
            form.reset();
        }
    })
}

const closeDialog = () => {
    visible.value = false;
};
</script>

<template>
    <Button
        type="button"
        variant="gray-outlined"
        size="sm"
        pill
        iconOnly
        @click="openDialog"
        :disabled="account.trading_user.acc_status !== 'active'"
    >
        <SwitchHorizontal01Icon class="w-4 text-gray-950" />
    </Button>

    <Dialog
        v-model:visible="visible"
        :header="$t('public.transfer')"
        modal
        class="dialog-xs sm:dialog-sm"
    >
        <form @submit.prevent="submitForm">
            <div class="flex flex-col items-center gap-5 self-stretch">
                <div class="flex flex-col justify-center items-center py-4 px-8 gap-2 self-stretch bg-gray-200">
                    <span class="text-gray-500 text-center text-xs font-medium">#{{ account.meta_login }} - {{ $t('public.current_account_balance') }}</span>
                    <span class="text-gray-950 text-center text-xl font-semibold">$ {{ account.balance }}</span>
                </div>
                <div class="flex flex-col items-start gap-1 self-stretch">
                    <InputLabel for="to_meta_login" :value="$t('public.transfer_to')" />
                    <Dropdown
                        v-model="selectedAccount"
                        :options="accounts"
                        optionLabel="meta_login"
                        :placeholder="$t('public.transfer_to_placeholder')"
                        class="w-full"
                        scroll-height="236px"
                        :invalid="!!form.errors.to_meta_login"
                        :loading="loadingAccounts"
                    >
                        <template #value="{value, placeholder}">
                            <div v-if="value" class="flex items-center gap-2">
                                {{ value.meta_login }}
                                <Tag
                                    :severity="value.account_type.trading_platform.slug === 'mt4' ? 'secondary' : 'info'"
                                    :value="value.account_type.trading_platform.slug"
                                    class="text-xxs uppercase"
                                />
                                <div
                        class="flex px-2 py-1 justify-center items-center text-xxs font-semibold hover:-translate-y-1 transition-all duration-300 ease-in-out rounded"
                        :style="{
                            backgroundColor: formatRgbaColor(value.account_type.color, 0.15),
                            color: `#${value.account_type.color}`,
                        }"
                    >
                        {{ (value.account_type.member_display_name ?? value.account_type.name) }}
                    </div>
                            </div>
                            <div v-else>
                                {{ placeholder }}
                            </div>
                        </template>
                        <template #option="{option}">
                            <div class="flex items-center gap-2">
                                {{ option.meta_login }}
                                <Tag
                                    :severity="option.account_type.trading_platform.slug === 'mt4' ? 'secondary' : 'info'"
                                    :value="option.account_type.trading_platform.slug"
                                    class="text-xxs uppercase"
                                />
                                <div
                        class="flex px-2 py-1 justify-center items-center text-xxs font-semibold hover:-translate-y-1 transition-all duration-300 ease-in-out rounded"
                        :style="{
                            backgroundColor: formatRgbaColor(option.account_type.color, 0.15),
                            color: `#${option.account_type.color}`,
                        }"
                    >
                        {{ (option.account_type.member_display_name ?? option.account_type.name) }}
                    </div>
                            </div>
                        </template>
                    </Dropdown>
                    <span class="self-stretch text-gray-500 text-xs">{{ $t('public.balance') }}: $ {{ formatAmount(selectedAccount?.balance ?? 0) }}</span>
                    <InputError :message="form.errors.to_login" />
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
                    <span v-if="loadingAccounts" class="text-xs text-gray-500">{{ $t('public.loading_caption') }}</span>
                    <span v-else-if="selectedAccount.account_type.account_group === 'PRIME'" class="self-stretch text-gray-500 text-xs">{{ $t('public.minimum_amount') }}: ${{ formatAmount( account.account_type.category === 'cent' ? selectedAccount.account_type.minimum_deposit * account.account_type.balance_multiplier : selectedAccount.account_type.minimum_deposit , 0) }}</span>
                    <InputError :message="form.errors.amount" />
                </div>
            </div>
            <div class="flex justify-end items-center pt-5 gap-4 self-stretch sm:pt-7">
                <Button
                    type="button"
                    variant="gray-tonal"
                    class="w-full sm:w-[120px]"
                    @click.prevent="closeDialog('transfer')"
                    :disabled="form.processing || loadingAccounts"
                >
                    {{ $t('public.cancel') }}
                </Button>
                <Button
                    type="submit"
                    variant="primary-flat"
                    class="w-full sm:w-[120px]"
                    @click.prevent="submitForm"
                    :disabled="form.processing || loadingAccounts"
                >
                    {{ $t('public.confirm') }}
                </Button>
            </div>
        </form>
    </Dialog>
</template>
