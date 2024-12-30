<script setup>
import StatusBadge from '@/Components/StatusBadge.vue';
import Tag from 'primevue/tag';
import {transactionFormat} from "@/Composables/index.js";
import { ref } from 'vue';

const { formatDateTime, formatAmount } = transactionFormat();

const props = defineProps({
    data: Object,
})

const tooltipText = ref('copy')

function copyToClipboard(text) {
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
    <template v-if="['apply_rebate', 'rebate_in', 'rebate_out'].includes(data.transaction_type)">
        <div class="flex flex-col items-center gap-3 self-stretch pb-4">
            <div class="flex items-center gap-1 self-stretch">
                <span class="w-[120px] text-gray-500 text-xs font-medium">{{ $t('public.date') }}</span>
                <span class="flex-grow text-gray-950 text-sm font-medium">{{ formatDateTime(data.created_at) }}</span>
            </div>
            <div class="flex items-center gap-1 self-stretch">
                <span class="w-[120px] text-gray-500 text-xs font-medium">{{ $t('public.description') }}</span>
                <div class="flex-grow text-gray-950 text-sm font-medium">
                    <span>{{ $t(`public.${data.transaction_type}`) }}</span>
                </div>
            </div>
            <div class="flex items-center gap-1 self-stretch">
                <span class="w-[120px] text-gray-500 text-xs font-medium">{{ $t('public.amount') }}</span>
                <span class="flex-grow text-gray-950 text-sm font-medium">
                    $ {{ formatAmount(data.transaction_amount) }}
                </span>
            </div>
            <div
                v-if="data.status"
                class="flex items-center gap-1 self-stretch"
            >
                <span class="w-[120px] text-gray-500 text-xs font-medium">{{ $t('public.status') }}</span>
                <StatusBadge :value="data.status">{{ $t('public.' + data.status) }}</StatusBadge>
            </div>
        </div>
        <div v-if="['rebate_in', 'rebate_out'].includes(data.transaction_type)" class="flex flex-col items-center py-4 gap-3 self-stretch border-t border-gray-200">
            <div class="flex flex-col items-start gap-1 self-stretch md:flex-row">
                <span class="h-5 flex flex-col justify-center self-stretch text-gray-500 text-xs font-medium md:w-[120px]">{{ $t('public.remarks') }}</span>
                <span class="md:max-w-[220px] text-gray-950 text-sm font-medium md:flex-grow">
                    <template v-if="data.remarks">
                        {{ data.remarks }}
                    </template>
                    <template v-else>-</template>
                </span>
            </div>
        </div>
    </template>
    <template v-else>
        <div class="flex flex-col items-center gap-3 self-stretch pb-4">
            <div class="flex items-center gap-1 self-stretch">
                <span class="w-[120px] text-gray-500 text-xs font-medium">{{ $t('public.transaction_id') }}</span>
                <span class="flex-grow text-gray-950 text-sm font-medium">{{ data.transaction_number }}</span>
            </div>
            <div class="flex items-center gap-1 self-stretch">
                <span class="w-[120px] text-gray-500 text-xs font-medium">{{ $t('public.transaction_date') }}</span>
                <span class="flex-grow text-gray-950 text-sm font-medium">{{ formatDateTime(data.created_at) }}</span>
            </div>
            <div class="flex items-center gap-1 self-stretch">
                <span class="w-[120px] text-gray-500 text-xs font-medium">
                    <template v-if="data.category === 'trading_account'">{{ $t('public.account') }}</template>
                    <template v-else>{{ $t('public.from') }}</template>
                </span>
                <span class="flex-grow text-gray-950 text-sm font-medium">
                    <template v-if="data.category === 'trading_account'">{{ data.transaction_type === 'deposit' ? data.to_meta_login : data.from_meta_login }}</template>
                    <template v-else>{{ $t(`public.${data.category}`) }}</template>
                </span>
            </div>
            <div class="flex items-center gap-1 self-stretch">
                <span class="w-[120px] text-gray-500 text-xs font-medium">{{ $t('public.description') }}</span>
                <div class="flex-grow text-gray-950 text-sm font-medium">
                    <span v-if="['transfer_to_account', 'account_to_account'].includes(data.transaction_type)">{{ $t('public.to') }} {{ data.to_meta_login }}</span>
                    <span v-else>{{ $t(`public.${data.transaction_type}`) }}</span>
                </div>
            </div>
            <div class="flex items-center gap-1 self-stretch">
                <span class="w-[120px] text-gray-500 text-xs font-medium">{{ $t('public.amount') }}</span>
                <span class="flex-grow text-gray-950 text-sm font-medium">
                    <template v-if="data.transaction_amount">
                        $ {{ formatAmount(data.transaction_type === 'withdrawal' ? data.amount : data.transaction_amount) }}
                    </template>
                    <template v-else>
                        -
                    </template>
                </span>
            </div>
            <div class="flex items-center gap-1 self-stretch">
                <span class="w-[120px] text-gray-500 text-xs font-medium">{{ $t('public.status') }}</span>
                <StatusBadge :value="data.status">{{ $t('public.' + data.status) }}</StatusBadge>
            </div>
        </div>
        <div v-if="['deposit', 'withdrawal'].includes(data.transaction_type)" class="flex flex-col items-center py-4 gap-3 self-stretch border-t border-b border-gray-200">
            <div v-if="data.transaction_type === 'deposit'" class="flex flex-col justify-center items-start gap-1 self-stretch md:flex-row md:justify-normal md:items-center relative">
                <template v-if="data.status !== 'processing'">
                    <Tag
                        v-if="tooltipText === 'copied'"
                        class="absolute -top-1 right-[120px] md:-top-7 md:right-20"
                        severity="contrast"
                        :value="$t(`public.${tooltipText}`)"
                    ></Tag>
                </template>
                <span class="self-stretch w-[120px] text-gray-500 text-xs font-medium">{{ $t('public.sent_address') }}</span>
                <div
                    class="w-full max-w-[360px] md:max-w-[220px] text-gray-950 font-medium text-sm truncate select-none cursor-pointer"
                    @click="copyToClipboard(data.from_wallet_address)"
                >
                    <template v-if="data.status === 'processing'">
                        -
                    </template>
                    <template v-else>{{ data.from_wallet_address }}</template>
                </div>
            </div>
            <div v-if="data.transaction_type === 'withdrawal'" class="h-[42px] flex flex-col justify-center items-start gap-1 self-stretch md:h-auto md:flex-row md:justify-normal md:items-center">
                <span class="self-stretch w-[120px] text-gray-500 text-xs font-medium">{{ $t('public.wallet_name') }}</span>
                <span class="w-full max-w-[360px] md:max-w-[220px] overflow-hidden text-gray-950 text-ellipsis text-sm font-medium">{{ data.wallet_name }}</span>
            </div>
            <div v-if="data.transaction_type === 'withdrawal'" class="h-[42px] flex flex-col justify-center items-start gap-1 self-stretch md:h-auto md:flex-row md:justify-normal md:items-center relative">
                <Tag
                    v-if="tooltipText === 'copied'"
                    class="absolute -top-1 right-[120px] md:-top-7 md:right-20"
                    severity="contrast"
                    :value="$t(`public.${tooltipText}`)"
                ></Tag>
                <span class="self-stretch w-[120px] text-gray-500 text-xs font-medium">{{ $t('public.receiving_address') }}</span>
                <div
                    class="w-full max-w-[360px] md:max-w-[220px] text-gray-950 font-medium text-sm truncate select-none cursor-pointer"
                    @click="copyToClipboard(data.to_wallet_address)"
                >
                    {{ data.to_wallet_address }}
                </div>
            </div>
        </div>
        <div v-if="['deposit', 'withdrawal'].includes(data.transaction_type)" class="flex flex-col items-center py-4 gap-3 self-stretch">
            <div class="flex flex-col items-start gap-1 self-stretch md:flex-row">
                <span class="h-5 flex flex-col justify-center self-stretch text-gray-500 text-xs font-medium md:w-[120px]">{{ $t('public.remarks') }}</span>
                <span class="md:max-w-[220px] text-gray-950 text-sm font-medium md:flex-grow">
                    <template v-if="data.remarks">
                        {{ data.remarks }}
                    </template>
                    <template v-else>-</template>
                </span>
            </div>
        </div>
    </template>
</template>