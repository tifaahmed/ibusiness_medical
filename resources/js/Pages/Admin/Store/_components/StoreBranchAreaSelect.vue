<template>
  <div class="space-y-1">
    <label class="text-xs font-medium">Area</label>
    <Select
      :model-value="selectedId"
      :options="options"
      :disabled="!cityId || loading"
      :placeholder="placeholder"
      @update:modelValue="pick"
    />
    <p v-if="legacyText" class="text-[11px] text-muted-foreground">Saved area: {{ legacyText }} (pick one above to replace it)</p>
    <p v-if="hint" class="text-[11px] text-muted-foreground">{{ hint }}</p>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import Select from '@/Components/ui/Select.vue';

/**
 * A store branch keeps its area as translated text, not an id. This picker
 * lists the chosen city's areas and writes the picked area's {ar, en} name into
 * the branch, so nothing about the stored shape changes. Only the chosen city's
 * areas are fetched (a few dozen) — never all 5,000+.
 */
const props = defineProps({
  modelValue: { type: Object, default: () => ({ ar: '', en: '' }) },
  cityId: { type: [String, Number], default: '' },
});
const emit = defineEmits(['update:modelValue']);

const cache = new Map();
const areas = ref([]);
const loading = ref(false);
const failed = ref(false);

const label = (name) => (typeof name === 'string' ? name : (name?.ar || name?.en || ''));
const options = computed(() => areas.value.map(a => ({ value: a.id, label: label(a.name) })));

const selectedId = computed(() => {
  const ar = (props.modelValue?.ar || '').trim();
  const en = (props.modelValue?.en || '').trim();
  const match = areas.value.find(a => (ar && a.name?.ar === ar) || (en && a.name?.en === en));
  return match ? match.id : '';
});

// Text saved before this picker existed that is not one of the city's areas.
const legacyText = computed(() => {
  if (selectedId.value || loading.value) return '';
  return (props.modelValue?.ar || props.modelValue?.en || '').trim();
});

const placeholder = computed(() => {
  if (!props.cityId) return 'Select a city first';
  if (loading.value) return 'Loading areas…';
  return 'No area';
});

const hint = computed(() => {
  if (failed.value) return 'The areas of this city could not be loaded.';
  if (props.cityId && !loading.value && !areas.value.length) return 'This city has no areas stored.';
  return '';
});

const pick = (id) => {
  const area = areas.value.find(a => String(a.id) === String(id));
  emit('update:modelValue', area
    ? { ar: area.name?.ar || '', en: area.name?.en || '' }
    : { ar: '', en: '' });
};

const load = async (cityId) => {
  failed.value = false;
  if (!cityId) { areas.value = []; return; }
  try {
    if (!cache.has(cityId)) {
      loading.value = true;
      const { data } = await axios.get(route('admin.facility.branch.city-areas', cityId));
      cache.set(cityId, data.areas || []);
    }
    if (String(props.cityId) === String(cityId)) areas.value = cache.get(cityId);
  } catch (error) {
    failed.value = true;
    areas.value = [];
    axios.post('/api/v1/client-errors', {
      message: 'Store form: city areas failed to load',
      stack: error?.stack,
      fatal: false,
      route: window.location.pathname,
      extra: { feature: 'store-form-areas', city_id: cityId, status: error?.response?.status },
    }).catch(() => {});
  } finally {
    if (String(props.cityId) === String(cityId)) loading.value = false;
  }
};

watch(() => props.cityId, load, { immediate: true });
</script>
