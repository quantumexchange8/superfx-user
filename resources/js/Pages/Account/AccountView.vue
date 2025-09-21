<script setup>
import Empty from "@/Components/Empty.vue";
import {onMounted, ref, watch, watchEffect} from "vue";
import {FilterMatchMode} from "primevue/api";
import DataView from 'primevue/dataview';
import Action from "@/Pages/Account/Partials/Action.vue";
import {generalFormat, transactionFormat} from "@/Composables/index.js";
import Dropdown from "primevue/dropdown";
import {usePage} from "@inertiajs/vue3";
import DepositAccount from "@/Pages/Account/Partials/DepositAccount.vue";
import AccountTransfer from "@/Pages/Account/Partials/AccountTransfer.vue";
import Button from "@/Components/Button.vue";
import {SwitchHorizontal01Icon} from "@/Components/Icons/outline.jsx";
import {IconDots} from "@tabler/icons-vue";

const props = defineProps({
    slug: String,
    methods: Array,
    accountsCount: Object,
});

const isLoading = ref(false);
const dv = ref(null);
const tradingAccounts = ref([]);
const totalRecords = ref(props.accountsCount[props.slug]);
const first = ref(0);
const { formatRgbaColor } = generalFormat();
const {formatAmount} = transactionFormat();

const filters = ref({
    trading_platform: { value: props.slug, matchMode: FilterMatchMode.EQUALS },
    account_type: { value: null, matchMode: FilterMatchMode.EQUALS },
});

const lazyParams = ref({});

const loadLazyData = (event) => {
    isLoading.value = true;

    lazyParams.value = { ...lazyParams.value, first: event?.first || first.value };
    lazyParams.value.filters = filters.value;
    try {
        setTimeout(async () => {
            const params = {
                page: JSON.stringify(event?.page + 1),
                include: [],
                lazyEvent: JSON.stringify(lazyParams.value)
            };

            const url = route('account.getAccountsData', params);
            const response = await fetch(url);
            const results = await response.json();

            tradingAccounts.value = results?.data?.data;

            totalRecords.value = results?.data?.total;

            isLoading.value = false;

        }, 100);
    }  catch (e) {
        tradingAccounts.value = [];
        totalRecords.value = 0;
        isLoading.value = false;
    }
};

const onPage = (event) => {
    lazyParams.value = event;
    loadLazyData(event);
};
const onSort = (event) => {
    lazyParams.value = event;
    loadLazyData(event);
};
const onFilter = (event) => {
    lazyParams.value.filters = filters.value ;
    loadLazyData(event);
};

onMounted(() => {
    lazyParams.value = {
        first: dv.value.first,
        rows: dv.value.rows,
        filters: filters.value
    }

    loadLazyData();
    getAccountTypeByPlatform();
});

watch([filters.value['account_type']], () => {
    loadLazyData();
});

const accountTypes = ref([])
const loadingAccountTypes = ref(false);

const getAccountTypeByPlatform = async () => {
    loadingAccountTypes.value = true;

    try {
        const response = await axios.get(
            `/getAccountTypeByPlatform?trading_platform=${filters.value['trading_platform'].value}`
        );

        accountTypes.value = response.data.accountTypes;
    } catch (error) {
        console.error('Error getting account types:', error);
    } finally {
        loadingAccountTypes.value = false;
    }
};

watchEffect(() => {
    if (usePage().props.toast !== null || usePage().props.notification !== null) {
        loadLazyData();
    }
});
</script>

