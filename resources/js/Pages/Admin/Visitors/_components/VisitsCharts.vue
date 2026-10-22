<template>
  <div class="bg-card text-card-foreground rounded-xl border border-border shadow-sm p-3 sm:p-4 space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <h3 class="flex items-center gap-1.5 text-sm font-semibold">
        <svg class="h-4 w-4 text-sky-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7 15l4-4 3 3 5-6" /></svg>
        Page views
      </h3>
      <div class="flex flex-wrap items-center gap-2">
        <div class="flex flex-wrap items-center gap-1 rounded-lg border border-border bg-background p-0.5">
          <button v-for="d in dayOptions" :key="d" type="button" class="min-w-10 px-2 py-1 text-xs font-medium rounded-md" :class="pill(f.days === d)" @click="set('days', d)">{{ d }}d</button>
        </div>
        <div class="flex flex-wrap items-center gap-1 rounded-lg border border-border bg-background p-0.5">
          <button v-for="s in sourceOptions" :key="s.value" type="button" class="min-w-14 px-2 py-1 text-xs font-medium rounded-md" :class="pill(f.source === s.value)" @click="set('source', s.value)">{{ s.label }}</button>
        </div>
        <div class="flex flex-wrap items-center gap-1 rounded-lg border border-border bg-background p-0.5">
          <button type="button" class="min-w-14 px-2 py-1 text-xs font-medium rounded-md" :class="pill(!f.unique)" @click="set('unique', false)">All views</button>
          <button type="button" class="min-w-14 px-2 py-1 text-xs font-medium rounded-md" :class="pill(f.unique)" @click="set('unique', true)">Unique</button>
        </div>
      </div>
    </div>

    <div v-if="error" class="rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-500">{{ error }}</div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
      <div class="rounded-lg border border-border p-3">
        <div class="text-xs text-muted-foreground">{{ f.unique ? 'Unique visitors' : 'Page views' }} · last {{ f.days }} days</div>
        <div class="text-xl font-bold">{{ loading ? '…' : num(stats?.total) }}</div>
      </div>
      <div class="rounded-lg border border-border p-3">
        <div class="text-xs text-muted-foreground">Busiest day</div>
        <div class="text-xl font-bold text-amber-500">{{ loading ? '…' : num(stats?.busiestDay?.count) }}</div>
        <div class="text-xs text-muted-foreground">{{ stats?.busiestDay ? day(stats.busiestDay.date) : '—' }}</div>
      </div>
      <div class="rounded-lg border border-border p-3 min-w-0">
        <div class="text-xs text-muted-foreground">Top page</div>
        <div class="text-xl font-bold text-emerald-500">{{ loading ? '…' : num(stats?.perPage?.[0]?.count) }}</div>
        <div class="text-xs text-muted-foreground truncate" dir="ltr">{{ stats?.perPage?.[0]?.page || '—' }}</div>
      </div>
    </div>

    <div class="relative h-56 sm:h-64">
      <canvas ref="canvas"></canvas>
      <div v-if="!loading && stats && !stats.total" class="absolute inset-0 flex flex-col items-center justify-center gap-1 text-sm text-muted-foreground">
        <svg class="h-8 w-8 opacity-50" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7 15l4-4 3 3 5-6" /></svg>
        No visits in this window.
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3">
      <TopList title="By source" :rows="(stats?.perSource || []).map((r) => ({ key: r.source, count: r.count, source: r.source }))" />
      <TopList title="Pages" :rows="(stats?.perPage || []).map((r) => ({ key: r.page, label: r.page, count: r.count, ltr: true }))" />
      <TopList title="Products" :rows="(stats?.perProduct || []).map((r) => ({ key: r.id, label: r.name, count: r.count, href: r.slug ? route('admin.product.show', r.slug) : null }))" />
      <TopList title="Facilities" :rows="(stats?.perFacility || []).map((r) => ({ key: r.id, label: r.name, count: r.count, href: r.slug ? route('admin.facility.show', r.slug) : null }))" />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick, h } from 'vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import { Chart, LineController, LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip, Legend } from 'chart.js';
import SourceBadge from './SourceBadge.vue';

Chart.register(LineController, LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip, Legend);

const dayOptions = ref([7, 30, 90]);
const sourceOptions = [
  { value: '', label: 'All' },
  { value: 'web', label: 'Website' },
  { value: 'mobile', label: 'Mobile app' },
  { value: 'android', label: 'Android' },
  { value: 'ios', label: 'iOS' },
];
const f = ref({ days: 30, source: '', unique: false });
const stats = ref(null);
const loading = ref(true);
const error = ref('');
const canvas = ref(null);
let chart = null;

