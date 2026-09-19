<template>
  <div class="flex flex-col lg:flex-row h-[70vh] lg:h-[calc(100vh-260px)] min-h-[420px] gap-0 rounded-xl border border-border overflow-hidden bg-card">
    <!-- Left: what was touched, then one row per governorate — or, once one is selected, one row per city of it. -->
    <div class="w-full lg:w-[300px] xl:w-[340px] flex-shrink-0 border-b lg:border-b-0 lg:border-r border-border flex flex-col min-h-0 h-1/2 lg:h-full">
      <div class="px-3 py-2 border-b border-border flex-shrink-0 bg-muted/30 space-y-1.5">
        <div class="flex items-center justify-between gap-2">
          <button
            v-if="selected"
            type="button"
            class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline cursor-pointer"
            @click="clearSelection"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
            {{ t.governorate?.map_back || 'All governorates' }}
          </button>
          <span v-else class="text-xs sm:text-sm font-medium text-foreground">{{ t.governorate?.map_legend || 'Governorates' }} ({{ governorates.length }})</span>
          <svg v-if="loading || citiesLoading" class="animate-spin h-3.5 w-3.5 text-muted-foreground" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 12a9 9 0 1 1-6.219-8.56" />
          </svg>
        </div>
        <div v-if="loadError" class="text-xs text-red-600">{{ loadError }}</div>
        <div v-else-if="touched === null" class="text-[11px] text-muted-foreground">{{ t.governorate?.map_hint || 'Tap or click anywhere inside a border to see which governorate you are in.' }}</div>
        <div v-else-if="touched.length === 0" class="text-xs font-medium text-muted-foreground">{{ t.governorate?.map_outside || 'This spot is outside every governorate border.' }}</div>
        <div v-else class="flex items-center gap-2 rounded-md border px-2 py-1.5 text-xs" :style="{ borderColor: colorOf(touched[0].id), background: colorOf(touched[0].id, 0.12) }">
          <span class="h-3 w-3 rounded-full flex-shrink-0" :style="{ background: colorOf(touched[0].id) }"></span>
          <span class="text-muted-foreground">{{ t.governorate?.map_you_are_in || 'You are in' }}</span>
          <span class="font-semibold text-foreground truncate">{{ nameOf(touched[0]) }}</span>
        </div>
        <div v-if="selected && touchedCities.length" class="flex items-center gap-2 rounded-md border px-2 py-1.5 text-xs" :style="{ borderColor: cityColor(touchedCities[0].id), background: cityColor(touchedCities[0].id, 0.12) }">
          <span class="h-3 w-3 rounded-full flex-shrink-0" :style="{ background: cityColor(touchedCities[0].id) }"></span>
          <span class="text-muted-foreground">{{ t.governorate?.map_city_in || 'City' }}</span>
          <span class="font-semibold text-foreground truncate">{{ nameOf(touchedCities[0]) }}</span>
        </div>
      </div>

      <!-- Selected governorate: its cities. -->
      <div v-if="selected" class="flex-1 min-h-0 overflow-y-auto">
        <div class="flex items-center justify-between gap-2 px-3 py-2 border-b border-border/50 bg-muted/20">
          <span class="flex items-center gap-2 min-w-0">
            <span class="h-3.5 w-3.5 rounded-sm flex-shrink-0 border" :style="{ background: colorOf(selected.id, 0.55), borderColor: colorOf(selected.id) }"></span>
            <span class="text-sm font-semibold text-foreground truncate">{{ nameOf(selected) }}</span>
          </span>
          <span class="text-[11px] text-muted-foreground flex-shrink-0">{{ t.governorate?.map_cities || 'Cities' }} ({{ cities.length }})</span>
        </div>
        <div v-if="citiesError" class="px-3 py-2 text-xs text-red-600">{{ citiesError }}</div>
        <div v-else-if="!citiesLoading && cities.length === 0" class="px-3 py-4 text-xs text-muted-foreground flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0"><path d="M3 21h18"></path><path d="M5 21V7l8-4v18"></path><path d="M19 21V11l-6-4"></path></svg>
          {{ t.governorate?.map_no_cities || 'No cities are stored for this governorate yet.' }}
        </div>
        <div v-if="citiesWithoutBorder > 0" class="px-3 py-1.5 text-[11px] border-b border-border/50 text-amber-700 bg-amber-500/10">
          {{ (t.governorate?.map_cities_no_border || ':count without a border').replace(':count', citiesWithoutBorder) }}
        </div>
        <div
          v-for="c in cities"
          :key="c.id"
          class="flex items-center gap-2 px-3 py-1.5 border-b border-border/50 cursor-pointer transition-colors"
          :class="isTouchedCity(c.id) ? 'bg-muted' : 'hover:bg-muted/40'"
          @mouseenter="setHoveredCity(c.id)"
          @mouseleave="setHoveredCity(null)"
          @click="focusCity(c)"
        >
          <span class="h-3.5 w-3.5 rounded-sm flex-shrink-0 border" :style="{ background: cityColor(c.id, c.geometry ? 0.55 : 0), borderColor: cityColor(c.id) }"></span>
          <span class="text-xs sm:text-sm text-foreground truncate flex-1">{{ nameOf(c) }}</span>
          <span v-if="!c.geometry" class="text-[10px] rounded-full border border-border px-1.5 py-0.5 text-muted-foreground flex-shrink-0">{{ t.governorate?.map_no_border || 'No border stored' }}</span>
        </div>
      </div>

      <!-- Nothing selected: every governorate in its own colour. -->
      <div v-else class="flex-1 min-h-0 overflow-y-auto">
        <div
          v-for="g in governorates"
          :key="g.id"
          class="flex items-center gap-2 px-3 py-1.5 border-b border-border/50 cursor-pointer transition-colors"
          :class="isTouched(g.id) ? 'bg-muted' : 'hover:bg-muted/40'"
          @mouseenter="setHovered(g.id)"
          @mouseleave="setHovered(null)"
          @click="selectGovernorate(g)"
        >
          <span class="h-3.5 w-3.5 rounded-sm flex-shrink-0 border" :style="{ background: colorOf(g.id, g.geometry ? 0.55 : 0), borderColor: colorOf(g.id) }"></span>
          <span class="text-xs sm:text-sm text-foreground truncate flex-1">{{ nameOf(g) }}</span>
          <span v-if="!g.geometry" class="text-[10px] rounded-full border border-border px-1.5 py-0.5 text-muted-foreground flex-shrink-0">{{ t.governorate?.map_no_border || 'No border stored' }}</span>
        </div>
      </div>
    </div>

    <!-- Right: the map itself. -->
    <div class="relative flex-1 min-h-0 h-1/2 lg:h-full">
      <div ref="mapEl" class="absolute inset-0 z-0"></div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { usePage } from '@inertiajs/vue3';
