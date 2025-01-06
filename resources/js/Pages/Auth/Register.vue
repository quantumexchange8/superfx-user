<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Button from '@/Components/Button.vue';
import {Head, Link, useForm, usePage} from '@inertiajs/vue3';
import ApplicationLogo from "@/Components/ApplicationLogo.vue";
import {
    IconUser,
    IconKey,
    IconScanEye,
    IconWorld,
    IconCheck,
    IconX
} from "@tabler/icons-vue"
import {computed, h, ref} from "vue";
import InputText from "primevue/inputtext";
import Dropdown from "primevue/dropdown";
import Password from 'primevue/password';
import {KycFemale, KycMale} from "@/Components/Icons/solid.jsx";
import OverlayPanel from 'primevue/overlaypanel';
import {loadLanguageAsync} from "laravel-vue-i18n";
import dayjs from "dayjs";

const props = defineProps({
    referral_code: String
})

const formSteps = ref([
    {
        step: 1,
        title: 'basic_details',
        caption_1: 'register_step_1_desc',
        caption_2: 'register_step_1_desc_2',
        state: 'active',
        selected: false
    },
    {
        step: 2,
        title: 'choose_a_password',
        caption_1: 'register_step_2_desc',
        caption_2: 'register_step_2_desc_2',
        state: 'inactive',
        selected: false
    },
]);

const getIconComponent = (step) => {
    switch (step) {
        case 1:
            return h(IconUser);
        case 2:
            return h(IconKey);
        case 3:
            return h(IconScanEye);
        default:
            return 'IconDefault';
    }
};

const selectedStep = computed(() => formSteps.value.find(step => step.selected));

// Function to set the selected step and update state
const selectStep = (stepNumber) => {
    formSteps.value.forEach(step => {
        step.selected = step.step === stepNumber;
        step.state = step.step <= stepNumber ? 'active' : 'inactive';
    });
};

// Initially select the first step
selectStep(1);

const handleContinue = () => {
    form.dial_code = selectedCountry.value;

    if (selectedCountry.value) {
        form.phone_number = selectedCountry.value.phone_code + form.phone;
    }

    form.step = selectedStep.value.step;

    // Validate the current step
    form.post(route('register.validateStep'), {
        onSuccess: () => {
            if (selectedStep.value.step < 2) {
                // Move to the next step
                selectStep(selectedStep.value.step + 1);
            } else {
                // If validation is successful on step 2, submit the form
                handleSubmit();
            }
        },
    });
};


const handleBack = () => {
    if (selectedStep.value.step > 1) {
        selectStep(selectedStep.value.step - 1);
    }
};

const handleSubmit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};

const form = useForm({
    step: 1,
    name: '',
    email: '',
    dial_code: '',
    phone: '',
    phone_number: '',
    password: '',
    password_confirmation: '',
    kyc_verification: '',
    referral_code: props.referral_code ? props.referral_code : '',
});

const countries = ref()
const selectedCountry = ref();
const getResults = async () => {
    try {
        const response = await axios.get('/getFilterData');
        countries.value = response.data.countries;
    } catch (error) {
        console.error('Error changing locale:', error);
    }
};

getResults();

const op = ref();
const toggle = (event) => {
    op.value.toggle(event);
}

const currentLocale = ref(usePage().props.locale);
const locales = [
    {'label': 'English', 'value': 'en'},
    {'label': '简体中文', 'value': 'cn'},
    {'label': '繁體中文', 'value': 'tw'},
    {'label': 'tiếng Việt', 'value': 'vn'},
];

const changeLanguage = async (langVal) => {
    try {
        currentLocale.value = langVal;
        await loadLanguageAsync(langVal);
        await axios.get(`/locale/${langVal}`);
    } catch (error) {
        console.error('Error changing locale:', error);
    }
};

