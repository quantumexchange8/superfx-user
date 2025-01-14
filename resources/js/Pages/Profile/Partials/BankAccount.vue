<script setup>
import {ref, watch, computed} from "vue";
import {useForm, usePage} from "@inertiajs/vue3";
import InputLabel from "@/Components/InputLabel.vue";
import InputText from 'primevue/inputtext';
import InputError from "@/Components/InputError.vue";
// import BaseListbox from "@/Components/BaseListbox.vue";
import Button from "@/Components/Button.vue";
import Dialog from 'primevue/dialog';
import Dropdown from "primevue/dropdown";
import {IconPlus,IconCircleCheckFilled} from '@tabler/icons-vue';

const bankAccDialog = ref(false);

const openDialog = () => {
    bankAccDialog.value = true;
}

// const selected = ref(paymentTypes.value[0]);
// const cryptoWallet = ref('USDT (TRC20)');
// const country = ref(45);
// const currency = ref('CNY');

const form = useForm({
    payment_account_name: '',
    payment_platform_name: '',
    payment_account_type: '',
    account_no: '',
    card_no: '',
    country: null,
    currency: '',
    bank_code: '',
});

const selectedType = ref('account')
const accountTypes = ref(['account', 'card']);
const selectType = (type) => {
    selectedType.value = type;
}

const submit = () => {
    form.bank_code = selectedBank.value.bank_code;
    form.payment_platform_name = selectedBank.value.bank_name;
    form.payment_account_type = selectedType.value;

    console.log(form.payment_account_type)
    form.post(route('profile.addPaymentAccount'), {
        onSuccess: () => {
            closeDialog();
            form.reset();
        },
    })
}

const closeDialog = () => {
    bankAccDialog.value = false;
}

const banks = ref([]);
const accounts = ref();
const selectedBank = ref({ bank_name: '', bank_code: '' });
const selectedAccount = ref({ account_name: '', account_number: '' });
const getResults = async () => {
    try {
        const response = await axios.get('/profile/getFilterData');
        banks.value = response.data.banks;
        accounts.value = response.data.bankOptions;
    } catch (error) {
        console.error('Error changing locale:', error);
    }
};

getResults();

const displayForm = useForm({
    account_id: null,
    payment_account_name: '',
    payment_platform_name: '',
    account_number: '',
    payment_account_type: '',
    bank_code: '',
});

// const paymentAccounts = ref(usePage().props.auth.payment_account);

// const displayAccounts = computed(() => {
//     return paymentAccounts.value.filter(account => account.payment_platform === 'bank');
// });

watch(
    () => selectedAccount.value,
    (newAccounts) => {
        if (Array.isArray(newAccounts)) {
            displayForm.account_id = newAccounts.map(account => account.id || '');
            displayForm.payment_account_name = newAccounts.map(account => account.account_name || '');
            displayForm.payment_platform_name = newAccounts.map(account => account.bank_name || '');
            displayForm.account_number = newAccounts.map(account => account.account_number || '');
            displayForm.payment_account_type = newAccounts.map(account => account.payment_account_type || '');
            displayForm.bank_code = newAccounts.map(account => account.bank_code || '');
        } else {
            // Handle cases where `newAccounts` is not an array
            displayForm.account_id = null;
            displayForm.payment_account_name = '';
            displayForm.payment_platform_name = '';
            displayForm.account_number = '';
            displayForm.payment_account_type = '';
            displayForm.bank_code = '';
        }
    },
    { immediate: true } // Update immediately upon initialization
);

const submitForm = () => {

    // form.post(route('profile.updateCryptoWalletInfo'), {
    //     preserveScroll: true,
    // });
};

</script>

