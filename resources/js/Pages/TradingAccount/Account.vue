<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Button from "@/Components/Button.vue";
import {ref, h, watch, onMounted, computed} from "vue";
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';
import IndividualAccounts from '@/Pages/TradingAccount/Partials/IndividualAccounts.vue';
import ManagedAccounts from '@/Pages/TradingAccount/Partials/ManagedAccounts.vue';
import DemoAccounts from '@/Pages/TradingAccount/Partials/DemoAccounts.vue';
import { usePage, useForm } from "@inertiajs/vue3";
import Dialog from 'primevue/dialog';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputText from 'primevue/inputtext';
import IconField from 'primevue/iconfield';
import Dropdown from "primevue/dropdown";
import {IconCircleCheckFilled, IconInfoOctagonFilled, IconX} from '@tabler/icons-vue';
import { trans, wTrans } from "laravel-vue-i18n";
import TermsAndCondition from "@/Components/TermsAndCondition.vue";

const props = defineProps({
    terms: Object
})

// Initialize the form with user data
const user = usePage().props.auth.user;

const liveAccountForm = useForm({
    user_id: user.id,
    accountType: '',
    leverage: '',
});

const demoAccountForm = useForm({
    user_id: user.id,
    amount: '',
    leverage: '',
});

const tabs = ref([
    { title: wTrans('public.individual'), component: h(IndividualAccounts), type: 'individual' },
    // { title: wTrans('public.demo'), component: h(DemoAccounts), type: 'demo' },
]);

const selectedType = ref('individual');
const activeIndex = ref(tabs.value.findIndex(tab => tab.type === selectedType.value));

// Watch for changes in selectedType and update the activeIndex accordingly
watch(selectedType, (newType) => {
    const index = tabs.value.findIndex(tab => tab.type === newType);
    if (index >= 0) {
        activeIndex.value = index;
    }
});

function updateType(event) {
    const selectedTab = tabs.value[event.index];
    selectedType.value = selectedTab.type;
}

const showLiveAccountDialog = ref(false);
const showDemoAccountDialog = ref(false);

// Functions to open and close the dialog
const openDialog = (dialogRef, formRef = null) => {
    if (formRef) formRef.reset();
    if (dialogRef === 'live') {
        selectedAccountType.value = '';
        showLiveAccountDialog.value = true;
    } else if (dialogRef === 'demo') {
        showDemoAccountDialog.value = true;
    }
};

const closeDialog = (dialogName, formRef = null) => {
    if (formRef) formRef.reset();
    if (dialogName === 'live') {
        showLiveAccountDialog.value = false;
    } else if (dialogName === 'demo') {
        showDemoAccountDialog.value = false;
    }
};


const leverages = ref();
const transferOptions = ref();
const accountOptions = ref([]);
const selectedAccountType = ref('');

const getOptions = async () => {
    try {
        const response = await axios.get('/account/getOptions');
        leverages.value = response.data.leverages;
        accountOptions.value = response.data.accountOptions;
    } catch (error) {
        console.error('Error changing locale:', error);
    }
};

getOptions();

// Handle selection of an account
function selectAccount(type) {
    selectedAccountType.value = type;
    liveAccountForm.accountType = type;

    // Find selected account and update leverage
    const selectedAccount = accountOptions.value.find(account => account.account_group === type);
    if (selectedAccount && selectedAccount.leverage) {
        liveAccountForm.leverage = selectedAccount.leverage;
    } else {
        liveAccountForm.leverage = ''; // Clear leverage if no value
    }
}

const openLiveAccount = () => {
    liveAccountForm.post(route('account.create_live_account'), {
        onSuccess: () => {
            closeDialog('live', liveAccountForm);
        },
        onError: (error) => {
            console.error('Failed to open live account.', error);
        },
    });
};

const openDemoAccount = () => {
    demoAccountForm.post(route('account.create_demo_account'), {
        onSuccess: () => {
            closeDialog('demo', demoAccountForm);
        },
        onError: (error) => {
            console.error('Failed to open live account.', error);
        },
    });
};

const buttonSize = computed(() => {
    return window.innerWidth < 768 ? 'sm' : 'base';
})

const noticeVisible = ref(true);
</script>

