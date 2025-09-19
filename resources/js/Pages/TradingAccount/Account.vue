<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import {ref, h, watch} from "vue";
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';
import DollarAccounts from '@/Pages/TradingAccount/Partials/DollarAccounts.vue';
import CentAccounts from '@/Pages/TradingAccount/Partials/CentAccounts.vue';
import { usePage } from "@inertiajs/vue3";
import {IconInfoOctagonFilled, IconX} from '@tabler/icons-vue';
import { wTrans } from "laravel-vue-i18n";
import OpenDemoAccount from "@/Pages/TradingAccount/Partials/OpenDemoAccount.vue";
import OpenLiveAccount from "@/Pages/TradingAccount/Partials/OpenLiveAccount.vue";

const props = defineProps({
    terms: Object,
    methods: Array,
    tradingPlatforms: Array,
})

// Initialize the form with user data
const user = usePage().props.auth.user;

const tabs = ref([
    { title: wTrans('public.dollar'), component: h(DollarAccounts, {methods: props.methods, tradingPlatforms: props.tradingPlatforms}), type: 'dollar' },
    { title: wTrans('public.cent'), component: h(CentAccounts, {methods: props.methods, tradingPlatforms: props.tradingPlatforms}), type: 'cent' },
    // { title: wTrans('public.demo'), component: h(DemoAccounts), type: 'demo' },
]);

const selectedType = ref('dollar');
const activeIndex = ref(tabs.value.findIndex(tab => tab.type === selectedType.value));

// Watch for changes in selectedType and update the activeIndex accordingly
watch(selectedType, (newType) => {
    const index = tabs.value.findIndex(tab => tab.type === newType);
    if (index >= 0) {
        activeIndex.value = index;
    }
});

function updateType(event) {
    const selectedTab = tabs.value[event.index];
    selectedType.value = selectedTab.type;
}

const noticeVisible = ref(true);
</script>

<template>
    <AuthenticatedLayout :title="$t('public.accounts')">
        <div class="flex flex-col gap-20 md:gap-[100px]">
            <div class="flex flex-col items-start gap-5 self-stretch">
                <!-- notice -->
                <TransitionGroup
                    tag="div"
                    enter-from-class="-translate-y-full opacity-0"
                    enter-active-class="duration-300"
                    leave-active-class="duration-300"
                    leave-to-class="-translate-y-full opacity-0"
                    class="w-full"
                >
                    <div
                        v-if="noticeVisible"
                        class="py-3 px-4 flex justify-center self-stretch gap-4 border-t-8 border-info-500 shadow-toast bg-white items-start"
                        role="alert"
                    >
                        <div class="text-info-500">
                            <IconInfoOctagonFilled size="20" />
                        </div>
                        <div
                            class="flex flex-col gap-1 items-start w-full text-sm"
                        >
                            <div class="text-gray-950 font-semibold">
                                {{ $t('public.inactive_account_notice') }}
                            </div>
                            <div class="text-gray-700">
                                {{ $t('public.inactive_account_notice_message') }}
                            </div>
                        </div>
                        <div class="text-gray-400 hover:text-gray-600 hover:cursor-pointer select-none" @click="noticeVisible = false">
                            <IconX size="16" stroke-width="1.25" />
                        </div>
                    </div>
                </TransitionGroup>

                <!-- banner -->
                <div class="h-[260px] pl-5 pt-5 pr-3 pb-[26px] self-stretch rounded-2xl bg-white shadow-toast md:h-60 bg-[url('/img/background-account-banner.svg')] bg-no-repeat bg-right-bottom bg-contain
                    md:pl-8 md:pt-[30px] md:pb-[30px] md:pr-[246px]
                    xl:pt-11 xl:pb-11 xl:pr-[343px]"
                    >
                    <!-- Content -->
                    <div class="flex flex-col items-center gap-5 md:w-[450px] md:gap-8 md:items-start lg:w-[454px] xl:w-[643px]">
                        <div class="flex flex-col justify-center items-start gap-2 self-stretch">
                            <span class="self-stretch text-gray-950 font-bold md:text-lg">{{ $t('public.account_banner_header') }}</span>
                            <span class="self-stretch text-gray-700 text-xs md:text-sm">{{ $t('public.account_banner_message') }}</span>
                        </div>
                        <div class="flex flex-col justify-center items-start gap-3 self-stretch md:flex-row md:justify-start md:items-center md:gap-5">
                            <OpenLiveAccount
                                :tradingPlatforms="tradingPlatforms"
                            />
                            <OpenDemoAccount />
                        </div>
                    </div>
                </div>

                <!-- tab -->
                <div class="flex flex-col-reverse md:flex-row items-center gap-3 self-stretch justify-between">
                    <TabView class="flex flex-col" :activeIndex="activeIndex" @tab-change="updateType">
                        <TabPanel v-for="(tab, index) in tabs" :key="index" :header="tab.title" />
                    </TabView>

                </div>

                <component :is="tabs[activeIndex]?.component" :user="user"/>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