<template>
    <div class="p-4 md:py-6 md:px-8 flex flex-col gap-8 items-start self-stretch rounded-2xl shadow-toast w-full">
        <div class="flex justify-between w-full">
            <div class="flex flex-col gap-1 items-start justify-center w-full">
                <span class="text-gray-950 font-bold">{{ $t('public.bank_account') }}</span>
                <span class="text-gray-500 text-xs">{{ $t('public.bank_account_caption') }}</span>
            </div>
            <Button
                type="button"
                class="md:flex justify-center hidden md:w-[270px]"
                variant="primary-flat"
                @click="openDialog"
            >
                <IconPlus
                    size="20"
                />{{ $t('public.add_bank_account') }}
            </Button>
        </div>

        <div class="">
            <span class="text-sm">{{ $t('public.account_name') }}</span>
            <Dropdown
                v-model="selectedAccount"
                :options="accounts"
                filter
                :filterFields="['account_name', 'account_number']"
                optionLabel="account_name"
                :placeholder="$t('public.account_name')"
                class="w-full"
                scroll-height="236px"
            >
                <template #value="slotProps">
                    <div v-if="slotProps.value.account_name" class="flex items-center">
                        <div>{{ slotProps.value.account_name }}</div>
                    </div>
                    <span v-else>
                        {{ $t('public.select_account') }}
                    </span>
                </template>
                <template #option="slotProps">
                    <div class="flex items-center w-[250px] md:w-full overflow-x-auto">
                        <div>{{ slotProps.option.account_name }} <span class="text-gray-500">( {{ slotProps.option.account_number }} )</span></div>
                    </div>
                </template>
            </Dropdown>
        </div>
        <div v-if="selectedAccount.account_number" class="w-full">
            <form class="flex flex-col gap-8 items-center self-stretch w-full">
                <div  class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <!-- <BankSetting/> -->
                    <div class="space-y-2 md:col-span-2">
                        <InputLabel
                            for="bank_name"
                            :value="$t('public.bank_name')"
                        />
                        <Dropdown
                            v-model="selectedAccount"
                            :options="banks"
                            filter
                            :filterFields="['bank_name', 'bank_code']"
                            optionLabel="bank_name"
                            :placeholder="$t('public.bank_name')"
                            class="w-full"
                            scroll-height="236px"
                            :invalid="!!displayForm.errors.bank_name"
                        >
                            <template #value="slotProps">
                                <div v-if="slotProps.value.bank_name" class="flex items-center">
                                    <div>{{ slotProps.value.bank_name }}</div>
                                </div>
                                <span v-else>
                                    {{ $t('public.bank_placeholder') }}
                                </span>
                            </template>
                            <template #option="slotProps">
                                <div class="flex items-center w-[250px] md:w-full overflow-x-auto">
                                    <div>{{ slotProps.option.bank_name }} <span class="text-gray-500">( {{ slotProps.option.bank_code }} )</span></div>
                                </div>
                            </template>
                        </Dropdown>
                        <InputError :message="displayForm.errors.bank_name" />
                    </div>
                    <div class="space-y-2 md:col-span-2">
                        <InputLabel
                            for="bank_code"
                            :value="$t('public.bank_code')"
                        />
                        <InputText
                            id="bank_code"
                            type="text"
                            class="block w-[170px]"
                            v-model="selectedAccount.bank_code"
                            :placeholder="$t('public.bank_code')"
                            disabled
                        />
                    </div>
                    <div class="flex flex-col items-start gap-2 self-stretch">
                        <InputLabel for="accountType" :value="$t('public.account_placeholder')" />
                        <div class="grid grid-cols-2 items-start gap-3 self-stretch">
                            <div
                                v-for="type in accountTypes"
                                :key="type"
                                @click="selectType(type)"
                                class="group col-span-1 items-start py-3 px-4 gap-1 self-stretch rounded-lg border shadow-input transition-colors duration-300 select-none cursor-pointer"
                                :class="{
                                    'bg-primary-50 border-primary-500': selectedAccount.payment_account_type === type,
                                    'bg-white border-gray-300 hover:bg-primary-50 hover:border-primary-500': selectedAccount.payment_account_type !== type,
                                }"
                            >
                                <div class="flex items-center gap-3 self-stretch">
                                    <span
                                        class="flex-grow text-sm font-semibold transition-colors duration-300 group-hover:text-primary-700"
                                        :class="{
                                            'text-primary-700': selectedAccount.payment_account_type === type,
                                            'text-gray-950': selectedAccount.payment_account_type !== type
                                        }"
                                    >
                                        {{ $t(`public.${type}`) }}
                                    </span>
                                    <IconCircleCheckFilled v-if="selectedAccount.payment_account_type === type" size="20" stroke-width="1.25" color="#06D001" />
                                </div>
                            </div>
                        </div>
                        <InputError v-if="displayForm.errors.payment_account_type" :message="displayForm.errors.payment_account_type" />
                    </div>
                    <div v-if="selectedAccount.payment_account_type ==='card'" class="space-y-2 md:col-span-2">
                        <InputLabel
                            for="card_no"
                            :value="$t('public.card_no')"
                        />
                        <InputText
                            id="card_no"
                            type="text"
                            class="block w-full"
                            v-model="selectedAccount.account_number"
                            :invalid="displayForm.errors.card_no"
                        />
                        <InputError :message="displayForm.errors.card_no" />
                    </div>
                    <div v-else class="space-y-2 md:col-span-2">
                        <InputLabel
                            for="account_no"
                            :value="$t('public.account_no')"
                        />
                        <InputText
                            id="account_no"
                            type="text"
                            class="block w-full"
                            v-model="selectedAccount.account_number"
                            :invalid="displayForm.errors.account_no"
                        />
                        <InputError :message="displayForm.errors.account_no" />
                    </div>
                    <div class="space-y-2 md:col-span-2">
                        <InputLabel
                            for="bank_account_name"
                            :value="$t('public.account_name')"
                        />
                        <InputText
                            id="bank_account_name"
                            type="text"
                            class="block w-full"
                            v-model="selectedAccount.account_name"
                            :invalid="displayForm.errors.payment_account_name"
                        />
                        <InputError :message="displayForm.errors.payment_account_name" />
                    </div>
                </div>
                <div class="flex justify-end items-center gap-4 self-stretch">
                    <Button
                        type="button"
                        variant="gray-tonal"
                        :disabled="displayForm.processing"
                        @click="resetForm"
                    >
                        {{ $t('public.cancel') }}
                    </Button>
                    <Button
                        variant="primary-flat"
                        :disabled="displayForm.processing"
                        @click="submitForm"
                    >
                        {{ $t('public.save_changes') }}
                    </Button>
                </div>
            </form>
        </div>
    </div>

    <Dialog
        v-model:visible="bankAccDialog"
        modal
        :header="$t('public.add_account')"
        class="dialog-xs md:dialog-md"
    >
        <form class="space-y-4">
            <div  class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <!-- <BankSetting/> -->
                <div class="space-y-2 md:col-span-2">
                    <InputLabel
                        for="bank_name"
                        :value="$t('public.bank_name')"
                    />
                    <Dropdown
                        v-model="selectedBank"
                        :options="banks"
                        filter
                        :filterFields="['bank_name', 'bank_code']"
                        optionLabel="bank_name"
                        :placeholder="$t('public.bank_name')"
                        class="w-full"
                        scroll-height="236px"
                        :invalid="!!form.errors.bank_name"
                    >
                        <template #value="slotProps">
                            <div v-if="slotProps.value.bank_name" class="flex items-center">
                                <div>{{ slotProps.value.bank_name }}</div>
                            </div>
                            <span v-else>
                                {{ $t('public.bank_placeholder') }}
                            </span>
                        </template>
                        <template #option="slotProps">
                            <div class="flex items-center w-[250px] md:w-full overflow-x-auto">
                                <div>{{ slotProps.option.bank_name }} <span class="text-gray-500">( {{ slotProps.option.bank_code }} )</span></div>
                            </div>
                        </template>
                    </Dropdown>
                    <InputError :message="form.errors.bank_name" />
                </div>
                <div class="space-y-2 md:col-span-2">
                    <InputLabel
                        for="bank_code"
                        :value="$t('public.bank_code')"
                    />
                    <InputText
                        id="bank_code"
                        type="text"
                        class="block w-[170px]"
                        v-model="selectedBank.bank_code"
                        :placeholder="$t('public.bank_code')"
                        disabled
                    />
                </div>
                <div class="flex flex-col items-start gap-2 self-stretch">
                    <InputLabel for="accountType" :value="$t('public.account_placeholder')" />
                    <div class="grid grid-cols-2 items-start gap-3 self-stretch">
                        <div
                            v-for="type in accountTypes"
                            :key="type"
                            @click="selectType(type)"
                            class="group col-span-1 items-start py-3 px-4 gap-1 self-stretch rounded-lg border shadow-input transition-colors duration-300 select-none cursor-pointer"
                            :class="{
                                'bg-primary-50 border-primary-500': selectedType === type,
                                'bg-white border-gray-300 hover:bg-primary-50 hover:border-primary-500': selectedType !== type,
                            }"
                        >
                            <div class="flex items-center gap-3 self-stretch">
                                <span
                                    class="flex-grow text-sm font-semibold transition-colors duration-300 group-hover:text-primary-700"
                                    :class="{
                                        'text-primary-700': selectedType === type,
                                        'text-gray-950': selectedType !== type
                                    }"
                                >
                                    {{ $t(`public.${type}`) }}
                                </span>
                                <IconCircleCheckFilled v-if="selectedType === type" size="20" stroke-width="1.25" color="#06D001" />
                            </div>
                        </div>
                    </div>
                    <InputError v-if="form.errors.payment_account_type" :message="form.errors.payment_account_type" />
                </div>
                <div v-if="selectedType ==='card'" class="space-y-2 md:col-span-2">
                    <InputLabel
                        for="card_no"
                        :value="$t('public.card_no')"
                    />
                    <InputText
                        id="card_no"
                        type="text"
                        class="block w-full"
                        v-model="form.card_no"
                        :invalid="form.errors.card_no"
                    />
                    <InputError :message="form.errors.card_no" />
                </div>
                <div v-else class="space-y-2 md:col-span-2">
                    <InputLabel
                        for="account_no"
                        :value="$t('public.account_no')"
                    />
                    <InputText
                        id="account_no"
                        type="text"
                        class="block w-full"
                        v-model="form.account_no"
                        :invalid="form.errors.account_no"
                    />
                    <InputError :message="form.errors.account_no" />
                </div>
                <div class="space-y-2 md:col-span-2">
                    <InputLabel
                        for="bank_account_name"
                        :value="$t('public.account_name')"
                    />
                    <InputText
                        id="bank_account_name"
                        type="text"
                        class="block w-full"
                        v-model="form.payment_account_name"
                        :invalid="form.errors.payment_account_name"
                    />
                    <InputError :message="form.errors.payment_account_name" />
                </div>
            </div>

            <div class="pt-5 flex justify-end">
                <Button
                    type="button"
                    class="flex justify-center"
                    variant="primary-flat"
                    :disabled="form.processing"
                    @click="submit"
                >
                    {{ $t('public.save') }}
                </Button>
            </div>
        </form>
    </Dialog>

</template>
