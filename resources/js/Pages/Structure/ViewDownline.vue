<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { IconChevronRight, IconCircleCheckFilled } from '@tabler/icons-vue';
import Button from '@/Components/Button.vue';
import DefaultProfilePhoto from "@/Components/DefaultProfilePhoto.vue";
import StatusBadge from '@/Components/StatusBadge.vue';
import { DepositIcon, WithdrawalIcon, RebateIcon, MemberIcon, AgentIcon } from '@/Components/Icons/solid';
import { computed, ref, watchEffect } from 'vue';
import {generalFormat, transactionFormat} from "@/Composables/index.js";
import Empty from "@/Components/Empty.vue";
import { usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import Vue3Autocounter from 'vue3-autocounter';

const props = defineProps({
    user: Object,
})

const { formatAmount } = transactionFormat();
const { formatRgbaColor } = generalFormat()

const tradingAccounts = ref();
const tradingAccountsLength = ref(0);
const userDetail = ref();
const counterDuration = ref(8);
const depositAmount = ref(9999999);
const withdrawalAmount = ref(9999999);
const rebateAmount = ref(0);
const memberAmount = ref(999);
const agentAmount = ref(999);

const getUserData = async () => {
    try {
        const response = await axios.get(`/structure/getUserData?id=${props.user.id}`);

        userDetail.value = response.data.userDetail;
        tradingAccounts.value = response.data.tradingAccounts;
        depositAmount.value = response.data.depositAmount;
        withdrawalAmount.value = response.data.withdrawalAmount;
        memberAmount.value = response.data.memberAmount;
        agentAmount.value = response.data.agentAmount;

        tradingAccountsLength.value = tradingAccounts.value.length;
        counterDuration.value = 1;
    } catch (error) {
        console.error('Error get user data:', error);
    }
};

getUserData();

watchEffect(() => {
    if (usePage().props.toast !== null) {
        getUserData();
    }
});

// data overview
const dataOverviews = computed(() => [
    {
        icon: DepositIcon,
        total: depositAmount.value,
        decimal: 2,
        label: trans('public.total_deposit')+" ($)",
        type: 'member',
    },
    {
        icon: WithdrawalIcon,
        total: withdrawalAmount.value,
        decimal: 2,
        label: trans('public.total_withdrawal')+" ($)",
        type: 'member',
    },
    {
        icon: RebateIcon,
        total: rebateAmount.value,
        decimal: 2,
        label: trans('public.rebate_earned')+" ($)",
        type: 'ib',
    },
    {
        icon: MemberIcon,
        total: memberAmount.value,
        decimal: 0,
        label: trans('public.referred_member'),
        type: 'member',
    },
    {
        icon: AgentIcon,
        total: agentAmount.value,
        decimal: 0,
        label: trans('public.referred_agent'),
        type: 'ib',
    },
]);

const filteredDataOverviews = computed(() => {
    if (props.user.role === 'member') {
        return dataOverviews.value.filter((item) =>
            item.type === 'member'
        );
    }

    return dataOverviews.value;
});


</script>

<template>
    <AuthenticatedLayout :title="$t('public.structure')">
        <div class="flex flex-col items-center gap-5 self-stretch">
            <!-- Breadcrumb -->
            <div class="flex flex-wrap md:flex-nowrap items-center gap-2 self-stretch">
                <Button
                    external
                    type="button"
                    variant="primary-text"
                    size="sm"
                    href="/structure?tab=listing"
                >
                    {{ $t('public.structure_listing') }}
                </Button>
                <IconChevronRight
                    :size="16"
                    stroke-width="1.25"
                />
                <span class="flex px-4 py-2 text-gray-400 items-center justify-center text-sm font-medium">{{ user.name }} - {{ $t('public.view_downline_info') }}</span>
            </div>

            <!-- Profile -->
            <div class="p-4 flex flex-col justify-center items-center gap-6 self-stretch rounded-2xl bg-white shadow-toast md:py-6 md:px-8 md:flex-row md:gap-9">
                <div
                    v-if="userDetail"
                    class="pb-6 flex items-center gap-4 self-stretch border-b border-solid border-gray-200 md:pb-0 md:pr-6 md:flex-col md:border-b-0 md:border-r"
                >
                    <div class="relative w-14 h-14 md:w-[60px] md:h-[60px]">
                        <div class="w-full h-full rounded-full overflow-hidden">
                            <div v-if="userDetail.profile_photo">
                                <img :src="userDetail.profile_photo" alt="Profile Photo" class="w-full h-full object-cover" />
                            </div>
                            <div v-else>
                                <DefaultProfilePhoto />
                            </div>
                        </div>

                        <IconCircleCheckFilled
                            v-if="userDetail.kyc_status === 'approved'"
                            size="16"
                            stroke-width="1.25"
                            class="absolute text-success-500 grow-0 shrink-0 -right-0 -bottom-0 bg-white rounded-full"
                        />
                    </div>

                    <div class="flex flex-col items-start gap-1 md:items-center md:self-stretch">
                        <div class="w-48 truncate text-gray-950 text-lg font-semibold md:text-center">
                            {{ userDetail.name }}
                        </div>
                        <div class="text-gray-700">
                            {{ userDetail.id_number }}
                        </div>
                    </div>
                </div>
                <!-- loading left column -->
                <div
                    v-else
                    class="pb-6 flex items-center gap-4 self-stretch border-b border-solid border-gray-200 md:pb-0 md:pr-6 md:flex-col md:border-b-0 md:border-r"
                >
                    <div class="animate-pulse w-14 h-14 rounded-full overflow-hidden md:w-[60px] md:h-[60px]">
                        <DefaultProfilePhoto />
                    </div>

                    <div class="flex flex-col items-start gap-1 flex-1 md:items-center md:self-stretch">
                        <div class="w-48">
                            <div class="h-4 bg-gray-200 rounded-full w-44 my-2 md:my-3 md:mx-auto"></div>
                        </div>
                        <div class="h-2 bg-gray-200 rounded-full w-20 mb-1"></div>
                    </div>
                </div>

                <div class="flex flex-col items-center gap-3 self-stretch md:gap-5 md:flex-1">
                    <div
                        v-if="userDetail"
                        class="grid grid-cols-2 grid-flow-row gap-y-3 gap-x-2 items-center self-stretch md:grid-rows-2 md:grid-flow-col md:gap-y-2 md:gap-x-5"
                    >
                        <div class="text-gray-500 text-xs">
                            {{ $t('public.email_address') }}
                        </div>
                        <div class="w-32 md:w-52 truncate flex-1 text-gray-950 text-sm font-medium">
                            {{ userDetail.email }}
                        </div>
                        <div class="text-gray-500 text-xs">
                            {{ $t('public.phone_number') }}
                        </div>
                        <div class="w-32 md:w-52 truncate flex-1 text-gray-950 text-sm font-medium">
                            {{ userDetail.dial_code }} {{ userDetail.phone }}
                        </div>
                    </div>
                    <!-- loading right top -->
                    <div
                        v-else
                        class="grid grid-cols-2 grid-flow-row gap-y-3 gap-x-2 items-center self-stretch md:grid-rows-2 md:grid-flow-col md:gap-y-2 md:gap-x-5"
                    >
                        <div class="text-gray-500 text-xs">
                            {{ $t('public.email_address') }}
                        </div>
                        <div class="h-2 bg-gray-200 rounded-full md:w-48 my-1.5"></div>
                        <div class="text-gray-500 text-xs">
                            {{ $t('public.phone_number') }}
                        </div>
                        <div class="h-2 bg-gray-200 rounded-full md:w-48 my-1.5"></div>
                    </div>

                    <div
                        v-if="userDetail"
                        class="grid grid-cols-2 grid-flow-row gap-y-3 gap-x-2 items-center self-stretch md:grid-rows-2 md:grid-flow-col md:gap-y-2 md:gap-x-5"
                    >
                        <div class="text-gray-500 text-xs">
                            {{ $t('public.role') }}
                        </div>
                        <div class="flex items-start">
                            <StatusBadge :value="userDetail.role">
                                {{ $t(`public.${userDetail.role}`) }}
                            </StatusBadge>
                        </div>
                        <div class="text-gray-500 text-xs">
                            {{ $t('public.upline') }}
                        </div>
                        <div class="flex items-center gap-2 flex-1">
                            <div class="w-[26px] h-[26px] rounded-full overflow-hidden">
                                <div v-if="userDetail.upline_profile_photo">
                                    <img :src="userDetail.upline_profile_photo" alt="Profile Photo" />
                                </div>
                                <div v-else>
                                    <DefaultProfilePhoto />
                                </div>
                            </div>
                            <div class="w-48 md:w-44 truncate flex-1 text-gray-950 text-sm font-medium">
                                {{ userDetail.upline_name??'-' }}
                            </div>
                        </div>
                    </div>
                    <!-- loading right bottom -->
                    <div
                        v-else
                        class="grid grid-cols-2 grid-flow-row gap-y-3 gap-x-2 items-center self-stretch md:grid-rows-2 md:grid-flow-col md:gap-y-2 md:gap-x-5"
                    >
                        <div class="text-gray-500 text-xs">
                            {{ $t('public.role') }}
                        </div>
                        <div class="h-2 bg-gray-200 rounded-full md:w-48 my-2"></div>
                        <div class="text-gray-500 text-xs">
                            {{ $t('public.upline') }}
                        </div>
                        <div class="flex items-center gap-2 flex-1">
                            <div class="animate-pulse w-[26px] h-[26px] rounded-full overflow-hidden">
                                <DefaultProfilePhoto />
                            </div>
                            <div class="h-2 bg-gray-200 rounded-full w-24 my-2"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Data Overview -->
            <div
                class="p-4 md:p-8 grid gap-5 md:gap-3 w-full rounded-2xl bg-white shadow-dropdown"
                :class="[
                    {'grid-cols-2 md:grid-cols-5': user.role === 'ib'},
                    {'grid-cols-2 md:grid-cols-3': user.role === 'member'},
                ]"
            >
                <div
                    v-for="(item, index) in filteredDataOverviews"
                    :key="index"
                    class="flex flex-col justify-center items-center gap-3 w-full last:col-span-2 md:col-span-1 md:last:col-span-1"
                >
                    <component :is="item.icon" class="grow-0 shrink-0" />
                    <div class="flex flex-col items-center gap-1 self-stretch">
                        <span class="text-gray-500 text-xs w-28 truncate sm:w-auto">{{ item.label }}</span>
                        <div class="text-gray-950 text-lg font-semibold">
                            <vue3-autocounter ref="counter" :startAmount="0" :endAmount="item.total" :duration="counterDuration" separator="," decimalSeparator="." :decimals="item.decimal" :autoinit="true" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Trading Accounts -->
            <div class="pt-3 flex flex-col items-start gap-5 self-stretch">
                <div class="self-stretch text-gray-950 font-bold">
                    {{ $t('public.all_trading_accounts') }}
                </div>

                <template v-if="tradingAccountsLength > 0 && tradingAccounts">
                    <div class="w-full grid grid-cols-1 gap-5 md:grid-cols-2">
                        <div
                            v-for="(tradingAccount, index) in tradingAccounts"
                            :key="index"
                            class="min-w-[300px] py-4 pl-6 pr-3 flex flex-col justify-center gap-3 md:gap-5 rounded-2xl border-l-8 bg-white shadow-toast"
                            :style="{'borderColor': `#${tradingAccount.account_type_color}`}"
                        >
                            <div class="flex items-start gap-4">
                                <span class="text-gray-950 font-semibold md:text-lg self-stretch">
                                    # {{ tradingAccount.meta_login }}
                                </span>
                                <div
                                    class="flex px-2 py-1 justify-center items-center text-xs font-semibold hover:-translate-y-1 transition-all duration-300 ease-in-out rounded"
                                    :style="{
                                        backgroundColor: formatRgbaColor(tradingAccount.account_type_color, 0.15),
                                        color: `#${tradingAccount.account_type_color}`,
                                    }"
                                >
                                    {{ tradingAccount.account_type }}
                                </div>
                            </div>

                            <div class="flex justify-between content-center gap-2 self-stretch">
                                <div class="flex flex-col md:flex-row md:gap-2 justify-start items-center w-full">
                                    <div class="text-gray-500 text-xs">
                                        {{ $t('public.balance_shortname') }}:
                                    </div>
                                    <div class="text-gray-950 text-xs font-medium">
                                        $ {{ formatAmount(tradingAccount.balance ?? 0) }}
                                    </div>
                                </div>
                                <div class="flex flex-col md:flex-row md:gap-2 justify-start items-center w-full">
                                    <div class="text-gray-500 text-xs">
                                        {{ $t('public.equity_shortname') }}:
                                    </div>
                                    <div class="text-gray-950 text-xs font-medium">
                                        $ {{ formatAmount(tradingAccount.equity ?? 0) }}
                                    </div>
                                </div>
                                <div
                                    v-if="tradingAccount.account_type !== 'Premium Account'"
                                    class="flex flex-col md:flex-row md:gap-2 justify-start items-center w-full"
                                >
                                    <div class="text-gray-500 text-xs">
                                        {{ $t('public.credit_shortname') }}:
                                    </div>
                                    <div class="text-gray-950 text-xs font-medium">
                                        $ {{ formatAmount(tradingAccount.credit ?? 0) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <template v-else>
                    <Empty :message="$t('public.trading_account_empty_caption')"/>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