const selectedKycVerification = ref(null);
const selectedKycVerificationName = ref(null);
const handleKycVerification = (event) => {
    const kycVerificationInput = event.target;
    const file = kycVerificationInput.files[0];

    if (file) {
        // Display the selected image
        const reader = new FileReader();
        reader.onload = () => {
            selectedKycVerification.value = reader.result;
        };
        reader.readAsDataURL(file);
        selectedKycVerificationName.value = file.name;
        form.kyc_verification = event.target.files[0];
    } else {
        selectedKycVerification.value = null;
    }
};

const removeKycVerification = () => {
    selectedKycVerification.value = null;
    form.kyc_verification = '';
};
</script>

<template>
    <Head :title="$t('public.register')"></Head>

    <div
        class="flex justify-center min-h-screen"
        style="background-image: url('/img/background-login.svg'); background-repeat: repeat-x;"
    >
        <!-- Row -->
        <div class="w-full flex">
            <!-- Col -->
            <div class="hidden md:flex flex-col items-start gap-[120px] px-5 bg-gray-50 min-w-80">
                <Link href="/" class="w-full flex items-center py-[18px]">
                    <div class="flex items-center self-stretch gap-2">
                        <div class="px-2">
                            <ApplicationLogo aria-hidden="true" class="w-10 h-7" />
                        </div>
                        <div
                            class="text-lg font-bold text-gray-800 w-full"
                        >
                            SuperForex.
                        </div>
                    </div>
                </Link>

                <!-- stepper -->
                <div class="flex flex-col gap-[60px] items-center">
                    <div v-for="(step, index) in formSteps" :key="index" class="flex items-center gap-4 self-stretch">
                        <div
                            class="flex items-center justify-center w-10 h-10 rounded-xl shrink-0 grow-0 bg-white shadow-toast relative overflow-visible"
                            :class="step.state === 'active' ? 'text-gray-950' : 'text-gray-400'"
                        >
                            <component :is="getIconComponent(step.step)" size="20" stroke-width="1.25" />
                            <div
                                v-if="step.step < selectedStep.step && step.state === 'active'"
                                class="flex justify-center items-center rounded-full bg-primary-500 w-4 h-4 absolute -bottom-1 -right-1"
                            >
                                <IconCheck size="12" color="white" stroke-width="1.25" />
                            </div>
                        </div>
                        <div class="flex flex-col items-start">
                            <span class="font-semibold" :class="step.state === 'active' ? 'text-gray-950' : 'text-gray-500'">{{ $t(`public.${step.title}`) }}</span>
                            <span class="text-sm" :class="step.state === 'active' ? 'text-gray-700' : 'text-gray-400'">{{ $t(`public.${step.caption_1}`) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Col -->
            <div class="w-full flex flex-col items-center">
                <div class="flex py-3 px-5 md:px-10 justify-end items-center w-full">
                    <div
                        class="w-[60px] h-[60px] p-[17.5px] flex items-center justify-center rounded-full hover:cursor-pointer hover:bg-gray-100 text-gray-800"
                        @click="toggle"
                    >
                        <IconWorld size="25" stroke-width="1.25" />
                    </div>
                </div>
                <div class="py-12 px-8 flex flex-col items-center gap-[60px]">
                    <div
                        class="flex flex-col justify-center items-center flex-[1_0%_0%] w-[320px] md:w-[360px]"
                        :class="selectedStep.step === 3 ? 'gap-6' : 'gap-8'"
                    >
                        <!-- caption -->
                        <div
                            class="flex flex-col items-center self-stretch"
                            :class="selectedStep.step === 3 ? 'gap-1' : 'gap-3'"
                        >
                            <span
                                class="font-bold text-gray-950 w-full"
                                :class="selectedStep.step === 3 ? 'text-md text-left' : 'text-lg md:text-xl'"
                            >{{ $t(`public.${selectedStep.title}`) }}</span>
                            <span
                                class="text-gray-500 w-full"
                                :class="selectedStep.step === 3 ? 'text-xs' : 'text-sm md:text-base'"
                            >{{ $t(`public.${selectedStep.caption_2}`) }}</span>
                        </div>

                        <!-- register form -->
                        <form class="flex flex-col items-center gap-6 self-stretch">
                            <!-- basic details -->
                            <template v-if="selectedStep.step === 1">
                                <div class="flex flex-col items-center gap-5 self-stretch pb-6">

                                    <!-- name -->
                                    <div class="flex flex-col gap-1 items-start self-stretch">
                                        <InputLabel for="name" :value="$t('public.full_name')" :invalid="!!form.errors.name" />

                                        <InputText
                                            id="name"
                                            type="text"
                                            class="block w-full"
                                            v-model="form.name"
                                            autofocus
                                            :placeholder="$t('public.full_name_placeholder')"
                                            :invalid="!!form.errors.name"
                                            autocomplete="name"
                                        />

                                        <InputError :message="form.errors.name" />
                                    </div>

                                    <!-- email -->
                                    <div class="flex flex-col gap-1 items-start self-stretch">
                                        <InputLabel for="email" :value="$t('public.email')" :invalid="!!form.errors.email" />

                                        <InputText
                                            id="email"
                                            type="email"
                                            class="block w-full"
                                            v-model="form.email"
                                            :placeholder="$t('public.enter_your_email')"
                                            :invalid="!!form.errors.email"
                                            autocomplete="username"
                                        />

                                        <InputError :message="form.errors.email" />
                                    </div>

                                    <!-- phone -->
                                    <div class="flex flex-col gap-1 items-start self-stretch">
                                        <InputLabel for="phone" :value="$t('public.phone_number')" :invalid="!!form.errors.phone" />
                                        <div class="flex gap-2 items-center self-stretch relative">
                                            <Dropdown
                                                v-model="selectedCountry"
                                                :options="countries"
                                                filter
                                                :filterFields="['name', 'phone_code']"
                                                optionLabel="name"
                                                :placeholder="$t('public.phone_code')"
                                                class="w-[100px]"
                                                scroll-height="236px"
                                                :invalid="!!form.errors.phone"
                                            >
                                                <template #value="slotProps">
                                                    <div v-if="slotProps.value" class="flex items-center">
                                                        <div>{{ slotProps.value.phone_code }}</div>
                                                    </div>
                                                    <span v-else>
                                            {{ slotProps.placeholder }}
                                        </span>
                                                </template>
                                                <template #option="slotProps">
                                                    <div class="flex items-center w-[262px] md:max-w-[236px]">
                                                        <div>{{ slotProps.option.name }} <span class="text-gray-500">{{ slotProps.option.phone_code }}</span></div>
                                                    </div>
                                                </template>
                                            </Dropdown>

                                            <InputText
                                                id="phone"
                                                type="text"
                                                class="block w-full"
                                                v-model="form.phone"
                                                :placeholder="$t('public.phone_number')"
                                                :invalid="!!form.errors.phone"
                                            />
                                        </div>
                                        <InputError :message="form.errors.phone" />
                                    </div>
                                </div>
                            </template>

                            <!-- password -->
                            <template v-if="selectedStep.step === 2">
                                <div class="flex flex-col items-center gap-5 self-stretch pb-6">

                                    <!-- password -->
                                    <div class="flex flex-col gap-1 items-start self-stretch">
                                        <InputLabel for="password" :value="$t('public.password')" />
                                        <Password
                                            v-model="form.password"
                                            toggleMask
                                            :invalid="!!form.errors.password"
                                        />
                                        <InputError :message="form.errors.password" />
                                        <span class="text-xs text-gray-500">{{ $t('public.password_desc') }}</span>
                                    </div>

                                    <!-- confirm password -->
                                    <div class="flex flex-col gap-1 items-start self-stretch">
                                        <InputLabel for="password_confirmation" :value="$t('public.confirm_password')" />
                                        <Password
                                            v-model="form.password_confirmation"
                                            toggleMask
                                            :invalid="!!form.errors.password"
                                        />
                                    </div>
                                </div>
                            </template>

                            <!-- kyc verification -->
                            <!-- <template v-if="selectedStep.step === 3">
                                <div class="flex flex-col items-center gap-5 self-stretch pb-6">
                                    <div class="flex items-center gap-5 self-stretch">
                                        <div class="flex justify-center bg-primary-600 w-full pt-2.5">
                                            <KycFemale />
                                        </div>
                                        <div class="flex justify-center bg-logo w-full pt-2.5">
                                            <KycMale />
                                        </div>
                                    </div>

                                    <div class="flex flex-col items-center self-stretch">
                                        <div class="text-gray-950 font-semibold text-sm self-stretch">
                                            {{ $t('public.upload_here') }}
                                        </div>
                                        <div class="flex flex-col gap-3 items-start self-stretch">
                                            <span class="text-xs text-gray-500">{{ $t('public.kyc_caption') }}</span>
                                            <div class="flex flex-col gap-3">
                                                <input
                                                    ref="kycVerificationInput"
                                                    id="kyc_verification"
                                                    type="file"
                                                    class="hidden"
                                                    accept="image/*"
                                                    @change="handleKycVerification"
                                                />
                                                <Button
                                                    type="button"
                                                    variant="primary-tonal"
                                                    @click="$refs.kycVerificationInput.click()"
                                                >
                                                    {{ $t('public.browse') }}
                                                </Button>
                                                <InputError :message="form.errors.kyc_verification" />
                                            </div>
                                            <div
                                                v-if="selectedKycVerification"
                                                class="relative w-full py-3 pl-4 flex justify-between rounded-xl bg-gray-50"
                                            >
                                                <div class="inline-flex items-center gap-3">
                                                    <img :src="selectedKycVerification" alt="Selected Image" class="max-w-full h-9 object-contain rounded" />
                                                    <div class="text-sm text-gray-950">
                                                        {{ selectedKycVerificationName }}
                                                    </div>
                                                </div>
                                                <Button
                                                    type="button"
                                                    variant="gray-text"
                                                    @click="removeKycVerification"
                                                    pill
                                                    iconOnly
                                                >
                                                    <IconX class="text-gray-700 w-5 h-5" />
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template> -->

                            <Button
                                variant="primary-flat"
                                class="w-full"
                                @click.prevent="handleContinue"
                                :disabled="form.processing"
                            >
                                {{ $t('public.continue') }}
                            </Button>

                            <div
                                v-if="selectedStep.step === 1"
                                class="flex items-center gap-3"
                            >
                                <span class="text-sm text-gray-700">{{ $t('public.already_have_an_account') }}</span>
                                <Link
                                    :href="route('login')"
                                    class="text-sm text-primary-500 font-semibold"
                                >
                                    {{ $t('public.login') }}
                                </Link>
                            </div>
                            <div
                                v-else
                                class="w-full"
                            >
                                <Button
                                    type="button"
                                    variant="gray-text"
                                    class="w-full"
                                    @click="handleBack"
                                >
                                    {{ $t('public.back_to_previous_page')}}
                                </Button>
                            </div>
                        </form>
                    </div>
                    <div class="text-center text-gray-500 text-xs mt-auto">© {{ dayjs().year() }} SuperForex. All rights reserved.</div>
                </div>
            </div>
        </div>
    </div>

    <OverlayPanel ref="op">
        <div class="py-2 flex flex-col items-center w-[120px]">
            <div
                v-for="locale in locales"
                class="p-3 flex items-center gap-3 self-stretch text-sm hover:bg-gray-100 hover:cursor-pointer"
                :class="{'bg-primary-50 text-primary-500': locale.value === currentLocale}"
                @click="changeLanguage(locale.value)"
            >
                {{ locale.label }}
            </div>
        </div>
    </OverlayPanel>
</template>