import 'leaflet/dist/leaflet.css';
import L from 'leaflet';

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const governorates = ref([]);
const loading = ref(false);
const loadError = ref('');
// null = nothing touched yet; [] = touched outside every border; otherwise the
// governorates whose border holds the touched point (more than one when two
// stored borders overlap along their edge).
const touched = ref(null);
const hoveredId = ref(null);

// The governorate whose cities are on the map, and those cities.
const selected = ref(null);
const cities = ref([]);
const citiesLoading = ref(false);
const citiesError = ref('');
const touchedCities = ref([]);
const hoveredCityId = ref(null);
const citiesCache = new Map();

const mapEl = ref(null);
let map = null;
const layersById = new Map();
const cityLayersById = new Map();

const citiesWithoutBorder = computed(() => cities.value.filter((c) => !c.geometry).length);

const nameOf = (g) => {
  const name = g?.name;
  if (typeof name === 'string') return name;
  if (name && typeof name === 'object') {
    const locale = page.props.locale || 'ar';
    return name[locale] || name.ar || name.en || Object.values(name)[0] || '';
  }
  return g?.slug || '';
};

// One hue per governorate, spread by the golden angle so neighbours whose ids
// sit next to each other still land far apart on the colour wheel.
const hueOf = (id) => Math.round((id * 137.508) % 360);
const colorOf = (id, alpha = 1) => `hsla(${hueOf(id)}, 68%, 46%, ${alpha})`;

