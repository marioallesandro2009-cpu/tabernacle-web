<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Chart, LineController, LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip, Legend, DoughnutController, ArcElement, BarController, BarElement } from 'chart.js';

Chart.register(LineController, LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip, Legend, DoughnutController, ArcElement, BarController, BarElement);

const props = defineProps({
    type: { type: String, default: 'line' },
    labels: { type: Array, required: true },
    datasets: { type: Array, required: true }, // [{label, data, color}]
    height: { type: Number, default: 260 },
});
const el = ref(null);
let chart;
const palette = ['#0b1a5c', '#e8973f', '#17a08c', '#b8661c', '#6b7bd1', '#9ca3af', '#d4577a', '#4b9fd5'];

function build() {
    chart?.destroy();
    const isDonut = props.type === 'doughnut';
    chart = new Chart(el.value, {
        type: props.type,
        data: {
            labels: props.labels,
            datasets: props.datasets.map((d, i) => isDonut
                ? { label: d.label, data: d.data, backgroundColor: props.labels.map((_, k) => palette[k % palette.length]), borderWidth: 0 }
                : { label: d.label, data: d.data, borderColor: d.color || palette[i], backgroundColor: (d.color || palette[i]) + (props.type === 'line' ? '22' : 'cc'), fill: props.type === 'line', tension: 0.3, pointRadius: props.labels.length > 45 ? 0 : 2, borderWidth: 2 }),
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { display: isDonut || props.datasets.length > 1, position: isDonut ? 'right' : 'bottom' } },
            scales: isDonut ? {} : { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false }, ticks: { maxTicksLimit: 10 } } },
        },
    });
}
onMounted(build);
watch(() => [props.labels, props.datasets], build, { deep: true });
onBeforeUnmount(() => chart?.destroy());
</script>

<template>
    <div :style="{ height: height + 'px' }"><canvas ref="el" role="img" aria-label="Grafik" /></div>
</template>
