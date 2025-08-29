<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Button from "@/Components/Button.vue"
import { useForm } from '@inertiajs/vue3';
import InputText from 'primevue/inputtext';
import Dropdown from 'primevue/dropdown';
import {ref, watch} from "vue";

const props = defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    user: Object
});

const countries = ref();
const nationalities = ref();
const selectedCode = ref();
const selectedCountry = ref();

const form = useForm({
    name: props.user.name,
    chinese_name: props.user.chinese_name,
    email: props.user.email,
    dial_code: '',
    phone: props.user.phone,
    phone_number: '',
    country_id: props.user.country_id,
    nationality: props.user.nationality,
});

watch(countries, () => {
    selectedCode.value = countries.value.find(country => country.phone_code === props.user.dial_code);
    selectedCountry.value = countries.value.find(country => country.id === props.user.country_id);
});

const getResults = async () => {
    try {
        const response = await axios.get('/profile/getFilterData');
        countries.value = response.data.countries;
        nationalities.value = response.data.nationalities;
    } catch (error) {
        console.error('Error changing locale:', error);
    }
};

getResults();

const submitForm = () => {
    form.dial_code = selectedCode.value;

    if (selectedCode.value) {
        form.phone_number = selectedCode.value.phone_code + form.phone;
    }

    form.post(route('profile.update'), {
        preserveScroll: true,
        preserveState: true,
    });
}

watch(selectedCountry, (newCountry) => {
    if (newCountry && newCountry.id !== form.country_id) {
        form.country_id = newCountry.id;
        const foundNationality = nationalities.value.find(nationality => nationality.id === newCountry.id);
        if (foundNationality) {
            form.nationality = foundNationality.nationality;
            // handleInputChange('nationality');
        } else {
            form.nationality = ''; // Reset if not found
        }
    }
});
</script>

<template>
    <form class="p-4 md:py-6 md:px-8 flex flex-col gap-8 md:gap-0 md:justify-between items-center self-stretch rounded-2xl shadow-toast w-full">
        <div class="flex flex-col gap-8 items-center self-stretch">
            <div class="flex flex-col gap-1 items-start justify-center w-full">
                <span class="text-gray-950 font-bold">{{ $t('public.account_details') }}</span>
                <span class="text-gray-500 text-xs">{{ $t('public.account_details_caption') }}</span>
            </div>

            <div class="flex flex-col gap-5 items-center self-stretch w-full">
                <div class="flex flex-col md:flex-row items-center gap-5 w-full">
                    <div class="flex flex-col gap-1 w-full">
                        <InputLabel for="name">
                            {{ $t('public.your_name') }}
                        </InputLabel>
                        <InputText
                            id="name"
                            type="text"
                            class="block w-full"
                            v-model="form.name"
                            :placeholder="$t('public.enter_name')"
                            :invalid="!!form.errors.name"
                            autocomplete="name"
                            disabled
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="flex flex-col gap-1 w-full">
                        <InputLabel for="chinese_name">
                            {{ $t('public.your_chinese_name') }}
                        </InputLabel>
                        <InputText
                            id="chinese_name"
                            type="text"
                            class="block w-full"
                            v-model="form.chinese_name"
                            :placeholder="$t('public.enter_chinese_name')"
                            :invalid="!!form.errors.chinese_name"
                        />
                        <InputError :message="form.errors.chinese_name" />
                    </div>
                </div>

                <div class="flex flex-col gap-1 w-full">
                    <InputLabel for="email">
                        {{ $t('public.email') }}
                    </InputLabel>
                    <InputText
                        id="email"
                        type="email"
                        class="block w-full"
                        v-model="form.email"
                        :placeholder="$t('public.enter_email')"
                        :invalid="!!form.errors.email"
                        autocomplete="email"
                        disabled
                    />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="flex flex-col gap-1 items-start self-stretch">
                    <InputLabel for="phone">
                        {{ $t('public.phone_number') }}
                    </InputLabel>
                    <div class="flex gap-2 items-center self-stretch relative">
                        <Dropdown
                            v-model="selectedCode"
                            :options="countries"
                            filter
                            :filterFields="['name', 'phone_code']"
                            optionLabel="name"
                            :placeholder="$t('public.phone_code')"
                            class="w-[100px] xl:w-[180px]"
                            scroll-height="236px"
                            :invalid="!!form.errors.phone"
                            :disabled="!countries"
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

                <!-- country -->
                <div class="flex flex-col gap-1 items-start self-stretch">
                    <InputLabel for="country" :value="$t('public.country')"/>
                    <Dropdown
                        v-model="selectedCountry"
                        :options="countries"
                        filter
                        :filterFields="['name']"
                        optionLabel="name"
                        :placeholder="$t('public.country_placeholder')"
                        class="w-full"
                        scroll-height="236px"
                        :invalid="!!form.errors.country_id"
                        :disabled="!countries"
                    >
                        <template #value="slotProps">
                            <div v-if="slotProps.value" class="flex items-center">
                                <div>{{ slotProps.value.name }}</div>
                            </div>
                            <span v-else>
                                {{ slotProps.placeholder }}
                            </span>
                        </template>
                        <template #option="slotProps">
                            <div class="flex items-center w-[262px] md:max-w-[236px]">
                                <div>{{ slotProps.option.name }}</div>
                            </div>
                        </template>
                    </Dropdown>
                    <InputError :message="form.errors.country_id" />
                </div>

                <!-- nationality -->
                <div class="flex flex-col gap-1 items-start self-stretch">
                    <InputLabel for="nationality" :value="$t('public.nationality')"/>
                    <Dropdown
                        v-model="form.nationality"
                        :options="nationalities"
                        filter
                        :filterFields="['nationality']"
                        optionLabel="nationality"
                        optionValue="nationality"
                        :placeholder="$t('public.nationality_placeholder')"
                        class="w-full"
                        scroll-height="236px"
                        :invalid="!!form.errors.nationality"
                        :disabled="!nationalities"
                    >
                        <template #value="slotProps">
                            <div v-if="slotProps.value" class="flex items-center">
                                <div>{{ slotProps.value }}</div>
                            </div>
                            <span v-else>
                                {{ slotProps.placeholder }}
                            </span>
                        </template>
                        <template #option="slotProps">
                            <div class="flex items-center w-[262px] md:max-w-[236px]">
                                <div>{{ slotProps.option.nationality }}</div>
                            </div>
                        </template>
                    </Dropdown>
                    <InputError :message="form.errors.nationality" />
                </div>
            </div>
        </div>


        <div class="flex justify-end items-center pt-10 md:pt-7 gap-4 self-stretch">
            <Button
                variant="primary-flat"
                :disabled="form.processing"
                @click="submitForm"
            >
                {{ $t('public.save_changes') }}
            </Button>
        </div>
    </form>
</template>