<template>
    <DataView
        :value="tradingAccounts"
        layout="grid"
        class="w-full"
        :lazy="true"
        :paginator="accountsCount[slug] > 0"
        :rows="12"
        :first="first"
        paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport"
        :currentPageReportTemplate="$t('public.paginator_caption')"
        v-model:filters="filters"
        ref="dv"
        dataKey="id"
        :totalRecords="totalRecords"
        @page="onPage($event)"
        @sort="onSort($event)"
        @filter="onFilter($event)"
        :globalFilterFields="['master_name', 'trader_name']"
    >
        <template #empty>
            <div
                v-if="accountsCount[slug] === 0"
            >
                <Empty
                    :title="$t('public.empty_live_acccount_title')"
                    :message="$t('public.empty_live_acccount_message')"
                />
            </div>
            <div
                v-else
            >
                <div
                    v-if="isLoading"
                    class="w-full grid grid-cols-1 gap-5 md:grid-cols-2"
                >
                    <div
                        v-for="(index) in accountsCount[slug]"
                        class="flex flex-col justify-center items-center py-4 pl-6 pr-3 gap-5 flex-grow md:pr-6 rounded-2xl border-l-8 bg-white shadow-toast w-full animate-pulse"
                    >
                        <div class="flex items-center gap-5 self-stretch">
                            <div class="w-32 h-3 bg-gray-200 rounded-full my-2"></div>
                            <div
                                class="flex px-2 py-1 items-center text-xs font-semibold hover:-translate-y-1 transition-all duration-300 ease-in-out rounded w-full"
                            >
                                <div class="w-20 h-2.5 bg-gray-200 rounded-full my-2"></div>
                            </div>

                            <Button
                                variant="gray-text"
                                size="sm"
                                type="button"
                                iconOnly
                                pill
                                aria-haspopup="true"
                                aria-controls="overlay_tmenu"
                                disabled
                            >
                                <IconDots size="16" stroke-width="1.25" color="#667085" />
                            </Button>
                        </div>
                        <div class="grid grid-cols-2 gap-2 self-stretch">
                            <div class="w-full flex items-center gap-1 flex-grow">
                                <span class="w-16 text-gray-500 text-xs">{{ $t('public.balance') }}:</span>
                                <div class="w-20 h-2 bg-gray-200 rounded-full my-1"></div>
                            </div>
                            <div class="w-full flex items-center gap-1 flex-grow">
                                <span class="w-16 text-gray-500 text-xs">{{ $t('public.equity') }}:</span>
                                <div class="w-20 h-2 bg-gray-200 rounded-full my-1"></div>
                            </div>
                            <div class="w-full flex items-center gap-1 flex-grow">
                                <span class="w-16 text-gray-500 text-xs">{{ $t('public.credit') }}:</span>
                                <div class="w-20 h-2 bg-gray-200 rounded-full my-1"></div>
                            </div>
                            <div class="w-full flex items-center gap-1 flex-grow">
                                <span class="w-16 text-gray-500 text-xs">{{ $t('public.leverage') }}:</span>
                                <div class="w-20 h-2 bg-gray-200 rounded-full my-1"></div>
                            </div>
                        </div>

                        <div class="flex justify-end items-center gap-3 self-stretch">
                            <Button
                                type="button"
                                variant="gray-outlined"
                                size="sm"
                                class="w-full"
                                disabled
                            >
                                {{ $t('public.deposit') }}
                            </Button>
                            <Button
                                type="button"
                                variant="gray-outlined"
                                size="sm"
                                pill
                                iconOnly
                                disabled
                            >
                                <SwitchHorizontal01Icon class="w-4 text-gray-950" />
                            </Button>
                        </div>
                    </div>
                </div>
                <div
                    v-else
                >
                    <Empty
                        :title="$t('public.empty_live_acccount_title')"
                        :message="$t('public.empty_live_acccount_message')"
                    />
                </div>
            </div>
        </template>

        <template #header>
            <div class="flex flex-col md:flex-row gap-3 items-center self-stretch">
                <Dropdown
                    v-model="filters['account_type'].value"
                    :options="accountTypes"
                    option-label="name"
                    option-value="name"
                    class="w-full md:w-60"
                    scroll-height="236px"
                    :placeholder="$t('public.account_type_placeholder')"
                    :loading="loadingAccountTypes || isLoading"
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
        </template>

        <template #grid="slotProps">
            <div
                v-if="isLoading"
                class="w-full grid grid-cols-1 gap-5 md:grid-cols-2"
            >
                <div
                    v-for="(index) in accountsCount[slug]"
                    class="flex flex-col justify-center items-center py-4 pl-6 pr-3 gap-5 flex-grow md:pr-6 rounded-2xl border-l-8 bg-white shadow-toast w-full animate-pulse"
                >
                    <div class="flex items-center gap-5 self-stretch">
                        <div class="w-32 h-3 bg-gray-200 rounded-full my-2"></div>
                        <div
                            class="flex px-2 py-1 items-center text-xs font-semibold hover:-translate-y-1 transition-all duration-300 ease-in-out rounded w-full"
                        >
                            <div class="w-20 h-2.5 bg-gray-200 rounded-full my-2"></div>
                        </div>

                        <Button
                            variant="gray-text"
                            size="sm"
                            type="button"
                            iconOnly
                            pill
                            aria-haspopup="true"
                            aria-controls="overlay_tmenu"
                            disabled
                        >
                            <IconDots size="16" stroke-width="1.25" color="#667085" />
                        </Button>
                    </div>
                    <div class="grid grid-cols-2 gap-2 self-stretch">
                        <div class="w-full flex items-center gap-1 flex-grow">
                            <span class="w-16 text-gray-500 text-xs">{{ $t('public.balance') }}:</span>
                            <div class="w-20 h-2 bg-gray-200 rounded-full my-1"></div>
                        </div>
                        <div class="w-full flex items-center gap-1 flex-grow">
                            <span class="w-16 text-gray-500 text-xs">{{ $t('public.equity') }}:</span>
                            <div class="w-20 h-2 bg-gray-200 rounded-full my-1"></div>
                        </div>
                        <div class="w-full flex items-center gap-1 flex-grow">
                            <span class="w-16 text-gray-500 text-xs">{{ $t('public.credit') }}:</span>
                            <div class="w-20 h-2 bg-gray-200 rounded-full my-1"></div>
                        </div>
                        <div class="w-full flex items-center gap-1 flex-grow">
                            <span class="w-16 text-gray-500 text-xs">{{ $t('public.leverage') }}:</span>
                            <div class="w-20 h-2 bg-gray-200 rounded-full my-1"></div>
                        </div>
                    </div>

                    <div class="flex justify-end items-center gap-3 self-stretch">
                        <Button
                            type="button"
                            variant="gray-outlined"
                            size="sm"
                            class="w-full"
                            disabled
                        >
                            {{ $t('public.deposit') }}
                        </Button>
                        <Button
                            type="button"
                            variant="gray-outlined"
                            size="sm"
                            pill
                            iconOnly
                            disabled
                        >
                            <SwitchHorizontal01Icon class="w-4 text-gray-950" />
                        </Button>
                    </div>
                </div>
            </div>

            <div
                v-else
                class="w-full grid grid-cols-1 gap-5 md:grid-cols-2"
            >
                <div
                    v-for="account in slotProps.items"
                    :key="account.id"
                    class="flex flex-col justify-center items-center py-4 pl-6 pr-3 gap-5 flex-grow md:pr-6 rounded-2xl border-l-8 bg-white shadow-toast w-full"
                    :style="{'borderColor': `#${account.account_type.color}`}"
                >
                    <div class="flex items-center gap-5 self-stretch">
                        <div class="flex items-center content-center gap-3 md:gap-4 flex-grow">
                            <span class="text-gray-950 font-semibold md:text-lg">#{{ account.meta_login }}</span>
                            <div
                                class="flex px-2 py-1 justify-center items-center text-xs font-semibold hover:-translate-y-1 transition-all duration-300 ease-in-out rounded"
                                :style="{
                            backgroundColor: formatRgbaColor(account.account_type.color, 0.15),
                            color: `#${account.account_type.color}`,
                        }"
                            >
                                {{ (account.account_type.member_display_name ?? account.account_type.name) }}
                            </div>
                        </div>
                        <Action
                            :account="account"
                        />
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
                            <span class="text-gray-950 text-xs font-medium">1:{{ account.margin_leverage }}</span>
                        </div>
                    </div>
                    <div class="flex justify-end items-center gap-3 self-stretch">
                        <DepositAccount
                            :account="account"
                            :methods="methods"
                        />
                        <AccountTransfer
                            :account="account"
                        />
                    </div>
                </div>
            </div>
        </template>
    </DataView>
</template>
