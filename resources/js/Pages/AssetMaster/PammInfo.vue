<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { IconChevronRight } from '@tabler/icons-vue';
import Button from '@/Components/Button.vue';
import { ref, watchEffect } from 'vue';
import DefaultProfilePhoto from '@/Components/DefaultProfilePhoto.vue';
import AssetMasterAction from "@/Pages/AssetMaster/Partials/AssetMasterAction.vue";
import { IconCoin, IconFee, IconPeriod } from '@/Components/Icons/solid';
import {transactionFormat} from "@/Composables/index.js";
import { usePage } from '@inertiajs/vue3';
import PammPerformance from "@/Pages/AssetMaster/Partials/PammPerformance.vue";

const { formatAmount } = transactionFormat();

const props = defineProps({
    master: Object,
})

const masterDetail = ref();
const getResults = async () => {
    try {
        const response = await axios.get(`/asset_master/getMasterDetail?id=${props.master.id}`);
        masterDetail.value = response.data.masterDetail;

    } catch (error) {
        console.error('Error get master detail:', error);
    }
};

getResults();

watchEffect(() => {
    if (usePage().props.toast !== null) {
        getResults();
    }
});

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
            <!-- Breadcrumb -->
            <div class="flex flex-wrap md:flex-nowrap items-center gap-2 self-stretch">
                <Button
                    external
                    type="button"
                    variant="primary-text"
                    size="sm"
                    :href="route('asset_master')"
                >
                    {{ $t('public.asset_master') }}
                </Button>
                <IconChevronRight
                    :size="16"
                    stroke-width="1.25"
                />
                <span class="flex px-4 py-2 text-gray-400 items-center justify-center text-sm font-medium">{{ master.asset_name }} - {{ $t('public.view_pamm_info') }}</span>
            </div>

            <div class="w-full grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="py-6 px-4 md:px-8 flex flex-col items-center gap-8 w-full rounded-2xl bg-white shadow-toast">
                    <div
                        v-if="masterDetail"
                        class="flex flex-col items-center gap-4 w-full"
                    >
                        <div class="w-[60px] h-[60px] shrink-0 grow-0 rounded-full overflow-hidden">
                            <div v-if="masterDetail.master_profile_photo">
                                <img :src="masterDetail.master_profile_photo" alt="Profile Photo" />
                            </div>
                            <div v-else>
                                <DefaultProfilePhoto />
                            </div>
                        </div>
                        <div class="flex flex-col items-center gap-1 self-stretch">
                            <div class="mx-auto self-stretch w-64 truncate text-gray-950 text-center text-lg font-semibold">
                                {{ masterDetail.asset_name }}
                            </div>
                            <div class="mx-auto self-stretch w-64 truncate text-gray-700 text-center">
                                {{ masterDetail.trader_name }}
                            </div>
                        </div>
                        <AssetMasterAction
                            :master="masterDetail"
                            :accounts="accounts"
                            :isLoading="isLoading"
                        />
                    </div>

                    <!-- loading -->
                    <div
                        v-else
                        class="flex flex-col items-center gap-4 w-full"
                    >
                        <div class="w-[60px] h-[60px] shrink-0 grow-0 rounded-full overflow-hidden animate-pulse">
                            <DefaultProfilePhoto />
                        </div>
                        <div class="flex flex-col items-center gap-1 self-stretch animate-pulse">
                            <div class="h-2 bg-gray-200 rounded-full w-48 md:w-64 my-3"></div>
                            <div class="h-2 bg-gray-200 rounded-full w-32 md:w-48 my-2"></div>
                        </div>
                        <AssetMasterAction :master="masterDetail" />
                    </div>

                    <div
                        v-if="masterDetail"
                        class="grid grid-cols-2 gap-5 w-full"
                    >
                        <div class="flex flex-col items-center gap-1">
                            <div class="self-stretch text-gray-500 text-center text-xs">
                                {{ $t('public.total_gain') }}
                            </div>
                            <div class="self-stretch text-gray-950 text-center text-lg font-semibold">
                                {{ formatAmount(masterDetail.total_gain) }} %
                            </div>
                        </div>
                        <div class="flex flex-col items-center gap-1">
                            <div class="self-stretch text-gray-500 text-center text-xs">
                                {{ $t('public.monthly_gain') }}
                            </div>
                            <div class="self-stretch text-gray-950 text-center text-lg font-semibold">
                                {{ formatAmount(masterDetail.monthly_gain) }} %
                            </div>
                        </div>
                        <div class="flex flex-col items-center gap-1">
                            <div class="self-stretch text-gray-500 text-center text-xs">
                                {{ $t('public.latest') }}
                            </div>
                            <div class="self-stretch text-center text-lg font-semibold">
                                <div
                                    v-if="masterDetail.latest_profit !== 0"
                                    :class="(masterDetail.latest_profit < 0) ? 'text-error-500' : 'text-success-500'"
                                >
                                    {{ formatAmount(masterDetail.latest_profit) }} %
                                </div>
                                <div
                                    v-else
                                    class="text-gray-950"
                                >
                                    -
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col items-center gap-1">
                            <div class="self-stretch text-gray-500 text-center text-xs">
                                {{ $t('public.with_us') }}
                            </div>
                            <div class="self-stretch text-gray-950 text-center text-lg font-semibold">
                                {{ masterDetail.with_us }} {{ $t('public.day_shortform') }}
                            </div>
                        </div>
                    </div>

                    <!-- loading -->
                    <div
                        v-else
                        class="grid grid-cols-2 gap-5 w-full"
                    >
                        <div class="flex flex-col items-center gap-1">
                            <div class="self-stretch text-gray-500 text-center text-xs">
                                {{ $t('public.total_gain') }}
                            </div>
                            <div class="h-2 bg-gray-200 rounded-full w-20 md:w-32 my-2.5 animate-pulse"></div>
                        </div>
                        <div class="flex flex-col items-center gap-1">
                            <div class="self-stretch text-gray-500 text-center text-xs">
                                {{ $t('public.monthly_gain') }}
                            </div>
                            <div class="h-2 bg-gray-200 rounded-full w-20 md:w-32 my-2.5 animate-pulse"></div>
                        </div>
                        <div class="flex flex-col items-center gap-1">
                            <div class="self-stretch text-gray-500 text-center text-xs">
                                {{ $t('public.latest') }}
                            </div>
                            <div class="h-2 bg-gray-200 rounded-full w-20 md:w-32 my-2.5 animate-pulse"></div>
                        </div>
                        <div class="flex flex-col items-center gap-1">
                            <div class="self-stretch text-gray-500 text-center text-xs">
                                {{ $t('public.with_us') }}
                            </div>
                            <div class="h-2 bg-gray-200 rounded-full w-20 md:w-32 my-2.5 animate-pulse"></div>
                        </div>
                    </div>
                </div>

                <div class="py-6 px-4 md:p-8 flex flex-col items-center gap-8 self-stretch rounded-2xl bg-white shadow-dropdown">
                    <div class="self-stretch text-gray-950 font-bold">
                        {{ $t('public.fees_and_conditions') }}
                    </div>
                    <div class="w-full grid grid-cols-2 items-center gap-5">
                        <div class="flex flex-col items-center justify-center gap-3">
                            <IconCoin />
                            <div class="flex flex-col items-center gap-1 self-stretch">
                                <div class="mx-auto w-32 truncate md:w-auto self-stretch text-gray-500 text-center text-xs">
                                    {{ $t('public.minimum_investment') }}
                                </div>
                                <div
                                    v-if="masterDetail"
                                    class="self-stretch text-gray-950 text-center text-lg font-semibold"
                                >
                                    $ {{ formatAmount(masterDetail.minimum_investment) }}
                                </div>
                                <div v-else>
                                    <div class="h-2 bg-gray-200 rounded-full w-16 md:w-28 my-2.5 animate-pulse"></div>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col items-center justify-center gap-3">
                            <IconFee />
                            <div class="flex flex-col items-center gap-1 self-stretch">
                                <div class="self-stretch text-gray-500 text-center text-xs">
                                    {{ $t('public.performance_fee') }}
                                </div>
                                <div
                                    v-if="masterDetail"
                                    class="self-stretch text-gray-950 text-center text-lg font-semibold"
                                >
                                    <div v-if="masterDetail.performance_fee">
                                        {{ formatAmount(masterDetail.performance_fee, 0)+'%' }}
                                    </div>
                                    <div v-else>
                                        0 %
                                    </div>
                                </div>
                                <div v-else>
                                    <div class="h-2 bg-gray-200 rounded-full w-16 md:w-28 my-2.5 animate-pulse"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-span-2 flex flex-col items-center justify-center gap-3">
                            <IconPeriod class="rounded-full" />
                            <div class="flex flex-col items-center gap-1 self-stretch">
                                <div class="self-stretch text-gray-500 text-center text-xs">
                                    {{ $t('public.minimum_investment_period') }}
                                </div>
                                <div
                                    v-if="masterDetail"
                                    class="self-stretch text-gray-950 text-center text-lg font-semibold"
                                >
                                    <div v-if="masterDetail.minimum_investment_period > 0">
                                        {{ masterDetail.minimum_investment_period }} {{ $t('public.months') }}
                                    </div>
                                    <div v-else>
                                        {{ $t('public.lock_free') }}
                                    </div>
                                </div>
                                <div v-else>
                                    <div class="h-2 bg-gray-200 rounded-full w-16 md:w-28 my-2.5 animate-pulse"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- graph -->
            <PammPerformance
                :masterDetail="masterDetail"
            />
        </div>
    </AuthenticatedLayout>
</template>
