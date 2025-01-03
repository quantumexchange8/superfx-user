<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { IconSearch, IconCircleXFilled, IconUserDollar, IconPremiumRights, IconAdjustments } from '@tabler/icons-vue';
import {h, ref, watch, watchEffect} from 'vue';
import InputText from 'primevue/inputtext';
import DefaultProfilePhoto from '@/Components/DefaultProfilePhoto.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import {transactionFormat} from "@/Composables/index.js";
import Button from '@/Components/Button.vue';
import Badge from '@/Components/Badge.vue';
import Dropdown from 'primevue/dropdown';
import { wTrans } from 'laravel-vue-i18n';
import AssetMasterAction from "@/Pages/AssetMaster/Partials/AssetMasterAction.vue";
import { usePage } from '@inertiajs/vue3';
import debounce from "lodash/debounce.js";
import Empty from "@/Components/Empty.vue";
import {NoAssetMaster} from "@/Components/Icons/solid.jsx";
import Paginator from "primevue/paginator"
import OverlayPanel from "primevue/overlaypanel"
import Checkbox from "primevue/checkbox"
import Slider from 'primevue/slider';
import ApplicationLogo from '@/Components/ApplicationLogo.vue'
import { Link } from '@inertiajs/vue3'


const props = defineProps({
    terms: Object,
})

const { formatAmount } = transactionFormat();

const sortingDropdownOptions = [
    {
        name: wTrans('public.latest'),
        value: 'latest'
    },
    {
        name: wTrans('public.popular'),
        value: 'popular'
    },
    {
        name: wTrans('public.largest_fund'),
        value: 'largest_fund'
    },
    {
        name: wTrans('public.most_investor'),
        value: 'most_investors'
    },
    {
        name: wTrans('public.my_favourites'),
        value: 'favourites'
    },
    {
        name: wTrans('public.my_joining'),
        value: 'joining'
    },
]

const masters = ref([]);
const masterLoading = ref(false);
const sorting = ref(sortingDropdownOptions[0].value)
const search = ref('');

const selectedTags = ref([]);
const minInvestmentAmountRange = ref([0, 5000000]);
const currentPage = ref(1);
const rowsPerPage = ref(12);
const totalRecords = ref(0);

const getResults = async (page = 1, pagination = rowsPerPage.value) => {
    masterLoading.value = true;

    try {
        let url = `/asset_master/getMasters?page=${page}&limit=${pagination}`;

        if (sorting.value) {
            url += `&sorting=${sorting.value}`;
        }

        if (selectedTags.value) {
            url += `&tag=${selectedTags.value}`;
        }

        // if (minInvestmentAmountRange.value) {
        //     url += `&minInvestmentAmountRange=${minInvestmentAmountRange.value}`;
        // }

        if (search.value) {
            url += `&search=${search.value}`;
        }

        const response = await axios.get(url);
        masters.value = response.data.masters;
        totalRecords.value = response.data.totalRecords;
        currentPage.value = response.data.currentPage;
    } catch (error) {
        console.error('Error getting masters:', error);
    } finally {
        masterLoading.value = false;
    }
};

getResults(currentPage.value, rowsPerPage.value);

const onPageChange = (event) => {
    currentPage.value = event.page + 1;
    getResults(currentPage.value);
};

const clearSearch = () => {
    search.value = '';
};

// overlay panel
const op = ref();
const filterCount = ref(0);

const toggle = (event) => {
    op.value.toggle(event);
}

const tags = ref([
    {name: "no_min", key: "no_min"},
    {name: "lock_free", key: "lock_free"},
    {name: "zero_fee", key: "zero_fee"},
]);


watchEffect(() => {
    if (usePage().props.toast !== null) {
        getResults();
        getAvailableAccounts();
    }
});

const clearFilter = () => {
    selectedTags.value = [];
    minInvestmentAmountRange.value = [];
};

// Watchers for search
watch(search, debounce(() => {
    getResults(1);
}, 300));

// Watchers for min invest amount range
watch(minInvestmentAmountRange, debounce(() => {
    getResults(1);
}, 300));

// Watchers for sortType
watch(sorting, () => {
    getResults(1);
});

watch([selectedTags], () => {
    filterCount.value = [selectedTags].reduce((count, ref) => {
        if (Array.isArray(ref.value)) {
            if (ref.value.length > 0) count += ref.value.length; // Count the elements in the array
        } else if (ref.value) {
            count++; // Count non-array values that are truthy
        }
        return count;
    }, 0);

    getResults(1);
});

const viewPammInfo = (index) => {
    window.open(route('asset_master.showPammInfo', masters.value[index].id), '_self')
}

const accounts = ref([]);
const isLoading = ref(false);

