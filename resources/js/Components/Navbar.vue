<script setup>
import { sidebarState } from '@/Composables'
import {
    IconWorld,
    IconLogout,
    IconMenu2,
    IconQrcode,
    IconCopy,
    IconDots
} from '@tabler/icons-vue';
import QrcodeVue from 'qrcode.vue'
import {Link, usePage} from "@inertiajs/vue3";
import OverlayPanel from "primevue/overlaypanel";
import {ref} from "vue";
import {loadLanguageAsync} from "laravel-vue-i18n";
import Dialog from "primevue/dialog";
import InputText from "primevue/inputtext";
import Button from "@/Components/Button.vue";
import Tag from "primevue/tag";

defineProps({
    title: String
})

const op = ref();
const visible = ref(false);
const registerLink = ref(`${window.location.origin}/register/${usePage().props.auth.user.referral_code}`);
const tooltipText = ref('copy')
const copiedText = ref('')
const qrcodeContainer = ref();

const toggle = (event) => {
    op.value.toggle(event);
}

const currentLocale = ref(usePage().props.locale);
const locales = [
    {'label': 'English', 'value': 'en'},
    {'label': '简体中文', 'value': 'cn'},
    {'label': '繁體中文', 'value': 'tw'},
    {'label': 'tiếng Việt', 'value': 'vn'},
];

const changeLanguage = async (langVal) => {
    try {
        op.value.toggle(false)
        currentLocale.value = langVal;
        await loadLanguageAsync(langVal);
        await axios.get(`/locale/${langVal}`);
    } catch (error) {
        console.error('Error changing locale:', error);
    }
};

const markupProfiles = ref([]);

const getUserMarkupProfiles = async () => {
    if (usePage().props.auth.user.role != 'member') {
        try {
            const response = await axios.get('/getUserMarkupProfiles');
            markupProfiles.value = response.data.user_markup_profiles;
            // console.log(markupProfiles)
        } catch (error) {
            console.error('Error getting markups:', error);
        }
    }

};

getUserMarkupProfiles();

const getRegisterLink = (referralCode) => {
    return `${window.location.origin}/register/${referralCode}`;
};

const copyToClipboard = (text) => {
    const textToCopy = text;
    copiedText.value = text;

    const textArea = document.createElement('textarea');
    document.body.appendChild(textArea);

    textArea.value = textToCopy;
    textArea.select();

    try {
        const successful = document.execCommand('copy');

        tooltipText.value = 'copied';
        setTimeout(() => {
            tooltipText.value = 'copy';
        }, 1500);
    } catch (err) {
        console.error('Copy to clipboard failed:', err);
    }

    document.body.removeChild(textArea);
}

</script>

<template>
    <nav
        aria-label="secondary"
        class="sticky top-0 z-20 py-2 px-3 md:px-5 bg-gray-25 flex items-center gap-3"
    >
        <div
            class="inline-flex justify-center items-center rounded-full hover:bg-gray-100 w-12 h-12 shrink-0 grow-0 hover:select-none hover:cursor-pointer"
            @click="sidebarState.isOpen = !sidebarState.isOpen"
        >
            <IconMenu2 size="20" color="#182230" stroke-width="1.25" />
        </div>
        <div
            class="text-base md:text-lg font-semibold text-gray-950 w-full"
        >
            {{ title }}
        </div>
        <div class="flex items-center">
            <div
                v-if="usePage().props.auth.user.role != 'member'"
                class="w-12 h-12 p-3.5 flex items-center justify-center rounded-full hover:cursor-pointer hover:bg-gray-100 text-gray-800"
                @click="visible = true"
            >
                <IconQrcode size="20" stroke-width="1.25" />
            </div>
            <div
                class="w-12 h-12 p-3.5 flex items-center justify-center rounded-full hover:cursor-pointer hover:bg-gray-100 text-gray-800"
                @click="toggle"
            >
                <IconWorld size="20" stroke-width="1.25" />
            </div>
            <Link
                class="w-12 h-12 p-3.5 flex items-center justify-center rounded-full hover:cursor-pointer hover:bg-gray-100 text-gray-800 hidden md:block"
                :href="route('logout')"
                method="post"
                as="button"
            >
                <IconLogout size="20" stroke-width="1.25" />
            </Link>
        </div>
    </nav>

    <OverlayPanel ref="op" >
        <div class="py-2 flex flex-col items-center w-[100px]">
            <div
                v-for="locale in locales"
                class="p-3 flex items-center gap-3 self-stretch text-sm hover:bg-gray-100 hover:cursor-pointer"
                :class="{'bg-primary-50 text-primary-500': locale.value === currentLocale}"
                @click="changeLanguage(locale.value)"
            >
                {{ locale.label }}
            </div>
        </div>
    </OverlayPanel>


    <Dialog
        v-model:visible="visible"
        modal
        header=" "
        class="dialog-xs md:dialog-md"
    >
        <div class="flex flex-col gap-5 md:gap-8 items-center self-stretch">
            <div class="flex flex-col gap-1 items-center self-stretch">
                <span class="md:text-xl font-bold text-gray-950">{{ $t('public.referral_code') }}</span>
                <span class="text-xs md:text-base text-gray-500">{{ $t('public.referral_caption') }}</span>
            </div>

            <div
                class="grid grid-cols-1 gap-3 md:gap-4 w-full"
            >
                <div
                    v-for="markupProfile in markupProfiles"
                    class="flex flex-col gap-3 border border-gray-300 rounded-md p-3 md:p-4 w-full"
                >
                    <div class="flex gap-2 items-center self-stretch justify-between">
                        <span class="font-semibold text-gray-950">{{ markupProfile.name }}</span>
                    </div>
                    <div class="flex gap-2 flex-wrap">
                        <Tag
                            v-for="account_type in markupProfile.account_types"
                            severity="primary"
                            :value="account_type.name"
                        ></Tag>
                    </div>
                    <div class="flex gap-2 items-center self-stretch relative">
                        <InputText
                            :value="getRegisterLink(markupProfile.referral_code)"
                            class="truncate w-full"
                            readonly
                        />
                        <Tag
                            v-if="tooltipText === 'copied'  && copiedText === getRegisterLink(markupProfile.referral_code)"
                            class="absolute -top-7 -right-3"
                            severity="contrast"
                            :value="$t(`public.${tooltipText}`)"
                        ></Tag>
                        <Button
                            type="button"
                            size="sm"
                            variant="gray-text"
                            iconOnly
                            pill
                            @click="copyToClipboard(getRegisterLink(markupProfile.referral_code))"
                        >
                            <IconCopy size="20" color="#667085" stroke-width="1.25" />
                        </Button>
                    </div>
                </div>
                
            </div>
        </div>
    </Dialog>

</template>
