<script setup>
import {onMounted, ref, watch} from "vue";
import {transactionFormat} from "@/Composables/index.js";
import { FilterMatchMode } from 'primevue/api';
import debounce from "lodash/debounce.js";
import {usePage} from "@inertiajs/vue3";
import dayjs from "dayjs";
import Button from '@/Components/Button.vue';
import Column from "primevue/column";
import Card from "primevue/card";
import DataTable from "primevue/datatable";
import Tag from "primevue/tag";
import {IconCircleXFilled, IconSearch, IconX, IconAdjustments} from "@tabler/icons-vue";
import InputText from "primevue/inputtext";
import Calendar from "primevue/calendar";
import Empty from "@/Components/Empty.vue";
import Loader from "@/Components/Loader.vue";
import DefaultProfilePhoto from "@/Components/DefaultProfilePhoto.vue";
import ColumnGroup from "primevue/columngroup";
import Row from "primevue/row";
import OverlayPanel from 'primevue/overlaypanel';

const isLoading = ref(false);
const dt = ref(null);
const histories = ref([]);
const exportTable = ref('no');
const {formatAmount} = transactionFormat();
const totalRecords = ref(0);
const first = ref(0);
const totalRebateAmount = ref();

const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
    start_date: { value: null, matchMode: FilterMatchMode.EQUALS },
    end_date: { value: null, matchMode: FilterMatchMode.EQUALS },
    start_close_date: { value: null, matchMode: FilterMatchMode.EQUALS },
    end_close_date: { value: null, matchMode: FilterMatchMode.EQUALS },
    account_type_id: { value: null, matchMode: FilterMatchMode.EQUALS },
});

const lazyParams = ref({});

const loadLazyData = (event) => {
    isLoading.value = true;

    lazyParams.value = { ...lazyParams.value, first: event?.first || first.value };

    try {
        setTimeout(async () => {
            const params = {
                page: JSON.stringify(event?.page + 1),
                sortField: event?.sortField,
                sortOrder: event?.sortOrder,
                include: [],
                lazyEvent: JSON.stringify(lazyParams.value)
            };
            console.log(params)
            const url = route('report.getRebateHistory', params);
            const response = await fetch(url);
            const results = await response.json();

            histories.value = results?.data?.data;
            totalRecords.value = results?.data?.total;
            totalRebateAmount.value = results?.totalRebateAmount;
            isLoading.value = false;
        }, 100);
    }  catch (e) {
        histories.value = [];
        totalRecords.value = 0;
        isLoading.value = false;
    }
};
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

const op = ref();
const toggle = (event) => {
    op.value.toggle(event);
}

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

const clearFilterGlobal = () => {
    filters.value['global'].value = null;
}

// Get current date
const today = new Date();

// Define minDate as the start of the current month and maxDate as today
const minDate = ref(new Date(today.getFullYear(), today.getMonth(), 1));
const maxDate = ref(today);

// Reactive variable for selected date range
const selectedDate = ref([minDate.value, maxDate.value]);
const selectedCloseDate = ref(null);

const clearDate = () => {
    selectedDate.value = null;
}

const clearCloseDate = () => {
    selectedCloseDate.value = null;
}

watch(selectedDate, (newDateRange) => {
    if (Array.isArray(newDateRange)) {
        const [startDate, endDate] = newDateRange;
        filters.value['start_date'].value = startDate;
        filters.value['end_date'].value = endDate;

        if (startDate !== null && endDate !== null) {
            loadLazyData();
        }
    }
    else if (newDateRange === null) {
        loadLazyData();
    }
    else {
        console.warn('Invalid date range format:', newDateRange);
    }
})

watch(selectedCloseDate, (newDateRange) => {
    if (Array.isArray(newDateRange)) {
        const [startCloseDate, endCloseDate] = newDateRange;
        filters.value['start_close_date'].value = startCloseDate;
        filters.value['end_close_date'].value = endCloseDate;

        if (startCloseDate !== null && endCloseDate !== null) {
            loadLazyData();
        }
    } 
    else if (newDateRange === null) {
        loadLazyData();
    }
    else {
        console.warn('Invalid date range format:', newDateRange);
    }
})
</script>