const pill = (on) => (on ? 'bg-primary text-primary-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground');
const num = (n) => (n == null ? '—' : Number(n).toLocaleString());
const day = (d) => new Date(`${d}T00:00:00`).toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short' });

/* One ranked list with an inline bar per row, so the long tail stays readable on a phone. */
const TopList = (props) => {
  const max = Math.max(1, ...props.rows.map((r) => r.count));
  return h('div', { class: 'rounded-lg border border-border p-3 min-w-0' }, [
    h('div', { class: 'mb-2 text-xs font-semibold text-muted-foreground' }, props.title),
    props.rows.length
      ? h('ul', { class: 'space-y-1.5 max-h-64 overflow-y-auto pr-1' }, props.rows.map((r) => h('li', { key: r.key, class: 'min-w-0' }, [
          h('div', { class: 'flex items-center justify-between gap-2 text-xs' }, [
            r.source
              ? h(SourceBadge, { source: r.source })
              : r.href
                ? h(Link, { href: r.href, class: 'truncate hover:underline' }, () => r.label)
                : h('span', { class: 'truncate', dir: r.ltr ? 'ltr' : undefined }, r.label),
            h('span', { class: 'shrink-0 font-semibold tabular-nums' }, Number(r.count).toLocaleString()),
          ]),
          h('div', { class: 'mt-0.5 h-1 rounded bg-muted' }, [h('div', { class: 'h-1 rounded bg-sky-500', style: { width: `${(r.count / max) * 100}%` } })]),
        ])))
      : h('div', { class: 'py-4 text-center text-xs text-muted-foreground' }, 'Nothing yet.'),
  ]);
};
TopList.props = ['title', 'rows'];

const SOURCE_STYLE = {
  web: { label: 'Website', color: '#0ea5e9' },
  android: { label: 'Android', color: '#10b981' },
  ios: { label: 'iOS', color: '#8b5cf6' },
};

/*
 * "All" and "Mobile app" draw one line per source, so the website and each
 * app can be compared day by day; a single source draws its own line, filled.
 */
function datasets() {
  const bySource = stats.value.perDayBySource || {};
  const keys = Object.keys(SOURCE_STYLE).filter((k) => bySource[k]);
  const many = keys.length > 1 || (!f.value.source && keys.length);
  const points = stats.value.perDay.length > 31 ? 0 : 2;
  const line = (label, color, rows, fill) => ({
    label, data: rows.map((p) => p.count), borderColor: color, backgroundColor: `${color}26`, fill, tension: 0.3, pointRadius: points, pointHoverRadius: 4, borderWidth: 2,
  });
  if (many) return keys.map((k) => line(SOURCE_STYLE[k].label, SOURCE_STYLE[k].color, bySource[k], false));
  const style = SOURCE_STYLE[f.value.source] || SOURCE_STYLE[keys[0]] || SOURCE_STYLE.web;
  return [line(style.label, style.color, stats.value.perDay, true)];
}

function draw() {
  if (!canvas.value || !stats.value) return;
  const labels = stats.value.perDay.map((p) => day(p.date));
  const sets = datasets();
  const grid = document.documentElement.classList.contains('dark') ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.08)';
  const tick = getComputedStyle(document.documentElement).getPropertyValue('--muted-foreground').trim() || '#9ca3af';
  if (chart) chart.destroy();
  chart = new Chart(canvas.value, {
    type: 'line',
    data: { labels, datasets: sets },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: { legend: { display: sets.length > 1, position: 'bottom', labels: { color: tick, boxWidth: 12, usePointStyle: true } }, tooltip: { displayColors: sets.length > 1 } },
      scales: {
        x: { grid: { display: false }, ticks: { color: tick, maxRotation: 0, autoSkip: true, maxTicksLimit: 8 } },
        y: { beginAtZero: true, grid: { color: grid }, ticks: { color: tick, precision: 0 } },
      },
    },
  });
}

async function load() {
  loading.value = true;
  error.value = '';
  try {
    const { data } = await axios.get(route('admin.visitors.stats'), { params: { days: f.value.days, unique: f.value.unique ? 1 : 0, source: f.value.source || undefined } });
    stats.value = data;
    if (Array.isArray(data.dayOptions)) dayOptions.value = data.dayOptions;
    await nextTick();
    draw();
  } catch (e) {
    error.value = 'Could not load the visit charts.';
    axios.post('/api/v1/client-errors', { message: String(e?.message || e), stack: e?.stack, route: 'admin.visitors.stats', extra: { ...f.value } }).catch(() => {});
  } finally {
    loading.value = false;
  }
}

function set(key, value) {
  f.value = { ...f.value, [key]: value };
  load();
}

onMounted(load);
onUnmounted(() => chart?.destroy());
</script>
