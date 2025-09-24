<script setup>
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import {ref, watch, onMounted, computed} from "vue";
import {FilterMatchMode} from "primevue/api";
import Loader from "@/Components/Loader.vue";
import DefaultProfilePhoto from "@/Components/DefaultProfilePhoto.vue";
import OverlayPanel from 'primevue/overlaypanel';
import Dropdown from "primevue/dropdown";
import Empty from "@/Components/Empty.vue";
import dayjs from "dayjs";
import Badge from "@/Components/Badge.vue";
import InputText from "primevue/inputtext";
import Button from "@/Components/Button.vue";
import {IconAdjustments, IconCircleXFilled, IconSearch, IconCircleCheckFilled, IconCloudDownload} from "@tabler/icons-vue";
import RadioButton from "primevue/radiobutton";
import debounce from "lodash/debounce.js";
import StatusBadge from "@/Components/StatusBadge.vue";
import {router} from "@inertiajs/vue3";
import {trans} from "laravel-vue-i18n";

const props = defineProps({
    maxLevels: Number
})

const isLoading = ref(false);
const dt = ref(null);
const children = ref([]);
const totalRecords = ref(0);
const first = ref(0);

const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
    role: { value: null, matchMode: FilterMatchMode.EQUALS },
    level: { value: null, matchMode: FilterMatchMode.EQUALS },
    upline: { value: null, matchMode: FilterMatchMode.EQUALS },
});

const lazyParams = ref({});

const loadLazyData = (event) => {
    isLoading.value = true;

    lazyParams.value = { ...lazyParams.value, first: event?.first || first.value };
    lazyParams.value.filters = filters.value;
    try {
        setTimeout(async () => {
            const params = {
                page: JSON.stringify(event?.page + 1),
                sortField: event?.sortField,
                sortOrder: event?.sortOrder,
                include: [],
                lazyEvent: JSON.stringify(lazyParams.value)
            };

            const url = route('structure.getDownlineListingData', params);
            const response = await fetch(url);
            const results = await response.json();

            children.value = results?.data?.data;
            totalRecords.value = results?.data?.total;

            isLoading.value = false;
        }, 100);
    }  catch (e) {
        children.value = [];
        totalRecords.value = 0;
        isLoading.value = false;
    }
};

onMounted(() => {
    lazyParams.value = {
        first: dt.value.first,
        rows: dt.value.rows,
        sortField: null,
        sortOrder: null,
        filters: filters.value
    };

    loadLazyData();
});

watch(
    filters.value['global'],
    debounce(() => {
        loadLazyData();
    }, 300)
);

watch([filters.value['role'], filters.value['level'], filters.value['upline']], () => {
    loadLazyData()
});

const onPage = (event) => {
    lazyParams.value = event;
    loadLazyData(event);
};
const onSort = (event) => {
    lazyParams.value = event;
    loadLazyData(event);
};
const onFilter = (event) => {
    lazyParams.value.filters = filters.value ;
    loadLazyData(event);
};

const filterCount = computed(() => {
    return Object.entries(filters.value)
        .filter(([key, filter]) =>
            key !== 'global' &&
            filter?.value !== null &&
            filter?.value !== '' &&
            filter?.value !== undefined
        ).length;
});

const clearAll = () => {
    filters.value['global'].value = null;
    filters.value['role'].value = null;
    filters.value['level'].value = null;
    filters.value['upline'].value = null;
};

const clearFilterGlobal = () => {
    filters.value['global'].value = null;
}

const op = ref();
const toggle = (event) => {
    op.value.toggle(event);
    createLevelOptions();
    getChildren();
}

const rowClicked = (id_number) => {
   router.visit(route('structure.viewDownline', id_number))
}

// Get uplines
const uplines = ref([]);
const loadingUplines = ref(false);

const getChildren = async () => {
    loadingUplines.value = true;

    try {
        const response = await axios.get('/getChildren');

        // All groups from API
        uplines.value = response.data.uplines;

    } catch (error) {
        console.error('Error getting uplines:', error);
    } finally {
        loadingUplines.value = false;
    }
};

const maxLevel = ref(props.maxLevels);
const levels = ref([])
const lvl = trans('public.level');