// Cities take their colour from their place in the selected governorate's list,
// not from their id: ids run into the hundreds, and it is the neighbours in
// THIS list that have to look different from each other. Deeper and darker than
// the governorate palette so a city reads against its governorate's tint.
const cityIndex = (id) => Math.max(0, cities.value.findIndex((c) => c.id === id));
const cityColor = (id, alpha = 1) => `hsla(${Math.round((cityIndex(id) * 137.508 + 25) % 360)}, 78%, 40%, ${alpha})`;

const styleFor = (id, state = 'idle') => {
  // With a governorate selected the others step back and the selected one is
  // only an outline, so its cities are what the eye lands on.
  if (selected.value) {
    if (id === selected.value.id) return { color: colorOf(id), weight: 3, opacity: 1, fillColor: colorOf(id), fillOpacity: 0.06 };
    return { color: colorOf(id), weight: 1, opacity: 0.45, fillColor: colorOf(id), fillOpacity: 0.07 };
  }
  return {
    color: colorOf(id),
    weight: state === 'touched' ? 4 : state === 'hover' ? 3 : 2,
    opacity: 1,
    fillColor: colorOf(id),
    fillOpacity: state === 'touched' ? 0.6 : state === 'hover' ? 0.5 : 0.32,
  };
};

const cityStyleFor = (id, state = 'idle') => ({
  color: cityColor(id),
  weight: state === 'touched' ? 4 : state === 'hover' ? 3 : 1.5,
  opacity: 1,
  fillColor: cityColor(id),
  fillOpacity: state === 'touched' ? 0.7 : state === 'hover' ? 0.6 : 0.4,
});

const isTouched = (id) => !!touched.value?.some((g) => g.id === id);
const isTouchedCity = (id) => touchedCities.value.some((c) => c.id === id);

const restyle = () => {
  layersById.forEach((layer, id) => {
    const state = isTouched(id) ? 'touched' : id === hoveredId.value ? 'hover' : 'idle';
    layer.setStyle(styleFor(id, state));
    if (state !== 'idle' && !selected.value) layer.bringToFront();
  });
};

const restyleCities = () => {
  cityLayersById.forEach((layer, id) => {
    const state = isTouchedCity(id) ? 'touched' : id === hoveredCityId.value ? 'hover' : 'idle';
    layer.setStyle(cityStyleFor(id, state));
    if (state !== 'idle') layer.bringToFront();
  });
};

const setHovered = (id) => {
  hoveredId.value = id;
  restyle();
};

const setHoveredCity = (id) => {
  hoveredCityId.value = id;
  restyleCities();
};

const escapeHtml = (value) => String(value).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

// Ray casting on one ring. GeoJSON positions are [lng, lat].
const inRing = (lng, lat, ring) => {
  let inside = false;
  for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
    const [xi, yi] = ring[i];
    const [xj, yj] = ring[j];
    if ((yi > lat) !== (yj > lat) && lng < ((xj - xi) * (lat - yi)) / (yj - yi) + xi) inside = !inside;
  }
  return inside;
};

// Inside the outer ring and outside every hole.
const inPolygon = (lng, lat, rings) => inRing(lng, lat, rings[0]) && !rings.slice(1).some((hole) => inRing(lng, lat, hole));

const contains = (geometry, lng, lat) => {
  if (!geometry) return false;
  if (geometry.type === 'Polygon') return inPolygon(lng, lat, geometry.coordinates);
  if (geometry.type === 'MultiPolygon') return geometry.coordinates.some((polygon) => inPolygon(lng, lat, polygon));
  return false;
};

