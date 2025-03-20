<script setup>
import Button from "@/Components/Button.vue";
import {ref, watch, computed} from "vue";
import InputLabel from "@/Components/InputLabel.vue";
import {
    IconLoader2,
    IconCircleCheckFilled,
    IconInfoOctagonFilled,
    IconLink,
    IconCopy
} from "@tabler/icons-vue";
import { usePage, useForm } from "@inertiajs/vue3";
import Dropdown from "primevue/dropdown";
import InputText from "primevue/inputtext";
import InputError from "@/Components/InputError.vue";
import Dialog from "primevue/dialog";
import Tag from "primevue/tag";
import { formToJSON } from "axios";

const visible = ref(false);

const codeForm = useForm({
    accountType: null,
    downline_id: '',
});

const downlineOptions = ref([]);
const accountOptions = ref([]);
const selectedAccountType = ref('');
const isLoading = ref(false);
const account_link = ref('');
const filteredAccounts = ref([]);
const tooltipText = ref('copy')

const getOptions = async () => {
    try {
        const response = await axios.get('/account/getOptions');
        downlineOptions.value = response.data.downlineOptions;
        accountOptions.value = response.data.accountOptions;

        filteredAccounts.value = accountOptions.value.filter(account => account.account_group !== 'Standard');
    } catch (error) {
        // console.error('Error changing locale:', error);
    }
};

getOptions();

// watch(accountOptions, (newOptions) => {
//     filteredAccounts.value = newOptions.filter(account => account.account_group !== 'Standard');
// });

// Handle selection of an account
function selectAccount(type) {
    selectedAccountType.value = type;
    codeForm.accountType = type;
}

const openDialog = () => {
    visible.value = true;
    selectedAccountType.value = '';
    account_link.value = '';
    codeForm.reset();
}


const closeDialog = () => {
    visible.value = false;
}

const submitForm = async () => {
    isLoading.value = true;
    codeForm.post(route('account.generate_link'), {
        onSuccess: () => {
            account_link.value = `${window.location.origin}/account/access/${usePage().props.notification.link}`;
        },
        onError: (error) => {
            // console.error('Failed to generate link.', error);
        },
        onFinish: () => {
            isLoading.value = false;
        },
    });
};

const copyToClipboard = (text) => {
    const textToCopy = text;

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
    <Button
        type="button"
        variant="primary-flat"
        size="sm"
        class="w-full md:w-auto"
        @click="openDialog()"
        :disabled="filteredAccounts.length === 0"
    >
        <IconLink size="20" />
        {{ $t('public.generate_account_link') }}
    </Button>

    <Dialog
        v-model:visible="visible"
        :header="$t('public.generate_account_link')"
        modal
        class="dialog-sm sm:dialog-md"
    >
        <div class="flex flex-col items-center gap-8 self-stretch sm:gap-10">
            <div class="flex flex-col items-center gap-5 self-stretch">
                <div class="flex flex-col items-start gap-2 self-stretch">
                    <InputLabel for="accountType" :value="$t('public.account_type_placeholder')"/>
                    <div class="grid grid-cols-2 items-start gap-3 self-stretch">
                        <div
                            v-for="(account, index) in filteredAccounts"
                            :key="account.account_group"
                            @click="selectAccount(account.account_group)"
                            class="group col-span-1 items-start py-3 px-4 gap-1 self-stretch rounded-lg border shadow-input transition-colors duration-300 select-none cursor-pointer"
                            :class="{
                                'bg-primary-50 border-primary-500': selectedAccountType === account.account_group,
                                'bg-white border-gray-300 hover:bg-primary-50 hover:border-primary-500': selectedAccountType !== account.account_group,
                                'border-error-500': codeForm.errors.downline_id,
                            }"
                        >
                            <div class="flex items-center gap-3 self-stretch">
                                <span
                                    class="flex-grow text-sm font-semibold transition-colors duration-300 group-hover:text-primary-700"
                                    :class="{
                                        'text-primary-700': selectedAccountType === account.account_group,
                                        'text-gray-950': selectedAccountType !== account.account_group
                                    }"
                                >
                                    {{ $t(`public.${account.slug}`) }}
                                </span>
                                <IconCircleCheckFilled v-if="selectedAccountType === account.account_group" size="20" stroke-width="1.25" color="#2970FF" />
                            </div>
                        </div>
                    </div>
                    <InputError :message="codeForm.errors.accountType" />
                </div>
                <div class="grid grid-cols-4 items-start gap-1 self-stretch">
                    <InputLabel for="downline" :value="$t('public.downline')" class="col-span-4"/>
                    <Dropdown
                        v-model="codeForm.downline_id"
                        :options="downlineOptions"
                        optionLabel="name"
                        optionValue="value"
                        class="col-span-3"
                        scroll-height="236px"
                        :invalid="!!codeForm.errors.downline_id"
                        >
                        <template #value="slotProps">
                            <span>
                                {{ downlineOptions.find(option => option.value === slotProps.value)?.name || slotProps.value || $t('public.downline_placeholder') }}
                            </span>
                    </template>
                    </Dropdown>
                    <Button
                        type="button"
                        variant="primary-flat"
                        @click.prevent="submitForm('deposit')"
                        :disabled="isLoading"
                    >
                        <IconLoader2 v-if="isLoading" class="animate-spin w-4" />
                        {{ $t('public.generate') }}
                    </Button>
                    <InputError :message="codeForm.errors.downline_id" class="col-span-4"/>
                </div>
            </div>
        </div>
        <!-- <div class="flex gap-3 pt-5 items-center self-stretch">
            <div class="h-[1px] bg-gray-200 rounded-[5px] w-full"></div>
            <div class="text-xs md:text-sm text-gray-500 text-center md:w-full">{{ $t('public.generated_link_below') }}</div>
            <div class="h-[1px] bg-gray-200 rounded-[5px] w-full"></div>
        </div> -->
        <div class="flex gap-1 pt-5 items-center self-stretch relative">
            <InputText
                v-model="account_link"
                class="truncate w-full"
                :placeholder="$t('public.link_placeholder')"
                readonly
            />
            <Tag
                v-if="tooltipText === 'copied'"
                class="absolute -top-7 -right-3"
                severity="contrast"
                :value="$t(`public.${tooltipText}`)"
            ></Tag>
            <Button
                type="button"
                variant="gray-text"
                iconOnly
                pill
                @click="copyToClipboard(account_link)"
            >
                <IconCopy size="20" color="#667085" stroke-width="1.25" />
            </Button>
        </div>
    </Dialog>
</template>
