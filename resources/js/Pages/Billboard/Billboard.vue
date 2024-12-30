<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import BonusWallet from "@/Pages/Billboard/BonusWallet.vue";
import Empty from "@/Components/Empty.vue";
import {ref} from "vue";
import StatusBadge from "@/Components/StatusBadge.vue";
import {transactionFormat} from "@/Composables/index.js";
import MeterGauge from "@/Components/MeterGauge.vue";
import {
    IconChartArcs,
    IconCirclePercentage,
    IconCalendarStats
} from "@tabler/icons-vue"
import dayjs from "dayjs";
import AchievementStatement from "@/Pages/Billboard/Partials/AchievementStatement.vue";
import TotalBonus from "@/Pages/Billboard/TotalBonus.vue";

const props = defineProps({
    achievementsCount: Number,
    terms: Object
})

const isLoading = ref(true);
const targetAchievements = ref([]);
const {formatAmount} = transactionFormat();

const getResults = async () => {
    isLoading.value = true;

    try {
        const response = await axios.get('/billboard/getTargetAchievements');
        targetAchievements.value = response.data.targetAchievements;
    } catch (error) {
        console.error('Error getting masters:', error);
    } finally {
        isLoading.value = false;
    }
}

getResults();
</script>

<template>
    <AuthenticatedLayout :title="$t('public.billboard')">
        <div class="flex flex-col gap-5 items-center self-stretch">
            <div class="flex flex-col md:flex-row items-center gap-5 self-stretch">
                <BonusWallet
                    :terms="terms"
                />
                <TotalBonus />
            </div>

            <span class="text-gray-950 font-bold w-full text-left py-3">{{ $t('public.my_target_achievements') }}</span>

            <div v-if="achievementsCount === 0 && !targetAchievements.length">
                <Empty
                    :title="$t('public.no_target_achievements')"
                >
                </Empty>
            </div>

            <div v-else class="w-full">
                <div v-if="isLoading" class="grid grid-cols-1 md:grid-cols-2 gap-5 self-stretch">
                    <div
                        class="rounded-2xl shadow-toast bg-white"
                    >
                        <div class="py-5 px-4 md:p-6 flex flex-col items-center gap-3 md:gap-4 self-stretch">
                            <div class="flex justify-between items-center self-stretch w-full">
                                <StatusBadge value="info" />
                                <span class="text-xxs text-gray-700 font-medium">#{{ $t('public.achieve_target_to_unlock_bonus', {'threshold': '70'}) }}</span>
                            </div>

                            <div class="py-2 flex gap-2 justify-center items-center self-stretch">
                                <div class="flex flex-col gap-1 self-stretch w-full animate-pulse">
                                    <div class="h-2.5 bg-gray-200 rounded-full w-40 my-1"></div>
                                    <div class="h-2 bg-gray-200 rounded-full w-36 my-1"></div>
                                </div>
                                <div class="flex flex-col gap-1 self-stretch items-end w-full animate-pulse">
                                    <div class="h-2.5 bg-primary-400 rounded-full w-40 my-1"></div>
                                    <div class="h-2 bg-gray-200 rounded-full w-36 my-1"></div>
                                </div>
                            </div>

                            <div class="flex justify-center items-center self-stretch w-full">
                                <MeterGauge :percentage="0" />
                            </div>

                            <div class="flex pt-4 items-center justify-center self-stretch w-full animate-pulse">
                                <div v-tooltip.bottom="$t('public.sales_category')" class="flex flex-col gap-3 items-center self-stretch w-full">
                                    <IconChartArcs size="20" color="#98A2B3" stroke-width="1.25" />
                                    <div class="h-2.5 bg-gray-200 rounded-full w-40 my-1"></div>
                                </div>
                                <div v-tooltip.bottom="$t('public.bonus_rate')" class="flex flex-col gap-3 items-center self-stretch w-full">
                                    <IconCirclePercentage size="20" color="#98A2B3" stroke-width="1.25" />
                                    <div class="h-2.5 bg-gray-200 rounded-full w-40 my-1"></div>
                                </div>
                                <div v-tooltip.bottom="$t('public.next_calculation_date')" class="flex flex-col gap-3 items-center self-stretch w-full">
                                    <IconCalendarStats size="20" color="#98A2B3" stroke-width="1.25" />
                                    <div class="h-2.5 bg-gray-200 rounded-full w-40 my-1"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-else
                    class="grid grid-cols-1 md:grid-cols-2 gap-5 self-stretch"
                >
                    <div
                        v-for="achievement in targetAchievements"
                        class="rounded-2xl shadow-toast bg-white"
                    >
                        <div class="py-5 px-4 md:p-6 flex flex-col items-center gap-3 md:gap-4 self-stretch">
                            <div class="flex justify-between items-center self-stretch w-full">
                                <StatusBadge :value="achievement.bonus_badge">
                                    {{ $t(`public.${achievement.sales_calculation_mode}`) }}
                                </StatusBadge>

                                <span class="text-xxs text-gray-700 font-medium">#{{ $t('public.achieve_target_to_unlock_bonus', {'threshold': achievement.bonus_calculation_threshold}) }}</span>
                            </div>

                            <div class="py-2 flex gap-2 justify-center items-center self-stretch">
                                <div class="flex flex-col gap-1 self-stretch w-full">
                                    <span v-if="achievement.sales_category === 'trade_volume'" class="text-lg md:text-xl text-gray-950 font-semibold">{{ formatAmount(achievement.achieved_amount) }} Ł</span>
                                    <span v-else class="text-lg md:text-xl text-gray-950 font-semibold">$ {{ formatAmount(achievement.achieved_amount) }}</span>

                                    <span v-if="achievement.sales_category === 'trade_volume'" class="text-sm text-gray-500">{{ $t('public.out_of') }} {{ formatAmount(achievement.target_amount) }} Ł</span>
                                    <span v-else class="text-sm text-gray-500">{{ $t('public.out_of') }} $ {{ formatAmount(achievement.target_amount) }}</span>
                                </div>
                                <div class="flex flex-col gap-1 self-stretch items-end w-full">
                                    <span class="text-lg md:text-xl text-primary-500 font-semibold">{{ achievement.achieved_percentage > achievement.bonus_calculation_threshold ? '$ ' + formatAmount(achievement.bonus_amount) : '-' }}</span>
                                    <span class="text-sm text-gray-500">{{ $t('public.bonus_earned') }}</span>
                                </div>
                            </div>

                            <div class="flex justify-center items-center self-stretch w-full">
                                <MeterGauge :percentage="achievement.achieved_percentage" />
                            </div>

                            <div class="flex pt-4 items-center justify-center self-stretch w-full">
                                <div v-tooltip.bottom="$t('public.sales_category')" class="flex flex-col gap-3 items-center self-stretch w-full">
                                    <IconChartArcs size="20" color="#98A2B3" stroke-width="1.25" />
                                    <span class="text-sm text-gray-950 font-medium">{{ $t(`public.${achievement.sales_category}`) }}</span>
                                </div>
                                <div v-tooltip.bottom="$t('public.bonus_rate')" class="flex flex-col gap-3 items-center self-stretch w-full">
                                    <IconCirclePercentage size="20" color="#98A2B3" stroke-width="1.25" />
                                    <span v-if="achievement.sales_category === 'trade_volume'" class="text-sm text-gray-950 font-medium">$ {{ formatAmount(achievement.bonus_rate) }}</span>
                                    <span v-else class="text-sm text-gray-950 font-medium">{{ achievement.bonus_rate % 1 === 0 ? formatAmount(achievement.bonus_rate, 0) : formatAmount(achievement.bonus_rate) }}%</span>
                                </div>
                                <div v-tooltip.bottom="$t('public.next_calculation_date')" class="flex flex-col gap-3 items-center self-stretch w-full">
                                    <IconCalendarStats size="20" color="#98A2B3" stroke-width="1.25" />
                                    <span class="text-sm text-gray-950 font-medium">{{ dayjs(achievement.next_calculation_date).format('YYYY/MM/DD') }}</span>
                                </div>
                            </div>
                        </div>

                        <AchievementStatement
                            :achievement="achievement"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
