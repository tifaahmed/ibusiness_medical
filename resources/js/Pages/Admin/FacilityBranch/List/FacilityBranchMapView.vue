<template>
  <div class="flex flex-col lg:flex-row h-[70vh] lg:h-[calc(100vh-320px)] min-h-[420px] gap-0 rounded-xl border border-border overflow-hidden bg-card">
    <!-- Left: one row per branch, in the order the map data came back. -->
    <div class="w-full lg:w-[360px] xl:w-[400px] flex-shrink-0 border-b lg:border-b-0 lg:border-r border-border flex flex-col min-h-0 h-1/2 lg:h-full">
      <div class="flex items-center justify-between gap-2 px-3 py-2 border-b border-border flex-shrink-0 bg-muted/30">
        <span class="text-xs sm:text-sm font-medium text-foreground">
          {{ (t.common?.showing_results || 'Showing :from to :to of :total results').replace(':from', meta.from || 0).replace(':to', meta.to || 0).replace(':total', meta.total || 0) }}
        </span>
        <svg v-if="loading" class="animate-spin h-3.5 w-3.5 text-muted-foreground" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 12a9 9 0 1 1-6.219-8.56" />
        </svg>
      </div>

      <div class="flex-1 min-h-0 overflow-y-auto">
        <div
          v-for="branch in branches"
          :key="branch.id"
          class="flex items-start gap-2 px-3 py-2 border-b border-border/50 cursor-pointer transition-colors"
          :class="hoveredId === branch.id ? 'bg-red-500/10' : 'hover:bg-muted/40'"
          @mouseenter="setHovered(branch.id)"
          @mouseleave="setHovered(null)"
          @click="focusBranch(branch)"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" :stroke="hoveredId === branch.id ? '#dc2626' : 'currentColor'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0 mt-0.5 text-muted-foreground">
            <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path>
            <circle cx="12" cy="10" r="3"></circle>
          </svg>
          <div class="min-w-0 flex-1">
            <div class="text-xs sm:text-sm font-medium text-foreground truncate">{{ getTranslatedName(branch.name) || '-' }}</div>
            <div class="text-[11px] text-muted-foreground truncate">{{ getTranslatedName(branch.facility?.name) }}</div>
          </div>
          <Link
            v-if="canWrite"
            :href="editUrl(branch)"
            class="flex-shrink-0 inline-flex items-center justify-center h-7 w-7 rounded-md border border-border bg-background hover:bg-muted text-foreground"
            :title="t.common?.edit || 'Edit'"
            @click.stop
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
          </Link>
        </div>

        <div v-if="!loading && branches.length === 0" class="p-4 text-center text-xs text-muted-foreground">
          {{ t.facility_branch?.map_no_branches || 'No branches with GPS coordinates match these filters.' }}
        </div>
      </div>

      <div class="flex-shrink-0 border-t border-border px-3 py-2 flex items-center justify-between gap-2 bg-muted/30">
        <PerPageSelect
          :model-value="perPage"
          :choices="[10, 15, 25, 50, 100, 200, 300, 500, 1000]"
          @update:model-value="handlePerPageChange"
          width-class="w-[72px]"
        />
        <div class="flex items-center gap-1">
          <button
            type="button"
            class="inline-flex items-center justify-center h-7 w-7 rounded-md border border-border bg-background text-xs font-medium disabled:opacity-40 disabled:cursor-not-allowed hover:bg-muted cursor-pointer"
            :disabled="currentPage <= 1 || loading"
            @click="goToPage(currentPage - 1)"
          >
            ‹
          </button>
          <span class="text-xs text-muted-foreground px-1 whitespace-nowrap">{{ currentPage }} / {{ meta.last_page || 1 }}</span>
          <button
            type="button"
            class="inline-flex items-center justify-center h-7 w-7 rounded-md border border-border bg-background text-xs font-medium disabled:opacity-40 disabled:cursor-not-allowed hover:bg-muted cursor-pointer"
            :disabled="currentPage >= (meta.last_page || 1) || loading"
            @click="goToPage(currentPage + 1)"
          >
            ›
          </button>
        </div>
      </div>
    </div>

    <!-- Right: the map. -->
    <div class="relative flex-1 min-h-0 h-1/2 lg:h-full">
      <div ref="mapEl" class="absolute inset-0"></div>

      <button
        type="button"
        @click="locateMe"
        class="absolute top-3 ltr:right-3 rtl:left-3 z-[1000] inline-flex items-center gap-1.5 rounded-md border border-border bg-background/95 px-2.5 py-1.5 text-xs font-medium shadow-md hover:bg-muted cursor-pointer"
        :title="t.facility_branch?.map_locate_me || 'Show my location'"
      >
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"></circle>
          <circle cx="12" cy="12" r="3"></circle>
          <line x1="12" y1="2" x2="12" y2="4"></line>
          <line x1="12" y1="20" x2="12" y2="22"></line>
          <line x1="2" y1="12" x2="4" y2="12"></line>
          <line x1="20" y1="12" x2="22" y2="12"></line>
        </svg>
        <span class="hidden sm:inline">{{ t.facility_branch?.map_locate_me || 'My location' }}</span>
      </button>

      <div v-if="locateError" class="absolute bottom-3 ltr:left-3 rtl:right-3 z-[1000] rounded-md border border-red-400/60 bg-red-500/90 px-2.5 py-1.5 text-xs font-medium text-white shadow-md max-w-[80%]">
        {{ locateError }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { usePermissions } from '@/composables/usePermissions';
import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
import PerPageSelect from '@/Components/ui/PerPageSelect.vue';

const props = defineProps({
  filters: {
    type: Object,
    default: () => ({}),
  },
});

const page = usePage();
const t = ref(page.props.translations?.admin || {});
watch(() => page.props.translations, (v) => { t.value = v?.admin || {}; });

// Editing is a write: hidden from read-only accounts, refused by the route either way.
const { canManage } = usePermissions();
const canWrite = computed(() => canManage('manage facility branches', 'manage own facility branches'));
const editUrl = (branch) => route('admin.facility-branch.edit', branch.slug);

const getTranslatedName = (name) => {
  if (typeof name === 'string') return name;
  if (typeof name === 'object' && name !== null) {
    const locale = page.props.locale || 'ar';
    return name[locale] || name['ar'] || name['en'] || Object.values(name)[0] || '';
  }
  return '';
};

const branches = ref([]);
const loading = ref(false);
const hoveredId = ref(null);
const locateError = ref('');
const currentPage = ref(1);
const perPage = ref(15);
const meta = ref({});

const mapEl = ref(null);
let map = null;
let myLocationMarker = null;
const markersById = new Map();

// The outlines of the place filters: governorate in amber, city in blue, both
// drawn when both are chosen. Not interactive, so clicks and hovers still
// reach the pins underneath them.
const boundaries = {
  governorate: { layer: null, routeName: 'admin.facility-branch.governorate-boundary', filterKey: 'governorate_id', style: { color: '#d97706', weight: 3, opacity: 0.95, fillColor: '#f59e0b', fillOpacity: 0.08 }, cache: new Map() },
  city: { layer: null, routeName: 'admin.facility-branch.city-boundary', filterKey: 'city_id', style: { color: '#2563eb', weight: 3, opacity: 0.95, fillColor: '#3b82f6', fillOpacity: 0.12 }, cache: new Map() },
};

// A plain map pin, drawn as SVG so it needs no image asset. Red once hovered,
// the same brand-amber pin everywhere else.
const pinIcon = (color) => L.divIcon({
  className: '',
  html: `<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="${color}" stroke="#1f2937" stroke-width="1" style="filter: drop-shadow(0 1px 2px rgba(0,0,0,0.4));"><path d="M12 2C7.6 2 4 5.6 4 10c0 6 8 12 8 12s8-6 8-12c0-4.4-3.6-8-8-8Z"/><circle cx="12" cy="10" r="3" fill="white"/></svg>`,
  iconSize: [30, 30],
  iconAnchor: [15, 30],
  popupAnchor: [0, -28],
});
const defaultIcon = pinIcon('#d97706');
const hoveredIcon = pinIcon('#dc2626');

// The admin's own position: a pulsing dot, deliberately not a pin, so it
// never reads as just another branch.
const myLocationIcon = L.divIcon({
  className: '',
  html: `<div style="position:relative;width:20px;height:20px;">
    <div style="position:absolute;inset:-10px;border-radius:9999px;background:rgba(37,99,235,0.25);animation:branchmap-ping 1.8s cubic-bezier(0,0,0.2,1) infinite;"></div>
    <div style="position:absolute;inset:0;border-radius:9999px;background:#2563eb;border:2px solid white;box-shadow:0 0 0 1px rgba(0,0,0,0.2);"></div>
  </div>`,
  iconSize: [20, 20],
  iconAnchor: [10, 10],
});

const setHovered = (id) => {
  const previous = hoveredId.value;
  hoveredId.value = id;

  if (previous !== null && markersById.has(previous)) {
    markersById.get(previous).setIcon(defaultIcon);
  }
  if (id !== null && markersById.has(id)) {
    const marker = markersById.get(id);
    marker.setIcon(hoveredIcon);
    marker.setZIndexOffset(1000);
  }
};

const focusBranch = (branch) => {
  if (!map) return;
  map.setView([branch.latitude, branch.longitude], Math.max(map.getZoom(), 15), { animate: true });
  markersById.get(branch.id)?.openPopup();
};

const popupHtml = (branch) => {
  const name = getTranslatedName(branch.name) || '-';
  const facility = getTranslatedName(branch.facility?.name);
  const address = getTranslatedName(branch.address);
  const link = branch.google_location_url
    ? `<a href="${branch.google_location_url}" target="_blank" rel="noopener noreferrer" style="color:#2563eb;text-decoration:underline;">${t.value.facility_branch?.view_on_maps || 'Open in Google Maps'}</a>`
    : '';
  const edit = canWrite.value
    ? `<a href="${editUrl(branch)}" data-branch-edit style="display:inline-block;padding:3px 10px;border:1px solid #d1d5db;border-radius:6px;color:#111827;text-decoration:none;font-weight:500;">${t.value.common?.edit || 'Edit'}</a>`
    : '';
  return `<div style="min-width:180px;font-size:12px;line-height:1.4;">
    <div style="font-weight:600;">${name}</div>
    ${facility ? `<div style="color:#6b7280;">${facility}</div>` : ''}
    ${address ? `<div style="margin-top:4px;">${address}</div>` : ''}
    ${link ? `<div style="margin-top:4px;">${link}</div>` : ''}
    ${edit ? `<div style="margin-top:8px;">${edit}</div>` : ''}
  </div>`;
};

const renderMarkers = () => {
  if (!map) return;

  markersById.forEach((marker) => map.removeLayer(marker));
  markersById.clear();

  const points = [];

  branches.value.forEach((branch) => {
    const marker = L.marker([branch.latitude, branch.longitude], { icon: defaultIcon });
    marker.bindPopup(popupHtml(branch));
    marker.on('mouseover', () => setHovered(branch.id));
    marker.on('mouseout', () => setHovered(null));
    marker.addTo(map);
    markersById.set(branch.id, marker);
    points.push([branch.latitude, branch.longitude]);
  });

  // With a place outlined, frame its border rather than just the pins.
  if (!fitBoundaries() && points.length > 0) {
    map.fitBounds(points, { padding: [30, 30], maxZoom: 14 });
  }
};

// Frame the smallest outline drawn (the city, else the governorate).
// Returns false when nothing is outlined.
const fitBoundaries = () => {
  const layer = boundaries.city.layer || boundaries.governorate.layer;
  if (!layer) return false;
  map.fitBounds(layer.getBounds(), { padding: [20, 20] });
  return true;
};

const clearBoundary = (kind) => {
  const entry = boundaries[kind];
  if (entry.layer) {
    map.removeLayer(entry.layer);
    entry.layer = null;
  }
};

const drawBoundary = async (kind, id) => {
  if (!map) return;
  const entry = boundaries[kind];
  clearBoundary(kind);
  if (!id) return;

  try {
    if (!entry.cache.has(id)) {
      const { data } = await axios.get(route(entry.routeName, id));
      entry.cache.set(id, data.geometry || null);
    }
    const geometry = entry.cache.get(id);

    // The filter may have changed while the request was in flight.
    if (!map || String(props.filters?.[entry.filterKey] || '') !== String(id)) return;
    clearBoundary(kind);
    if (!geometry) return;

    entry.layer = L.geoJSON(geometry, { interactive: false, style: entry.style }).addTo(map);
    // The city sits on top of its governorate, never under it.
    if (kind === 'governorate') entry.layer.bringToBack();
    fitBoundaries();
  } catch (error) {
    console.error(`Failed to load ${kind} boundary:`, error);
  }
};

const filterParams = () => {
  const f = props.filters || {};
  const params = { page: currentPage.value, per_page: perPage.value };
  if (f.search) params.search = f.search;
  if (f.facility_id) params.facility_id = f.facility_id;
  if (f.governorate_id) params.governorate_id = f.governorate_id;
  if (f.city_id) params.city_id = f.city_id;
  if (f.facility_type_id) params.facility_type_id = f.facility_type_id;
  if (f.no_governorate) params.no_governorate = 1;
  if (f.no_city) params.no_city = 1;
  if (f.no_gps) params.no_gps = 1;
  return params;
};

const fetchBranches = async () => {
  loading.value = true;
  try {
    const { data } = await axios.get(route('admin.facility-branch.map-data'), { params: filterParams() });
    branches.value = data.data || [];
    meta.value = data.meta || {};
    await nextTick();
    renderMarkers();
  } catch (error) {
    console.error('Failed to load branch map data:', error);
  } finally {
    loading.value = false;
  }
};

const goToPage = (target) => {
  const lastPage = meta.value.last_page || 1;
  currentPage.value = Math.min(Math.max(target, 1), lastPage);
  fetchBranches();
};

const handlePerPageChange = (value) => {
  perPage.value = Number(value);
  currentPage.value = 1;
  fetchBranches();
};

const locateMe = () => {
  locateError.value = '';

  if (!('geolocation' in navigator)) {
    locateError.value = t.value.facility_branch?.map_locate_unsupported || 'This browser cannot share your location.';
    return;
  }

  navigator.geolocation.getCurrentPosition(
    (position) => {
      const { latitude, longitude } = position.coords;
      if (myLocationMarker) {
        map.removeLayer(myLocationMarker);
      }
      myLocationMarker = L.marker([latitude, longitude], { icon: myLocationIcon, zIndexOffset: 2000 }).addTo(map);
      map.setView([latitude, longitude], Math.max(map.getZoom(), 13), { animate: true });
    },
    (error) => {
      locateError.value = error.code === error.PERMISSION_DENIED
        ? (t.value.facility_branch?.map_locate_denied || 'Location permission was denied.')
        : (t.value.facility_branch?.map_locate_failed || 'Could not determine your location.');
    },
    { enableHighAccuracy: true, timeout: 10000 }
  );
};

// Popup HTML is plain markup, so its Edit link is routed through Inertia here
// instead of reloading the whole page.
const handlePopupClick = (event) => {
  const link = event.target.closest?.('a[data-branch-edit]');
  if (!link || event.metaKey || event.ctrlKey || event.shiftKey) return;
  event.preventDefault();
  router.visit(link.getAttribute('href'));
};

onMounted(() => {
  mapEl.value.addEventListener('click', handlePopupClick);
  map = L.map(mapEl.value, { zoomControl: true }).setView([26.8206, 30.8025], 6);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    maxZoom: 19,
  }).addTo(map);

  fetchBranches();
  drawBoundary('governorate', props.filters?.governorate_id);
  drawBoundary('city', props.filters?.city_id);
  // Best-effort, silent: a denial here should not interrupt the map.
  if ('geolocation' in navigator) {
    navigator.geolocation.getCurrentPosition(
      (position) => {
        const { latitude, longitude } = position.coords;
        myLocationMarker = L.marker([latitude, longitude], { icon: myLocationIcon, zIndexOffset: 2000 }).addTo(map);
      },
      () => {},
      { enableHighAccuracy: false, timeout: 8000 }
    );
  }
});

onBeforeUnmount(() => {
  mapEl.value?.removeEventListener('click', handlePopupClick);
  if (map) {
    map.remove();
    map = null;
  }
});

watch(() => props.filters, () => {
  currentPage.value = 1;
  fetchBranches();
}, { deep: true });

watch(() => props.filters?.governorate_id, (id) => drawBoundary('governorate', id));
watch(() => props.filters?.city_id, (id) => drawBoundary('city', id));
</script>

<style>
@keyframes branchmap-ping {
  0% { transform: scale(0.6); opacity: 1; }
  75%, 100% { transform: scale(1.8); opacity: 0; }
}
</style>
