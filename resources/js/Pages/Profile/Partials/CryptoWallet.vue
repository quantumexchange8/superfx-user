<script setup>
import InputError from "@/Components/InputError.vue";
import InputText from "primevue/inputtext";
import {computed, ref, watch} from "vue";
import {useForm, usePage} from "@inertiajs/vue3";
import Button from "@/Components/Button.vue";

const user = ref(usePage().props.auth.user);
const paymentAccounts = ref(usePage().props.auth.payment_account);

const form = useForm({
    id: new Array(3).fill(''),
    user_id: '',
    wallet_name: new Array(3).fill(''),
    token_address: new Array(3).fill(''),
});

const displayAccounts = computed(() => {
    if (paymentAccounts.value.length === 0) {
        return new Array(3).fill({ wallet_name: '', token_address: '' });
    }
    return paymentAccounts.value.concat(new Array(3 - paymentAccounts.value.length).fill({ wallet_name: '', token_address: '' }));
});

watch(
    () => paymentAccounts.value,
    (newAccounts) => {
        if (paymentAccounts.value.length > 0) {
            const updatedAccounts = newAccounts.concat(new Array(3 - newAccounts.length).fill({ wallet_name: '', token_address: '' }));
            form.wallet_name = updatedAccounts.map((account, index) => account.payment_account_name || form.wallet_name[index] || '');
            form.token_address = updatedAccounts.map((account, index) => account.account_no || form.token_address[index] || '');
        }
    },
    { immediate: true }
);

const submitForm = () => {
    form.id = displayAccounts.value.map((account, index) =>
        account && account.id ? account.id : form.id[index]
    );
    form.wallet_name = displayAccounts.value.map((account, index) =>
        account && account.wallet_name ? account.wallet_name : form.wallet_name[index]
    );
    form.token_address = displayAccounts.value.map((account, index) =>
        account && account.token_address ? account.token_address : form.token_address[index]
    );

    form.user_id = user.value.id;

    form.post(route('profile.updateCryptoWalletInfo'), {
        preserveScroll: true,
    });
};

const dirtyFields = ref({
    wallet_name: new Array(3).fill(false),
    token_address: new Array(3).fill(false),
});

const handleInputChange = (field, index) => {
    dirtyFields.value[field][index] = true;
};

const resetForm = () => {
    dirtyFields.value.wallet_name.forEach((isDirty, index) => {
        if (isDirty) {
            form.wallet_name[index] = '';
        }
    });

    dirtyFields.value.token_address.forEach((isDirty, index) => {
        if (isDirty) {
            form.token_address[index] = '';
        }
    });

    // Reset dirty fields tracking
    dirtyFields.value = {
        wallet_name: new Array(3).fill(false),
        token_address: new Array(3).fill(false),
    };
};
</script>

<template>
    <div class="p-4 md:py-6 md:px-8 flex flex-col gap-8 items-center self-stretch rounded-2xl shadow-toast w-full">
        <div class="flex flex-col gap-1 items-start justify-center w-full">
            <span class="text-gray-950 font-bold">{{ $t('public.cryptocurrency_wallet') }}</span>
            <span class="text-gray-500 text-xs">{{ $t('public.cryptocurrency_wallet_caption') }}</span>
        </div>

        <form class="flex flex-col gap-8 items-center self-stretch w-full">
            <div class="flex flex-col md:flex-row gap-5 items-start flex-wrap w-full">
                <div
                    v-for="(account, index) in displayAccounts"
                    class="flex flex-col md:flex-row gap-2 md:gap-5 w-full md:items-center"
                >
                    <label
                        :for="`wallet_name_${index+1}`"
                        class="block min-w-[72px] text-sm font-semibold"
                        :class="{
                            'text-gray-700': !form.errors[`wallet_name.${index}`],
                            'text-error-500': form.errors[`wallet_name.${index}`]
                        }"
                    >
                        {{ $t('public.wallet') }} #{{ index + 1 }}
                    </label>
                    <div class="flex flex-col gap-1 w-full">
                        <InputText
                            :id="`wallet_name_${index+1}`"
                            type="text"
                            class="block w-full"
                            :aria-label="`wallet_name_${index+1}`"
                            v-model="form.wallet_name[index]"
                            :placeholder="$t('public.wallet_name')"
                            :invalid="!!form.errors[`wallet_name.${index}`]"
                            @input="handleInputChange('wallet_name', index)"
                        />
                        <InputError :message="form.errors[`wallet_name.${index}`]" />
                    </div>
                    <div class="flex flex-col gap-1 w-full">
                        <InputText
                            :id="`token_address_${index+1}`"
                            type="text"
                            class="block w-full"
                            :aria-label="`token_address_${index+1}`"
                            v-model="form.token_address[index]"
                            :placeholder="$t('public.token_address')"
                            :invalid="!!form.errors[`token_address.${index}`]"
                            @input="handleInputChange('token_address', index)"
                        />
                        <InputError :message="form.errors[`token_address.${index}`]" />
                    </div>
                </div>
            </div>

            <div class="flex justify-end items-center gap-4 self-stretch">
                <Button
                    type="button"
                    variant="gray-tonal"
                    :disabled="form.processing"
                    @click="resetForm"
                >
                    {{ $t('public.cancel') }}
                </Button>
                <Button
                    variant="primary-flat"
                    :disabled="form.processing"
                    @click="submitForm"
                >
                    {{ $t('public.save_changes') }}
                </Button>
            </div>
        </form>
    </div>
</template>
