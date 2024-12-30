<script setup>
import { computed } from 'vue';

const props = defineProps({
    percentage: {
        type: Number,
        default: 0,
    }
})

const cssTransformRotateValue = computed(() => {
    const clampedPercentage = Math.min(Math.max(props.percentage, 0), 100);
    const percentageAsFraction = clampedPercentage / 100;
    const halfPercentage = percentageAsFraction / 2;
    return `${halfPercentage}turn`;
});
</script>

<template>
    <div class="w-full max-w-[300px]">
        <div class="relative w-full pb-[50%] bg-gray-100 overflow-hidden rounded-t-[100%_200%]">
            <div
                class="absolute top-full left-0 w-full h-full bg-gradient-to-l from-primary-600 to-turquoise transform origin-top transition-transform duration-200 ease-out"
                :style="{ transform: `rotate(${cssTransformRotateValue})` }"
            ></div>
            <div class="absolute top-[25%] left-1/2 w-[75%] h-[150%] bg-white transform -translate-x-1/2 rounded-full flex flex-col gap-1 items-center justify-center pb-[25%] box-border">
                <span class="text-xxl text-gray-950 font-semibold">{{ percentage.toFixed(2) }}%</span>
                <span class="text-sm text-gray-500">{{ $t('public.achieve_so_far') }}</span>
            </div>
        </div>
    </div>
</template>
