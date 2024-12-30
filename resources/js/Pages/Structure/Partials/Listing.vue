<script setup>
import {computed, onMounted, ref, watch, watchEffect} from "vue";
import InputText from 'primevue/inputtext';
import RadioButton from 'primevue/radiobutton';
import Button from '@/Components/Button.vue';
import {usePage} from '@inertiajs/vue3';
import OverlayPanel from 'primevue/overlaypanel';
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import DefaultProfilePhoto from "@/Components/DefaultProfilePhoto.vue";
import {FilterMatchMode} from "primevue/api";
import Loader from "@/Components/Loader.vue";
import Dropdown from "primevue/dropdown";
import {
    IconSearch,
    IconCircleXFilled,
    IconAdjustments,
    IconCloudDownload,
} from '@tabler/icons-vue';
import Badge from '@/Components/Badge.vue';
import { trans, wTrans } from "laravel-vue-i18n";
import {transactionFormat} from "@/Composables/index.js";
import StatusBadge from "@/Components/StatusBadge.vue";

const loading = ref(false);
const dt = ref();
const users = ref();
const { formatDate } = transactionFormat();
const paginator_caption = wTrans('public.paginator_caption');

onMounted(() => {
    getResults();
})

const getResults = async () => {
    loading.value = true;

    try {
        const response = await axios.get('/structure/getDownlineListingData');
        users.value = response.data.users;
    } catch (error) {
        console.error('Error get listing:', error);
    } finally {
        loading.value = false;
    }
};

const getFilterData = async () => {
    try {
        const uplineResponse = await axios.get('/structure/getFilterData');
        uplines.value = uplineResponse.data.uplines;
        maxLevel.value = uplineResponse.data.maxLevel;
        createLevelOptions();
    } catch (error) {
        console.error('Error filter data:', error);
    }
};

getFilterData();

const exportCSV = () => {
    dt.value.exportCSV();
};

const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
    name: { value: null, matchMode: FilterMatchMode.STARTS_WITH },
    upline_id: { value: null, matchMode: FilterMatchMode.EQUALS },
    level: { value: null, matchMode: FilterMatchMode.EQUALS },
    role: { value: null, matchMode: FilterMatchMode.EQUALS },
    status: { value: null, matchMode: FilterMatchMode.EQUALS },
});

const rowClicked = (id_number) => {
    window.open(route('structure.viewDownline', id_number), '_self')
}

// overlay panel
const op = ref();
const uplines = ref()
const maxLevel = ref(0)
const levels = ref([])
const upline_id = ref(null)
const level = ref(null)
const filterCount = ref(0);
const lvl = trans('public.level');

const toggle = (event) => {
    op.value.toggle(event);
}

const createLevelOptions = () => {
    for (let index = 1; index <= maxLevel.value; index++) {
        levels.value.push({
            value: index,
            name: `${lvl} ${index}`
        })
    }
}

watch([upline_id, level], ([newUplineId, newLevel]) => {
    if (upline_id.value !== null) {
        filters.value['upline_id'].value = newUplineId.value
    }

    if (level.value !== null) {
        filters.value['level'].value = newLevel.value
    }
})

watch(filters, () => {
    // Count active filters
    filterCount.value = Object.values(filters.value).filter(filter => filter.value !== null).length;
}, { deep: true });

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

watchEffect(() => {
    if (usePage().props.toast !== null) {
        getResults();
    }
});

</script>