// Bounding-box area: only used to order cities, biggest first, so a small
// district drawn later still shows on top of the larger one that holds it.
const roughSize = (geometry) => {
  if (!geometry) return 0;
  let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
  const walk = (c) => {
    if (typeof c[0] === 'number') {
      minX = Math.min(minX, c[0]); maxX = Math.max(maxX, c[0]);
      minY = Math.min(minY, c[1]); maxY = Math.max(maxY, c[1]);
    } else {
      c.forEach(walk);
    }
  };
  walk(geometry.coordinates);
  return (maxX - minX) * (maxY - minY);
};

/**
 * Ship a failure to the client-error endpoint so a map that will not load
 * shows up in /admin/client-error-logs, not only in the admin's face.
 */
const reportClientError = (message, error, step, extra = {}) => {
  try {
    fetch('/api/v1/client-errors', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        message,
        stack: error?.stack || String(error?.message || error || ''),
        route: window.location.pathname,
        fatal: false,
        extra: { step, ...extra },
      }),
    }).catch(() => {});
  } catch {
    // Reporting is never worth a second error.
  }
};

const popupHtml = (matches, cityMatches = []) => {
  if (matches.length === 0) {
    return `<div style="font-size:12px;">${escapeHtml(t.value.governorate?.map_outside || 'This spot is outside every governorate border.')}</div>`;
  }
  const [first, ...others] = matches;
  const label = escapeHtml(t.value.governorate?.map_you_are_in || 'You are in');
  const open = escapeHtml(t.value.governorate?.map_open || 'Open governorate');
  const also = escapeHtml(t.value.governorate?.map_overlap || 'Also inside');
  const cityLabel = escapeHtml(t.value.governorate?.map_city_in || 'City');
  const city = cityMatches[0];
  return `<div style="min-width:150px;">
    <div style="font-size:11px;color:#6b7280;">${label}</div>
    <div style="font-size:15px;font-weight:600;color:${colorOf(first.id)};">${escapeHtml(nameOf(first))}</div>
    ${others.length ? `<div style="font-size:11px;color:#6b7280;margin-top:2px;">${also}: ${others.map((g) => escapeHtml(nameOf(g))).join('، ')}</div>` : ''}
    ${city ? `<div style="font-size:11px;color:#6b7280;margin-top:6px;">${cityLabel}</div><div style="font-size:14px;font-weight:600;color:${cityColor(city.id)};">${escapeHtml(nameOf(city))}</div>` : ''}
    <a href="${route('admin.governorate.show', first.slug)}" style="display:inline-block;margin-top:6px;font-size:12px;">${open}</a>
  </div>`;
};

const clearCityLayers = () => {
  cityLayersById.forEach((layer) => map?.removeLayer(layer));
  cityLayersById.clear();
};

const drawCities = () => {
  clearCityLayers();
  if (!map) return;
  // Biggest first, so the small ones land on top and stay visible.
  [...cities.value]
    .filter((c) => c.geometry)
    .sort((a, b) => roughSize(b.geometry) - roughSize(a.geometry))
    .forEach((c) => {
      const layer = L.geoJSON(c.geometry, { interactive: false, style: cityStyleFor(c.id) }).addTo(map);
      cityLayersById.set(c.id, layer);
    });
};

const fitSelected = () => {
  const layer = selected.value && layersById.get(selected.value.id);
  if (map && layer) map.fitBounds(layer.getBounds(), { padding: [30, 30] });
};

const loadCities = async (governorate) => {
  if (citiesCache.has(governorate.id)) return citiesCache.get(governorate.id);
  const { data } = await axios.get(route('admin.governorate.city-borders', governorate.id));
  const list = data.cities || [];
  citiesCache.set(governorate.id, list);
  return list;
};

