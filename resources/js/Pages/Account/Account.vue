<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import OpenDemoAccount from "@/Pages/Account/Partials/OpenDemoAccount.vue";
import {IconInfoOctagonFilled, IconX} from "@tabler/icons-vue";
import OpenLiveAccount from "@/Pages/Account/Partials/OpenLiveAccount.vue";
import {computed, ref} from "vue";
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';
import AccountView from "@/Pages/Account/AccountView.vue";

const props = defineProps({
    methods: Array,
    tradingPlatforms: Array,
    accountsCount: Object,
});

const visible = ref(true);

const activeIndex = ref(0);

// Generate tabs dynamically from tradingPlatforms
const tabs = computed(() => {
    return props.tradingPlatforms.map((platform) => ({
        title: platform.slug,
        value: platform.slug
    }));
});
</script>

<template>
    <AuthenticatedLayout :title="$t('public.accounts')">
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
                    v-if="visible"
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
                    <div class="text-gray-400 hover:text-gray-600 hover:cursor-pointer select-none" @click="visible = false">
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
                        <OpenDemoAccount
                            :disabled="tabs?.[activeIndex]?.value === 'mt5'"
                        />
                    </div>
                </div>
            </div>

            <TabView v-model:activeIndex="activeIndex" class="w-full">
                <TabPanel
                    v-for="(tab, index) in tabs"
                    :key="tab.value"
                >
                    <template #header>
                        <div class="flex gap-2 items-center">
                            <img :src="`/img/trading/${tab.title}.png`" alt="mt" class="w-9 h-9 rounded-full grow-0 shrink-0" />
                            <span class="uppercase">{{ tab.title }}</span>
                        </div>
                    </template>
                    <!-- Pass selected slug to AccountView -->
                    <AccountView
                        :slug="tab.value"
                        :methods="methods"
                        :accountsCount="accountsCount"
                    />
                </TabPanel>
            </TabView>
        </div>
    </AuthenticatedLayout>
</template>
