<script setup>
import OverlayPanel from 'primevue/overlaypanel';
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import {FilterMatchMode} from "primevue/api";
import Loader from "@/Components/Loader.vue";
import {
    IconSearch,
    IconCircleXFilled,
    IconAdjustments,
    IconCloudDownload,
    IconX,
} from '@tabler/icons-vue';
import {transactionFormat} from "@/Composables/index.js";
import {ref, watch, watchEffect} from "vue";
import { wTrans } from 'laravel-vue-i18n';
import Button from '@/Components/Button.vue';
import Badge from '@/Components/Badge.vue';
import InputText from 'primevue/inputtext';
import { usePage } from '@inertiajs/vue3';
import StatusBadge from "@/Components/StatusBadge.vue";
import RadioButton from 'primevue/radiobutton';
import Calendar from 'primevue/calendar';
import Dialog from 'primevue/dialog';
import TransactionDetails from '@/Pages/Transaction/Partials/TransactionDetails.vue';
import Slider from 'primevue/slider';
import dayjs from 'dayjs'
import Empty from '@/Components/Empty.vue';

const { formatDateTime, formatAmount } = transactionFormat();

const loading = ref(false);
const dt = ref();
const paginator_caption = wTrans('public.paginator_caption');
const transactions = ref();
const selectedDate = ref();
const minFilterAmount = ref(0);
const maxFilterAmount = ref(0);
const totalTransaction = ref(0);

const getResults = async (filterDate = null) => {
    if (loading.value) return;
    loading.value = true;

    try {
        let url = '/transaction/getTransactions';

        if (filterDate) {
            const [startDate, endDate] = filterDate;
            url += `?startDate=${dayjs(startDate).format('YYYY-MM-DD')}&endDate=${dayjs(endDate).format('YYYY-MM-DD')}`;
        }

        const response = await axios.get(url);
        transactions.value = response.data.transactions;
        console.log(transactions)
        totalTransaction.value = transactions.value?.length;
        maxFilterAmount.value = transactions.value?.length ? Math.max(...transactions.value.map(item => parseFloat(item.transaction_amount || 0))) : 0;
    } catch (error) {
        console.error('Error get transactions:', error);
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

const exportCSV = () => {
    dt.value.exportCSV();
};

const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
    name: { value: null, matchMode: FilterMatchMode.STARTS_WITH },
    transaction_type: { value: null, matchMode: FilterMatchMode.EQUALS },
    amount: { value: [minFilterAmount.value, maxFilterAmount.value], matchMode: FilterMatchMode.BETWEEN },
    status: { value: null, matchMode: FilterMatchMode.EQUALS },
});

// Watch minFilterAmount and maxAmount to update the amount filter
watch([minFilterAmount, maxFilterAmount], ([newMin, newMax]) => {
    filters.value.amount.value = [newMin, newMax];
});

watch(selectedDate, (newDateRange) => {
    if (Array.isArray(newDateRange)) {
        const [startDate, endDate] = newDateRange;

        if (startDate && endDate) {
            getResults([startDate, endDate]);
        } else if (startDate || endDate) {
            getResults([startDate || endDate, endDate || startDate]);
        } else {
            getResults([]);
        }
    } else {
        console.warn('Invalid date range format:', newDateRange);
    }
});

const clearDate = () => {
    selectedDate.value = null;
};

// overlay panel
const op = ref();
const filterCount = ref(0);

const toggle = (event) => {
    op.value.toggle(event);
}

const recalculateTotals = () => {
    const filtered = transactions.value.filter(transaction => {
        return (
            (!filters.value.name?.value || transaction.name.startsWith(filters.value.name.value)) &&
            (!filters.value.transaction_type?.value || transaction.transaction_type === filters.value.transaction_type.value) &&
            (!filters.value.amount?.value[0] || !filters.value.amount?.value[1] || (transaction.transaction_amount >= filters.value.amount.value[0] && transaction.transaction_amount <= filters.value.amount.value[1])) &&
            (!filters.value.status?.value || transaction.status === filters.value.status.value)
        );
    });

    totalTransaction.value = filtered.length;
};

