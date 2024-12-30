<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import {transactionFormat} from "@/Composables/index.js";
import {computed, ref} from "vue";
import {
    DepositIcon,
    NetAssetIcon,
    NetBalanceIcon,
    WithdrawalIcon
} from "@/Components/Icons/solid.jsx";
import {trans} from "laravel-vue-i18n";
import RebateTable from "@/Pages/RebateAllocate/Partials/RebateTable.vue";

const { formatAmount } = transactionFormat();
const rebates = ref([]);
const account_type_id = ref(1);
const group_total_deposit = ref(999);
const group_total_withdrawal = ref(999);
const total_group_net_balance = ref(999);
const total_group_net_asset = ref(999);

const getRebateAllocateData = async () => {
    try {
        const response = await axios.get(`/rebate_allocate/getRebateAllocateData?account_type_id=${account_type_id.value}`);
        rebates.value = response.data.rebate_data;
    } catch (error) {
        console.error('Error get listing:', error);
    }
}

getRebateAllocateData();
</script>

<template>
    <AuthenticatedLayout :title="$t('public.rebate_allocate')">
        <div class="flex flex-col items-center gap-5 self-stretch">
            <div class="grid grid-cols-6 md:grid-cols-5 gap-3 md:gap-5 content-center self-stretch">
                <div
                    v-for="(rebate, index) in rebates"
                    :class="[
                    'flex flex-col justify-center items-center gap-3 md:gap-4 rounded-2xl shadow-toast p-4 md:p-5 xl:px-8 xl:py-6 col-span-2 md:col-span-1',
                    { 'col-span-3 md:col-span-1': index >= rebates.length - 2 }
                ]"
                >
                    <div class="flex flex-col items-start gap-3 self-stretch">
                        <div class="w-[50px] h-[50px] grow-0 shrink-0">
                            <img :src="`/img/rebate/3d-${rebate.symbol_group.display}.svg`" alt="">
                        </div>
                        <span class="text-sm text-gray-950 font-medium w-full md:w-[90px] lg:w-full truncate">{{ $t(`public.${rebate.symbol_group.display}`) }}</span>
                    </div>
                    <div class="flex flex-col items-start gap-1 self-stretch">
                        <span class="text-gray-950 text-xl font-semibold">{{ formatAmount(rebate.amount) }}</span>
                        <span class="text-gray-500 text-xs">{{ $t('public.rebate') }} / Ł ($)</span>
                    </div>
                </div>
            </div>

            <RebateTable />
        </div>
    </AuthenticatedLayout>
</template>