<template>
    <div class="flex flex-col items-center px-4 py-6 gap-5 self-stretch rounded-2xl border border-gray-200 bg-white shadow-table md:px-6 md:gap-5">
        <div
            class="w-full"
        >
            <DataTable
                :value="histories"
                :rowsPerPageOptions="[10, 20, 50, 100]"
                lazy
                paginator
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
                :globalFilterFields="['name', 'email', 'username', 'meta_login']"
            >
                <template #header>
                    <div class="flex flex-col md:flex-row gap-3 items-center self-stretch pb-3 md:pb-5">
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
                        <div class="w-full flex flex-col gap-3 md:flex-row">
                            <div class="w-full md:w-[272px]">
                                <!-- <Calendar
                                    v-model="selectedDate"
                                    selectionMode="range"
                                    :manualInput="false"
                                    :minDate="minDate"
                                    :maxDate="maxDate"
                                    dateFormat="dd/mm/yy"
                                    showIcon
                                    iconDisplay="input"
                                    placeholder="yyyy/mm/dd - yyyy/mm/dd"
                                    class="w-full md:w-[272px]"
                                />
                                <div
                                    v-if="selectedDate && selectedDate.length > 0"
                                    class="absolute top-2/4 -mt-2.5 right-4 text-gray-400 select-none cursor-pointer bg-white"
                                    @click="clearDate"
                                >
                                    <IconX size="20" />
                                </div> -->
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
                                </Button>
                            </div>
