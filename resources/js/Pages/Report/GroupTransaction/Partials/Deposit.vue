<script setup>
import InputText from 'primevue/inputtext';
import Button from '@/Components/Button.vue';
import {onMounted, ref, watch} from "vue";
import StatusBadge from "@/Components/StatusBadge.vue";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import DefaultProfilePhoto from "@/Components/DefaultProfilePhoto.vue";
import {FilterMatchMode} from "primevue/api";
import {generalFormat, transactionFormat} from '@/Composables/index.js';
import Empty from '@/Components/Empty.vue';
import Loader from "@/Components/Loader.vue";
import {IconAdjustments, IconCircleXFilled, IconSearch, IconX} from '@tabler/icons-vue';
import Calendar from 'primevue/calendar';
import debounce from "lodash/debounce.js";
import OverlayPanel from 'primevue/overlaypanel';
import MultiSelect from 'primevue/multiselect';
import Slider from "primevue/slider";
import RadioButton from "primevue/radiobutton";

const { formatDate, formatAmount } = transactionFormat();
const {formatRgbaColor} = generalFormat();

const props = defineProps({
    downlines: Array
});

const transactions = ref();
const groupTotalDeposit = ref(0);
const groupTotalWithdrawal = ref(0);
const groupTotalNetBalance = ref(0);
const dt = ref();
const loading = ref(false);
const downlines = ref();
const selectedDownlines = ref([]);
const totalRecords = ref(0);
const first = ref(0);
const exportStatus = ref(false);
const minFilterAmount = ref(0);
const maxFilterAmount = ref(100000);

// Watch for changes in props.uplines
watch(() => props.downlines, (newDownlines) => {
    // Whenever uplines change, update the local ref
    downlines.value = newDownlines;
  }, { immediate: true }
);

// Watch for individual changes in upline_id and apply it to filters
watch([selectedDownlines], (newDownlineId) => {

    if (newDownlineId !== null) {
        // note to self: check a proper usage for multiselect to prevent below solution
        const flatDownlineId = Array.isArray(newDownlineId[0]) ? newDownlineId[0] : newDownlineId;
        filters.value['downline_id'].value = flatDownlineId.map(downline => downline.value);
    }
});

// Get current date
const today = new Date();

// Define minDate as the start of the current month and maxDate as today
const minDate = ref(new Date(today.getFullYear(), today.getMonth(), 1));
const maxDate = ref(today);

// Reactive variable for selected date range
const selectedDate = ref([minDate.value, maxDate.value]);

const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
    start_date: { value: minDate.value, matchMode: FilterMatchMode.EQUALS },
    end_date: { value: maxDate.value, matchMode: FilterMatchMode.EQUALS },
    type: { value: 'deposit', matchMode: FilterMatchMode.EQUALS },
    downline_id: { value: [], matchMode: FilterMatchMode.EQUALS },
    role: { value: null, matchMode: FilterMatchMode.EQUALS },
    amount: { value: [minFilterAmount.value, maxFilterAmount.value], matchMode: FilterMatchMode.BETWEEN },
});

// Clear date selection
const clearDate = () => {
    selectedDate.value = null;
    filters.value['start_date'].value = null;
    filters.value['end_date'].value = null;
};

watch(selectedDate, (newDateRange) => {
    if (Array.isArray(newDateRange)) {
        const [startDate, endDate] = newDateRange;
        filters.value['start_date'].value = startDate;
        filters.value['end_date'].value = endDate;

        if (startDate !== null && endDate !== null) {
            loadLazyData();
        }
    }
    else {
        // console.warn('Invalid date range format:', newDateRange);
    }
})

const emit = defineEmits(['updateGroupTotals']);

watch([groupTotalDeposit, groupTotalWithdrawal, groupTotalNetBalance], ([deposit, withdrawal, netBalance]) => {
    emit('updateGroupTotals', {
        deposit,
        withdrawal,
        netBalance
    });
});

const lazyParams = ref({});

