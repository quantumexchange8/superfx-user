<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Button from '@/Components/Button.vue';
import { usePage } from "@inertiajs/vue3";
import TieredMenu from 'primevue/tieredmenu';
import { computed, ref, h, watchEffect } from "vue";
import { 
    Apple,
    PlayStore,
    GooglePlay,
} from '@/Components/Icons/solid.jsx';
import { 
    Download02Icon,
    Link01Icon,
} from '@/Components/Icons/outline.jsx';
import {
    IconBrandWindows,
    IconBrandFinder,
    IconDeviceDesktop,
    IconWorldWww,
    IconBrandApple,
    IconBrandAndroid,
} from '@tabler/icons-vue';

const menu = ref();
const items = ref([
    {
        label: 'windows',
        icon: h(IconBrandWindows),
        command: () => {
            window.location.href = 'https://getctrader.com/mosanes/ctrader-mosanes-setup.exe';
        },
    },
    {
        label: 'mac',
        icon: h(IconBrandFinder),
        command: () => {
            window.location.href = 'https://getctradermac.com/mosanes/ctrader-mosanes-setup.dmg';
        },
    }
]);

const toggle = (event) => {
    menu.value.toggle(event);
};

</script>

<template>
    <AuthenticatedLayout :title="$t('public.download_center')">
        <div class="flex flex-col items-center">
            <div class="flex flex-col items-center gap-[41px] self-stretch md:gap-0">
                <div class="max-h-[660px] flex flex-col items-center gap-[-24px] self-stretch md:flex-row md:items-start md:px-10 md:max-h-[400px]">
                    <div class="flex flex-col items-start gap-5 self-stretch md:gap-8 md:pt-10 md:pb-16">
                        <img src="/img/ctrader-logo.svg" alt="logo">
                        <div class="flex flex-col items-start gap-4 self-stretch">
                            <span class="self-stretch text-gray-950 text-xl font-bold md:text-xxl">{{ $t('public.download_center_header_title') }}</span>
                            <span class="self-stretch text-gray-700 text-sm md:text-base">{{ $t('public.download_center_header_message') }}</span>
                        </div>
                    </div>
                    <img src="/img/mobile.png"  alt="" class="relative w-[300px] h-full flex flex-col justify-end items-center md:h-[510px]">
                </div>
                <div class="flex flex-col items-center py-24 gap-10 self-stretch md:px-10">
                    <div class="flex flex-col items-center gap-2 self-stretch">
                        <span class="self-stretch text-gray-950 text-center text-xl font-bold md:text-xxl">{{ $t('public.download_section_header') }}</span>
                        <span class="self-stretch text-gray-700 text-center text-sm md:text-base">{{ $t('public.download_section_message') }}</span>
                    </div>
                    <div class="w-full grid grid-cols-2 gap-3 md:gap-7 xl:grid-cols-4">
                        <div class="hidden md:flex flex-col min-w-[200px] items-start pl-3 pt-4 pb-3 gap-6 rounded-2xl bg-primary-500 md:pl-6 md:pr-6 md:pb-6 xl:min-w-0">
                            <div class="w-12 h-12 flex justify-center items-center p-3 rounded-full bg-white text-gray-950">
                                <IconDeviceDesktop size="24" stroke-width="1.25" />
                            </div>
                            <div class="flex flex-col items-start gap-1 self-stretch">
                                <span class="self-stretch text-gray-25 text-xs">{{ $t('public.desktop') }}</span>
                                <span class="self-stretch text-white text-xl font-semibold">{{ $t('public.pc') }}</span>
                            </div>
                            <Button type="button" size="sm" pill variant="primary-text" class="bg-white" @click="toggle" aria-haspopup="true" aria-controls="overlay_tmenu">
                                <Download02Icon class="w-4 h-4 text-black" />
                                <span class="text-black text-center text-sm font-medium">{{ $t('public.download') }}</span>
                            </Button>
                            <TieredMenu ref="menu" id="overlay_tmenu" :model="items" popup>
                                <template #item="{ item, props }">
                                    <div
                                        class="flex items-center gap-3 self-stretch"
                                        v-bind="props.action"
                                    >
                                        <component :is="item.icon" size="20" stroke-width="1.25" :color="item.label === 'delete_account' ? '#F04438' : '#667085'" />
                                        <span class="font-medium" :class="{'text-error-500': item.label === 'delete_account'}">{{ $t(`public.${item.label}`) }}</span>
                                    </div>
                                </template>
                            </TieredMenu>
                        </div>
                        <div class="hidden md:flex flex-col min-w-[200px] md items-start pl-3 pt-4 pb-3 gap-6 rounded-2xl bg-gray-100 md:pl-6 md:pr-6 md:pb-6 xl:min-w-0">
                            <div class="w-12 h-12 flex justify-center items-center p-3 rounded-full bg-white text-gray-950">
                                <IconWorldWww size="24" stroke-width="1.25" />
                            </div>
                            <div class="flex flex-col items-start gap-1 self-stretch">
                                <span class="self-stretch text-gray-500 text-xs">{{ $t('public.popular_browser') }}</span>
                                <span class="self-stretch text-gray-950 text-xl font-semibold">{{ $t('public.web') }}</span>
                            </div>
                            <a href="https://app.home.mosanes.com/" class="w-full">
                                <Button type="button" size="sm" pill variant="primary-flat">
                                        <Link01Icon class="w-4 h-4 text-white" />
                                        <span class="text-white text-center text-sm font-medium">{{ $t('public.web_trader') }}</span>
                                </Button>
                            </a>
                        </div>
                        <div class="min-w-[150px] flex flex-col items-start pl-3 pt-4 pb-3 gap-6 rounded-2xl bg-gray-100 md:min-w-[200px] md:pl-6 md:pr-6 md:pb-6 xl:min-w-0">
                            <div class="w-12 h-12 flex justify-center items-center p-3 rounded-full bg-white text-gray-950">
                                <IconBrandApple size="24" stroke-width="1.25" />
                            </div>
                            <div class="flex flex-col items-start gap-1 self-stretch">
                                <span class="self-stretch text-gray-500 text-xs">{{ $t('public.mobile_or_tablet') }}</span>
                                <span class="self-stretch text-gray-950 text-xl font-semibold">{{ $t('public.ios') }}</span>
                            </div>
                            <a href="https://apps.apple.com/my/app/ctrader/id767428811" class="w-full">
                                <div class="w-[108px] h-9 flex justify-center items-start pl-[7.20px] pr-[5.40px] py-[5.50px] gap-[7.20px] bg-black rounded-[5.4px] cursor-pointer hover:bg-gray-700">
                                    <Apple class="w-[18px] h-[21.60px] flex-shrink-0" />
                                    <div class="w-[70.2px] flex flex-col items-start flex-shrink-0">
                                        <span class="self-stretch text-white text-[8px] leading-[8px] font-medium">{{ $t('public.download_on_the') }}</span>
                                        <span class="self-stretch text-white text-[16px] leading-none font-medium tracking-[-1px]">{{ $t('public.app_store') }}</span>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="min-w-[150px] flex flex-col items-start pl-3 pt-4 pb-3 gap-6 rounded-2xl bg-gray-100 md:min-w-[200px] md:pl-6 md:pr-6 md:pb-6 xl:min-w-0">
                            <div class="w-12 h-12 flex justify-center items-center p-3 rounded-full bg-white text-gray-950">
                                <IconBrandAndroid size="24" stroke-width="1.25" />
                            </div>
                            <div class="flex flex-col items-start gap-1 self-stretch">
                                <span class="self-stretch text-gray-500 text-xs">{{ $t('public.mobile_or_tablet') }}</span>
                                <span class="self-stretch text-gray-950 text-xl font-semibold">{{ $t('public.android') }}</span>
                            </div>
                            <a href="https://play.google.com/store/apps/details?id=com.spotware.ct&hl=en" class="w-full">
                                <div class="w-[108px] h-9 flex justify-center items-center pl-[7.20px] pr-[9px] pt-[4.50px] pb-[3.30px] gap-[6.30px] bg-black rounded-[5.4px] cursor-pointer hover:bg-gray-700">
                                    <PlayStore class="w-[18.90px] h-[21.60px] flex-shrink-0" />
                                    <div class="flex flex-col items-start gap-[2.7px]">
                                        <span class="self-stretch text-white text-[9px] uppercase">{{ $t('public.get_it_on') }}</span>
                                        <GooglePlay class="w-[66.6px] h-[13.5px] fill-white" />
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