watch(filters, () => {
    recalculateTotals();
    // Check if amount filter covers the entire range (considering full range as minFilterAmount and maxFilterAmount)
    const amountFilterIsActive = filters.value.amount.value[0] !== minFilterAmount.value || filters.value.amount.value[1] !== maxFilterAmount.value;

    // Count active filters
    filterCount.value = Object.entries(filters.value).filter(([key, filter]) => {
        // Exclude amount filter if it covers the entire range
        if (filter === filters.value.amount) {
            return amountFilterIsActive;
        }
        return filter.value !== null;
    }).length;
}, { deep: true });

const clearFilter = () => {
    filters.value = {
        global: { value: null, matchMode: FilterMatchMode.CONTAINS },
        name: { value: null, matchMode: FilterMatchMode.STARTS_WITH },
        transaction_type: { value: null, matchMode: FilterMatchMode.EQUALS },
        amount: { value: [minFilterAmount.value, maxFilterAmount.value], matchMode: FilterMatchMode.BETWEEN },
        status: { value: null, matchMode: FilterMatchMode.EQUALS },
    };
};

const clearFilterGlobal = () => {
    filters.value['global'].value = null;
}

// dialog
const visible = ref(false);
const selectedRow = ref();
const rowClicked = (data) => {
    selectedRow.value = data;
    visible.value = true;
}
</script>

