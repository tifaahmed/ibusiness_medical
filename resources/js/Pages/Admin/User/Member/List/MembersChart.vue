<template>
  <div
    :class="zoomed ? 'fixed inset-0 z-50 flex items-center justify-center bg-gray-900/80' : ''"
    @click.self="zoomed = false"
  >
    <div
      :class="[
        'bg-card text-card-foreground rounded-xl border border-border shadow-sm w-full transition-all duration-300',
        zoomed ? 'max-w-5xl max-h-full overflow-auto m-4' : ''
      ]"
    >
      <div class="p-4 sm:p-6">
        <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
          <div class="flex items-center gap-3">
            <h3 class="text-sm font-semibold whitespace-nowrap transition-transform duration-150 hover:scale-110">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline-block mr-1.5 -mt-0.5">
                <line x1="18" x2="18" y1="20" y2="10"/><line x1="12" x2="12" y1="20" y2="4"/><line x1="6" x2="6" y1="20" y2="14"/>
              </svg>
              {{ t.member_list?.chart_title || 'Members over time' }}
            </h3>
            <span class="text-xs text-white bg-muted/50 px-2 py-0.5 rounded-md border border-border whitespace-nowrap transition-transform duration-150 hover:scale-110">
              {{ t.member_list?.chart_total || 'Total' }}: <strong>{{ totalMembers }}</strong>
            </span>
          </div>
            <div class="flex items-center gap-2 flex-wrap">
              <div class="flex items-center gap-1 rounded-lg border border-border bg-background p-0.5">
                <button
                  type="button"
                  :class="mode === 'month' ? 'bg-primary text-primary-foreground shadow-xs' : 'text-white hover:text-foreground'"
                  class="px-2.5 py-1 text-xs font-medium rounded-md transition-all duration-150 cursor-pointer hover:scale-110"
                  @click="switchMode('month')"
                >
                  {{ t.member_list?.chart_month || 'Month' }}
                </button>
                <button
                  type="button"
                  :class="mode === 'day' ? 'bg-primary text-primary-foreground shadow-xs' : 'text-white hover:text-foreground'"
                  class="px-2.5 py-1 text-xs font-medium rounded-md transition-all duration-150 cursor-pointer hover:scale-110"
                  @click="switchMode('day')"
                >
                  {{ t.member_list?.chart_day || 'Day' }}
                </button>
              </div>
              <div class="flex items-center gap-1 rounded-lg border border-border bg-background p-0.5">
                <button
                  v-for="opt in dayOptions"
                  :key="opt.value"
                  type="button"
                  :class="(chartDays || '') === String(opt.value) ? 'bg-primary text-primary-foreground shadow-xs' : 'text-white hover:text-foreground'"
                  class="px-2 py-1 text-[11px] font-medium rounded-md transition-all duration-150 cursor-pointer hover:scale-110"
                  @click="setChartDays(opt.value)"
                >
                  {{ opt.label }}
                </button>
              </div>
              <button
              type="button"
              @click="toggleZoom"
              class="flex items-center justify-center h-7 w-7 rounded-md border border-border bg-background text-white hover:bg-primary hover:text-primary-foreground transition-all duration-150 cursor-pointer hover:scale-110"
              :title="zoomed ? 'Zoom out' : 'Zoom in'"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"/>
                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                <line v-if="!zoomed" x1="11" y1="8" x2="11" y2="14"/>
                <line v-if="!zoomed" x1="8" y1="11" x2="14" y2="11"/>
                <line v-if="zoomed" x1="8" y1="11" x2="14" y2="11"/>
              </svg>
            </button>
          </div>
        </div>
        <div class="relative" :style="{ height: zoomed ? '60vh' : '260px' }">
          <div class="overflow-x-auto overflow-y-hidden">
            <div :style="chartWrapperStyle">
              <canvas ref="chartRef"></canvas>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import {
  Chart,
  BarController,
  BarElement,
  CategoryScale,
  LinearScale,
  Tooltip,
  Legend,
} from 'chart.js';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip, Legend);

let hoveredIndex = -1;

const barValueLabel = {
  id: 'barValueLabel',
  afterDatasetsDraw(chart) {
    const ctx = chart.ctx;
    chart.data.datasets.forEach((dataset, i) => {
      const meta = chart.getDatasetMeta(i);
      meta.data.forEach((bar, index) => {
        const value = dataset.data[index];
        if (value === 0) return;
        const isHovered = index === hoveredIndex;
        ctx.save();
        ctx.fillStyle = '#ffffff';
        ctx.font = isHovered ? 'bold 16px sans-serif' : '9px sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'bottom';
        ctx.fillText(value, bar.x, bar.y - (isHovered ? 10 : 3));
        ctx.restore();
      });
    });
    if (hoveredIndex >= 0) {
      const xScale = chart.scales.x;
      const tick = xScale.ticks?.[hoveredIndex];
      if (tick) {
        ctx.save();
        ctx.font = 'bold 13px sans-serif';
        ctx.fillStyle = '#ffffff';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'top';
        ctx.fillText(tick.label, xScale.getPixelForTick(hoveredIndex), xScale.bottom + 4);
        ctx.restore();
      }
    }
  },
};

