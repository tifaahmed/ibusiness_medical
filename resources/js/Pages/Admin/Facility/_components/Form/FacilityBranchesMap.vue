<template>
  <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
    <div data-slot="card-header" class="px-6 py-2">
      <div data-slot="card-title" class="leading-none font-semibold title-golden flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
          <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path>
          <circle cx="12" cy="10" r="3"></circle>
        </svg>
        {{ t.facility?.branches_map || 'Branches map' }}
        <span class="text-xs font-normal text-muted-foreground">
          ({{ points.length }}<template v-if="missing > 0"> / {{ points.length + missing }}</template>)
        </span>
      </div>
    </div>
    <div data-slot="card-content" class="px-6 space-y-2">
      <div class="relative h-80 w-full overflow-hidden rounded-lg border border-border">
        <div ref="mapEl" class="absolute inset-0"></div>
        <div
          v-if="points.length === 0"
          class="absolute inset-0 z-[1000] flex items-center justify-center bg-background/70 px-4 text-center text-xs text-muted-foreground pointer-events-none"
        >
          {{ t.facility?.branches_map_empty || 'None of the branches has GPS coordinates yet.' }}
        </div>
      </div>
      <p v-if="missing > 0 && points.length > 0" class="text-[11px] text-muted-foreground">
        {{ (t.facility?.branches_map_missing || ':count branch(es) without coordinates are not shown.').replace(':count', missing) }}
      </p>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import 'leaflet/dist/leaflet.css';
import L from 'leaflet';

// Every branch of the facility with GPS coordinates, drawn as it stands in the
// form — so an edit to a branch's coordinates moves its pin before saving.
const props = defineProps({
  branches: { type: Array, default: () => [] },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const text = (value) => {
  if (typeof value === 'string') return value;
  if (value && typeof value === 'object') {
    const locale = page.props.locale || 'ar';
    return value[locale] || value.ar || value.en || Object.values(value)[0] || '';
  }
  return '';
};

const escapeHtml = (value) => String(value).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

const coordinate = (value) => {
  if (value === '' || value === null || value === undefined) return null;
  const n = Number(value);
  return Number.isFinite(n) ? n : null;
};

const points = computed(() => props.branches
  .map((branch) => ({ branch, lat: coordinate(branch.latitude), lng: coordinate(branch.longitude) }))
  .filter(({ lat, lng }) => lat !== null && lng !== null && Math.abs(lat) <= 90 && Math.abs(lng) <= 180));

const missing = computed(() => props.branches.length - points.value.length);

const mapEl = ref(null);
let map = null;
let markers = [];
let resizeObserver = null;

const pin = L.divIcon({
  className: '',
  html: `<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="#d97706" stroke="#1f2937" stroke-width="1" style="filter: drop-shadow(0 1px 2px rgba(0,0,0,0.4));"><path d="M12 2C7.6 2 4 5.6 4 10c0 6 8 12 8 12s8-6 8-12c0-4.4-3.6-8-8-8Z"/><circle cx="12" cy="10" r="3" fill="white"/></svg>`,
  iconSize: [30, 30],
  iconAnchor: [15, 30],
  popupAnchor: [0, -28],
});

const popupHtml = (branch, lat, lng) => {
  const name = escapeHtml(text(branch.name) || '-');
  const address = escapeHtml(text(branch.address));
  const link = `https://www.google.com/maps?q=${lat},${lng}`;
  return `<div style="min-width:170px;font-size:12px;line-height:1.4;">
    <div style="font-weight:600;">${name}</div>
    ${address ? `<div style="margin-top:4px;">${address}</div>` : ''}
    <div style="margin-top:4px;"><a href="${link}" target="_blank" rel="noopener noreferrer" style="color:#2563eb;text-decoration:underline;">${escapeHtml(t.value.facility_branch?.view_on_maps || 'Open in Google Maps')}</a></div>
  </div>`;
};

const render = () => {
  if (!map) return;

  markers.forEach((marker) => map.removeLayer(marker));
  markers = points.value.map(({ branch, lat, lng }) =>
    L.marker([lat, lng], { icon: pin }).bindPopup(popupHtml(branch, lat, lng)).addTo(map)
  );

  if (points.value.length === 1) {
    map.setView([points.value[0].lat, points.value[0].lng], 15);
  } else if (points.value.length > 1) {
    map.fitBounds(points.value.map(({ lat, lng }) => [lat, lng]), { padding: [30, 30], maxZoom: 15 });
  }
};

onMounted(() => {
  map = L.map(mapEl.value, { zoomControl: true }).setView([26.8206, 30.8025], 6);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    maxZoom: 19,
  }).addTo(map);
  render();

  // The card sits inside a tab that is hidden with v-show: Leaflet measures
  // zero there, so it is told to re-measure when the tab shows again.
  resizeObserver = new ResizeObserver(() => map?.invalidateSize());
  resizeObserver.observe(mapEl.value);
});

onBeforeUnmount(() => {
  resizeObserver?.disconnect();
  if (map) {
    map.remove();
    map = null;
  }
});

watch(points, render, { deep: true });
</script>
