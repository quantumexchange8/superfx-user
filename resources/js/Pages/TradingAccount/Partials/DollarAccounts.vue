<script setup>
import {ref, onMounted, watchEffect, watch} from "vue";
import Action from "@/Pages/TradingAccount/Partials/Action.vue";
import ActionButton from "@/Pages/TradingAccount/Partials/ActionButton.vue";
import Empty from '@/Components/Empty.vue';
import {generalFormat, transactionFormat} from "@/Composables/index.js";
import {usePage} from "@inertiajs/vue3";
import Dropdown from "primevue/dropdown";

const props = defineProps({
    user: Object,
    methods: Array,
    tradingPlatforms: Array,
})

const isLoading = ref(false);
const accounts = ref([]);
const accountType = ref('dollar');
const { formatAmount } = transactionFormat();
const { formatRgbaColor } = generalFormat();

// Fetch live accounts from the backend
const fetchLiveAccounts = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get(`/account/getLiveAccount?accountType=${accountType.value}&trading_platform=${selectedTradingPlatform.value}`);
        accounts.value = response.data;
    } catch (error) {
        console.error('Error fetching live accounts:', error);
    } finally {
        isLoading.value = false;
    }
};

watchEffect(() => {
    if (usePage().props.toast !== null || usePage().props.notification !== null) {
        fetchLiveAccounts();
    }
});

const selectedTradingPlatform = ref(props.tradingPlatforms[0].slug);
const selectedAccountType = ref()
const accountTypes = ref([])
const loadingAccountTypes = ref(false);

const getAccountTypeByPlatform = async () => {
    loadingAccountTypes.value = true;

    try {
        const response = await axios.get(
            `/getAccountTypeByPlatform?trading_platform=${selectedTradingPlatform.value}`
        );

        const allAccountTypes = response.data.accountTypes;

        // Only keep account types where category === accountType.value
        accountTypes.value = allAccountTypes.filter(
            acc => acc.category === accountType.value
        );
    } catch (error) {
        console.error('Error getting account types:', error);
    } finally {
        loadingAccountTypes.value = false;
    }
};

watch(selectedTradingPlatform, () => {
    getAccountTypeByPlatform()
    fetchLiveAccounts();
}, {immediate: true})
</script>

