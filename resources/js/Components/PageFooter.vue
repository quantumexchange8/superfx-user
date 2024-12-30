<script setup>
</script>

<template>
    <footer class="flex flex-col gap-3 flex-shrink-0 p-5 bg-gray-100">
        <div class="flex flex-col gap-1 items-start self-stretch text-xs">
            <span class="text-gray-700 font-bold">{{ $t('public.risk_warning') }}</span>
            <div class="text-gray-500">
                {{ $t('public.risk_warning_desc') }}
            </div>
        </div>
    </footer>
</template>