const getAvailableAccounts = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get('/asset_master/getAvailableAccounts');
        accounts.value = response.data.accounts;
    } catch (error) {
        console.error('Error get masters:', error);
    } finally {
        isLoading.value = false;
    }
};

getAvailableAccounts();
</script>

<template>
    <AuthenticatedLayout :title="$t('public.asset_master')">
        <div class="flex flex-col items-center gap-5 self-stretch">

            <div class="relative flex flex-col items-center gap-5 w-full h-full">
                <!-- Banner Image -->
                <a href="https://home.superforex.com/" target="blank" class="w-full h-full">
                <img src="/img/banner-pamm.svg" alt="" class="w-full h-full hover:cursor-pointer">
                
                <!-- Logo and Text Block -->
                <div class="absolute top-[8%] left-[4%]">
                    <div class="flex gap-1 md:gap-3 items-center">
                        <ApplicationLogo aria-hidden="true" class="size-[3%] fill-white" />
                        <div class="text-[6px] md:text-base 3xl:text-xl font-bold text-white mt-0.5 md:mt-0">
                            SuperForex.
                        </div>
                    </div>
                </div>

                <!-- Headline and Description -->
                <div class="absolute top-[28%] left-[4%]">
                    <div class="max-w-[177px] md:max-w-[385px] xl:max-w-[520px] 3xl:max-w-[760px] flex flex-col">
                        <div class="font-bold text-white text-xs md:text-[27px] xl:text-[36px] 3xl:text-[53px] w-full leading-[14px] md:leading-8 xl:leading-10 3xl:leading-[50px]">{{ $t('public.banner_maximize_return') }}</div>
                        <div class="font-bold text-transparent bg-clip-text bg-gradient-to-b from-[#FEDC32] to-[#F79009] text-xs md:text-[27px] xl:text-[36px] 3xl:text-[53px] w-full leading-[14px] md:leading-8 xl:leading-10 3xl:leading-[70px]">{{ $t('public.banner_pamm') }}</div>
                    </div>
                    <div class="text-white text-[6px] md:text-xs xl:text-sm 3xl:text-lg md:mt-1">
                        {{ $t('public.banner_message') }}
                    </div>
                </div>
                    <div class="absolute bottom-[9%] left-[6%] font-semibold text-white text-[4px] md:text-[9px] xl:text-[12px] 3xl:text-[18px]">
                        www.superforex.com
                    </div>
                </a>
            </div>

            <div class="flex flex-col md:flex-row gap-3 items-center self-stretch">
                <div class="relative w-full md:w-60">
                    <div class="absolute top-2/4 -mt-[9px] left-4 text-gray-400">
                        <IconSearch size="20" stroke-width="1.25" />
                    </div>
                    <InputText v-model="search" :placeholder="$t('public.keyword_search')" class="font-normal pl-12 w-full md:w-60" />
                    <div
                        v-if="search !== ''"
                        class="absolute top-2/4 -mt-2 right-4 text-gray-300 hover:text-gray-400 select-none cursor-pointer"
                        @click="clearSearch"
                    >
                        <IconCircleXFilled size="16" />
                    </div>
                </div>

                <div class="w-full flex md:justify-between items-center self-stretch gap-3">
                    <Button
                        variant="gray-outlined"
                        @click="toggle"
                        size="sm"
                        class="flex gap-3 items-center justify-center py-3 w-full md:w-[130px]"
                    >
                        <IconAdjustments size="20" color="#0C111D" stroke-width="1.25" />
                        <div class="text-sm text-gray-950 font-medium">
                            {{ $t('public.filter') }}
                        </div>
                        <Badge class="w-5 h-5 text-xs text-white" variant="numberbadge">
                            {{ filterCount }}
                        </Badge>
                    </Button>
                    <Dropdown
                        v-model="sorting"
                        :options="sortingDropdownOptions"
                        optionLabel="name"
                        option-value="value"
                        class="w-full md:w-40"
                        scrollHeight="236px"
                    />
                </div>
            </div>

            <div
                v-if="masterLoading"
                class="grid grid-cols-1 md:grid-cols-2 2xl:grid-cols-4 gap-5 self-stretch"
            >
                <div class="w-full p-6 flex flex-col items-center gap-4 rounded-2xl bg-white shadow-toast">
                    <div class="w-full flex items-center gap-4 hover:cursor-pointer">
                        <div class="w-[42px] h-[42px] shrink-0 grow-0 rounded-full overflow-hidden animate-pulse">
                            <DefaultProfilePhoto />
                        </div>
                        <div class="flex flex-col items-start animate-pulse">
                            <div class="h-2 bg-gray-200 rounded-full w-40 my-2"></div>
                            <div class="h-2 bg-gray-200 rounded-full w-36 my-1.5"></div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 self-stretch">
                        <div class="h-2 bg-info-200 rounded-full w-12 my-2"></div>
                        <div class="h-2 bg-gray-200 rounded-full w-12 my-2"></div>
                        <div class="h-2 bg-gray-200 rounded-full w-12 my-2"></div>
                    </div>

                    <div class="py-2 flex justify-center items-center gap-2 self-stretch border-y border-solid border-gray-200">
                        <div class="w-full flex flex-col items-center">
                            <div class="h-2 bg-gray-200 rounded-full w-16 my-2"></div>
                            <div class="self-stretch text-gray-500 text-center text-xs">
                                {{ $t('public.total_gain') }}
                            </div>
                        </div>
                        <div class="w-full flex flex-col items-center">
                            <div class="h-2 bg-gray-200 rounded-full w-16 my-2"></div>
                            <div class="self-stretch text-gray-500 text-center text-xs">
                                {{ $t('public.monthly_gain') }}
                            </div>
                        </div>
                        <div class="w-full flex flex-col items-center">
                            <div class="h-2 bg-gray-200 rounded-full w-16 my-2"></div>
                            <div class="self-stretch text-gray-500 text-center text-xs">
                                {{ $t('public.latest') }}
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col items-center gap-1 self-stretch">
                        <div class="py-1 flex items-center gap-3 self-stretch">
                            <IconUserDollar size="20" stroke-width="1.25" />
                            <div class="h-2 bg-gray-200 rounded-full w-40 my-1.5"></div>
                        </div>
                        <div class="py-1 flex items-center gap-3 self-stretch">
                            <IconPremiumRights size="20" stroke-width="1.25" />
                            <div class="h-2 bg-gray-200 rounded-full w-40 my-1.5"></div>
                        </div>
                    </div>

                    <AssetMasterAction
                        :master="null"
                    />
                </div>
            </div>

            <div
                v-if="!masterLoading && masters.length > 0"
                class="w-full"
            >
                <div class="w-full grid grid-cols-1 md:grid-cols-2 2xl:grid-cols-4 gap-5 self-stretch">
                    <div
                        v-for="(master, index) in masters"
                        :key="index"
                        class="w-full p-6 flex flex-col items-center gap-4 rounded-2xl bg-white shadow-toast"
                    >
                        <div
                            class="flex flex-col gap-4 items-center self-stretch hover:cursor-pointer"
                            @click="viewPammInfo(index)"
                        >
                            <div class="w-full flex items-center gap-4">
                                <div class="w-[42px] h-[42px] shrink-0 grow-0 rounded-full overflow-hidden">
                                    <div v-if="master.master_profile_photo">
                                        <img :src="master.master_profile_photo" alt="Profile Photo" />
                                    </div>
                                    <div v-else>
                                        <DefaultProfilePhoto />
                                    </div>
                                </div>
                                <div class="flex flex-col items-start">
                                    <div class="self-stretch truncate w-44 md:w-full md:max-w-[240px] xl:max-w-full 2xl:max-w-[200px] 3xl:max-w-[240px] text-gray-950 font-bold">
                                        {{ master.asset_name }}
                                    </div>
                                    <div class="self-stretch truncate w-36 text-gray-500 text-sm">
                                        {{ master.trader_name }}
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 self-stretch">
                                <StatusBadge value="info">
                                    $ {{ formatAmount(master.minimum_investment) }}
                                </StatusBadge>
                                <StatusBadge value="gray">
                                    <div v-if="master.minimum_investment_period !== 0">
                                        {{ master.minimum_investment_period }} {{ $t('public.months') }}
                                    </div>
                                    <div v-else>
                                        {{ $t('public.lock_free') }}
                                    </div>
                                </StatusBadge>
                                <StatusBadge value="gray">
                                    {{ master.performance_fee > 0 ? formatAmount(master.performance_fee, 0) + '% ' + $t('public.fee') : $t('public.zero_fee') }}
                                </StatusBadge>
                            </div>

                            <div class="py-2 flex justify-center items-center gap-2 self-stretch border-y border-solid border-gray-200">
                                <div class="w-full flex flex-col items-center">
                                    <div class="self-stretch text-gray-950 text-center font-semibold">
                                        {{ formatAmount(master.total_gain) }}%
                                    </div>
                                    <div class="self-stretch text-gray-500 text-center text-xs">
                                        {{ $t('public.total_gain') }}
                                    </div>
                                </div>
                                <div class="w-full flex flex-col items-center">
                                    <div class="self-stretch text-gray-950 text-center font-semibold">
                                        {{ formatAmount(master.monthly_gain) }}%
                                    </div>
                                    <div class="w-16 sm:w-auto mx-auto truncate self-stretch text-gray-500 text-center text-xs">
                                        {{ $t('public.monthly_gain') }}
                                    </div>
                                </div>
                                <div class="w-full flex flex-col items-center">
                                    <div class="self-stretch text-center font-semibold">
                                        <div
                                            v-if="master.latest_profit !== 0"
                                            :class="(master.latest_profit < 0) ? 'text-error-500' : 'text-success-500'"
                                        >
                                            {{ formatAmount(master.latest_profit) }}%
                                        </div>
                                        <div
                                            v-else
                                            class="text-gray-950"
                                        >
                                            -
                                        </div>
                                    </div>
                                    <div class="self-stretch text-gray-500 text-center text-xs">
                                        {{ $t('public.latest') }}
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-col items-center gap-1 self-stretch">
                                <div class="py-1 flex items-center gap-3 self-stretch">
                                    <IconUserDollar size="20" stroke-width="1.25" />
                                    <div class="w-full text-gray-950 text-sm font-medium">
                                        {{ master.total_investors }} {{ $t('public.investors') }}
                                    </div>
                                </div>
                                <div class="py-1 flex items-center gap-3 self-stretch">
                                    <IconPremiumRights size="20" stroke-width="1.25" />
                                    <div class="w-full text-gray-950 text-sm font-medium">
                                        {{ $t('public.total_fund_size_caption') }}
                                        <span class="text-primary-500">$ {{ formatAmount(master.total_fund) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <AssetMasterAction
                            :master="master"
                            :accounts="accounts"
                            :isLoading="isLoading"
                            :terms="terms"
                        />
                    </div>
                </div>
                <Paginator
                    :first="(currentPage - 1) * rowsPerPage"
                    :rows="rowsPerPage"
                    :totalRecords="totalRecords"
                    @page="onPageChange"
                />
            </div>

            <!-- Empty Data -->
            <Empty
                v-else-if="!masterLoading && masters.length === 0"
                :title="$t('public.no_asset_master_available')"
                :message="$t('public.no_asset_master_available_desc')"
            >
                <template #image>
                    <NoAssetMaster class="w-60 h-[180px]" />
                </template>
            </Empty>
        </div>

        <OverlayPanel ref="op">
            <div class="w-60 flex flex-col items-center">
                <div class="flex flex-col gap-8 w-60 py-5 px-4">

                    <!-- Filter Tags -->
                    <div class="flex flex-col items-center gap-2 self-stretch">
                        <span class="self-stretch text-gray-950 text-xs font-bold">{{ $t('public.filter_by_tags') }}</span>
                        <div class="flex flex-col gap-1 self-stretch">
                            <div v-for="tag of tags" :key="tag.key" class="flex items-center gap-2">
                                <div class="flex items-center justify-center p-2 select-none cursor-pointer hover:bg-gray-100 rounded-full">
                                    <Checkbox
                                        v-model="selectedTags"
                                        :inputId="tag.key"
                                        :value="tag.name"
                                    />
                                </div>
                                <label :for="tag.key" class="text-sm text-gray-950">{{ $t(`public.${tag.name}`) }} {{ tag.name === 'no_min' ? '$' : '' }}</label>
                            </div>
                        </div>
                    </div>

                    <!-- Filter Status -->
                    <!-- <div class="flex flex-col items-center gap-2 self-stretch">
                        <span class="self-stretch text-gray-950 text-xs font-bold">{{ $t('public.filter_by_min_investment_amount') }}</span>
                        <Slider
                            v-model="minInvestmentAmountRange"
                            range
                            class="w-full"
                            :min="0"
                            :max="5000000"
                        />
                        <div class="flex justify-between items-center w-full text-xs">
                            <div class="flex flex-col items-start self-stretch">
                                <span class="text-gray-500">{{ $t('public.min') }}</span>
                                <span class="text-gray-950">$ {{ formatAmount(minInvestmentAmountRange[0], 0) }}</span>
                            </div>
                            <div class="flex flex-col items-start self-stretch">
                                <span class="text-gray-500 w-full text-right">{{ $t('public.max') }}</span>
                                <span class="text-gray-950">$ {{ formatAmount(minInvestmentAmountRange[1], 0) }}</span>
                            </div>
                        </div>
                    </div> -->
                </div>
                <div class="w-full flex justify-center items-center py-5 px-4 border-t border-gray-200">
                    <Button
                        type="button"
                        variant="primary-outlined"
                        class="flex justify-center w-full"
                        @click="clearFilter()"
                    >
                        {{ $t('public.clear_all') }}
                    </Button>
                </div>
            </div>
        </OverlayPanel>
    </AuthenticatedLayout>
</template>