<template>
    <div class="flex items-center gap-5 self-stretch w-full">
        <Dropdown
            v-model="selectedTradingPlatform"
            :options="tradingPlatforms"
            option-label="slug"
            option-value="slug"
            class="w-full md:w-60"
            scroll-height="236px"
            :placeholder="$t('public.leverages_placeholder')"
        >
            <template #value="{value, placeholder}">
                <div v-if="value">
                    <span class="uppercase">{{ value }}</span>
                </div>
                <div v-else>
                    {{ placeholder }}
                </div>
            </template>
            <template #option="{option}">
                <span class="uppercase">{{ option.slug }}</span>
            </template>
        </Dropdown>

        <Dropdown
            v-model="selectedAccountType"
            :options="accountTypes"
            option-label="name"
            option-value="name"
            class="w-full md:w-60"
            scroll-height="236px"
            :placeholder="$t('public.account_type_placeholder')"
            :loading="loadingAccountTypes"
        >
            <template #value="{value, placeholder}">
                <div v-if="value">
                    <span class="uppercase">{{ value }}</span>
                </div>
                <div v-else>
                    {{ placeholder }}
                </div>
            </template>
            <template #option="{option}">
                <span class="uppercase">{{ option.name }}</span>
            </template>
        </Dropdown>
    </div>

    <div
        v-if="isLoading"
        class="flex flex-col justify-center items-center py-4 pl-6 pr-3 gap-5 flex-grow md:pr-6 rounded-2xl border-l-8 bg-white shadow-toast w-1/2 animate-pulse"
    >
        <div class="flex items-center gap-5 self-stretch">
            <div class="w-32 h-3 bg-gray-200 rounded-full my-2"></div>
            <div
                class="flex px-2 py-1 justify-center items-center text-xs font-semibold hover:-translate-y-1 transition-all duration-300 ease-in-out rounded"
            >
                <div class="w-20 h-2.5 bg-gray-200 rounded-full my-2"></div>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-2 self-stretch">
            <div class="w-full flex items-center gap-1 flex-grow">
                <span class="w-16 text-gray-500 text-xs">{{ $t('public.balance') }}:</span>
                <div class="w-20 h-2 bg-gray-200 rounded-full my-2"></div>
            </div>
            <div class="w-full flex items-center gap-1 flex-grow">
                <span class="w-16 text-gray-500 text-xs">{{ $t('public.equity') }}:</span>
                <div class="w-20 h-2 bg-gray-200 rounded-full my-2"></div>
            </div>
            <div class="w-full flex items-center gap-1 flex-grow">
                <span class="w-16 text-gray-500 text-xs">{{ $t('public.credit') }}:</span>
                <div class="w-20 h-2 bg-gray-200 rounded-full my-2"></div>
            </div>
            <div class="w-full flex items-center gap-1 flex-grow">
                <span class="w-16 text-gray-500 text-xs">{{ $t('public.leverage') }}:</span>
                <div class="w-20 h-2 bg-gray-200 rounded-full my-2"></div>
            </div>
        </div>
    </div>

    <div
        v-if="!isLoading && accounts.length > 0"
        class="w-full grid grid-cols-1 gap-5 md:grid-cols-2"
    >
        <div
            v-for="account in accounts"
            :key="account.id"
            class="flex flex-col justify-center items-center py-4 pl-6 pr-3 gap-5 flex-grow md:pr-6 rounded-2xl border-l-8 bg-white shadow-toast w-full"
            :style="{'borderColor': `#${account.account_type_color}`}"
        >
            <div class="flex items-center gap-5 self-stretch">
                <div class="flex items-center content-center gap-3 md:gap-4 flex-grow">
                    <span class="text-gray-950 font-semibold md:text-lg">#{{ account.meta_login }}</span>
                    <div
                        class="flex px-2 py-1 justify-center items-center text-xs font-semibold hover:-translate-y-1 transition-all duration-300 ease-in-out rounded"
                        :style="{
                            backgroundColor: formatRgbaColor(account.account_type_color, 0.15),
                            color: `#${account.account_type_color}`,
                        }"
                    >
                        {{ (account.member_display_name ?? account.account_type_name) }}
                    </div>
                </div>
                <Action :account="account" :type="accountType" />
            </div>
            <div class="grid grid-cols-2 gap-2 self-stretch">
                <div class="w-full flex items-center gap-1 flex-grow">
                    <span class="w-16 text-gray-500 text-xs">{{ $t('public.balance') }}:</span>
                    <span class="text-gray-950 text-xs font-medium">$&nbsp;{{ formatAmount(account.balance ?? 0) }}</span>
                </div>
                <div class="w-full flex items-center gap-1 flex-grow">
                    <span class="w-16 text-gray-500 text-xs">{{ $t('public.equity') }}:</span>
                    <span class="text-gray-950 text-xs font-medium">$&nbsp;{{ formatAmount(account.equity ?? 0) }}</span>
                </div>
                <div class="w-full flex items-center gap-1 flex-grow">
                    <span class="w-16 text-gray-500 text-xs">{{ $t('public.credit') }}:</span>
                    <span class="text-gray-950 text-xs font-medium">$&nbsp;{{ formatAmount(account.credit ?? 0) }}</span>
                </div>
                <div class="w-full flex items-center gap-1 flex-grow">
                    <span class="w-16 text-gray-500 text-xs">{{ $t('public.leverage') }}:</span>
                    <span class="text-gray-950 text-xs font-medium">1:{{ account.leverage }}</span>
                </div>
            </div>
            <div class="flex justify-end items-center gap-3 self-stretch">
                <ActionButton
                    :account="account"
                    :methods="methods"
                />
            </div>
        </div>
    </div>

    <Empty
        v-else-if="!isLoading && accounts.length === 0"
        :title="$t('public.empty_live_acccount_title')"
        :message="$t('public.empty_live_acccount_message')"
    />

</template>
