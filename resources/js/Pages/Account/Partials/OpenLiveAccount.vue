<script setup>
import InputError from "@/Components/InputError.vue";
import Dialog from "primevue/dialog";
import InputLabel from "@/Components/InputLabel.vue";
import Dropdown from "primevue/dropdown";
import SelectChipGroup from "@/Components/SelectChipGroup.vue";
import Tag from "primevue/tag";
import Button from "@/Components/Button.vue";
import {computed, ref, watch} from "vue";
import {useForm} from "@inertiajs/vue3";
import Skeleton from "primevue/skeleton";

const props = defineProps({
    tradingPlatforms: Array,
})

const visible = ref(false);

const openDialog = () => {
    visible.value = true;
}

const form = useForm({
    trading_platform: props.tradingPlatforms[0].slug,
    account_type_id: '',
    leverage: '',
});

// Get account types
const accountTypes = ref([])
const loadingAccountTypes = ref(false);

const getAccountTypeByPlatform = async () => {
    loadingAccountTypes.value = true;

    try {
        const response = await axios.get(
            `/getAccountTypeByPlatform?trading_platform=${form.trading_platform}`
        );

        // All groups from API
        accountTypes.value = response.data.accountTypes;

    } catch (error) {
        console.error('Error getting account types:', error);
    } finally {
        loadingAccountTypes.value = false;
    }
};

watch(() => form.trading_platform, () => {
    getAccountTypeByPlatform()
}, {immediate: true})

const leverages = ref([])
const loadingLeverages = ref(false);

const getLeverages = async () => {
    loadingLeverages.value = true;

    try {
        const response = await axios.get(
            `/getLeverages?account_type_id=${form.account_type_id}`
        );

        // All groups from API
        leverages.value = response.data.leverages;

    } catch (error) {
        console.error('Error getting leverages:', error);
    } finally {
        loadingLeverages.value = false;
    }
};

watch(() => form.account_type_id, () => {
    getLeverages()
})

const submitForm = () => {
    form.post(route('account.storeLiveAccount'), {
        onSuccess: () => {
            closeDialog();
            form.reset();
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
        variant="primary-flat"
        class="w-[142px] md:w-full text-nowrap"
        :size="buttonSize"
        @click="openDialog"
    >
        {{ $t('public.live_account') }}
    </Button>

    <Dialog
        v-model:visible="visible"
        modal
        :header="$t('public.open_live_account')"
        class="dialog-sm sm:dialog-sm"
    >
        <form>
            <div class="flex flex-col items-center gap-8 self-stretch sm:gap-10">
                <div class="flex flex-col items-center gap-5 self-stretch">
                    <div class="flex flex-col items-start gap-1 self-stretch">
                        <InputLabel
                            for="trading_platform"
                            :value="$t('public.platform')"
                        />
                        <Dropdown
                            v-model="form.trading_platform"
                            :options="tradingPlatforms"
                            option-label="slug"
                            option-value="slug"
                            class="w-full"
                            scroll-height="236px"
                            :placeholder="$t('public.leverages_placeholder')"
                            :invalid="!!form.errors.trading_platform"
                        >
                            <template #value="{value, placeholder}">
                                <div v-if="value">
                                    <span class="uppercase">{{ value }}</span>
                                </div>
                                <div v-else>
                                    {{ placeholder }}
                                </div>
                            </template>
                            <template #option="{option}">
                                <span class="uppercase">{{ option.slug }}</span>
                            </template>
                        </Dropdown>
                        <InputError :message="form.errors.trading_platform" />
                    </div>

                    <div class="flex flex-col items-start gap-1 self-stretch">
                        <InputLabel for="accountType" :value="$t('public.account_type_placeholder')" />
                        <Skeleton
                            v-if="loadingAccountTypes"
                            width="10rem"
                            height="2.75rem"
                        />
                        <SelectChipGroup
                            v-else
                            v-model="form.account_type_id"
                            :items="accountTypes"
                            value-key="id"
                        >
                            <template #option="{ item }">
                                <span>{{ item.name }}</span>
                            </template>
                        </SelectChipGroup>
                        <InputError :message="form.errors.account_type_id" />
                    </div>

                    <div class="flex flex-col items-start gap-1 self-stretch">
                        <InputLabel for="leverage" :value="$t('public.leverages')" />
                        <Dropdown
                            v-model="form.leverage"
                            :options="leverages"
                            optionLabel="display"
                            optionValue="value"
                            class="w-full"
                            scroll-height="236px"
                            :placeholder="$t('public.leverages_placeholder')"
                            :invalid="!!form.errors.leverage"
                            :loading="loadingLeverages"
                            :disabled="!form.account_type_id"
                        >
                            <template #value="{value, placeholder}">
                                <div v-if="value">
                                    1:{{ value }}
                                </div>
                                <div v-else>
                                    {{ placeholder }}
                                </div>
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
                    {{ $t('public.open_live_account') }}
                </Button>
            </div>
        </form>
    </Dialog>
</template>
