<script setup>
import Button from "@/Components/Button.vue"
import {ref, watchEffect} from "vue";
import {transactionFormat} from "@/Composables/index.js";
import {usePage} from "@inertiajs/vue3";
import {useConfirm} from "primevue/useconfirm";
import {trans} from "laravel-vue-i18n";
import Dialog from "primevue/dialog";
import WalletWithdrawal from "@/Pages/Dashboard/Partials/WalletWithdrawal.vue";
import BonusWithdrawalHistory from "@/Pages/Billboard/Partials/BonusWithdrawalHistory.vue";
import toast from "@/Composables/toast.js";

const props = defineProps({
    terms: Object
})

const bonusWallet = ref();
const {formatAmount} = transactionFormat();

const getBonusWallet = async () => {
    try {
        const response = await axios.get('/billboard/getBonusWallet');
        bonusWallet.value = response.data.bonusWallet
    } catch (error) {
        console.error('Error pending counts:', error);
    }
};

getBonusWallet();

const visible = ref(false);
const dialogType = ref('');
const paymentAccounts = usePage().props.auth.payment_account;
const confirm = useConfirm();

const requireAccountConfirmation = (accountType) => {
    const messages = {
        crypto: {
            group: 'headless-primary',
            header: trans('public.crypto_wallet_required'),
            text: trans('public.crypto_wallet_required_text'),
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
        if (bonusWallet.value) {
            dialogType.value = type;
            visible.value = true;
        } else {
            toast.add({
                title: trans('public.no_target_achievements'),
                type: 'warning',
            });
        }
    }
}

watchEffect(() => {
    if (usePage().props.notification !== null) {
        getBonusWallet();
    }
});
</script>

<template>
    <div class="flex flex-col items-center self-stretch rounded-2xl bg-white shadow-toast w-full md:w-1/3 min-w-[260px] relative overflow-hidden">
        <div class="flex items-center justify-center relative w-full min-h-[200px]">
            <div class="absolute -left-3 xl:left-0">
                <img src="/img/billboard/bonus-illustration-left.svg" alt="left">
            </div>
            <div class="absolute -right-3 xl:right-0">
                <img src="/img/billboard/bonus-illustration-right.svg" alt="right">
            </div>
        </div>
        <div class="absolute top-[35%] -translate-y-1/2">
            <div class="flex flex-col items-center self-stretch gap-3">
                <span class="text-sm text-gray-500">{{ $t('public.available_bonus') }}</span>
                <span class="text-xxl text-gray-950 font-semibold">$ {{ formatAmount(bonusWallet ? bonusWallet.balance : 0) }}</span>
            </div>
        </div>
        <div class="p-6 flex flex-col items-center self-stretch gap-3">
            <Button
                type="button"
                variant="primary-flat"
                class="w-full"
                @click="openDialog('bonus_withdrawal')"
            >
                {{ $t('public.withdrawal') }}
            </Button>
            <Button
                type="button"
                variant="gray-outlined"
                class="w-full"
                @click="openDialog('bonus_withdrawal_history')"
            >
                {{ $t('public.history') }}
            </Button>
        </div>
    </div>

    <Dialog
        v-model:visible="visible"
        modal
        :header="$t(`public.${dialogType}`)"
        :class="[dialogType === 'bonus_withdrawal' ? 'dialog-xs md:dialog-sm' : 'dialog-xs md:dialog-md']"
    >
        <template v-if="dialogType === 'bonus_withdrawal'">
            <WalletWithdrawal
                :wallet="bonusWallet"
                :terms="terms"
                @update:visible="visible = false"
            />
        </template>

        <template v-if="dialogType === 'bonus_withdrawal_history'">
            <BonusWithdrawalHistory
                :bonusWallet="bonusWallet"
            />
        </template>
    </Dialog>
</template>
