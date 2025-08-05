<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import {transactionFormat} from "@/Composables/index.js";
import {usePage} from "@inertiajs/vue3";
import {computed, ref, watchEffect} from "vue";
import {
    DepositIcon,
    NetAssetIcon,
    NetBalanceIcon,
    WithdrawalIcon
} from "@/Components/Icons/solid.jsx";
import {trans} from "laravel-vue-i18n";
import RebateTable from "@/Pages/RebateAllocate/Partials/RebateTable.vue";

const props = defineProps({
    accountTypes: Array,
})

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

const handleAccountTypeChange = (newType) => {
    account_type_id.value = newType
    getRebateAllocateData();
};

watchEffect(() => {
    if (usePage().props.toast !== null) {
        getRebateAllocateData();
    }
});
</script>

<template>
    <AuthenticatedLayout :title="$t('public.rebate_allocate')">
        <div class="flex flex-col items-center gap-5 self-stretch">
            <div class="grid grid-cols-6 gap-3  content-center self-stretch">
                <div
                    v-for="(rebate, index) in rebates"
                    :class="[
                    'flex flex-col justify-center items-center gap-3 md:gap-4 rounded-2xl shadow-toast p-4 md:p-5 xl:px-8 xl:py-6 col-span-3 sm:col-span-2 xl:col-span-1',
                ]"
                >
                    <div class="flex flex-col items-start gap-3 self-stretch">
                        <!-- <div class="w-[50px] h-[50px] grow-0 shrink-0">
                            <img :src="`/img/rebate/3d-${rebate.symbol_group.display}.svg`" alt="">
                        </div> -->
                        <span class="text-sm text-gray-950 font-medium w-full truncate">{{ $t(`public.${rebate.symbol_group.display}`) }}</span>
                    </div>
                    <div class="flex flex-col items-start gap-1 self-stretch">
                        <span class="text-gray-950 text-xl font-semibold">{{ formatAmount(rebate.amount) }}</span>
                        <span class="text-gray-500 text-xs">{{ $t('public.rebate') }} / Ł ($)</span>
                    </div>
                </div>
            </div>

            <RebateTable :accountTypes="accountTypes" @update:accountType="handleAccountTypeChange"/>
        </div>
    </AuthenticatedLayout>
</template>
