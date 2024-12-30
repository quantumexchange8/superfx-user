<script setup>
import PammPerformanceChart from "@/Pages/AssetMaster/Partials/PammPerformanceChart.vue";
import Dropdown from "primevue/dropdown";
import {ref} from "vue";
import dayjs from "dayjs";
import {transactionFormat} from "@/Composables/index.js";

const props = defineProps({
    masterDetail: Object
})

const selectedMonth = ref('');
const selectedMonthProfit = ref(0);
const historyPeriodOptions = ref([]);
const currentYear = dayjs().year();
const {formatAmount} = transactionFormat()

// Populate historyPeriodOptions with all months of the current year
for (let month = 1; month <= 12; month++) {
    historyPeriodOptions.value.push({
        value: dayjs().month(month - 1).year(currentYear).format('MM/YYYY')
    });
}

selectedMonth.value = dayjs().format('MM/YYYY');
</script>

<template>
    <div class="p-4 md:p-8 flex flex-col items-center self-stretch gap-5 rounded-2xl bg-white shadow-toast w-full">
        <div class="flex flex-col items-start self-stretch md:hidden">
            <span class="text-gray-950 text-sm font-bold">{{ $t('public.monthly_pnl_performance') }}</span>
            <div class="flex items-center justify-between self-stretch">
                 <span
                     class="text-xl font-semibold"
                     :class="{
                    'text-green': selectedMonthProfit > 0,
                    'text-pink': selectedMonthProfit < 0,
                    'text-gray-500': selectedMonthProfit === 0,
                }"
                 >{{ masterDetail ? formatAmount(selectedMonthProfit) : 0 }}%</span>
                <Dropdown
                    v-model="selectedMonth"
                    :options="historyPeriodOptions"
                    optionLabel="value"
                    optionValue="value"
                    class="border-none shadow-none font-medium text-gray-950"
                    scroll-height="236px"
                />
            </div>
        </div>
        <div class="hidden md:flex flex-col items-start self-stretch">
            <div class="flex justify-between items-center self-stretch">
                <span class="text-gray-950 text-sm font-bold">{{ $t('public.monthly_pnl_performance') }}</span>
                <Dropdown
                    v-model="selectedMonth"
                    :options="historyPeriodOptions"
                    optionLabel="value"
                    optionValue="value"
                    class="border-none shadow-none font-medium text-gray-950"
                    scroll-height="236px"
                />
            </div>
            <span
                class="text-xxl font-semibold"
                :class="{
                    'text-green': selectedMonthProfit > 0,
                    'text-pink': selectedMonthProfit < 0,
                    'text-gray-500': selectedMonthProfit === 0,
                }"
            >{{ masterDetail ? formatAmount(selectedMonthProfit) : 0 }}%</span>
        </div>

        <!-- chart -->
        <PammPerformanceChart
            :selectedMonth="selectedMonth"
            :masterDetail="masterDetail"
            @update:selectedMonthProfit="selectedMonthProfit = $event"
        />
    </div>
</template>
