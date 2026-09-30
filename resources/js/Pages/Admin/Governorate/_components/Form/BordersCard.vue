<template>
  <div class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
    <div class="py-2 px-6">
      <div class="title-golden leading-none font-semibold flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
          <polygon points="3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21"></polygon>
          <line x1="9" y1="3" x2="9" y2="18"></line>
          <line x1="15" y1="6" x2="15" y2="21"></line>
        </svg>
        {{ tr('border_title', 'Borders on the map') }}
      </div>
      <p class="text-xs text-muted-foreground mt-1">
        {{ tr('border_hint', 'Pick the governorate or one of its cities, then drag a point to move it, click a point to remove it, or click a small circle on an edge to add one. Borders are saved on their own, separately from the form below.') }}
      </p>
    </div>

    <div class="px-6">
      <BorderMap :targets="targets" :loading="loading" :load-error="loadError" :error-extra="{ governorate_id: governorate.id }" />
    </div>
  </div>
</template>

<script setup>
import { ref, shallowRef, computed, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import BorderMap from './BorderMap.vue';

const props = defineProps({
  // The governorate being edited: { id, name, slug }.
  governorate: { type: Object, required: true },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});
const tr = (key, fallback) => t.value.governorate?.[key] || fallback;

// The governorate's own border is saved under the governorate permission this
// page already needs; a city's is `manage cities`, so without it that row only zooms.
const canManageCities = computed(() => {
  const user = page.props.auth?.user;
  return !!user && ((user.roles || []).includes('super_admin') || (user.permissions || []).includes('manage cities'));
});

const targets = shallowRef([]);
const loading = ref(true);
const loadError = ref('');

onMounted(async () => {
  try {
    const { data } = await axios.get(route('admin.governorate.city-borders', props.governorate.id));
    targets.value = [
      { key: 'g', kind: 'governorate', id: props.governorate.id, name: props.governorate.name, geometry: data.governorate_geometry || null, saveUrl: route('admin.governorate.boundary.update', props.governorate.id) },
      ...(data.cities || []).map((c) => ({
        key: `c${c.id}`, kind: 'city', id: c.id, name: c.name, unmarked: !!c.is_unmarked, locked: !canManageCities.value, geometry: c.geometry || null, saveUrl: route('admin.city.boundary.update', c.id),
      })),
    ];
  } catch (error) {
    console.error('Failed to load the borders:', error);
    loadError.value = tr('border_load_failed', 'The borders could not be loaded.');
    try {
      fetch('/api/v1/client-errors', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({
          message: 'Border editor failed to load',
          stack: error?.stack || String(error?.message || error || ''),
          route: window.location.pathname,
          fatal: false,
          extra: { step: 'load-borders', governorate_id: props.governorate.id },
        }),
      }).catch(() => {});
    } catch {
      // Reporting is never worth a second error.
    }
  } finally {
    loading.value = false;
  }
});
</script>
