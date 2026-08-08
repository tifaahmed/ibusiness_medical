<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="$emit('close')">
      <div class="fixed inset-0 bg-black/50" @click="$emit('close')" />
      <div class="relative w-full max-w-4xl max-h-[90vh] overflow-y-auto rounded-xl border border-border bg-card p-6 shadow-2xl">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-lg font-semibold">{{ t.charts?.title || 'Advanced Charts' }}</h2>
          <button
            type="button"
            class="inline-flex items-center justify-center rounded-md p-1.5 text-muted-foreground hover:text-foreground hover:bg-muted cursor-pointer"
            @click="$emit('close')"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
          </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Monthly batch creation -->
          <div class="rounded-lg border border-border bg-background p-4">
            <h3 class="text-sm font-medium mb-3">{{ t.charts?.monthly_batches || 'Batches per month' }}</h3>
            <canvas ref="monthlyChartRef"></canvas>
          </div>

          <!-- Completion distribution -->
          <div class="rounded-lg border border-border bg-background p-4">
            <h3 class="text-sm font-medium mb-3">{{ t.charts?.completion_status || 'Completion status' }}</h3>
            <canvas ref="completionChartRef"></canvas>
          </div>

          <!-- Top batches by quantity -->
          <div class="rounded-lg border border-border bg-background p-4 lg:col-span-2">
            <h3 class="text-sm font-medium mb-3">{{ t.charts?.top_batches || 'Top batches by quantity' }}</h3>
            <canvas ref="topBatchesChartRef"></canvas>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import {
  Chart,
  BarController,
  BarElement,
  DoughnutController,
  ArcElement,
  CategoryScale,
  LinearScale,
  Tooltip,
  Legend,
} from 'chart.js';

Chart.register(BarController, BarElement, DoughnutController, ArcElement, CategoryScale, LinearScale, Tooltip, Legend);

const props = defineProps({
  cards: { type: Object, required: true },
  chartData: { type: Object, default: () => ({ monthly_batches: {}, completion: { completed: 0, pending: 0 } }) },
});

defineEmits(['close']);

const page = usePage();
const t = computed(() => page.props.translations?.admin?.card_list || {});

const monthlyChartRef = ref(null);
const completionChartRef = ref(null);
const topBatchesChartRef = ref(null);

let monthlyChart = null;
let completionChart = null;
let topBatchesChart = null;

function getCssVar(name) {
  return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
}

const isDark = computed(() => document.documentElement.classList.contains('dark'));

const colors = {
  primary: '#d4a84b',
  primaryHover: '#c49a3f',
  emerald: '#16a34a',
  emeraldLight: '#86efac',
  red: '#dc2626',
  blue: '#2563eb',
  muted: '#6b7280',
  grid: isDark.value ? '#374151' : '#e5e7eb',
  text: isDark.value ? '#e5e7eb' : '#374151',
};

const topBatches = computed(() => {
  const items = [...(props.cards.data || [])]
    .sort((a, b) => b.quantity - a.quantity)
    .slice(0, 10);
  return {
    labels: items.map(c => c.batch_name || `Batch #${c.id}`),
    quantities: items.map(c => c.quantity),
    completed: items.map(c => c.completed_count),
  };
});

function buildMonthlyChart(canvas) {
  const entries = Object.entries(props.chartData.monthly_batches || {});
  const labels = entries.map(([m]) => {
    const [y, mo] = m.split('-');
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    return `${months[parseInt(mo) - 1]} ${y}`;
  });
  const data = entries.map(([, v]) => v);

  monthlyChart = new Chart(canvas, {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        label: 'Batches',
        data,
        backgroundColor: colors.primary,
        borderColor: colors.primaryHover,
        borderWidth: 1,
        borderRadius: 4,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      plugins: {
        legend: { display: false },
      },
      scales: {
        x: {
          ticks: { color: colors.text, font: { size: 10 } },
          grid: { color: colors.grid },
        },
        y: {
          ticks: { color: colors.text, font: { size: 10 }, stepSize: 1 },
          grid: { color: colors.grid },
        },
      },
    },
  });
}

function buildCompletionChart(canvas) {
  const { completed, pending } = props.chartData.completion;
  completionChart = new Chart(canvas, {
    type: 'doughnut',
    data: {
      labels: ['Completed', 'Pending'],
      datasets: [{
        data: [completed, pending],
        backgroundColor: [colors.emerald, colors.muted],
        borderWidth: 0,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      plugins: {
        legend: {
          position: 'bottom',
          labels: { color: colors.text, padding: 16, font: { size: 12 } },
        },
      },
    },
  });
}

function buildTopBatchesChart(canvas) {
  const data = topBatches.value;
  topBatchesChart = new Chart(canvas, {
    type: 'bar',
    data: {
      labels: data.labels,
      datasets: [
        {
          label: 'Quantity',
          data: data.quantities,
          backgroundColor: colors.blue,
          borderRadius: 4,
        },
        {
          label: 'Completed',
          data: data.completed,
          backgroundColor: colors.emerald,
          borderRadius: 4,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      plugins: {
        legend: {
          position: 'bottom',
          labels: { color: colors.text, padding: 16, font: { size: 12 } },
        },
      },
      scales: {
        x: {
          ticks: { color: colors.text, font: { size: 10 } },
          grid: { color: colors.grid },
        },
        y: {
          ticks: { color: colors.text, font: { size: 10 } },
          grid: { color: colors.grid },
          beginAtZero: true,
        },
      },
    },
  });
}

onMounted(() => {
  const observer = new MutationObserver(() => {
    colors.grid = isDark.value ? '#374151' : '#e5e7eb';
    colors.text = isDark.value ? '#e5e7eb' : '#374151';
  });
  observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

  if (monthlyChartRef.value) buildMonthlyChart(monthlyChartRef.value);
  if (completionChartRef.value) buildCompletionChart(completionChartRef.value);
  if (topBatchesChartRef.value) buildTopBatchesChart(topBatchesChartRef.value);
});

onUnmounted(() => {
  monthlyChart?.destroy();
  completionChart?.destroy();
  topBatchesChart?.destroy();
});
</script>
