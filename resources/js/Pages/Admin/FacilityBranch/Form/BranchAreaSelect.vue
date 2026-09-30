<template>
  <FormSelect
    :model-value="modelValue"
    :label="t.area?.optional_label || 'Area (optional)'"
    :options="options"
    :error="error"
    :disabled="!cityId || loading"
    :placeholder="placeholder"
    :hint="hint"
    @update:model-value="$emit('update:modelValue', $event)"
  />
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { FormSelect } from '@/Components/form';
import { bilingualLabel } from '@/lib/lookupNames';

/**
 * The branch's OPTIONAL area: one of the chosen city's areas, or nothing.
 * Only the chosen city's areas are asked for (a few dozen), when the city
 * changes — the 5,000+ areas are never shipped with the page. An area that is
 * not in the new city's list is cleared, so the form can never submit a
 * mismatched pair (the server refuses one too).
 */
const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  cityId: { type: [String, Number], default: '' },
  error: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});
const locale = computed(() => page.props.locale || 'ar');

// Kept for the life of the page: flipping between two cities asks once each.
const cache = new Map();
const areas = ref([]);
const loading = ref(false);
const failed = ref(false);

const options = computed(() => areas.value.map((area) => ({
  value: area.id,
  label: bilingualLabel(area.name, locale.value),
})));

const placeholder = computed(() => {
  if (!props.cityId) return t.value.area?.pick_city_first || 'Select a city first';
  if (loading.value) return t.value.area?.loading || 'Loading areas…';
  return t.value.area?.none_optional || 'No area';
});

const hint = computed(() => {
  if (failed.value) return t.value.area?.load_failed || 'The areas of this city could not be loaded.';
  if (props.cityId && !loading.value && areas.value.length === 0) return t.value.area?.city_has_none || 'This city has no areas stored.';
  return '';
});

const reportClientError = (message, error, extra = {}) => {
  try {
    fetch('/api/v1/client-errors', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        message,
        stack: error?.stack || String(error?.message || error || ''),
        route: window.location.pathname,
        fatal: false,
        extra: { step: 'load-branch-areas', ...extra },
      }),
    }).catch(() => {});
  } catch {
    // Reporting is never worth a second error.
  }
};

const load = async (cityId) => {
  failed.value = false;
  if (!cityId) {
    areas.value = [];
    if (props.modelValue) emit('update:modelValue', '');
    return;
  }

  try {
    if (!cache.has(cityId)) {
      loading.value = true;
      const { data } = await axios.get(route('admin.facility.branch.city-areas', cityId));
      cache.set(cityId, data.areas || []);
    }
    // The admin may have picked another city while this one was loading.
    if (String(props.cityId) !== String(cityId)) return;

    areas.value = cache.get(cityId);
    if (props.modelValue && !areas.value.some((a) => String(a.id) === String(props.modelValue))) {
      emit('update:modelValue', '');
    }
  } catch (error) {
    console.error('Failed to load the areas of a city:', error);
    failed.value = true;
    areas.value = [];
    reportClientError('Branch form: city areas failed to load', error, { city_id: cityId });
  } finally {
    if (String(props.cityId) === String(cityId)) loading.value = false;
  }
};

watch(() => props.cityId, (cityId) => load(cityId), { immediate: true });
</script>
