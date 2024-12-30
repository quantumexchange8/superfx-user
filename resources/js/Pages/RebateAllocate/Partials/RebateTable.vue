<script setup>
import {computed, onMounted, ref, watch, watchEffect} from "vue";
import InputText from 'primevue/inputtext';
import RadioButton from 'primevue/radiobutton';
import Button from '@/Components/Button.vue';
import {usePage} from '@inertiajs/vue3';
import OverlayPanel from 'primevue/overlaypanel';
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import {FilterMatchMode} from "primevue/api";
import Loader from "@/Components/Loader.vue";
import Dropdown from "primevue/dropdown";
import {
    IconSearch,
    IconCircleXFilled,
    IconAdjustmentsHorizontal,
} from '@tabler/icons-vue';
import { wTrans } from "laravel-vue-i18n";
import AgentDropdown from '@/Pages/RebateAllocate/Partials/AgentDropdown.vue';

const dropdownOptions = [
    {
        name: wTrans('public.standard_account'),
        value: '1'
    },
    {
        name: wTrans('public.premium_account'),
        value: '2'
    },
]

const accountType = ref(dropdownOptions[0].value);
const loading = ref(false);
const dt = ref();
const agents = ref();

const getResults = async (type_id = 1) => {
    loading.value = true;

    try {
        const response = await axios.get(`/rebate_allocate/getAgents?type_id=${type_id}`);
        agents.value = response.data;
    } catch (error) {
        console.error('Error get agents:', error);
    } finally {
        loading.value = false;
    }
};

getResults();

watchEffect(() => {
    if (usePage().props.toast !== null) {
        getResults();
    }
});

watch(accountType, (newValue) => {
    getResults(newValue);
})

const changeAgent = async (newAgent) => {
    console.log(newAgent)
    loading.value = true;

    try {
        const response = await axios.get(`/rebate_allocate/changeAgents?id=${newAgent.id}&level=${newAgent.level}&type_id=${accountType.value}`);
        agents.value = response.data;
        console.log(agents.value);
    } catch (error) {
        console.error('Error get change:', error);
    } finally {
        loading.value = false;
    }
}

const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
    name: { value: null, matchMode: FilterMatchMode.STARTS_WITH },
    upline_id: { value: null, matchMode: FilterMatchMode.EQUALS },
    level: { value: null, matchMode: FilterMatchMode.EQUALS },
    role: { value: null, matchMode: FilterMatchMode.EQUALS },
    status: { value: null, matchMode: FilterMatchMode.EQUALS },
});

const clearFilter = () => {
    filters.value = {
        global: { value: null, matchMode: FilterMatchMode.CONTAINS },
        name: { value: null, matchMode: FilterMatchMode.STARTS_WITH },
        upline_id: { value: null, matchMode: FilterMatchMode.EQUALS },
        level: { value: null, matchMode: FilterMatchMode.EQUALS },
        role: { value: null, matchMode: FilterMatchMode.EQUALS },
        status: { value: null, matchMode: FilterMatchMode.EQUALS },
    };

    upline_id.value = null;
    level.value = null;
};

const clearFilterGlobal = () => {
    filters.value['global'].value = null;
}

</script>

<template>
    <div class="p-6 flex flex-col items-center justify-center self-stretch gap-6 border border-gray-200 bg-white shadow-table rounded-2xl">
        <DataTable
            v-model:filters="filters"
            :value="agents"
            tableStyle="min-width: 50rem"
            :globalFilterFields="['name']"
            ref="dt"
            :loading="loading"
            table-style="min-width:fit-content"
        >
            <template #header>
                <div class="flex flex-col md:flex-row gap-3 items-center self-stretch md:justify-between">
                    <div class="relative w-full md:w-60">
                        <div class="absolute top-2/4 -mt-[9px] left-4 text-gray-400">
                            <IconSearch size="20" stroke-width="1.25" />
                        </div>
                        <InputText v-model="filters['global'].value" :placeholder="$t('public.search_agent')" class="font-normal pl-12 w-full md:w-60" />
                        <div
                            v-if="filters['global'].value !== null"
                            class="absolute top-2/4 -mt-2 right-4 text-gray-300 hover:text-gray-400 select-none cursor-pointer"
                            @click="clearFilterGlobal"
                        >
                            <IconCircleXFilled size="16" />
                        </div>
                    </div>
                    <Dropdown
                        v-model="accountType"
                        :options="dropdownOptions"
                        optionLabel="name"
                        optionValue="value"
                        class="w-full md:w-52 font-normal"
                    />
                </div>
            </template>
            <template #empty> {{ $t('public.no_user_header') }} </template>
            <template #loading>
                <div class="flex flex-col gap-2 items-center justify-center">
                    <Loader />
                    <span class="text-sm text-gray-700">{{ $t('public.loading_users_caption') }}</span>
                </div>
            </template>
            <Column field="level" style="width:10%;">
                <template #header>
                    <span>{{ $t('public.level') }}</span>
                </template>
                <template #body="slotProps">
                    {{ slotProps.data[0][0].level }}
                </template>
            </Column>
            <Column field="agent" class="w-auto">
                <template #header>
                    <span>{{ $t('public.agent') }}</span>
                </template>
                <template #body="slotProps">
                    <AgentDropdown :agents="slotProps.data[0]" @update:modelValue="changeAgent($event)" class="w-full" />
                </template>
            </Column>
            <Column field="forex" class="hidden md:table-cell" style="width:10%;">
                <template #header>
                    <span>{{ $t('public.forex') }}</span>
                </template>
                <template #body="slotProps">
                    {{ slotProps.data[1].forex }}
                </template>
            </Column>
            <Column field="stocks" class="hidden md:table-cell" style="width:10%;">
                <template #header>
                    <span>{{ $t('public.stocks') }}</span>
                </template>
                <template #body="slotProps">
                    {{ slotProps.data[1].stocks }}
                </template>
            </Column>
            <Column field="indices" class="hidden md:table-cell" style="width:10%;">
                <template #header>
                    <span>{{ $t('public.indices') }}</span>
                </template>
                <template #body="slotProps">
                    {{ slotProps.data[1].indices }}
                </template>
            </Column>
            <Column field="commodities" class="hidden md:table-cell" style="width:10%;">
                <template #header>
                    <span class="w-12 truncate lg:w-auto">{{ $t('public.commodities') }}</span>
                </template>
                <template #body="slotProps">
                    {{ slotProps.data[1].commodities }}
                </template>
            </Column>
            <Column field="cryptocurrency" class="hidden md:table-cell" style="width:10%;">
                <template #header>
                    <span class="w-12 truncate lg:w-auto">{{ $t('public.cryptocurrency') }}</span>
                </template>
                <template #body="slotProps">
                    {{ slotProps.data[1].cryptocurrency }}
                </template>
            </Column>
            <Column field="action" style="width:5%;">
                <template #body="slotProps">
                    <Button
                        variant="gray-text"
                        type="button"
                        size="sm"
                        iconOnly
                        pill
                    >
                        <IconAdjustmentsHorizontal size="16" stroke-width="1.25" />
                    </Button>
                </template>
            </Column>
        </DataTable>
    </div>
</template>