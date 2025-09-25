<script setup>
import Button from "@/Components/Button.vue";
import {computed, ref} from "vue";
import Dialog from "primevue/dialog";
import Dropdown from "primevue/dropdown";
import InputLabel from "@/Components/InputLabel.vue";
import {useForm} from "@inertiajs/vue3";
import InputError from "@/Components/InputError.vue";
import {transactionFormat} from "@/Composables/index.js";

const props = defineProps({
    disabled: Boolean
})

const visible = ref(false);
const {formatAmount} = transactionFormat();

const openDialog = () => {
    visible.value = true;
}

const amountSel = [
    100, 200, 300, 400, 500, 1000, 2000, 3000, 4000, 5000,
    10000, 15000, 20000, 25000, 30000, 35000, 40000, 45000, 50000,
    60000, 70000, 80000, 90000, 100000, 200000, 300000, 400000, 500000, 1000000
];

const leverages = [
    {name: '1:1', value: 1},
    {name: '1:10', value: 10},
    {name: '1:20', value: 20},
    {name: '1:50', value: 50},
    {name: '1:100', value: 100},
    {name: '1:200', value: 200},
    {name: '1:300', value: 300},
    {name: '1:400', value: 400},
    {name: '1:500', value: 500},
]

const form = useForm({
    amount: '',
    leverage: ''
})

const submitForm = () => {
    form.post(route('account.storeDemoAccount'), {
        onSuccess: () => {
            closeDialog();
        }
    })
}

const closeDialog = () => {
    visible.value = false;
}

const buttonSize = computed(() => {
    return window.innerWidth < 768 ? 'sm' : 'base';
})
</script>

<template>
    <Button
        type="button"
        variant="primary-outlined"
        class="w-[142px] md:w-full text-nowrap"
        :size="buttonSize"
        @click="openDialog"
        :disabled="disabled"
    >
        {{ $t('public.demo_account') }}
    </Button>

    <Dialog
        v-model:visible="visible"
        modal
        :header="$t('public.open_demo_account')"
        class="dialog-xs md:dialog-sm"
    >
        <div class="flex flex-col items-center gap-8 self-stretch sm:gap-10">
            <div class="flex flex-col items-center gap-5 self-stretch">
                <div class="flex flex-col items-start gap-2 self-stretch">
                    <InputLabel for="amount" :value="$t('public.amount')" />
                    <Dropdown
                        v-model="form.amount"
                        :options="amountSel"
                        class="w-full"
                        scroll-height="236px"
                        :invalid="!!form.errors.amount"
                        :placeholder="$t('public.select')"
                    >
                        <template #value="slotProps">
                            {{ slotProps.value ? `$ ${formatAmount(slotProps.value)}` : slotProps.placeholder }}
                        </template>
                        <template #option="slotProps">
                            {{ `$ ${formatAmount(slotProps.option)}`}}
                        </template>
                    </Dropdown>
                    <InputError :message="form.errors.amount" />
                </div>
                <div class="flex flex-col items-start gap-1 self-stretch">
                    <InputLabel for="leverage" :value="$t('public.leverage')" />
                    <Dropdown
                        v-model="form.leverage"
                        :options="leverages"
                        optionLabel="name"
                        optionValue="value"
                        class="w-full"
                        scroll-height="236px"
                        :invalid="!!form.errors.leverage"
                    >
                        <template #value="slotProps">
                        <span>
                            {{ leverages.find(option => option.value === slotProps.value)?.name || slotProps.value || $t('public.leverages_placeholder') }}
                        </span>
                        </template>
                    </Dropdown>
                    <InputError :message="form.errors.leverage" />
                </div>
            </div>
        </div>
        <div class="flex justify-end items-center pt-5 gap-4 self-stretch md:pt-7">
            <Button
                variant="primary-flat"
                type="submit"
                :class="{ 'opacity-25': form.processing }"
                :disabled="form.processing"
                @click.prevent="submitForm"
            >
                {{ $t('public.open_demo_account') }}
            </Button>
        </div>
    </Dialog>
</template>
