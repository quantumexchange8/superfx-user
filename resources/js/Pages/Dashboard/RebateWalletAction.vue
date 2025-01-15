<script setup>
import Button from "@/Components/Button.vue";
import {ref} from "vue";
import Dialog from "primevue/dialog";
import WalletWithdrawal from "@/Pages/Dashboard/Partials/WalletWithdrawal.vue";
import {usePage} from "@inertiajs/vue3";
import {trans} from "laravel-vue-i18n";
import {useConfirm} from "primevue/useconfirm";
import WalletTransfer from "@/Pages/Dashboard/Partials/WalletTransfer.vue";

const props = defineProps({
    rebateWallet: Object,
    terms: Object,
})

const visible = ref(false);
const dialogType = ref('');
const paymentAccounts = usePage().props.auth.payment_account;
const confirm = useConfirm();

const requireAccountConfirmation = (accountType) => {
    const messages = {
        crypto: {
            group: 'headless-primary',
            header: trans('public.payment_account_required'),
            text: trans('public.payment_account_required_text'),
            actionType: 'crypto',
            cancelButton: trans('public.later'),
            acceptButton: trans('public.add_Wallet'),
            action: () => {
                window.location.href = route('profile');
            }
        }
    };

    const { group, header, text, dynamicText, suffix, actionType, cancelButton, acceptButton, action } = messages[accountType];

    confirm.require({
        group,
        header,
        actionType,
        message: {
            text,
            dynamicText,
            suffix
        },
        cancelButton,
        acceptButton,
        accept: action
    });
}

const openDialog = (type) => {
    if (type === 'withdrawal' && paymentAccounts.length === 0) {
        requireAccountConfirmation('crypto');
    } else {
        dialogType.value = type;
        visible.value = true;
    }
}
</script>

<template>
    <div class="bg-white py-7 px-10 flex gap-5 items-center self-stretch z-20">
        <Button
            type="button"
            variant="gray-outlined"
            class="w-full"
            @click="openDialog('transfer')"
            :disabled="!rebateWallet"
        >
            {{ $t('public.transfer') }}
        </Button>
        <Button
            type="button"
            variant="gray-outlined"
            class="w-full"
            @click="openDialog('withdrawal')"
            :disabled="!rebateWallet"
        >
            {{ $t('public.withdrawal') }}
        </Button>
    </div>

    <Dialog
        v-model:visible="visible"
        modal
        :header="$t(`public.${dialogType}`)"
        class="dialog-xs md:dialog-sm"
    >
        <template v-if="dialogType === 'transfer'">
            <WalletTransfer
                :rebateWallet="rebateWallet"
                @update:visible="visible = false"
            />
        </template>

        <template v-if="dialogType === 'withdrawal'">
            <WalletWithdrawal
                :wallet="rebateWallet"
                :terms="terms"
                @update:visible="visible = false"
            />
        </template>
    </Dialog>
</template>