const createLevelOptions = () => {
    for (let index = 1; index <= maxLevel.value; index++) {
        levels.value.push({
            value: index,
            name: `${lvl} ${index}`
        })
    }
}

const getProfilePhoto = (user) => {
    // Find first media in 'profile_photo' collection
    const mediaItem = user.media?.find(m => m.collection_name === 'profile_photo');
    return mediaItem?.original_url || null;
};

const exportStatus = ref(false);

const exportReport = () => {
    exportStatus.value = true;
    isLoading.value = true;

    lazyParams.value = { ...lazyParams.value, first: event?.first || first.value };

    const params = {
        page: JSON.stringify(event?.page + 1),
        sortField: event?.sortField,
        sortOrder: event?.sortOrder,
        include: [],
        lazyEvent: JSON.stringify(lazyParams.value),
        exportStatus: true,
    };

    const url = route('structure.getDownlineListingData', params);

    try {
        window.location.href = url;
    } catch (e) {
        console.error('Error occurred during export:', e);
    } finally {
        isLoading.value = false;
        exportStatus.value = false;
    }
};
</script>

<template>
    <div class="p-6 flex flex-col items-center justify-center self-stretch gap-6 border border-gray-200 bg-white shadow-table rounded-2xl">
        <DataTable
            :value="children"
            :rowsPerPageOptions="[10, 20, 50, 100]"
            lazy
            :paginator="children?.length > 0"
            removableSort
            paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport"
            :currentPageReportTemplate="$t('public.paginator_caption')"
            :first="first"
            :rows="10"
            v-model:filters="filters"
            ref="dt"
            dataKey="id"
            :totalRecords="totalRecords"
            :loading="isLoading"
            @page="onPage($event)"
            @sort="onSort($event)"
            @filter="onFilter($event)"
            selectionMode="single"
            @row-click="rowClicked($event.data.id_number)"
            :globalFilterFields="['name', 'email', 'id_number']"
        >
            <template #header>
                <div class="flex flex-col md:flex-row gap-3 items-center self-stretch md:pb-6">
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
                                @click="exportReport"
                                class="w-full md:w-auto"
                            >
                                {{ $t('public.export') }}
                                <IconCloudDownload size="20" />
                            </Button>
                        </div>
                    </div>
                </div>
            </template>
            <template #empty>
                <Empty
                    :title="$t('public.empty_account_title')"
                    :message="$t('public.empty_account_message')"
                />
            </template>
            <template #loading>
                <div class="flex flex-col gap-2 items-center justify-center">
                    <Loader />
                    <span class="text-sm text-gray-700">{{ $t('public.loading_accounts_data') }}</span>
                </div>
            </template>

            <template v-if="children?.length > 0">
                <Column
                    field="joined_date"
                    sortable
                    class="hidden md:table-cell"
                >
                    <template #header>
                        <span>{{ $t('public.joined_date') }}</span>
                    </template>
                    <template #body="{data}">
                        {{ dayjs(data.created_at).format('YYYY/MM/DD') }}
                    </template>
                </Column>

                <Column
                    field="level"
                    class="hidden md:table-cell w-1/5 md:w-auto"
                >
                    <template #header>
                        <span>{{ $t('public.level') }}</span>
                    </template>
                    <template #body="slotProps">
                        <span class="md:hidden">Lvl </span>
                        {{ slotProps.data.level }}
                    </template>
                </Column>

                <Column
                    field="name"
                    sortable
                    :header="$t('public.name')"
                    class="w-auto hidden md:table-cell"
                >
                    <template #body="{data}">
                        <div class="flex items-center gap-3">
                            <div class="w-7 h-7 rounded-full overflow-hidden grow-0 shrink-0">
                                <template v-if="getProfilePhoto(data)">
                                    <img :src="getProfilePhoto(data)" alt="profile_photo" class="w-7 h-7 object-cover">
                                </template>
                                <template v-else>
                                    <DefaultProfilePhoto />
                                </template>
                            </div>
                            <div class="flex flex-col items-start">
                                <div class="flex items-center gap-2 w-24 xl:w-40">
                                    <div class="max-w-full truncate font-medium">
                                        {{ data.name }}
                                    </div>
                                    <IconCircleCheckFilled v-if="data.kyc_status === 'approved'" size="16" stroke-width="1.25" class="text-success-500 grow-0 shrink-0" />
                                </div>
                                <div class="w-24 truncate text-gray-500 text-xs xl:w-40">
                                    {{ data.email }}
                                </div>
                            </div>
                        </div>
                    </template>
                </Column>

                <Column
                    field="role"
                    class="hidden md:table-cell"
                >
                    <template #header>
                        <span>{{ $t('public.role') }}</span>
                    </template>
                    <template #body="slotProps">
                        <div class="flex items-center">
                            <StatusBadge :value="slotProps.data.role">
                                {{ $t(`public.${slotProps.data.role}`) }}
                            </StatusBadge>
                        </div>
                    </template>
                </Column>

                <Column
                    field="upline"
                    :header="$t('public.upline')"
                    class="hidden md:table-cell"
                >
                    <template #body="{data}">
                        <div class="flex items-center gap-3">
                            <div class="w-7 h-7 rounded-full overflow-hidden grow-0 shrink-0">
                                <template v-if="getProfilePhoto(data.upline)">
                                    <img :src="getProfilePhoto(data.upline)" alt="profile_photo" class="w-7 h-7 object-cover">
                                </template>
                                <template v-else>
                                    <DefaultProfilePhoto />
                                </template>
                            </div>
                            <div class="w-20 truncate font-medium xl:w-36">
                                {{ data.upline.name }}
                            </div>
                        </div>
                    </template>
                </Column>

                <Column class="md:hidden">
                    <template #body="{data}">
                        <div class="flex items-center gap-2 self-stretch">
                            <div class="flex flex-col items-start w-full">
                                <span class="text-sm text-gray-950 font-semibold">{{ data?.name }}</span>
                                <div class="text-xs">
                                    <span class="text-gray-500">{{ $t('public.joined_date') }}</span> <span class="text-gray-700 font-medium"> {{ dayjs(data?.created_at).format('YYYY/MM/DD') }}</span>
                                </div>
                            </div>
                            <StatusBadge :value="data.role">
                                {{ $t(`public.${data.role}`) }}
                            </StatusBadge>
                        </div>
                    </template>
                </Column>
            </template>
        </DataTable>
    </div>

    <OverlayPanel ref="op">
        <div class="flex flex-col gap-5 w-60 py-5 px-4">
            <!-- Filter role-->
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
                        <RadioButton v-model="filters['role'].value" inputId="role_agent" value="ib" class="w-4 h-4" />
                        <label for="role_agent">{{ $t('public.ib') }}</label>
                    </div>
                </div>
            </div>

            <!-- Filter Level-->
            <div class="flex flex-col gap-2 items-center self-stretch">
                <div class="flex self-stretch text-xs text-gray-950 font-semibold">
                    {{ $t('public.filter_level_header') }}
                </div>
                <Dropdown
                    v-model="filters['level'].value"
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
                    {{ $t('public.filter_upline') }}
                </div>
                <Dropdown
                    v-model="filters['upline'].value"
                    :options="uplines"
                    filter
                    :filterFields="['name', 'email', 'id_number']"
                    optionLabel="name"
                    :placeholder="$t('public.filter_upline')"
                    class="w-full"
                    scroll-height="236px"
                    :virtualScrollerOptions="{ itemSize: 42 }"
                    :loading="loadingUplines"
                >
                    <template #option="{option}">
                        <span>{{ option.name }}</span>
                    </template>
                    <template #value="{value, placeholder}">
                        <div v-if="value">
                            <span>{{ value.name }}</span>
                        </div>
                        <span v-else class="text-gray-400">
                            {{ placeholder }}
                        </span>
                    </template>
                </Dropdown>
            </div>

            <div class="flex w-full">
                <Button
                    type="button"
                    variant="primary-outlined"
                    class="flex justify-center w-full"
                    @click="clearAll"
                >
                    {{ $t('public.clear_all') }}
                </Button>
            </div>
        </div>
    </OverlayPanel>
</template>
