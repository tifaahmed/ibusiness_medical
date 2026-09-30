<template>
  <div class="flex flex-col lg:flex-row h-[75vh] min-h-[480px] rounded-xl border border-border overflow-hidden bg-card">
    <!-- Left: one row per border that can be edited -->
    <div class="w-full lg:w-[300px] flex-shrink-0 border-b lg:border-b-0 lg:border-r border-border flex flex-col min-h-0 h-1/3 lg:h-full">
      <div v-if="loadError" class="px-3 py-2 text-xs text-red-600 border-b border-border">{{ loadError }}</div>
      <div v-if="loading" class="px-3 py-2 text-xs text-muted-foreground border-b border-border">{{ tr('border_loading', 'Loading borders…') }}</div>
      <div class="flex-1 overflow-y-auto min-h-0">
        <button
          v-for="target in targets"
          :key="target.key"
          type="button"
          class="w-full flex items-center gap-2 px-3 py-2 text-start border-b border-border/50 text-sm hover:bg-accent/50 transition-colors cursor-pointer"
          :class="{ 'bg-accent': editingKey === target.key }"
          @click="startEdit(target.key)"
        >
          <svg v-if="target.kind === 'governorate'" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0"><polygon points="3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21"></polygon></svg>
          <span v-else class="h-3.5 w-3.5 rounded-sm flex-shrink-0 border" :style="{ background: colorOf(target, target.geometry ? 0.55 : 0), borderColor: colorOf(target) }"></span>
          <span class="flex-1 min-w-0 truncate" :class="{ 'font-semibold': target.kind === 'governorate' }">{{ nameOf(target) }}</span>
          <span v-if="target.sub" class="font-mono text-[10px] text-muted-foreground flex-shrink-0">{{ target.sub }}</span>
          <span v-if="target.unmarked" class="text-[10px] px-1.5 py-0.5 rounded bg-slate-500/15 text-slate-600 flex-shrink-0">{{ tr('border_unmarked', 'unmarked') }}</span>
          <span
            class="text-[10px] px-1.5 py-0.5 rounded flex-shrink-0"
            :class="target.geometry ? 'bg-emerald-500/15 text-emerald-700' : 'bg-amber-500/15 text-amber-700'"
          >{{ target.geometry ? tr('border_has', 'border') : tr('border_none', 'no border') }}</span>
        </button>
      </div>
    </div>

    <!-- Right: the map and, while a border is being edited, its toolbar -->
    <div class="flex-1 min-w-0 flex flex-col min-h-0">
      <div v-if="editing" class="flex flex-wrap items-center gap-2 px-3 py-2 border-b border-border bg-muted/30 text-xs">
        <span class="font-semibold">{{ nameOf(editing) }}</span>
        <template v-if="!drawing">
          <span v-if="partCount > 0" class="text-muted-foreground">
            {{ tr('border_part', 'Part') }}
            <button
              v-for="i in partCount"
              :key="i"
              type="button"
              class="ms-1 px-1.5 py-0.5 rounded border cursor-pointer"
              :class="activePart === i - 1 ? 'bg-primary text-primary-foreground border-primary' : 'bg-background border-border'"
              @click="setActive(i - 1)"
            >{{ i }}</button>
            · {{ pointCount }} {{ tr('border_points', 'points') }}
          </span>
          <span v-else class="text-muted-foreground">{{ tr('border_empty', 'No border yet — draw one.') }}</span>
        </template>
        <span v-else class="text-muted-foreground">{{ tr('border_drawing', 'Click the map to place the corners') }} ({{ drawPointCount }})</span>

        <div class="flex-1"></div>

        <template v-if="!drawing">
          <button type="button" class="btn-xs" @click="startDraw">{{ tr('border_draw', 'Draw a part') }}</button>
          <button v-if="partCount > 0" type="button" class="btn-xs" @click="removePart">{{ tr('border_remove_part', 'Remove this part') }}</button>
          <button type="button" class="btn-xs" :disabled="!dirty || saving" @click="resetEdit">{{ tr('border_reset', 'Reset') }}</button>
          <button type="button" class="btn-xs" :disabled="saving" @click="stopEdit">{{ tr('border_close', 'Close') }}</button>
          <button type="button" class="btn-xs btn-primary" :disabled="!dirty || saving" @click="save">
            {{ saving ? tr('border_saving', 'Saving…') : tr('border_save', 'Save border') }}
          </button>
        </template>
        <template v-else>
          <button type="button" class="btn-xs" @click="undoDrawPoint">{{ tr('border_undo', 'Undo point') }}</button>
          <button type="button" class="btn-xs" @click="cancelDraw">{{ tr('border_cancel', 'Cancel') }}</button>
          <button type="button" class="btn-xs btn-primary" :disabled="drawPointCount < 3" @click="finishDraw">{{ tr('border_finish', 'Finish part') }}</button>
        </template>
      </div>
      <div ref="mapEl" class="flex-1 min-h-[280px] z-0" :class="{ 'cursor-crosshair': drawing }"></div>
    </div>
  </div>
