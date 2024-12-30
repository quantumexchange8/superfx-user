<script setup>
import Chart from 'chart.js/auto'
import {onMounted, ref, watch} from "vue";

const props = defineProps({
    selectedYear: String,
})

let chartInstance = null;
const year = ref(props.selectedYear);
const chartData = ref({
    labels: [],
    datasets: [],
});
const emit = defineEmits(["update:totalEarnedBonus", "update:percentageChange"]);

const fetchData = async () => {
    try {
        // Fetch the chart data based on the selected year
        const response = await axios.get('/billboard/getTotalEarnedBonusData', { params: { year: year.value } });
        const { labels, datasets } = response.data.chartData;
        emit('update:totalEarnedBonus', response.data.totalEarnedBonus)
        emit('update:percentageChange', response.data.percentageChange)

        chartData.value.labels = labels;
        chartData.value.datasets = datasets;

        // Update the chart data
        if (chartInstance) {
            chartInstance.data = chartData.value;
            chartInstance.update();
        }
    } catch (error) {
        console.error('Error fetching chart data:', error);
    }
};

const initializeChart = () => {
    const ctx = document.getElementById('total_earned_bonus_chart');
    if (!ctx) return;

    // Initialize the chart
    chartInstance = new Chart(ctx, {
        type: 'bar',
        data: chartData.value,
        options: {
            maintainAspectRatio: false,
            scales: {
                y: {
                    ticks: {
                        color: '#667085',
                        font: {
                            family: 'Poppins, sans-serif',
                            size: 12,
                            weight: 400,
                        },
                        display: false,
                        count: 6,
                    },
                    beginAtZero: true,
                    border: {
                        display: false
                    },
                    grid: {
                        drawTicks: false,
                        color: '#F2F4F7',
                    },
                },
                x: {
                    ticks: {
                        color: '#667085',
                        font: {
                            family: 'Poppins, sans-serif',
                            size: 12,
                            weight: 400,
                        },
                    },
                    grid: {
                        drawTicks: false,
                        color: 'transparent'
                    },
                }
            },
            plugins: {
                legend: {
                    display: false
                },
            }
        }
    });
};

onMounted(() => {
    // Initialize the chart when the component is mounted
    initializeChart();
    // Fetch the initial data for the chart
    fetchData();
});

watch(() => props.selectedYear, (newYear) => {
    year.value = newYear;
    fetchData();
});
</script>

<template>
    <div class="w-full">
        <canvas id="total_earned_bonus_chart" height="200"></canvas>
    </div>
</template>
