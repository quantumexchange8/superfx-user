<script setup>
import Button from "@/Components/Button.vue";
import {ref} from "vue";
import dayjs from "dayjs";
import {useConfirm} from "primevue/useconfirm";
import {trans} from "laravel-vue-i18n";
import {useForm} from "@inertiajs/vue3";
import Vue3Autocounter from 'vue3-autocounter';

const rebateEarn = ref();
const lastAppliedOn = ref();

const getRebateEarnData = async () => {
    try {
        const response = await axios.get('/dashboard/getRebateEarnData');
        rebateEarn.value = Number(response.data.rebateEarn);
        lastAppliedOn.value = response.data.lastAppliedOn;
    } catch (error) {
        console.error('Error fetching live accounts:', error);
    }
};

getRebateEarnData();

const form = useForm({
    rebate_amount: ''
});

const confirm = useConfirm();

const requireConfirmation = () => {
    confirm.require({
        group: 'headless-primary',
        header: trans('public.apply_rebate'),
        actionType: 'rebate',
        message: {
            text: trans('public.apply_rebate_desc'),
        },
        cancelButton: trans('public.cancel'),
        acceptButton: trans('public.confirm'),
        accept: () => {
            form.rebate_amount = rebateEarn.value;
            form.post(route('dashboard.applyRebate'), {
                onSuccess: () => {
                    getRebateEarnData();
                }
            });
        }
    });
}

</script>

<template>
    <div class="bg-white rounded-2xl p-4 md:px-10 md:py-8 flex flex-col gap-2 md:gap-7 items-center self-stretch shadow-toast w-full">
        <span class="text-left w-full text-sm font-medium text-gray-500">{{ $t('public.total_rebate_earned') }}</span>
        <div class="text-left w-full text-xxl font-semibold text-gray-950">
            $ <vue3-autocounter ref="counter" :startAmount="0" :endAmount="rebateEarn" :duration="1" separator="," decimalSeparator="." :decimals="3" :autoinit="true" />
        </div>
        <div class="flex flex-col gap-3 items-center self-stretch pt-1 md:pt-0">
            <Button
                type="button"
                variant="primary-flat"
                class="w-full"
                @click="requireConfirmation"
            >
                {{ $t('public.apply_rebate') }}
            </Button>
            <span class="text-sm text-gray-500">{{ $t('public.last_applied_on') }} {{ lastAppliedOn ? dayjs(lastAppliedOn).format('YYYY/MM/DD HH:mm:ss') : '-' }}</span>
        </div>
    </div>
</template>
