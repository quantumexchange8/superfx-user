<script setup>
import Button from "@/Components/Button.vue";
import {ref, watch} from "vue";
import Dialog from "primevue/dialog";
import DefaultProfilePhoto from "@/Components/DefaultProfilePhoto.vue";
import {transactionFormat} from "@/Composables/index.js";
import InputLabel from "@/Components/InputLabel.vue";
import Dropdown from "primevue/dropdown";
import {useForm} from "@inertiajs/vue3";
import InputError from "@/Components/InputError.vue";
import InputNumber from "primevue/inputnumber";
import {IconHeartFilled, IconHeart} from '@tabler/icons-vue';
// import TermsAndCondition from "@/Components/TermsAndCondition.vue";

const props = defineProps({
    master: Object,
    accounts: Array,
    isLoading: Boolean,
    terms: Object,
})

const visible = ref(false);
const { formatAmount } = transactionFormat();
const selectedAccount = ref();
const loading = ref(false);

watch([() => props.accounts, () => props.isLoading], ([newAccounts, newLoading]) => {
    selectedAccount.value = newAccounts[0];
    loading.value = newLoading;
})

watch(selectedAccount, (newAccount) => {
    selectedAccount.value = newAccount;
    form.investment_amount = Number(newAccount.balance)
})

const form = useForm({
    asset_master_id: '',
    meta_login: '',
    investment_amount: null,
})

const submitForm = () => {
    if (selectedAccount.value) {
        form.meta_login = selectedAccount.value.meta_login;
    }
    form.asset_master_id = props.master.id;

    form.post(route('asset_master.joinPamm'), {
        onSuccess: () => {
            visible.value = false;
        }
    });
}

const likeCounts = ref({});
const isUserLike = ref(props.master ? props.master.isFavourite : false);
const isAnimating = ref(false);

const handleClick = () => {
    if (isAnimating.value) return;

    isAnimating.value = true;

    // Wait for the animation to finish before making the POST request
    setTimeout(() => {
        addToFavourites(props.master.id);
    }, 500);
};

const addToFavourites = async (masterId) => {
    try {
        const response = await axios.post(route('asset_master.addToFavourites'), {
            master_id: masterId,
        });

        isUserLike.value = response.data.isLike;

        if (!likeCounts.value[masterId]) {
            likeCounts.value[masterId] = 0;
        }

        if (isUserLike.value) {
            likeCounts.value[masterId] += 1;
        } else {
            likeCounts.value[masterId] -= 1;
        }
    } catch (error) {
        console.error('Error updating favourites:', error);
    } finally {
        isAnimating.value = false;
    }
};
</script>

