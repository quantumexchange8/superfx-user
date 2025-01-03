<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { DepositIcon, WithdrawalIcon } from '@/Components/Icons/solid';
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';
import { ref, h, watchEffect } from 'vue';
import { wTrans } from 'laravel-vue-i18n';
import TradingAccounts from '@/Pages/Transaction/Partials/TradingAccounts.vue';
import RebateWallet from '@/Pages/Transaction/Partials/RebateWallet.vue';
import { usePage } from '@inertiajs/vue3';
import {transactionFormat} from "@/Composables/index.js";

const { formatAmount } = transactionFormat();

const tabs = ref([
        {
            title: wTrans('public.trading_accounts'),
            component: h(TradingAccounts)
        },
        {
            title: wTrans('public.rebate_wallet'),
            component: h(RebateWallet),
        }
]);

const totalDeposit = ref(null)
const totalWithdrawal = ref(null)

const getResults = async () => {
    try {
        const response = await axios.get('/transaction/getTotal');

        totalDeposit.value = response.data.totalDeposit;
        totalWithdrawal.value = response.data.totalWithdrawal;
    } catch (error) {
        console.error('Error get total:', error);
    }
};

getResults();

watchEffect(() => {
    if (usePage().props.toast !== null) {
        getResults();
    }
});

const user = usePage().props.auth.user;
</script>

<template>
    <AuthenticatedLayout :title="$t('public.transaction')">
        <div class="flex flex-col items-center gap-5 self-stretch">
            <div class="grid grid-cols-2 gap-5 w-full">
                <div class="flex flex-col justify-center items-center gap-3 p-6 rounded-2xl border-b-8 w-full shadow-toast border-green">
                    <DepositIcon />
                    <div class="flex flex-col items-center gap-1 w-full">
                        <span class="text-gray-500 text-xs md:text-sm">{{ $t('public.total_deposit') }}</span>
                        <div
                            v-if="totalDeposit != null"
                            class="text-gray-950 text-lg md:text-xl font-semibold"
                        >
                            $ {{ formatAmount(totalDeposit) }}
                        </div>
                        <div
                            v-else
                            class="h-2 bg-gray-200 rounded-full w-12 md:w-40 my-3 animate-pulse"
                        >
                        </div>
                    </div>
                </div>
                <div class="flex flex-col justify-center items-center gap-3 p-6 rounded-2xl border-b-8 w-full shadow-toast border-pink">
                    <WithdrawalIcon/>
                    <div class="flex flex-col items-center gap-1 w-full">
                        <span class="text-gray-500 text-xs md:text-sm">{{ $t('public.total_withdrawal') }}</span>
                        <div
                            v-if="totalWithdrawal != null"
                            class="text-gray-950 text-lg md:text-xl font-semibold"
                        >
                            $ {{ formatAmount(totalWithdrawal) }}
                        </div>
                        <div
                            v-else
                            class="h-2 bg-gray-200 rounded-full w-12 md:w-40 my-3 animate-pulse"
                        >
                        </div>
                    </div>
                </div>
            </div>

            <TabView
                v-if="user.role === 'ib'"
                class="flex flex-col gap-5 self-stretch"
            >
                <TabPanel v-for="(tab, index) in tabs" :key="index" :header="tab.title">
                    <component :is="tab.component" />
                </TabPanel>
            </TabView>

            <TradingAccounts
                v-else
            />
        </div>
    </AuthenticatedLayout>
</template>