</template>

<script setup>
import { ref, shallowRef, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
import { useNotification } from '@/composables/useNotification';

const props = defineProps({
  // The borders that can be edited, one row each:
  // { key, kind: 'governorate' | 'city' | 'area', id, name, sub?, color?, outline?, unmarked?, locked?, geometry, saveUrl }.
  // `outline` draws it as a bare outline that never swallows a click meant for a border inside it.
  targets: { type: Array, default: () => [] },
  // Read-only borders drawn faintly behind them for orientation: { name, geometry }.
  context: { type: Array, default: () => [] },
  // Look and zoom only: no editing (a viewer without the manage permission).
  readonly: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  loadError: { type: String, default: '' },
  // Sent along with any error report, e.g. { city_id }.
  errorExtra: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['saved']);

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});
const tr = (key, fallback) => t.value.governorate?.[key] || fallback;

// The props' targets, replaced (never mutated) on a save so the large GeoJSON
// inside is not wrapped in a deep reactive proxy.
const targets = shallowRef([]);
const saving = ref(false);

const editingKey = ref(null);
const editing = computed(() => targets.value.find((x) => x.key === editingKey.value) || null);
const partCount = ref(0);
const activePart = ref(0);
const pointCount = ref(0);
const dirty = ref(false);
const drawing = ref(false);
const drawPointCount = ref(0);

const mapEl = ref(null);
let map = null;
let baseGroup = null;
let editGroup = null;
let drawGroup = null;
let contextGroup = null;

// The editable copy: an array of polygons, each an array of rings of [lng, lat]
// (ring 0 is the outline, the rest are holes). Plain data, not reactive.
let working = [];
let drawPoints = [];

const localeNameOf = (name) => {
  if (typeof name === 'string') return name;
  if (name && typeof name === 'object') {
    const locale = page.props.locale || 'ar';
    return name[locale] || name.en || name.ar || Object.values(name).find(Boolean) || '';
  }
  return '';
};
const nameOf = (target) => localeNameOf(target?.name);

const cityIndex = (target) => Math.max(0, targets.value.filter((x) => x.kind !== 'governorate').findIndex((x) => x.key === target.key));
const colorOf = (target, alpha = 1) => {
  if (target.color) return target.color.replace('__A__', alpha);
  if (target.kind === 'governorate') return `hsla(215, 25%, 25%, ${alpha})`;
  if (target.unmarked) return `hsla(215, 10%, 50%, ${alpha})`;
  return `hsla(${Math.round((cityIndex(target) * 137.508 + 25) % 360)}, 78%, 40%, ${alpha})`;
};