<template>
    <div class="flex justify-center items-center gap-5 self-stretch">
        <Button
            variant="primary-flat"
            size="sm"
            type="button"
            class="w-full"
            :disabled="!master"
            @click="visible = true"
        >
            {{ $t('public.join_pamm') }}
        </Button>

        <div class="flex items-center gap-3 w-full max-w-14">
            <div
                class="select-none transition ease-in-out duration-300 hover:scale-110"
                @click="handleClick"
                :class="{
                  'icon-animation': isAnimating,
                  'cursor-not-allowed': isAnimating,
                  'cursor-pointer': !isAnimating
                }"
            >
                <IconHeartFilled
                    v-if="isUserLike"
                    size="24"
                    color="#FF2D58"
                    :class="{'icon-spin': isAnimating}"
                />
                <IconHeart
                    v-else
                    size="24"
                    color="#667085"
                    stroke-width="1.25"
                    :class="{'icon-spin': isAnimating}"
                />
            </div>

            <div
                v-if="master"
                class="text-gray-950 text-sm font-medium"
            >
                {{ master.total_likes_count + (likeCounts[master.id] || 0) }}
            </div>
        </div>
    </div>

    <Dialog
        v-model:visible="visible"
        modal
        :header="$t('public.join_pamm')"
        class="dialog-xs md:dialog-md"
    >
        <form @submit.prevent="submitForm">
            <div class="flex flex-col items-center self-stretch gap-5 md:gap-8">
                <div class="py-5 px-6 flex flex-col items-center gap-4 bg-gray-50 divide-y self-stretch">
                    <div class="w-full flex items-center gap-4">
                        <div class="w-[42px] h-[42px] shrink-0 grow-0 rounded-full overflow-hidden">
                            <div v-if="master.profile_photo">
                                <img :src="master.profile_photo" alt="Profile Photo" />
                            </div>
                            <div v-else>
                                <DefaultProfilePhoto />
                            </div>
                        </div>
                        <div class="flex flex-col items-start self-stretch">
                            <div class="self-stretch truncate w-[190px] md:w-64 text-gray-950 font-bold">
                                {{ master.asset_name }}
                            </div>
                            <div class="self-stretch truncate w-24 text-gray-500 text-sm">
                                {{ master.trader_name }}
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-col items-start gap-3 w-full pt-4 self-stretch">
                        <span class="text-sm text-gray-950 font-bold">{{ $t('public.fees_and_conditions' )}}</span>
                        <div class="flex flex-col gap-1 items-center self-stretch">
                            <div class="flex py-1 gap-3 items-center self-stretch">
                                <span class="w-full text-gray-500 font-medium text-xs">{{ $t('public.minimum_investment') }}</span>
                                <span class="w-full text-gray-950 font-medium text-sm">$ {{ formatAmount(master.minimum_investment) }}</span>
                            </div>
                            <div class="flex py-1 gap-3 items-center self-stretch">
                                <span class="w-full text-gray-500 font-medium text-xs">{{ $t('public.minimum_investment_period') }}</span>
                                <span class="w-full text-gray-950 font-medium text-sm">{{ master.minimum_investment_period }} {{ $t('public.months') }}</span>
                            </div>
                            <div class="flex py-1 gap-3 items-center self-stretch">
                                <span class="w-full text-gray-500 font-medium text-xs">{{ $t('public.performance_fee') }}</span>
                                <span class="w-full text-gray-950 font-medium text-sm lowercase">{{ master.performance_fee > 0 ? master.performance_fee + '%' : $t('public.zero_fee')}}</span>
                            </div>
                            <div class="flex py-1 gap-3 items-center self-stretch">
                                <span class="w-full text-gray-500 font-medium text-xs">{{ $t('public.penalty_fee') }}</span>
                                <span class="w-full text-gray-950 font-medium text-sm lowercase">{{ master.penalty_fee > 0 ? master.penalty_fee + '%' : $t('public.zero_penalty_fee')}}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col md:flex-row gap-3 md:gap-5 items-center self-stretch">
                    <div class="flex flex-col items-start gap-1 self-stretch w-full">
                        <InputLabel for="meta_login" :value="$t('public.managed_account')" />
                        <Dropdown
                            v-model="selectedAccount"
                            :options="accounts"
                            optionLabel="meta_login"
                            class="w-full"
                            :loading="loading"
                            scroll-height="236px"
                            :invalid="!!form.errors.meta_login"
                            :placeholder="loading ? $t('public.loading_caption') : (accounts.length > 0 ? $t('public.select') : $t('public.no_available_accounts'))"
                            :disabled="!accounts.length"
                        />
                        <InputError :message="form.errors.meta_login" />
                    </div>
                    <div class="flex flex-col items-start gap-1 self-stretch w-full">
                        <InputLabel for="investment_amount" :value="$t('public.investment_amount') + ' ($)'" />
                        <InputNumber
                            v-model="form.investment_amount"
                            inputId="investment_amount"
                            prefix="$ "
                            class="w-full"
                            inputClass="py-3 px-4"
                            :min="0"
                            :step="100"
                            :minFractionDigits="2"
                            :placeholder="'$ ' + formatAmount(master.minimum_investment)"
                            fluid
                            readonly
                            :invalid="!!form.errors.investment_amount"
                        />
                        <InputError :message="form.errors.investment_amount" />
                    </div>
                </div>

                <!-- risk warning -->
                <div class="flex flex-col items-start gap-3 self-stretch">
                    <span class="text-gray-950 text-sm font-bold">{{ $t('public.warning') }}</span>
                    <div class="text-xs text-gray-500 prose">
                        <ul>
                            <li>
                                {{ $t('public.warning_1') }}
                            </li>
                            <li>
                                {{ $t('public.warning_2') }}
                            </li>
                            <li>
                                {{ $t('public.warning_3_1') }} <a href="https://ctrader.com/eula/" target="_blank" class="text-primary font-medium no-underline hover:text-primary-600">{{ $t('public.warning_3_2') }}</a>{{ $t('public.warning_3_3') }}
                            </li>
                        </ul>
                        <!-- <div>
                            {{ $t('public.warning_4_1') }}
                            <TermsAndCondition
                                :termsLabel="$t('public.warning_4_2')"
                                :terms="terms"
                            />
                            {{ $t('public.warning_4_3') }}
                        </div> -->
                    </div>
                </div>
            </div>

            <div class="flex gap-3 md:gap-4 md:justify-end pt-5 md:pt-7 self-stretch">
                <Button
                    variant="primary-flat"
                    size="base"
                    class="w-full md:w-auto"
                    @click="submitForm"
                    :disabled="form.processing"
                >
                    {{ $t('public.join_now') }}
                </Button>
            </div>
        </form>
    </Dialog>
</template>

<style scoped>
.icon-animation {
    display: inline-block;
    transition: transform 0.5s ease-in-out;
}

@keyframes customSpinAndScale {
    0% {
        transform: rotate(0deg) scale(1);
    }
    20% {
        transform: rotate(-30deg) scale(1);
    }
    40% {
        transform: rotate(-30deg) scale(1);
    }
    100% {
        transform: rotate(360deg) scale(1.2);
    }
}

@keyframes scaleDownAndUp {
    0% {
        transform: scale(1.2);
    }
    20% {
        transform: scale(0.4);
    }
    80% {
        transform: scale(1.4);
    }
    100% {
        transform: scale(1);
    }
}

.icon-spin {
    animation: customSpinAndScale 0.6s ease-in-out, scaleDownAndUp 0.8s ease-in-out 0.6s;
}
</style>
