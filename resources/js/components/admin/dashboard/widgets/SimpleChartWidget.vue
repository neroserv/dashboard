<script setup lang="ts">
import { Chart, registerables } from 'chart.js';
import { ref, onMounted, watch } from 'vue';
import AdminDashboardWidgetShell from '@/components/admin/dashboard/AdminDashboardWidgetShell.vue';

Chart.register(...registerables);

const props = defineProps<{
    data?: { labels?: string[]; values?: number[]; [key: string]: unknown } | null;
    title?: string;
    description?: string;
    chartType?: 'bar' | 'line' | 'doughnut';
}>();

const canvasRef = ref<HTMLCanvasElement | null>(null);
let chartInstance: Chart | null = null;

function getChartData() {
    const d = props.data;
    if (!d || typeof d !== 'object') {
        return { labels: [], values: [] };
    }
    if (Array.isArray(d.labels) && Array.isArray(d.values)) {
        return { labels: d.labels, values: d.values };
    }
    const keys = Object.keys(d).filter((k) => k !== 'labels' && k !== 'values' && typeof d[k] === 'number');
    return { labels: keys, values: keys.map((k) => Number(d[k])) };
}

function buildChart() {
    if (!canvasRef.value || !props.data) {
        return;
    }
    const { labels, values } = getChartData();
    if (!labels.length && !values.length) {
        return;
    }
    if (chartInstance) {
        chartInstance.destroy();
        chartInstance = null;
    }
    const type = props.chartType ?? 'bar';
    const primaryRgb = typeof window !== 'undefined'
        ? getComputedStyle(document.documentElement).getPropertyValue('--bs-primary-rgb').trim() || '13, 110, 253'
        : '13, 110, 253';
    const barColor = `rgba(${primaryRgb}, 0.65)`;
    chartInstance = new Chart(canvasRef.value, {
        type,
        data: {
            labels: labels.length ? labels : values.map((_, i) => String(i + 1)),
            datasets: [
                {
                    label: props.title ?? 'Wert',
                    data: values,
                    backgroundColor:
                        type === 'doughnut'
                            ? ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#6f42c1', '#20c997'].slice(0, 10)
                            : barColor,
                    borderColor: type === 'doughnut' ? '#fff' : barColor,
                    borderWidth: type === 'doughnut' ? 2 : 0,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: { padding: { top: 4, right: 4, bottom: 0, left: 4 } },
            plugins: {
                legend: {
                    display: type === 'doughnut',
                    position: 'bottom',
                    labels: { boxWidth: 10, font: { size: 10 } },
                },
            },
            scales:
                type !== 'doughnut'
                    ? {
                          x: { ticks: { maxRotation: 0, font: { size: 10 } }, grid: { display: false } },
                          y: { beginAtZero: true, ticks: { font: { size: 10 } }, grid: { color: 'rgba(0,0,0,0.06)' } },
                      }
                    : undefined,
        },
    });
}

onMounted(buildChart);
watch(() => props.data, buildChart, { deep: true });
</script>

<template>
    <AdminDashboardWidgetShell :title="title" :description="description">
        <div v-if="data" class="admin-chart-wrap position-relative w-100">
            <canvas ref="canvasRef" />
        </div>
        <p v-else class="text-muted small mb-0">Keine Daten.</p>
    </AdminDashboardWidgetShell>
</template>

<style scoped>
.admin-chart-wrap {
    height: 11rem;
    min-height: 8rem;
    max-height: 14rem;
}
</style>