const loadLazyData = (event) => {
    loading.value = true;

    lazyParams.value = { ...lazyParams.value, first: event?.first || first.value };
    lazyParams.value.filters = filters.value;
    // console.log(filters.value)
    try {
        setTimeout(async () => {
            // console.log(lazyParams.value.filters)
            const params = {
                page: JSON.stringify(event?.page + 1),
                sortField: event?.sortField,
                sortOrder: event?.sortOrder,
                include: [],
                lazyEvent: JSON.stringify(lazyParams.value)
            };
            const url = route('report.getGroupTransaction', params);
            const response = await fetch(url);
            const results = await response.json();

            transactions.value = results?.transactions?.data;
            totalRecords.value = results?.transactions?.total;
            groupTotalDeposit.value = results.groupTotalDeposit;
            groupTotalWithdrawal.value = results.groupTotalWithdrawal;
            groupTotalNetBalance.value = results.groupTotalNetBalance;
            emit('updateGroupTotals', {
                deposit: groupTotalDeposit.value,
                withdrawal: groupTotalWithdrawal.value,
                netBalance: groupTotalNetBalance.value
            });
            loading.value = false;
            // console.log(rebateListing)
            // console.log(results)
        }, 100);
    }  catch (e) {
        rebateListing.value = [];
        totalRecords.value = 0;
        loading.value = false;
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
const filterCount = ref(0);
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

    // console.log(selectedDate)
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

const clearFilter = () => {
    filters.value = {
        global: { value: null, matchMode: FilterMatchMode.CONTAINS },
        start_date: { value: null, matchMode: FilterMatchMode.EQUALS },
        end_date: { value: null, matchMode: FilterMatchMode.EQUALS },
        type: { value: 'deposit', matchMode: FilterMatchMode.EQUALS },
        downline_id: { value: [], matchMode: FilterMatchMode.EQUALS },
        role: { value: null, matchMode: FilterMatchMode.EQUALS },
        amount: { value: [minFilterAmount.value, maxFilterAmount.value], matchMode: FilterMatchMode.BETWEEN },
    };

    selectedDate.value = [minDate.value, maxDate.value];
    selectedDownlines.value = [];
};

watch(filters, debounce(() => {
    const amountFilterIsActive = filters.value.amount.value[0] !== minFilterAmount.value || filters.value.amount.value[1] !== maxFilterAmount.value;

    filterCount.value = Object.entries(filters.value).filter(([key, filter]) => {
        // Exclude amount filter if it covers the entire range
        if (filter === filters.value.amount) {
            return amountFilterIsActive;
        }
        return filter.value !== null;
    }).length;

    loadLazyData();
}, 500), { deep: true });

const exportListing = () => {
    exportStatus.value = true;
    loading.value = true;

    lazyParams.value = { ...lazyParams.value, first: event?.first || first.value };

    if (filters.value) {
        lazyParams.value.filters = { ...filters.value };
    } else {
        lazyParams.value.filters = {};
    }

    let params = {
        include: [],
        lazyEvent: JSON.stringify(lazyParams.value),
        exportStatus: true,
    };

    const url = route('report.getGroupTransaction', params);

    try {

        window.location.href = url;
    } catch (e) {
        console.error('Error occurred during export:', e);
    } finally {
        loading.value = false;
        exportStatus.value = false;
    }
};
</script>

<template>
    <div class="flex flex-col items-center px-4 py-6 gap-5 self-stretch rounded-2xl border border-gray-200 bg-white shadow-table md:px-6 md:gap-5">
        <DataTable
            v-model:filters="filters"
            :value="transactions"
            lazy
            paginator
            :totalRecords="totalRecords"
            removableSort
            :rows="10"
            :rowsPerPageOptions="[10, 20, 50, 100]"
            tableStyle="md:min-width: 50rem"
            paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport"
            currentPageReportTemplate="Showing {first} to {last} of {totalRecords} entries"
            :globalFilterFields="['name']"
            selectionMode="single"
            ref="dt"
            :loading="loading"
            @page="onPage($event)"
            @sort="onSort($event)"
            @filter="onFilter($event)"
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
                    <div class="w-full flex flex-col gap-3 md:flex-row">
                        <div class="w-full md:w-[272px]">
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
                        <div class="w-full flex justify-end">
                            <Button
                                variant="primary-outlined"
                                @click="exportListing()"
                                class="w-full md:w-auto"
                            >
                                {{ $t('public.export') }}
                            </Button>
                        </div>
                    </div>
                </div>
            </template>
            <template #empty><Empty :title="$t('public.empty_group_transaction_title')" :message="$t('public.empty_group_transaction_deposit_message')"/></template>
            <template #loading>
                <div class="flex flex-col gap-2 items-center justify-center">
                    <Loader />
                    <span class="text-sm text-gray-700">{{ $t('public.loading_transactions_caption') }}</span>
                </div>
            </template>
            <template v-if="transactions?.length > 0">
                <Column
                    field="created_at"
                    sortable
                    :header="$t('public.date')"
                    class="hidden md:table-cell"
                >
                    <template #body="slotProps">
                        {{ formatDate(slotProps.data.created_at) }}
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
                                <div class="flex gap-1 items-center">
                                    <div class="font-medium">
                                        {{ slotProps.data.name }}
                                    </div>
                                    <div class="flex py-1.5 items-center flex-1">
                                        <StatusBadge :value="slotProps.data.role">
                                            {{ $t(`public.${slotProps.data.role}`) }}
                                        </StatusBadge>
                                    </div>
                                </div>
                                <div class="text-gray-500 text-xs">
                                    {{ slotProps.data.email }}
                                </div>
                            </div>
                        </div>
                    </template>
                </Column>
                <Column
                    field="id_number"
                    :header="`${$t('public.id_number')}`"
                    class="hidden md:table-cell"
                >
                    <template #body="slotProps">
                        {{ slotProps.data.id_number }}
                    </template>
                </Column>
                <Column
                    field="meta_login"
                    :header="`${$t('public.account')}`"
                    class="hidden md:table-cell"
                >
                    <template #body="slotProps">
                        <div class="flex items-center content-center gap-3 flex-grow relative">
                            <span >{{ slotProps.data.meta_login }}</span>
                            <div
                                class="flex px-2 py-1 justify-center items-center text-xs font-semibold hover:-translate-y-1 transition-all duration-300 ease-in-out rounded"
                                :style="{
                                    backgroundColor: formatRgbaColor(slotProps.data.account_type.color, 0.15),
                                    color: `#${slotProps.data.account_type.color}`,
                                }"
                            >
                                {{ $t(`public.${slotProps.data.account_type.slug}`) }}
                            </div>
                        </div>
                    </template>
                </Column>
                <Column
                    field="amount"
                    sortable
                    :header="`${$t('public.amount')} ($)`"
                    class="hidden md:table-cell"
                >
                    <template #body="slotProps">
                        {{ formatAmount(slotProps.data.transaction_amount) }}
                    </template>
                </Column>
                <Column
                    field="status"
                    :header="`${$t('public.status')}`"
                    class="hidden md:table-cell"
                >
                    <template #body="slotProps">
                        <div class="flex py-1.5 items-center flex-1">
                            <StatusBadge :value="slotProps.data.status">
                                {{ $t(`public.${slotProps.data.status}`) }}
                            </StatusBadge>
                        </div>
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
                                        {{ slotProps.data.name }}
                                    </div>
                                    <div class="text-gray-500 text-sm">
                                        {{ `${formatDate(slotProps.data.created_at)}&nbsp;|&nbsp;${slotProps.data.meta_login}` }}
                                    </div>
                                </div>
                            </div>
                            <div class="overflow-hidden text-right text-ellipsis font-semibold">
                                $&nbsp;{{ formatAmount(slotProps.data.transaction_amount) }}
                            </div>
                        </div>
                    </template>
                </Column>
            </template>
        </DataTable>
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

            <!-- Filter Upline-->
            <div class="flex flex-col gap-2 items-center self-stretch">
                <div class="flex self-stretch text-xs text-gray-950 font-semibold">
                    {{ $t('public.filter_id') }}
                </div>
                <MultiSelect
                    v-model="selectedDownlines"
                    :options="downlines"
                    :placeholder="$t('public.filter_id')"
                    :maxSelectedLabels="1"
                    :selectedItemsLabel="`${selectedDownlines.length} ${$t('public.id_selected')}`"
                    class="w-full md:w-64 font-normal pl-3"
                    :showToggleAll="false"
                >
                    <template #option="{option}">
                        <span>{{ option.id_number }}</span>
                    </template>
                    <template #value>
                        <div v-if="selectedDownlines.length === 1">
                            <span>{{ selectedDownlines[0].id_number }}</span>
                        </div>
                        <span v-else-if="selectedDownlines.length > 1">
                            {{ selectedDownlines.length }} {{ $t('public.id_selected') }}
                        </span>
                        <span v-else class="text-gray-400">
                            {{ $t('public.filter_id') }}
                        </span>
                    </template>
                </MultiSelect>
            </div>

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
                        <RadioButton v-model="filters['role'].value" inputId="role_agent" value="ib" class="w-4 h-4" />
                        <label for="role_agent">{{ $t('public.ib') }}</label>
                    </div>
                </div>
            </div>

            <!-- Filter Amount-->
            <div class="flex flex-col gap-2 items-center self-stretch">
                <div class="flex self-stretch text-xs text-gray-950 font-semibold">
                    {{ $t('public.filter_amount_header') }}
                </div>
                <div class="flex flex-col items-center gap-1 self-stretch">
                    <div class="h-4 self-stretch">
                        <Slider v-model="filters['amount'].value" :min="minFilterAmount" :max="maxFilterAmount" range />
                    </div>
                    <div class="flex justify-between items-center self-stretch">
                        <span class="text-gray-950 text-sm">${{ minFilterAmount }}</span>
                        <span class="text-gray-950 text-sm">${{ maxFilterAmount }}</span>
                    </div>
                </div>
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