/**
 * Ship a failure to the client-error endpoint so an editor that will not load
 * or save shows up in /admin/client-error-logs, not only in the admin's face.
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
        extra: { step, ...props.errorExtra, ...extra },
      }),
    }).catch(() => {});
  } catch {
    // Reporting is never worth a second error.
  }
};

const toPolygons = (geometry) => {
  if (!geometry) return [];
  const coordinates = geometry.type === 'Polygon' ? [geometry.coordinates] : geometry.coordinates;
  return JSON.parse(JSON.stringify(coordinates));
};

const toGeometry = (polygons) => {
  if (!polygons.length) return null;
  return polygons.length === 1
    ? { type: 'Polygon', coordinates: polygons[0] }
    : { type: 'MultiPolygon', coordinates: polygons };
};

const latLngsOf = (polygon) => polygon.map((ring) => ring.map(([lng, lat]) => [lat, lng]));

const ringSize = (ring) => {
  let minX = 180, minY = 90, maxX = -180, maxY = -90;
  ring.forEach(([x, y]) => {
    minX = Math.min(minX, x); maxX = Math.max(maxX, x);
    minY = Math.min(minY, y); maxY = Math.max(maxY, y);
  });
  return (maxX - minX) * (maxY - minY);
};

const syncCounts = () => {
  partCount.value = working.length;
  pointCount.value = working[activePart.value] ? working[activePart.value][0].length - 1 : 0;
};

// ---- Drawing the read-only layers -----------------------------------------------

const drawContext = () => {
  if (!map) return;
  contextGroup.clearLayers();
  props.context.forEach((item) => {
    if (!item.geometry) return;
    L.geoJSON(item.geometry, {
      style: { color: '#64748b', weight: 1, opacity: 0.7, fillColor: '#94a3b8', fillOpacity: 0.08, dashArray: '4 3' },
      interactive: false,
    }).addTo(contextGroup);
  });
};

const drawBase = () => {
  if (!map) return;
  baseGroup.clearLayers();
  // Cities first, the governorate outline on top of them so it stays visible.
  [...targets.value].reverse().forEach((target) => {
    if (!target.geometry || target.key === editingKey.value) return;
    const isGovernorate = target.kind === 'governorate' || !!target.outline;
    L.geoJSON(target.geometry, {
      style: {
        color: colorOf(target),
        weight: isGovernorate ? 3 : 1.5,
        opacity: 1,
        fillColor: colorOf(target),
        fillOpacity: isGovernorate ? 0.03 : 0.3,
      },
      // The governorate outline never swallows a click meant for a city under it.
      interactive: !isGovernorate,
      bubblingMouseEvents: false,
    })
      .bindTooltip(nameOf(target), { sticky: true })
      .on('click', () => startEdit(target.key))
      .addTo(baseGroup);
  });
};

// ---- Editing --------------------------------------------------------------------

const vertexIcon = (cls) => L.divIcon({ className: `border-editor-vertex ${cls}`, iconSize: [14, 14] });

const markDirty = () => {
  dirty.value = true;
  syncCounts();
};

const removeVertex = (ring, k) => {
  if (ring.length - 1 <= 3) {
    useNotification().error(tr('border_min_points', 'A border needs at least 3 points.'));
    return;
  }
  if (k === 0) {
    ring.shift();
    ring[ring.length - 1] = [...ring[0]];
  } else {
    ring.splice(k, 1);
  }
  markDirty();
  drawEdit();
};

const drawEdit = () => {
  if (!map) return;
  editGroup.clearLayers();

  working.forEach((polygon, index) => {
    const active = index === activePart.value;
    const shape = L.polygon(latLngsOf(polygon), {
      color: '#dc2626',
      weight: active ? 3 : 2,
      opacity: active ? 1 : 0.6,
      fillColor: '#dc2626',
      fillOpacity: active ? 0.15 : 0.08,
      interactive: !drawing.value,
      bubblingMouseEvents: false,
    })
      .on('click', () => setActive(index))
      .addTo(editGroup);

    if (!active || drawing.value) return;

    const ring = polygon[0];
    const n = ring.length - 1;

    // Corners: drag to move, click to remove.
    for (let k = 0; k < n; k++) {
      L.marker([ring[k][1], ring[k][0]], { draggable: true, icon: vertexIcon('is-corner'), keyboard: false })
        .on('drag', (e) => {
          const { lat, lng } = e.target.getLatLng();
          ring[k] = [Number(lng.toFixed(5)), Number(lat.toFixed(5))];
          if (k === 0) ring[n] = [...ring[0]];
          shape.setLatLngs(latLngsOf(polygon));
        })
        .on('dragend', () => {
          markDirty();
          drawEdit();
        })
        .on('click', () => removeVertex(ring, k))
        .addTo(editGroup);
    }

    // Edge midpoints: click to add a corner there.
    for (let k = 0; k < n; k++) {
      const mid = [(ring[k][0] + ring[k + 1][0]) / 2, (ring[k][1] + ring[k + 1][1]) / 2];
      L.marker([mid[1], mid[0]], { icon: vertexIcon('is-mid'), keyboard: false })
        .on('click', () => {
          ring.splice(k + 1, 0, [Number(mid[0].toFixed(5)), Number(mid[1].toFixed(5))]);
          markDirty();
          drawEdit();
        })
        .addTo(editGroup);
    }
  });

  syncCounts();
};

const setActive = (index) => {
  if (drawing.value) return;
  activePart.value = index;
  drawEdit();
};

const largestPart = () => {
  let best = 0;
  working.forEach((polygon, i) => {
    if (ringSize(polygon[0]) > ringSize(working[best][0])) best = i;
  });
  return best;
};

const startEdit = (key) => {
  // A locked row (the admin may not change that kind of border) only zooms.
  if (props.readonly || targets.value.find((x) => x.key === key)?.locked) {
    const shown = targets.value.find((x) => x.key === key);
    if (shown?.geometry && map) map.fitBounds(L.geoJSON(shown.geometry).getBounds(), { padding: [30, 30] });
    return;
  }
  if (key === editingKey.value) return;
  if (dirty.value) {
    useNotification().info(tr('border_unsaved', 'Save or reset the border you are editing first.'));
    return;
  }

  cancelDraw();
  editingKey.value = key;
  const target = targets.value.find((x) => x.key === key);
  working = toPolygons(target?.geometry);
  activePart.value = working.length ? largestPart() : 0;
  dirty.value = false;
  drawBase();
  drawEdit();

  if (working.length) {
    map.fitBounds(editGroup.getBounds(), { padding: [30, 30] });
  } else {
    // Nothing to edit yet: go straight to drawing.
    startDraw();
  }
};

const stopEdit = () => {
  if (dirty.value && !window.confirm(tr('border_discard', 'Discard the unsaved changes to this border?'))) return;
  cancelDraw();
  editingKey.value = null;
  working = [];
  dirty.value = false;
  syncCounts();
  editGroup?.clearLayers();
  drawBase();
};

const resetEdit = () => {
  cancelDraw();
  working = toPolygons(editing.value?.geometry);
  activePart.value = working.length ? largestPart() : 0;
  dirty.value = false;
  drawEdit();
};

const removePart = () => {
  if (!working.length) return;
  working.splice(activePart.value, 1);
  activePart.value = 0;
  markDirty();
  drawEdit();
};

// ---- Drawing a new part -----------------------------------------------------------

const renderDraw = () => {
  drawGroup.clearLayers();
  const latlngs = drawPoints.map(([lng, lat]) => [lat, lng]);
  if (latlngs.length >= 3) {
    L.polygon(latlngs, { color: '#2563eb', weight: 2, fillOpacity: 0.12, interactive: false }).addTo(drawGroup);
  } else if (latlngs.length === 2) {
    L.polyline(latlngs, { color: '#2563eb', weight: 2, interactive: false }).addTo(drawGroup);
  }
  latlngs.forEach((ll) => L.circleMarker(ll, { radius: 4, color: '#2563eb', fillOpacity: 1, interactive: false }).addTo(drawGroup));
  drawPointCount.value = drawPoints.length;
};

const startDraw = () => {
  drawing.value = true;
  drawPoints = [];
  renderDraw();
  drawEdit();
};

const cancelDraw = () => {
  if (!drawing.value) return;
  drawing.value = false;
  drawPoints = [];
  drawGroup?.clearLayers();
  drawPointCount.value = 0;
  drawEdit();
};

const undoDrawPoint = () => {
  drawPoints.pop();
  renderDraw();
};

const finishDraw = () => {
  if (drawPoints.length < 3) return;
  working.push([[...drawPoints, drawPoints[0]].map(([lng, lat]) => [lng, lat])]);
  activePart.value = working.length - 1;
  drawing.value = false;
  drawPoints = [];
  drawGroup.clearLayers();
  drawPointCount.value = 0;
  markDirty();
  drawEdit();
};

// ---- Saving -----------------------------------------------------------------------

const save = async () => {
  const target = editing.value;
  if (!target || saving.value) return;

  const geometry = toGeometry(working);
  if (geometry === null && !window.confirm(tr('border_clear_confirm', 'This removes the whole border. Continue?'))) return;

  saving.value = true;
  try {
    const { data } = await axios.put(target.saveUrl, { geometry });

    targets.value = targets.value.map((x) => (x.key === target.key ? { ...x, geometry: data.geometry } : x));
    dirty.value = false;
    emit('saved', { key: target.key, kind: target.kind, id: target.id, geometry: data.geometry });
    useNotification().success(tr('border_saved', 'Border saved.'));
    stopEdit();
  } catch (error) {
    console.error('Failed to save a border:', error);
    const validation = error?.response?.data?.errors?.geometry?.[0];
    useNotification().error(validation || error?.response?.data?.message || tr('border_save_failed', 'The border could not be saved.'));
    reportClientError('Border save failed', error, 'save-border', { kind: target.kind, target_id: target.id, status: error?.response?.status });
  } finally {
    saving.value = false;
  }
};

// ---- Following the props ---------------------------------------------------------

const fitAll = () => {
  if (!map) return;
  const shapes = targets.value.filter((x) => x.geometry).map((x) => L.geoJSON(x.geometry));
  if (!shapes.length) return;
  map.fitBounds(L.featureGroup(shapes).getBounds(), { padding: [20, 20] });
};

let fitted = false;
watch(() => props.targets, (next) => {
  targets.value = next;
  // The row being edited was deleted from under the editor: drop the edit.
  if (editingKey.value && !next.some((x) => x.key === editingKey.value)) {
    cancelDraw();
    editingKey.value = null;
    working = [];
    dirty.value = false;
    syncCounts();
    editGroup?.clearLayers();
  }
  drawBase();
  if (!fitted && next.some((x) => x.geometry)) {
    fitted = true;
    fitAll();
  }
}, { immediate: true });

watch(() => props.context, drawContext);

defineExpose({ startEdit });

onMounted(() => {
  try {
    map = L.map(mapEl.value, { zoomControl: true }).setView([27, 30], 6);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
      maxZoom: 19,
    }).addTo(map);
    contextGroup = L.featureGroup().addTo(map);
    baseGroup = L.featureGroup().addTo(map);
    editGroup = L.featureGroup().addTo(map);
    drawGroup = L.featureGroup().addTo(map);
    map.on('click', (event) => {
      if (!drawing.value) return;
      drawPoints.push([Number(event.latlng.lng.toFixed(5)), Number(event.latlng.lat.toFixed(5))]);
      renderDraw();
    });
  } catch (error) {
    console.error('Failed to start the border map:', error);
    reportClientError('Border editor map failed to start', error, 'init-map');
    return;
  }
  drawContext();
  drawBase();
  if (!fitted && targets.value.some((x) => x.geometry)) {
    fitted = true;
    fitAll();
  }
});

onBeforeUnmount(() => {
  if (map) {
    map.remove();
    map = null;
  }
});
</script>

<style>
/* Leaflet builds these markers outside the component's DOM, so they cannot be scoped. */
.border-editor-vertex {
  border-radius: 50%;
  box-sizing: border-box;
}
.border-editor-vertex.is-corner {
  background: #fff;
  border: 2px solid #dc2626;
  cursor: move;
}
.border-editor-vertex.is-mid {
  background: rgba(255, 255, 255, 0.7);
  border: 1px solid #dc2626;
  transform: scale(0.6);
  cursor: copy;
}
.btn-xs {
  padding: 2px 8px;
  border-radius: 6px;
  border: 1px solid var(--border, #d4d4d8);
  background: var(--background, #fff);
  font-size: 12px;
  cursor: pointer;
}
.btn-xs:disabled {
  opacity: 0.5;
  pointer-events: none;
}
.btn-xs.btn-primary {
  background: var(--primary, #2563eb);
  color: var(--primary-foreground, #fff);
  border-color: var(--primary, #2563eb);
}
</style>
