<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Button from "@/Components/Button.vue"
import { useForm, usePage } from '@inertiajs/vue3';
import InputText from 'primevue/inputtext';
import Dropdown from 'primevue/dropdown';
import {ref, watch} from "vue";

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const user = usePage().props.auth.user;
const countries = ref()
const selectedCountry = ref();

const form = useForm({
    name: user.name,
    email: user.email,
    dial_code: '',
    phone: user.phone,
    phone_number: '',
});

watch(countries, () => {
    selectedCountry.value = countries.value.find(country => country.phone_code === user.dial_code);
});

const getResults = async () => {
    try {
        const response = await axios.get('/profile/getFilterData');
        countries.value = response.data.countries;
    } catch (error) {
        console.error('Error changing locale:', error);
    }
};

getResults();

const dirtyFields = ref({
    dial_code: false,
    phone: false,
});

const handleInputChange = (field) => {
    dirtyFields.value[field] = true;
    console.log(field);
};

const resetForm = () => {
    // Only reset fields that are marked as dirty
    if (dirtyFields.value.dial_code) {
        selectedCountry.value = countries.value.find(country => country.phone_code === user.dial_code) || null;
        form.dial_code = user.dial_code || '';
    }

    if (dirtyFields.value.phone) {
        form.phone = user.phone || '';
    }

    // Reset dirty fields tracking
    dirtyFields.value = {
        dial_code: false,
        phone: false,
    };
};

const submitForm = () => {
    form.dial_code = selectedCountry.value;

    if (selectedCountry.value) {
        form.phone_number = selectedCountry.value.phone_code + form.phone;
    }

    form.post(route('profile.update'), {
        onSuccess: () => {
            visible.value = false;
            form.reset();
        },
    });
}
</script>

<template>
    <form class="p-4 md:py-6 md:px-8 flex flex-col gap-8 md:gap-0 md:justify-between items-center self-stretch rounded-2xl shadow-toast w-full">
        <div class="flex flex-col gap-8 items-center self-stretch">
            <div class="flex flex-col gap-1 items-start justify-center w-full">
                <span class="text-gray-950 font-bold">{{ $t('public.account_details') }}</span>
                <span class="text-gray-500 text-xs">{{ $t('public.account_details_caption') }}</span>
            </div>

            <div class="flex flex-col gap-5 items-center self-stretch w-full">
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
                            v-model="selectedCountry"
                            :options="countries"
                            filter
                            :filterFields="['name', 'phone_code']"
                            optionLabel="name"
                            :placeholder="$t('public.phone_code')"
                            class="w-[100px] xl:w-[180px]"
                            scroll-height="236px"
                            :invalid="!!form.errors.phone"
                            :disabled="!countries"
                            @change="handleInputChange('dial_code')"
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
                            @input="handleInputChange('phone')"
                        />
                    </div>
                    <InputError :message="form.errors.phone" />
                </div>
            </div>
        </div>


        <div class="flex justify-end items-center pt-10 md:pt-7 gap-4 self-stretch">
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
</template>