<!--                            <div class="w-full flex justify-end">-->
<!--                                <Button-->
<!--                                    variant="primary-outlined"-->
<!--                                    @click="exportCSV($event)"-->
<!--                                    class="w-full md:w-auto"-->
<!--                                >-->
<!--                                    {{ $t('public.export') }}-->
<!--                                </Button>-->
<!--                            </div>-->
                        </div>
                        <div class="flex justify-end self-stretch md:hidden">
                            <span class="text-gray-500 text-right text-sm font-medium">{{ $t('public.total') }}:</span>
                            <span class="text-gray-950 text-sm font-semibold ml-2">$ {{ formatAmount(totalRebateAmount) }}</span>
                        </div>
                    </div>
                </template>
                <template #empty>
                    <Empty
                        :title="$t('public.empty_rebate_record_title')"
                        :message="$t('public.empty_rebate_record_message')"
                    />
                </template>
                <template #loading>
                    <div class="flex flex-col gap-2 items-center justify-center">
                        <Loader />
                        <span class="text-sm text-gray-700">{{ $t('public.loading_rebate_record_caption') }}</span>
                    </div>
                </template>
                <template v-if="histories?.length > 0">
                    <Column
                        field="created_at"
                        sortable
                        :header="`${$t('public.date')}`"
                        class="hidden md:table-cell"
                    >
                        <template #body="slotProps">
                            {{ dayjs(slotProps.data.created_at).format('YYYY/MM/DD') }}
                        </template>
                    </Column>
                    <Column
                        field="deal_id"
                        sortable
                        :header="`${$t('public.ticket')}`"
                        class="hidden md:table-cell"
                    >
                        <template #body="slotProps">
                            {{ slotProps.data.deal_id }}
                        </template>
                    </Column>
                    <Column
                        field="open_time"
                        sortable
                        :header="`${$t('public.open_time')}`"
                        class="hidden md:table-cell"
                    >
                        <template #body="slotProps">
                            {{ slotProps.data.open_time }}
                        </template>
                    </Column>
                    <Column
                        field="closed_time"
                        sortable
                        :header="`${$t('public.closed_time')}`"
                        class="hidden md:table-cell"
                    >
                        <template #body="slotProps">
                            {{ slotProps.data.closed_time }}
                        </template>
                    </Column>
                    <Column
                        field="trade_open_price"
                        :header="`${$t('public.open_price')}`"
                        class="hidden md:table-cell"
                    >
                        <template #body="slotProps">
                            {{ formatAmount(slotProps.data.trade_open_price ?? 0) }}
                        </template>
                    </Column>
                    <Column
                        field="trade_close_price"
                        :header="`${$t('public.close_price')}`"
                        class="hidden md:table-cell"
                    >
                        <template #body="slotProps">
                            {{ formatAmount(slotProps.data.trade_close_price ?? 0) }}
                        </template>
                    </Column>
                    <Column
                        field="t_type"
                        :header="`${$t('public.type')}`"
                        class="hidden md:table-cell"
                    >
                        <template #body="slotProps">
                            {{ $t(`public.${slotProps.data.t_type}`) }}
                        </template>
                    </Column>
                    <Column
                        field="name"
                        :header="$t('public.name')"
                        class="hidden md:table-cell"
                    >
                        <template #body="slotProps">
                            <div class="flex items-center gap-3">
                                <div class="w-7 h-7 rounded-full overflow-hidden grow-0 shrink-0">
                                    <DefaultProfilePhoto />
                                </div>
                                <div class="flex flex-col items-start">
                                    <div class="font-medium">
                                        {{ slotProps.data.downline.name }}
                                    </div>
                                    <div class="text-gray-500 text-xs">
                                        {{ slotProps.data.downline.email }}
                                    </div>
                                </div>
                            </div>
                        </template>
                    </Column>
                    <Column
                        field="trade_profit"
                        :header="`${$t('public.profit')}`"
                        class="hidden md:table-cell"
                    >
                        <template #body="slotProps">
                            {{ formatAmount(slotProps.data.trade_profit ?? 0) }}
                        </template>
                    </Column>
                    <Column
                        field="meta_login"
                        :header="`${$t('public.account')}`"
                        class="hidden md:table-cell"
                    >
                        <template #body="slotProps">
                            {{ slotProps.data.meta_login }}
                        </template>
                    </Column>
                    <Column
                        field="symbol"
                        :header="`${$t('public.product')}`"
                        class="hidden md:table-cell"
                    >
                        <template #body="slotProps">
                            {{ slotProps.data.symbol }}
                        </template>
                    </Column>
                    <Column
                        field="volume"
                        sortable
                        :header="`${$t('public.volume')}&nbsp;(Ł)`"
                        class="hidden md:table-cell"
                    >
                        <template #body="slotProps">
                            {{ formatAmount(slotProps.data.volume) }}
                        </template>
                    </Column>
                    <Column
                        field="revenue"
                        sortable
                        :header="`${$t('public.rebate')}&nbsp;($)`"
                        class="hidden md:table-cell"
                    >
                        <template #body="slotProps">
                            {{ formatAmount(slotProps.data.revenue) }}
                        </template>
                    </Column>
                    <Column
                        field="t_status"
                        :header="`${$t('public.status')}`"
                        class="hidden md:table-cell"
                    >
                        <template #body="slotProps">
                            {{ $t(`public.completed`) }}
                        </template>
                    </Column>
                    <Column class="md:hidden">
                        <template #body="slotProps">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-7 h-7 rounded-full overflow-hidden grow-0 shrink-0">
                                        <DefaultProfilePhoto />
                                    </div>
                                    <div class="flex flex-col items-start">
                                        <div class="text-sm font-semibold">
                                            {{ slotProps.data.downline.name }}
                                        </div>
                                        <div class="text-gray-500 text-xs">
                                            {{ `${slotProps.data.meta_login}&nbsp;|&nbsp;${formatAmount(slotProps.data.volume)}&nbsp;Ł` }}
                                        </div>
                                    </div>
                                </div>
                                <div class="overflow-hidden text-right text-ellipsis font-semibold">
                                    $&nbsp;{{ formatAmount(slotProps.data.revenue) }}
                                </div>
                            </div>
                        </template>
                    </Column>
                    <ColumnGroup type="footer">
                        <Row>
                            <Column class="hidden md:table-cell" :footer="$t('public.total') + ':'" :colspan="13" footerStyle="text-align:right" />
                            <Column class="hidden md:table-cell" :footer="'$' + formatAmount(totalRebateAmount ?? 0)" />
                        </Row>
                    </ColumnGroup>
                </template>
            </DataTable>
        </div>
    </div>

    <OverlayPanel ref="op">
        <div class="flex flex-col gap-8 w-72 py-5 px-4">
            <div class="flex flex-col gap-2 items-center self-stretch">
                <div class="flex self-stretch text-xs text-gray-950 font-semibold">
                    {{ $t('public.filter_date') }}
                </div>
                <div class="flex flex-col relative gap-1 self-stretch">
                    <Calendar
                        v-model="selectedDate"
                        selectionMode="range"
                        :manualInput="false"
                        :minDate="minDate"
                        :maxDate="maxDate"
                        dateFormat="dd/mm/yy"
                        showIcon
                        iconDisplay="input"
                        placeholder="yyyy/mm/dd - yyyy/mm/dd"
                        class="w-full md:w-[272px]"
                    />
                    <div
                        v-if="selectedDate && selectedDate.length > 0"
                        class="absolute top-2/4 -mt-2.5 right-4 text-gray-400 select-none cursor-pointer bg-white"
                        @click="clearDate"
                    >
                        <IconX size="20" />
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-2 items-center self-stretch">
                <div class="flex self-stretch text-xs text-gray-950 font-semibold">
                    {{ $t('public.filter_closed_time') }}
                </div>
                <div class="flex flex-col relative gap-1 self-stretch">
                    <Calendar
                        v-model="selectedCloseDate"
                        selectionMode="range"
                        :manualInput="false"
                        :minDate="minDate"
                        :maxDate="maxDate"
                        dateFormat="dd/mm/yy"
                        showIcon
                        iconDisplay="input"
                        placeholder="yyyy/mm/dd - yyyy/mm/dd"
                        class="w-full md:w-[272px]"
                    />
                    <div
                        v-if="selectedCloseDate && selectedCloseDate.length > 0"
                        class="absolute top-2/4 -mt-2.5 right-4 text-gray-400 select-none cursor-pointer bg-white"
                        @click="clearCloseDate"
                    >
                        <IconX size="20" />
                    </div>
                </div>
            </div>
        </div>
    </OverlayPanel>
</template>