<template>
    <AuthenticatedLayout :title="$t('public.accounts')">
        <div class="flex flex-col gap-20 md:gap-[100px]">
            <div class="flex flex-col items-start gap-5 self-stretch">
                <!-- notice -->
                <TransitionGroup
                    tag="div"
                    enter-from-class="-translate-y-full opacity-0"
                    enter-active-class="duration-300"
                    leave-active-class="duration-300"
                    leave-to-class="-translate-y-full opacity-0"
                    class="w-full"
                >
                    <div
                        v-if="noticeVisible"
                        class="py-3 px-4 flex justify-center self-stretch gap-4 border-t-8 border-info-500 shadow-toast bg-white items-start"
                        role="alert"
                    >
                        <div class="text-info-500">
                            <IconInfoOctagonFilled size="20" />
                        </div>
                        <div
                            class="flex flex-col gap-1 items-start w-full text-sm"
                        >
                            <div class="text-gray-950 font-semibold">
                                {{ $t('public.inactive_account_notice') }}
                            </div>
                            <div class="text-gray-700">
                                {{ $t('public.inactive_account_notice_message') }}
                            </div>
                        </div>
                        <div class="text-gray-400 hover:text-gray-600 hover:cursor-pointer select-none" @click="noticeVisible = false">
                            <IconX size="16" stroke-width="1.25" />
                        </div>
                    </div>
                </TransitionGroup>

                <!-- banner -->
                <div class="h-[260px] pl-5 pt-5 pr-3 pb-[26px] self-stretch rounded-2xl bg-white shadow-toast md:h-60 bg-[url('/img/background-account-banner.svg')] bg-no-repeat bg-right-bottom bg-contain
                    md:pl-8 md:pt-[30px] md:pb-[30px] md:pr-[246px]
                    xl:pt-11 xl:pb-11 xl:pr-[343px]"
                    >
                    <!-- Content -->
                    <div class="flex flex-col items-center gap-5 md:w-[450px] md:gap-8 md:items-start lg:w-[454px] xl:w-[643px]">
                        <div class="flex flex-col justify-center items-start gap-2 self-stretch">
                            <span class="self-stretch text-gray-950 font-bold md:text-lg">{{ $t('public.account_banner_header') }}</span>
                            <span class="self-stretch text-gray-700 text-xs md:text-sm">{{ $t('public.account_banner_message') }}</span>
                        </div>
                        <div class="flex flex-col justify-center items-start gap-3 self-stretch md:flex-row md:justify-start md:items-center md:gap-5">
                            <Button
                                type="button"
                                variant="primary-flat"
                                class="w-[142px] md:w-1/2"
                                :size="buttonSize"
                                @click="openDialog('live', liveAccountForm)"
                                :disabled="!accountOptions.length"
                            >
                                {{ $t('public.live_account') }}
                            </Button>
                            <!-- <Button
                                type="button"
                                variant="primary-outlined"
                                class="w-[142px] md:w-full"
                                :size="buttonSize"
                                @click="openDialog('demo', demoAccountForm)"
                            >
                                {{ $t('public.demo_account') }}
                            </Button> -->
                        </div>
                    </div>
                </div>

                <!-- tab -->
                <div class="flex items-center gap-3 self-stretch">
                    <TabView class="flex flex-col" :activeIndex="activeIndex" @tab-change="updateType">
                        <TabPanel v-for="(tab, index) in tabs" :key="index" :header="tab.title" />
                    </TabView>
                </div>

                <component :is="tabs[activeIndex]?.component" />
            </div>

        </div>
    </AuthenticatedLayout>

    <Dialog v-model:visible="showLiveAccountDialog" modal :header="$t('public.open_live_account')" class="dialog-sm sm:dialog-md">
        <div class="flex flex-col items-center gap-8 self-stretch sm:gap-10">
            <div class="flex flex-col items-center gap-5 self-stretch">
                <div class="flex flex-col items-start gap-2 self-stretch">
                    <InputLabel for="accountType" :value="$t('public.account_type_placeholder')" />
                    <div class="grid grid-cols-2 items-start gap-3 self-stretch">
                        <div
                            v-for="(account, index) in accountOptions"
                            :key="account.account_group"
                            @click="selectAccount(account.account_group)"
                            class="group col-span-1 items-start py-3 px-4 gap-1 self-stretch rounded-lg border shadow-input transition-colors duration-300 select-none cursor-pointer"
                            :class="{
                                'bg-primary-50 border-primary-500': selectedAccountType === account.account_group,
                                'bg-white border-gray-300 hover:bg-primary-50 hover:border-primary-500': selectedAccountType !== account.account_group,
                                'border-error-500': liveAccountForm.errors.leverage,
                            }"
                        >
                            <div class="flex items-center gap-3 self-stretch">
                                <span
                                    class="flex-grow text-sm font-semibold transition-colors duration-300 group-hover:text-primary-700"
                                    :class="{
                                        'text-primary-700': selectedAccountType === account.account_group,
                                        'text-gray-950': selectedAccountType !== account.account_group
                                    }"
                                >
                                    {{ $t(`public.${account.slug}`) }}
                                </span>
                                <IconCircleCheckFilled v-if="selectedAccountType === account.account_group" size="20" stroke-width="1.25" color="#2970FF" />
                            </div>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-2 items-start gap-1 self-stretch">
                    <InputLabel for="leverage" :value="$t('public.leverages')" class="col-span-2"/>
                    <Dropdown
                        v-model="liveAccountForm.leverage"
                        :options="leverages"
                        optionLabel="name"
                        optionValue="value"
                        class="col-span-1"
                        scroll-height="236px"
                        :invalid="!!liveAccountForm.errors.leverage"
                        :disabled="!accountOptions.find(account => account.account_group === selectedAccountType) || accountOptions.find(account => account.account_group === selectedAccountType)?.leverage !== 0"
                        >
                    <template #value="slotProps">
                        <span :class="{
                            'text-gray-400': !accountOptions.find(account => account.account_group === selectedAccountType) || accountOptions.find(account => account.account_group === selectedAccountType)?.leverage !== 0
                        }">
                            {{ leverages.find(option => option.value === slotProps.value)?.name || slotProps.value || $t('public.leverages_placeholder') }}
                        </span>
                    </template>
                    </Dropdown>
                </div>
            </div>
            <div class="self-stretch">
                <div class="text-gray-500 text-xs">{{ $t('public.agreement_text') }}
                    <TermsAndCondition
                        :termsLabel="$t('public.trading_account_agreement')"
                        :terms="terms"
                    />.
                </div>
            </div>
        </div>
        <div class="flex justify-end items-center pt-5 gap-4 self-stretch md:pt-7">
            <Button variant="primary-flat" type="button" :class="{ 'opacity-25': liveAccountForm.processing }" :disabled="liveAccountForm.processing" @click.prevent="openLiveAccount">{{ $t('public.open_live_account') }}</Button>
        </div>
    </Dialog>

    <Dialog v-model:visible="showDemoAccountDialog" modal :header="$t('public.open_demo_account')" class="dialog-xs sm:dialog-sm">
        <div class="flex flex-col items-center gap-8 self-stretch sm:gap-10">
            <div class="flex flex-col items-center gap-5 self-stretch">
                <div class="flex flex-col items-start gap-2 self-stretch">
                    <InputLabel for="amount" :value="$t('public.amount')" />
                    <IconField iconPosition="left" class="w-full">
                        <div class="text-gray-950 text-sm">$</div>
                        <InputText
                            id="amount"
                            type="number"
                            class="block w-full"
                            v-model="demoAccountForm.amount"
                            :placeholder="$t('public.amount_placeholder')"
                            :invalid="!!demoAccountForm.errors.amount"
                        />
                    </IconField>
                    <InputError :message="demoAccountForm.errors.amount" />
                </div>
                <div class="flex flex-col items-start gap-1 self-stretch">
                    <InputLabel for="leverage" :value="$t('public.leverage')" />
                    <Dropdown
                        v-model="demoAccountForm.leverage"
                        :options="leverages"
                        optionLabel="name"
                        optionValue="value"
                        class="w-full"
                        scroll-height="236px"
                        :invalid="!!demoAccountForm.errors.leverage"
                    >
                    <template #value="slotProps">
                        <span :class="{
                            'text-gray-400': !!accountOptions.find(account => account.account_group === selectedAccountType)?.leverage
                        }">
                            {{ leverages.find(option => option.value === slotProps.value)?.name || slotProps.value || $t('public.leverages_placeholder') }}
                        </span>
                    </template>
                    </Dropdown>
                </div>
            </div>
            <div class="self-stretch">
                <div class="text-gray-500 text-xs">{{ $t('public.agreement_text') }}
                    <TermsAndCondition
                        :termsLabel="$t('public.trading_account_agreement')"
                        :terms="terms"
                    />.
                </div>
            </div>
        </div>
        <div class="flex justify-end items-center pt-5 gap-4 self-stretch md:pt-7">
            <Button variant="primary-flat" type="button" :class="{ 'opacity-25': demoAccountForm.processing }" :disabled="demoAccountForm.processing" @click.prevent="openDemoAccount">{{ $t('public.open_demo_account') }}</Button>
        </div>
    </Dialog>
</template>
