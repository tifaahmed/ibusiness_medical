<template>
  <div class="space-y-1.5">
    <div class="relative h-64 w-full overflow-hidden rounded-lg border border-border">
      <div ref="mapEl" class="absolute inset-0"></div>
      <div
        v-if="!hasPoint"
        class="absolute inset-0 z-[1000] flex items-center justify-center bg-background/70 px-4 text-center text-xs text-muted-foreground pointer-events-none"
      >
        {{ t.facility_branch?.map_no_point || 'Enter the latitude and longitude, or use "Find GPS on map with AI", to see the pin here.' }}
      </div>
    </div>
    <a
      v-if="hasPoint"
      :href="`https://www.google.com/maps?q=${lat},${lng}`"
      target="_blank"
      rel="noopener noreferrer"
      class="inline-block text-[11px] text-blue-600 underline"
    >
      {{ t.facility_branch?.view_on_maps || 'Open in Google Maps' }}
    </a>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import 'leaflet/dist/leaflet.css';
import L from 'leaflet';

// Shows where the branch's coordinates land. Display only: the numbers are
// still typed (or found by the AI button) in the boxes above, and the pin
// follows them as they change.
const props = defineProps({
  latitude: { type: [Number, String], default: '' },
  longitude: { type: [Number, String], default: '' },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const parse = (value) => {
  if (value === '' || value === null || value === undefined) return null;
  const n = Number(value);
  return Number.isFinite(n) ? n : null;
};

const lat = computed(() => parse(props.latitude));
const lng = computed(() => parse(props.longitude));
const hasPoint = computed(() => lat.value !== null && lng.value !== null
  && lat.value >= -90 && lat.value <= 90 && lng.value >= -180 && lng.value <= 180);

const mapEl = ref(null);
let map = null;
let marker = null;

const pin = L.divIcon({
  className: '',
  html: `<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="#d97706" stroke="#1f2937" stroke-width="1" style="filter: drop-shadow(0 1px 2px rgba(0,0,0,0.4));"><path d="M12 2C7.6 2 4 5.6 4 10c0 6 8 12 8 12s8-6 8-12c0-4.4-3.6-8-8-8Z"/><circle cx="12" cy="10" r="3" fill="white"/></svg>`,
  iconSize: [30, 30],
  iconAnchor: [15, 30],
});

const render = () => {
  if (!map) return;

  if (!hasPoint.value) {
    if (marker) {
      map.removeLayer(marker);
      marker = null;
    }
    return;
  }

  const point = [lat.value, lng.value];
  if (marker) {
    marker.setLatLng(point);
  } else {
    marker = L.marker(point, { icon: pin }).addTo(map);
  }
  map.setView(point, Math.max(map.getZoom(), 15));
};

onMounted(() => {
  map = L.map(mapEl.value, { zoomControl: true }).setView([26.8206, 30.8025], 6);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    maxZoom: 19,
  }).addTo(map);
  render();
});

onBeforeUnmount(() => {
  if (map) {
    map.remove();
    map = null;
    marker = null;
  }
});

watch([lat, lng], render);
</script>