const props = defineProps({
  chartData: { type: Object, required: true },
  chartDays: { type: [String, Number], default: null },
});

const dayOptions = [
  { label: '7d', value: 7 },
  { label: '14d', value: 14 },
  { label: '30d', value: 30 },
];

function setChartDays(value) {
  router.get(route('admin.user.membership.list'), { chart_days: value || undefined }, {
    preserveState: true,
    preserveScroll: true,
  });
}

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const mode = ref('month');
const chartRef = ref(null);
const zoomed = ref(false);
let chartInstance = null;

function toggleZoom() {
  zoomed.value = !zoomed.value;
  requestAnimationFrame(() => buildChart());
}

const totalMembers = computed(() => {
  const raw = props.chartData || {};
  const source = mode.value === 'month' ? raw.monthly : raw.daily;
  if (!source) return 0;
  return Object.values(source).reduce((sum, v) => sum + v, 0);
});

const chartWrapperStyle = computed(() => {
  if (mode.value !== 'day') return {};
  const raw = props.chartData || {};
  const entries = Object.entries(raw.daily || {});
  const count = entries.length;
  const minWidth = Math.max(count * 60, 300);
  return { minWidth: `${minWidth}px` };
});

function getCssVar(name) {
  return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
}

const isDark = computed(() => document.documentElement.classList.contains('dark'));

const CHART_COLORS = {
  bar: '#d4a84b',
  barHover: '#c49a3f',
  grid: isDark.value ? '#374151' : '#e5e7eb',
  text: '#ffffff',
};

function formatMonthLabel(key) {
  if (!key || key.length < 7) return key;
  const [y, m] = key.split('-');
  const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  return `${months[parseInt(m, 10) - 1]} ${y}`;
}

function formatDayLabel(key) {
  if (!key) return key;
  const d = new Date(key + 'T00:00:00');
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: '2-digit' });
}

function buildChart() {
  if (!chartRef.value) return;

  const raw = props.chartData || {};
  const entries = mode.value === 'month'
    ? Object.entries(raw.monthly || {})
    : Object.entries(raw.daily || {});

  const labels = entries.map(([k]) => mode.value === 'month' ? formatMonthLabel(k) : formatDayLabel(k));
  const data = entries.map(([, v]) => v);

  if (chartInstance) chartInstance.destroy();

  chartInstance = new Chart(chartRef.value, {
    type: 'bar',
    plugins: [barValueLabel],
    data: {
      labels,
      datasets: [{
        label: mode.value === 'month' ? 'Per month' : 'Per day',
        data,
        backgroundColor: CHART_COLORS.bar,
        hoverBackgroundColor: CHART_COLORS.barHover,
        borderRadius: 3,
        borderSkipped: false,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      hover: {
        mode: 'index',
        intersect: true,
      },
      onHover: (e, elements) => {
        const idx = elements.length > 0 ? elements[0].index : -1;
        if (idx !== hoveredIndex) {
          hoveredIndex = idx;
          requestAnimationFrame(() => chartInstance?.draw());
        }
      },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#1f2937',
          titleColor: '#f3f4f6',
          bodyColor: '#e5e7eb',
          borderColor: '#374151',
          borderWidth: 1,
          padding: 10,
          displayColors: false,
          cornerRadius: 6,
        },
      },
      scales: {
        x: {
          ticks: {
            color: CHART_COLORS.text,
            font: { size: 10 },
            maxRotation: 45,
            autoSkip: false,
          },
          grid: { color: CHART_COLORS.grid },
        },
        y: {
          ticks: { color: CHART_COLORS.text, font: { size: 10 }, stepSize: 1 },
          grid: { color: CHART_COLORS.grid },
          beginAtZero: true,
        },
      },
    },
  });
}

function switchMode(newMode) {
  mode.value = newMode;
  buildChart();
}

let darkObserver = null;

onMounted(() => {
  darkObserver = new MutationObserver(() => {
    CHART_COLORS.grid = isDark.value ? '#374151' : '#e5e7eb';
    if (chartInstance) {
      chartInstance.options.scales.x.grid.color = CHART_COLORS.grid;
      chartInstance.options.scales.y.grid.color = CHART_COLORS.grid;
      chartInstance.update();
    }
  });
  darkObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
  window.addEventListener('keydown', onKeydown);
  buildChart();
});

function onKeydown(e) {
  if (e.key === 'Escape' && zoomed.value) zoomed.value = false;
}

watch(zoomed, () => {
  requestAnimationFrame(() => chartInstance?.resize());
});

watch(() => props.chartData, () => {
  chartInstance?.destroy();
  chartInstance = null;
  buildChart();
}, { deep: true });

onUnmounted(() => {
  chartInstance?.destroy();
  darkObserver?.disconnect();
  window.removeEventListener('keydown', onKeydown);
});
</script>
