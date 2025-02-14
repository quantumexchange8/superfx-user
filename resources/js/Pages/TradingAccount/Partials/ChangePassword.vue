<script setup>
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import Button from "@/Components/Button.vue"
import {useForm} from "@inertiajs/vue3";
import {ref, watch} from "vue";
import {
    IconCircleCheckFilled,
} from "@tabler/icons-vue";
import Password from 'primevue/password';


const props = defineProps({
    account: Object,
})

const passwordTypes = ref(['master', 'investor']);
const selectedType = ref('');

const selectType = (type) => {
    selectedType.value = type;
}

const emit = defineEmits(['update:visible'])

const form = useForm({
    account_id: props.account.id,
    password_type: '',
    password: '',
    password_confirmation: '',
})

const submitForm = () => {
    form.password_type = selectedType.value;
    form.post(route('account.change_password'), {
        onSuccess: () => {
            closeDialog();
        }
    });
}

const closeDialog = () => {
    emit('update:visible', false)
}
</script>

<template>
    <form>
        <div class="flex flex-col items-center gap-8 self-stretch md:gap-10">
            <div class="flex flex-col items-center gap-5 self-stretch">
                <div class="flex flex-col items-start gap-2 self-stretch">
                    <InputLabel for="accountType" :value="$t('public.password_type_placeholder')" />
                    <div class="grid grid-cols-2 items-start gap-3 self-stretch">
                        <div
                            v-for="type in passwordTypes"
                            :key="type"
                            @click="selectType(type)"
                            class="group col-span-1 items-start py-3 px-4 gap-1 self-stretch rounded-lg border shadow-input transition-colors duration-300 select-none cursor-pointer"
                            :class="{
                                'bg-primary-50 border-primary-500': selectedType === type,
                                'bg-white border-gray-300 hover:bg-primary-50 hover:border-primary-500': selectedType !== type,
                            }"
                        >
                            <div class="flex items-center gap-3 self-stretch">
                                <span
                                    class="flex-grow text-sm font-semibold transition-colors duration-300 group-hover:text-primary-700"
                                    :class="{
                                        'text-primary-700': selectedType === type,
                                        'text-gray-950': selectedType !== type
                                    }"
                                >
                                    {{ $t(`public.${type}`) }}
                                </span>
                                <IconCircleCheckFilled v-if="selectedType === type" size="20" stroke-width="1.25" color="#06D001" />
                            </div>
                        </div>
                    </div>
                    <InputError :message="form.errors.password_type"/>
                </div>

                <!-- input fields -->
                <div class="flex flex-col items-start gap-1 self-stretch">
                    <InputLabel for="password" :value="$t('public.password')" />
                    <Password
                        ref="passwordInput"
                        v-model="form.password"
                        toggleMask
                        :invalid="!!form.errors.password"
                    />
                    <InputError :message="form.errors.password"/>
                </div>

                <div class="flex flex-col items-start gap-1 self-stretch">
                    <InputLabel for="password_confirmation" :value="$t('public.confirm_password')" />
                    <Password
                        v-model="form.password_confirmation"
                        toggleMask
                        :invalid="!!form.errors.password"
                    />
                </div>
            </div>
        </div>
        <div class="flex justify-end items-center pt-5 gap-4 self-stretch sm:pt-7">
            <Button
                type="button"
                variant="gray-tonal"
                class="w-full md:w-[120px]"
                @click.prevent="closeDialog()"
                :disabled="form.processing"
            >
                {{ $t('public.cancel') }}
            </Button>
            <Button
                variant="primary-flat"
                class="w-full md:w-[120px]"
                @click.prevent="submitForm"
                :disabled="form.processing"
            >
                {{ $t('public.confirm') }}
            </Button>
        </div>
    </form>
</template>
