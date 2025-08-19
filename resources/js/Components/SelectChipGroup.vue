<script setup>
import { IconCircleCheckFilled } from "@tabler/icons-vue";

const props = defineProps({
    modelValue: [String, Number, Object],
    items: {
        type: Array,
        required: true,
    },
    valueKey: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(["update:modelValue"]);

const selectItem = (item) => {
    const value = props.valueKey ? item[props.valueKey] : item;
    emit("update:modelValue", value);
};

const isSelected = (item) => {
    const value = props.valueKey ? item[props.valueKey] : item;
    return props.modelValue === value;
};
</script>

<template>
    <div class="grid gap-3 self-stretch grid-cols-1 sm:grid-cols-2">
        <div
            v-for="item in items"
            :key="props.valueKey ? item[props.valueKey] : item"
            @click="selectItem(item)"
            class="group flex flex-col items-start py-3 px-4 gap-1 self-stretch rounded-lg border shadow-input transition-colors duration-300 select-none cursor-pointer w-full relative"
            :class="{
                'bg-primary-50 border-primary': isSelected(item),
                'bg-white border-surface-300 hover:bg-primary-50 hover:border-primary':
                  !isSelected(item),
            }"
        >
            <div class="flex items-center self-stretch">
                <div
                    class="flex-grow text-xs font-semibold transition-colors duration-300 group-hover:text-primary-700"
                    :class="{
                        'text-primary-700': isSelected(item),
                        'text-surface-950': !isSelected(item),
                    }"
                >
                    <slot name="option" :item="item">
                        <div class="uppercase">
                            {{ $t(`public.${props.valueKey ? item[props.valueKey] : item}`) }}
                        </div>
                    </slot>
                </div>

                <div v-if="isSelected(item)" class="absolute right-2">
                    <IconCircleCheckFilled size="16" stroke-width="1.5" color="#34d399" />
                </div>
            </div>
        </div>
    </div>
</template>