<template>
    <div class="p-6 flex flex-col items-center justify-center self-stretch gap-6 border border-gray-200 bg-white shadow-table rounded-2xl">
        <DataTable
            v-model:filters="filters"
            :value="users"
            paginator
            removableSort
            :rows="10"
            :rowsPerPageOptions="[10, 20, 50, 100]"
            tableStyle="min-width: 50rem"
            paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport"
            :currentPageReportTemplate="paginator_caption"
            :globalFilterFields="['name']"
            ref="dt"
            :loading="loading"
            table-style="min-width:fit-content"
            selectionMode="single"
            @row-click="rowClicked($event.data.id_number)"
        >
            <template #header>
                <div class="flex flex-col md:flex-row gap-3 items-center self-stretch">
                    <div class="relative w-full md:w-60">
                        <div class="absolute top-2/4 -mt-[9px] left-4 text-gray-400">
                            <IconSearch size="20" stroke-width="1.25" />
                        </div>
                        <InputText v-model="filters['global'].value" :placeholder="$t('public.keyword_search')" class="font-normal pl-12 w-full md:w-60" />
                        <div
                            v-if="filters['global'].value !== null"
                            class="absolute top-2/4 -mt-2 right-4 text-gray-300 hover:text-gray-400 select-none cursor-pointer"
                            @click="clearFilterGlobal"
                        >
                            <IconCircleXFilled size="16" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 w-full gap-3">
                        <Button
                            variant="gray-outlined"
                            @click="toggle"
                            size="sm"
                            class="flex gap-3 items-center justify-center py-3 w-full md:w-[130px]"
                        >
                            <IconAdjustments size="20" color="#0C111D" stroke-width="1.25" />
                            <div class="text-sm text-gray-950 font-medium">
                                {{ $t('public.filter') }}
                            </div>
                            <Badge class="w-5 h-5 text-xs text-white" variant="numberbadge">
                                {{ filterCount }}
                            </Badge>
                        </Button>
                        <div class="w-full flex justify-end">
                            <Button
                                variant="primary-outlined"
                                @click="exportCSV($event)"
                                class="w-full md:w-auto"
                            >
                                {{ $t('public.export') }}
                                <IconCloudDownload size="20" />
                            </Button>
                        </div>
                    </div>
                </div>
            </template>
            <template #empty> {{ $t('public.no_user_header') }} </template>
            <template #loading>
                <div class="flex flex-col gap-2 items-center justify-center">
                    <Loader />
                    <span class="text-sm text-gray-700">{{ $t('public.loading_users_caption') }}</span>
                </div>
            </template>
            <Column field="joined_date" sortable style="width: 15%" class="hidden md:table-cell">
                <template #header>
                    <span>{{ $t('public.joined_date') }}</span>
                </template>
                <template #body="slotProps">
                    {{ formatDate(slotProps.data.joined_date) }}
                </template>
            </Column>
            <Column field="level" sortable headerClass="hidden md:table-cell" class="w-1/5 md:w-auto">
                <template #header>
                    <span>{{ $t('public.level') }}</span>
                </template>
                <template #body="slotProps">
                    <span class="md:hidden">Lvl </span>
                    {{ slotProps.data.level }}
                </template>
            </Column>
            <Column field="name" sortable :header="$t('public.name')" headerClass="hidden md:table-cell" class="w-auto">
                <template #body="slotProps">
                    <div class="flex items-center gap-3">
                        <div class="w-7 h-7 rounded-full overflow-hidden grow-0 shrink-0">
                            <div v-if="slotProps.data.profile_photo">
                                <img :src="slotProps.data.profile_photo" alt="">
                            </div>
                            <div v-else>
                                <DefaultProfilePhoto />
                            </div>
                        </div>
                        <div class="flex flex-col items-start">
                            <div class="w-20 truncate font-medium xl:w-36">
                                {{ slotProps.data.name }}
                            </div>
                            <div class="w-20 truncate text-gray-500 text-xs xl:w-36">
                                {{ slotProps.data.email }}
                            </div>
                        </div>
                    </div>
                </template>
            </Column>
            <Column field="role" headerClass="hidden md:table-cell">
                <template #header>
                    <span>{{ $t('public.role') }}</span>
                </template>
                <template #body="slotProps">
                    <div class="flex py-1.5 items-center flex-1">
                        <StatusBadge :value="slotProps.data.role">
                            {{ $t(`public.${slotProps.data.role}`) }}
                        </StatusBadge>
                    </div>
                </template>
            </Column>
            <Column field="upline" sortable :header="$t('public.upline')" style="width: 35%" class="hidden md:table-cell">
                <template #body="slotProps">
                    <div class="flex items-center gap-3">
                        <div class="w-7 h-7 rounded-full overflow-hidden grow-0 shrink-0">
                            <div v-if="slotProps.data.upline_profile_photo">
                                <img :src="slotProps.data.upline_profile_photo" alt="">
                            </div>
                            <div v-else>
                                <DefaultProfilePhoto />
                            </div>
                        </div>
                        <div class="w-20 truncate font-medium xl:w-36">
                            {{ slotProps.data.upline_name }}
                        </div>
                    </div>
                </template>
            </Column>
        </DataTable>
    </div>

    <OverlayPanel ref="op">
        <div class="flex flex-col gap-8 w-60 py-5 px-4">
            <!-- Filter Role-->
            <div class="flex flex-col gap-2 items-center self-stretch">
                <div class="flex self-stretch text-xs text-gray-950 font-semibold">
                    {{ $t('public.filter_role_header') }}
                </div>
                <div class="flex flex-col gap-1 self-stretch">
                    <div class="flex items-center gap-2 text-sm text-gray-950">
                        <RadioButton v-model="filters['role'].value" inputId="role_member" value="member" class="w-4 h-4" />
                        <label for="role_member">{{ $t('public.member') }}</label>
                    </div>
                    <div class="flex items-center gap-2 text-sm text-gray-950">
                        <RadioButton v-model="filters['role'].value" inputId="role_agent" value="agent" class="w-4 h-4" />
                        <label for="role_agent">{{ $t('public.agent') }}</label>
                    </div>
                </div>
            </div>

            <!-- Filter Level-->
            <div class="flex flex-col gap-2 items-center self-stretch">
                <div class="flex self-stretch text-xs text-gray-950 font-semibold">
                    {{ $t('public.filter_level_header') }}
                </div>
                <Dropdown
                    v-model="level"
                    :options="levels"
                    filter
                    :filterFields="['name']"
                    optionLabel="name"
                    :placeholder="$t('public.select_level_placeholder')"
                    class="w-full"
                    scroll-height="236px"
                >
                    <template #value="slotProps">
                        <div v-if="slotProps.value" class="flex items-center gap-3">
                            <div class="flex items-center gap-2">
                                <div class="w-4 h-4 rounded-full overflow-hidden grow-0 shrink-0"></div>
                                <div>{{ slotProps.value.name }}</div>
                            </div>
                        </div>
                        <span v-else class="text-gray-400">{{ slotProps.placeholder }}</span>
                    </template>
                    <template #option="slotProps">
                        <div class="flex items-center gap-2">
                            <div>{{ slotProps.option.name }}</div>
                        </div>
                    </template>
                </Dropdown>
            </div>

            <!-- Filter Upline-->
            <div class="flex flex-col gap-2 items-center self-stretch">
                <div class="flex self-stretch text-xs text-gray-950 font-semibold">
                    {{ $t('public.filter_upline_header') }}
                </div>
                <Dropdown
                    v-model="upline_id"
                    :options="uplines"
                    filter
                    :filterFields="['name']"
                    optionLabel="name"
                    :placeholder="$t('public.select_upline_placeholder')"
                    class="w-full"
                    scroll-height="236px"
                >
                    <template #value="slotProps">
                        <div v-if="slotProps.value" class="flex items-center gap-3">
                            <div class="flex items-center gap-2">
                                <div class="w-5 h-5 rounded-full overflow-hidden">
                                    <template v-if="slotProps.value.profile_photo">
                                        <img :src="slotProps.value.profile_photo" alt="profile_picture" />
                                    </template>
                                    <template v-else>
                                        <DefaultProfilePhoto />
                                    </template>
                                </div>
                                <div>{{ slotProps.value.name }}</div>
                            </div>
                        </div>
                        <span v-else class="text-gray-400">{{ slotProps.placeholder }}</span>
                    </template>
                    <template #option="slotProps">
                        <div class="flex items-center gap-2">
                            <div class="w-5 h-5 rounded-full overflow-hidden">
                                <template v-if="slotProps.option.profile_photo">
                                    <img :src="slotProps.option.profile_photo" alt="profile_picture" />
                                </template>
                                <template v-else>
                                    <DefaultProfilePhoto />
                                </template>
                            </div>
                            <div>{{ slotProps.option.name }}</div>
                        </div>
                    </template>
                </Dropdown>
            </div>

            <div class="flex w-full">
                <Button
                    type="button"
                    variant="primary-outlined"
                    class="flex justify-center w-full"
                    @click="clearFilter()"
                >
                    {{ $t('public.clear_all') }}
                </Button>
            </div>
        </div>
    </OverlayPanel>
</template>
