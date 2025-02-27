<script setup>
import { ref, watch } from "vue";
import axios from 'axios';
import RebateSummary from "@/Pages/Report/Rebate/Partials/RebateSummary.vue";
import RebateSummaryChart from "@/Pages/Report/Rebate/Partials/RebateSummaryChart.vue";
import RebateListingTable from "@/Pages/Report/Rebate/Partials/RebateListingTable.vue";
import { usePage } from "@inertiajs/vue3";
import { transactionFormat } from '@/Composables/index.js';

const props = defineProps({
    group: String,
});

const { formatDate, formatDateTime, formatAmount } = transactionFormat();

// Initialize the form with user data
const user = usePage().props.auth.user;

// Define reactive variables
const rebateSummary = ref([]);
const totalVolume = ref(0);
const totalRebate = ref(0);
const dateRange = ref([]);
const selectedGroup = ref('dollar');

// Function to fetch rebate summary data
const getResults = async (dateRange, selectedGroup) => {
    const [startDate, endDate] = dateRange;
    const params = new URLSearchParams();

    // Append date range to the URL if it's not null
    if (startDate && endDate) {
        params.append("startDate", formatDate(startDate));
        params.append("endDate", formatDate(endDate));
    }

    if (selectedGroup) {
        params.append("group", selectedGroup);
    }

    try {
        const response = await axios.get('/report/getRebateSummary', { params });
        const data = response.data;

        // Update the rebateSummary and totals
        rebateSummary.value = data.rebateSummary;
        totalVolume.value = data.totalVolume;
        totalRebate.value = data.totalRebate;
    } catch (error) {
        console.error('Error fetching rebate summary:', error);
    }
};

// Watch for changes in the dateRange and fetch rebate summary data
watch(dateRange, (newDateRange) => {
    if (newDateRange === null || newDateRange === undefined) {
        // Handle null or undefined newDateRange
        getResults([], selectedGroup.value);
    } else {
        getResults(newDateRange, selectedGroup.value);
    }
});

watch(() => props.group, (newGroup) => {
    // Whenever uplines change, update the local ref
    selectedGroup.value = newGroup;
    getResults(dateRange.value, newGroup);
  }, { immediate: true }
);

// Handle the update-date event from RebateListingTable
const handleUpdateDate = (newDateRange) => {
    // console.log('Date Range Received:', newDateRange);
    dateRange.value = newDateRange;
};

</script>

<template>
    <div class="w-full grid grid-cols-1 xl:grid-cols-2 gap-5">
        <RebateSummary :rebateSummary="rebateSummary" :totalVolume="totalVolume" :totalRebate="totalRebate" :selectedGroup="selectedGroup"/>
        <RebateSummaryChart :rebateSummary="rebateSummary" :totalVolume="totalVolume" :totalRebate="totalRebate" />
    </div>
    <RebateListingTable @update-date="handleUpdateDate" :selectedGroup="selectedGroup"/>
</template>