<template>
        <div class="p-6 flex flex-col items-center justify-center self-stretch gap-6 border border-gray-200 bg-white shadow-table rounded-2xl">
        <DataTable
            v-model:filters="filters"
            :value="transactions"
            :paginator="transactions?.length > 0 && totalTransaction > 0"
            removableSort
            :rows="10"
            :rowsPerPageOptions="[10, 20, 50, 100]"
            tableStyle="min-width: 50rem"
            paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport"
            :currentPageReportTemplate="paginator_caption"
            :globalFilterFields="['transaction_number', 'to_meta_login', 'from_meta_login']"
            ref="dt"
            :loading="loading"
            table-style="min-width:fit-content"
            selectionMode="single"
            @row-click="rowClicked($event.data)"
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
            <template #empty><Empty :title="$t('public.empty_transaction_title')" :message="$t('public.empty_transaction_message')"/></template>
            <template #loading>
                <div class="flex flex-col gap-2 items-center justify-center">
                    <Loader />
                    <span class="text-sm text-gray-700">{{ $t('public.loading_caption') }}</span>
                </div>
            </template>
            <template v-if="transactions?.length > 0 && totalTransaction > 0">
                <Column
                    field="created_at"
                    sortable
                    :header="$t('public.date')"
                    class="w-1/6"
                >
                    <template #body="slotProps">
                        {{ formatDateTime(slotProps.data.created_at) }}
                    </template>
                </Column>
                <Column
                    field="transaction_number"
                    sortable
                    class="w-auto"
                    :header="$t('public.id')"
                >
                    <template #body="slotProps">
                        {{ slotProps.data.transaction_number }}
                    </template>
                </Column>
                <Column
                    field="description"
                    class="w-auto"
                    :header="$t('public.description')"
                >
                    <template #body="slotProps">
                        {{ $t(`public.${slotProps.data.transaction_type}`) }}
                    </template>
                </Column>
                <Column
                    field="account"
                    class="w-auto"
                    :header="$t('public.account')"
                >
                    <template #body="slotProps">
                        <div v-if="slotProps.data.transaction_type === 'deposit'">
                            {{ slotProps.data.to_meta_login }}
                        </div>
                        <div v-else-if="slotProps.data.transaction_type === 'withdrawal' && slotProps.data.from_meta_login !== null">
                            {{ slotProps.data.from_meta_login }}
                        </div>
                        <div v-else>
                            <!-- Optional: Handle unexpected transaction types -->
                            {{ $t('public.wallet') }}
                        </div>
                    </template>
                </Column>
                <Column
                    field="amount"
                    sortable
                    class="w-auto"
                    :header="`${$t('public.amount')} ($)`"
                >
                    <template #body="slotProps">
                        <template v-if="slotProps.data.transaction_amount">
                            $ {{ formatAmount(slotProps.data.transaction_type === 'withdrawal' ? slotProps.data.amount : slotProps.data.transaction_amount) }}
                        </template>
                        <template v-else>
                            -
                        </template>
                    </template>
                </Column>
                <Column
                    field="status"
                    :header="$t('public.status')"
                >
                    <template #body="slotProps">
                        <div class="flex py-1.5 items-center flex-1">
                            <StatusBadge :value="slotProps.data.status">
                                {{ $t(`public.${slotProps.data.status}`) }}
                            </StatusBadge>
                        </div>
                    </template>
                </Column>
            </template>
        </DataTable>
    </div>

    <OverlayPanel ref="op">
        <div class="flex flex-col gap-8 w-60 py-5 px-4">
            <!-- Filter type-->
            <div class="flex flex-col gap-2 items-center self-stretch">
                <div class="flex self-stretch text-xs text-gray-950 font-semibold">
                    {{ $t('public.filter_type_header') }}
                </div>
                <div class="flex flex-col gap-1 self-stretch">
                    <div class="flex items-center gap-2 text-sm text-gray-950">
                        <RadioButton
                            v-model="filters['transaction_type'].value"
                            inputId="type_deposit"
                            value="deposit"
                            class="w-4 h-4"
                        />
                        <label for="type_deposit">{{ $t('public.deposit') }}</label>
                    </div>
                    <div class="flex items-center gap-2 text-sm text-gray-950">
                        <RadioButton
                            v-model="filters['transaction_type'].value"
                            inputId="type_withdrawal"
                            value="withdrawal"
                            class="w-4 h-4"
                        />
                        <label for="type_withdrawal">{{ $t('public.withdrawal') }}</label>
                    </div>
                </div>
            </div>

            <!-- Filter Date-->
            <div class="flex flex-col gap-2 items-center self-stretch">
                <div class="flex self-stretch text-xs text-gray-950 font-semibold">
                    {{ $t('public.filter_date_header') }}
                </div>
                <div class="relative w-full">
                    <Calendar
                        v-model="selectedDate"
                        selectionMode="range"
                        dateFormat="yy/mm/dd"
                        iconDisplay="input"
                        :placeholder="$t('public.date_placeholder')"
                        class="w-full font-normal"
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

            <!-- Filter type-->
            <div class="flex flex-col gap-2 items-center self-stretch">
                <div class="flex self-stretch text-xs text-gray-950 font-semibold">
                    {{ $t('public.filter_status_header') }}
                </div>
                <div class="flex flex-col gap-1 self-stretch">
                    <div class="flex items-center gap-2 text-sm text-gray-950">
                        <RadioButton
                            v-model="filters['status'].value"
                            inputId="status_successful"
                            value="successful"
                            class="w-4 h-4"
                        />
                        <label for="status_successful">{{ $t('public.successful') }}</label>
                    </div>
                    <div class="flex items-center gap-2 text-sm text-gray-950">
                        <RadioButton
                            v-model="filters['status'].value"
                            inputId="status_processing"
                            value="processing"
                            class="w-4 h-4"
                        />
                        <label for="status_processing">{{ $t('public.processing') }}</label>
                    </div>
                    <div class="flex items-center gap-2 text-sm text-gray-950">
                        <RadioButton
                            v-model="filters['status'].value"
                            inputId="status_failed"
                            value="failed"
                            class="w-4 h-4"
                        />
                        <label for="status_failed">{{ $t('public.failed') }}</label>
                    </div>
                    <div class="flex items-center gap-2 text-sm text-gray-950">
                        <RadioButton
                            v-model="filters['status'].value"
                            inputId="status_rejected"
                            value="rejected"
                            class="w-4 h-4"
                        />
                        <label for="status_rejected">{{ $t('public.rejected') }}</label>
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

    <Dialog
        v-model:visible="visible"
        modal
        :header="$t('public.details')"
        class="dialog-xs md:dialog-sm"
    >
        <TransactionDetails :data="selectedRow" @update:visible="visible = false"/>
    </Dialog>
</template>