const selectGovernorate = async (g) => {
  if (!map) return;
  selected.value = g;
  touched.value = [g];
  touchedCities.value = [];
  cities.value = [];
  citiesError.value = '';
  clearCityLayers();
  restyle();
  fitSelected();

  citiesLoading.value = true;
  try {
    const list = await loadCities(g);
    // The admin may have picked another governorate while this one was loading.
    if (selected.value?.id !== g.id) return;
    cities.value = list;
    drawCities();
  } catch (error) {
    console.error('Failed to load governorate cities:', error);
    citiesError.value = t.value.governorate?.map_cities_load_failed || 'The cities of this governorate could not be loaded.';
    reportClientError('Governorate cities map failed to load', error, 'load-city-borders', { governorate_id: g.id });
  } finally {
    if (selected.value?.id === g.id) citiesLoading.value = false;
  }
};

const clearSelection = () => {
  selected.value = null;
  cities.value = [];
  touched.value = null;
  touchedCities.value = [];
  citiesError.value = '';
  citiesLoading.value = false;
  clearCityLayers();
  restyle();
  fitAll();
};

const focusCity = (c) => {
  const layer = cityLayersById.get(c.id);
  touchedCities.value = [c];
  restyleCities();
  if (map && layer) map.fitBounds(layer.getBounds(), { padding: [30, 30] });
};

const handleMapClick = (event) => {
  const { lat, lng } = event.latlng;
  const matches = governorates.value.filter((g) => contains(g.geometry, lng, lat));

  // A touch inside the selected governorate answers with its cities; a touch in
  // another one moves the selection there.
  if (selected.value && matches.some((g) => g.id === selected.value.id)) {
    touched.value = [selected.value, ...matches.filter((g) => g.id !== selected.value.id)];
    // Smallest first: the district, not the big city that holds it.
    touchedCities.value = cities.value
      .filter((c) => contains(c.geometry, lng, lat))
      .sort((a, b) => roughSize(a.geometry) - roughSize(b.geometry));
    restyle();
    restyleCities();
  } else if (matches.length) {
    touchedCities.value = [];
    selectGovernorate(matches[0]);
    touched.value = matches;
  } else {
    touched.value = [];
    touchedCities.value = [];
    restyle();
    restyleCities();
  }

  L.popup({ closeButton: true }).setLatLng(event.latlng).setContent(popupHtml(touched.value, touchedCities.value)).openOn(map);
};

const fitAll = () => {
  const group = [...layersById.values()];
  if (map && group.length) map.fitBounds(L.featureGroup(group).getBounds(), { padding: [20, 20] });
};

const draw = () => {
  if (!map) return;
  governorates.value.forEach((g) => {
    if (!g.geometry) return;
    // Not interactive: the whole map answers a touch by hit-testing itself, so
    // two borders that overlap are both reported instead of the top one hiding the other.
    layersById.set(g.id, L.geoJSON(g.geometry, { interactive: false, style: styleFor(g.id) }).addTo(map));
  });
  fitAll();
};

const load = async () => {
  loading.value = true;
  loadError.value = '';
  try {
    const { data } = await axios.get(route('admin.governorate.borders'));
    governorates.value = data.governorates || [];
    draw();
  } catch (error) {
    console.error('Failed to load governorate borders:', error);
    loadError.value = t.value.governorate?.map_load_failed || 'The governorate borders could not be loaded.';
    reportClientError('Governorate borders map failed to load', error, 'load-borders');
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  try {
    map = L.map(mapEl.value, { zoomControl: true }).setView([27, 30], 6);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
      maxZoom: 19,
    }).addTo(map);
    map.on('click', handleMapClick);
  } catch (error) {
    console.error('Failed to start the governorate map:', error);
    loadError.value = t.value.governorate?.map_load_failed || 'The governorate borders could not be loaded.';
    reportClientError('Governorate map failed to start', error, 'init-map');
    return;
  }
  load();
});

onBeforeUnmount(() => {
  if (map) {
    map.remove();
    map = null;
  }
  layersById.clear();
  cityLayersById.clear();
});
</script>
