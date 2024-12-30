<script setup>
import {ref, h, computed} from 'vue';
import { usePage, useForm } from "@inertiajs/vue3";
import Dialog from 'primevue/dialog';
import Button from "@/Components/Button.vue";
import {
    IconDots,
    IconCreditCardPay,
    IconScale,
    IconHistory,
    IconDatabaseMinus,
    IconTrash
} from '@tabler/icons-vue';
import toast from '@/Composables/toast';
import AccountReport from '@/Pages/TradingAccount/Partials/AccountReport.vue';
import { useConfirm } from 'primevue/useconfirm';
import { trans } from "laravel-vue-i18n";
import TieredMenu from "primevue/tieredmenu";
import AccountWithdrawal from "@/Pages/TradingAccount/Partials/AccountWithdrawal.vue";
import ChangeLeverage from "@/Pages/TradingAccount/Partials/ChangeLeverage.vue";

const props = defineProps({
    account: Object,
    type: String,
});

const paymentAccounts = usePage().props.auth.payment_account;
const menu = ref();
const visible = ref(false);
const dialogType = ref('');

const items = ref([
    {
        label: 'withdrawal',
        icon: h(IconCreditCardPay),
        command: () => {
            if (paymentAccounts.length === 0) {
                requireAccountConfirmation('crypto');
            } else {
                visible.value = true;
                dialogType.value = 'withdrawal';
            }
        },
    },
    {
        label: 'change_leverage',
        icon: h(IconScale),
        command: () => {
            if (props.account.account_type_leverage === 0) {
                visible.value = true;
                dialogType.value = 'change_leverage';
            } else {
                toast.add({
                    title: trans('public.toast_leverage_change_warning'),
                    type: 'warning',
                });
            }
        },
        account_type: 'standard_account'
    },
    {
        label: 'revoke_pamm',
        icon: h(IconDatabaseMinus),
        command: () => {
            requireAccountConfirmation('revoke');
        },
        account_type: 'premium_account',
        disabled: props.account.status === 'pending' || props.account.status === null,
    },
    {
        label: 'account_report',
        icon: h(IconHistory),
        command: () => {
            visible.value = true;
            dialogType.value = 'account_report';
        },
    },
    {
        separator: true,
    },
    {
        label: 'delete_account',
        icon: h(IconTrash),
        command: () => {
            requireAccountConfirmation('live');
        },
    },
]);

const filteredItems = computed(() => {
    return items.value.filter(item => {
        if (props.account.asset_master_id) {
            return !(item.label === 'withdrawal' || item.label === 'change_leverage' || item.label === 'delete_account' || item.separator);
        }

        if (item.account_type) {
            return item.account_type === props.account.account_type;
        }
        return true;
    });
});

const toggle = (event) => {
    menu.value.toggle(event);
};

const form = useForm({
    account_id: props.account.id,
    type: '',
});

const confirm = useConfirm();
const requireAccountConfirmation = (accountType) => {
    const messages = {
        live: {
            group: 'headless-error',
            header: trans('public.delete_account'),
            text: trans('public.delete_account_text'),
            dynamicText: props.account.meta_login,
            suffix: '? ' + trans('public.confirmation_text_suffix'),
            actionType: 'delete',
            cancelButton: trans('public.cancel'),
            acceptButton: trans('public.delete_confirm'),
            action: () => {
                form.delete(route('account.delete_account'));
            }
        },
        demo: {
            group: 'headless-error',
            header: trans('public.delete_demo_account'),
            text: trans('public.delete_demo_account_text') + '?',
            suffix: trans('public.confirmation_text_suffix'),
            actionType: 'delete',
            cancelButton: trans('public.cancel'),
            acceptButton: trans('public.delete_confirm'),
            action: () => {
                form.type = 'demo';
                form.delete(route('account.delete_account'));
            }
        },
        revoke: {
            group: 'headless-error',
            header: trans('public.revoke_pamm'),
            text: trans('public.revoke_account_text'),
            suffix: '? ' + trans('public.confirmation_text_suffix'),
            actionType: 'revoke',
            cancelButton: trans('public.cancel'),
            acceptButton: trans('public.revoke_confirm'),
            action: () => {
                form.post(route('account.revoke_account'));
            }
        },
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
};
</script>

<template>
    <Button
        v-if="props.type !== 'demo'"
        variant="gray-text"
        size="sm"
        type="button"
        iconOnly
        pill
        @click="toggle"
        aria-haspopup="true"
        aria-controls="overlay_tmenu"
    >
        <IconDots size="16" stroke-width="1.25" color="#667085" />
    </Button>

    <Button
        v-if="props.type === 'demo'"
        variant="gray-text"
        size="sm"
        type="button"
        iconOnly
        pill
        @click="requireAccountConfirmation('demo')"
    >
        <IconTrash size="16" stroke-width="1.25" color="#667085" />
    </Button>

    <!-- Menu -->
    <TieredMenu ref="menu" id="overlay_tmenu" :model="filteredItems" popup>
        <template #item="{ item, props }">
            <div
                class="flex items-center gap-3 self-stretch"
                v-bind="props.action"
                :class="{ 'hidden': item.disabled }"
            >
                <component :is="item.icon" size="20" stroke-width="1.25" :color="item.label === 'delete_account' ? '#F04438' : '#667085'" />
                <span class="font-medium" :class="{'text-error-500': item.label === 'delete_account'}">{{ $t(`public.${item.label}`) }}</span>
            </div>
        </template>
    </TieredMenu>

    <Dialog
        v-model:visible="visible"
        modal
        :header="$t(`public.${dialogType}`)"
        class="dialog-xs"
        :class="(dialogType === 'account_report') ? 'md:dialog-md' : 'md:dialog-sm'"
    >
        <template v-if="dialogType === 'withdrawal'">
            <AccountWithdrawal
                :account="account"
                @update:visible="visible = false"
            />
        </template>

        <template v-if="dialogType === 'change_leverage'">
            <ChangeLeverage
                :account="account"
                @update:visible="visible = false"
            />
        </template>

        <template v-if="dialogType === 'account_report'">
            <AccountReport
                :account="props.account"
                @update:visible="visible = false"
            />
        </template>
    </Dialog>
</template>
