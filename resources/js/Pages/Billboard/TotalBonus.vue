<script setup>
import TotalBonusChart from "@/Pages/Billboard/Partials/TotalBonusChart.vue";
import Dropdown from "primevue/dropdown";
import {ref} from "vue";
import dayjs from "dayjs";
import {transactionFormat} from "@/Composables/index.js";
import {IconTriangleFilled, IconTriangleInvertedFilled} from "@tabler/icons-vue"

const selectedYear = ref('');
const yearOptions = ref([]);
const {formatAmount} = transactionFormat()
const startYear = 2024;
const endYear = dayjs().year();
const totalEarnedBonus = ref(0);
const percentageChange = ref(0)

// Populate yearOptions with all years from startYear to endYear
for (let year = startYear; year <= endYear; year++) {
    yearOptions.value.push({
        value: year.toString()
    });
}

selectedYear.value = dayjs().year().toString();
</script>

<template>
    <div class="py-5 px-4 md:py-6 md:px-8 w-full md:w-2/3 flex flex-col items-center gap-5 bg-white rounded-2xl shadow-toast">
        <div class="flex flex-col self-stretch">
            <div class="flex justify-between items-center self-stretch">
                <span class="text-sm text-gray-500">{{ $t('public.total_bonus_earned') }} ($)</span>
                <Dropdown
                    v-model="selectedYear"
                    :options="yearOptions"
                    optionLabel="value"
                    optionValue="value"
                    class="border-none shadow-none font-medium text-gray-950"
                    scroll-height="236px"
                />
            </div>

            <div class="flex gap-5 items-end">
                <span class="text-xl md:text-xxl text-gray-950 font-semibold">{{ formatAmount(totalEarnedBonus) }}</span>
                <div class="flex gap-2 items-center self-stretch">
                    <IconTriangleFilled v-if="percentageChange > 0" size="12" color="#06D001" />
                    <IconTriangleInvertedFilled v-if="percentageChange < 0" size="12" color="#FF2D58" />
                    <span class="text-xs md:text-sm" :class="{
                        'text-green': percentageChange > 0,
                        'text-pink': percentageChange < 0,
                        'text-gray-500': percentageChange === 0,
                    }">{{ percentageChange }}%</span>
                    <span class="text-gray-400 text-xs md:text-sm">{{ $t('public.vs_last_month') }}</span>
                </div>
            </div>
        </div>

        <TotalBonusChart
            :selectedYear="selectedYear"
            @update:total-earned-bonus="totalEarnedBonus = $event"
            @update:percentage-change="percentageChange = $event"
        />
    </div>
</template>
