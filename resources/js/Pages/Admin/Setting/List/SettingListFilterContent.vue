<template>
  <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 sm:gap-4">
    <div class="flex items-center gap-2 w-full sm:w-auto">
      <input
        type="text"
        :value="localFilters.search"
        @input="handleSearchChange"
        :placeholder="t.setting?.search_placeholder || 'Search by key, name or value…'"
        class="w-full sm:w-72 py-2 px-3 border border-border bg-transparent text-foreground placeholder:text-muted-foreground/60 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-ring/50 focus:border-ring transition-colors"
      />
    </div>
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
      <Select
        :model-value="localFilters.value_type"
        :options="valueTypes.map(type => ({ value: type.value ?? type, label: `${ typeLabel(type) }` }))"
        :placeholder="t.setting?.all_types || 'All types'"
        @update:model-value="handleTypeChange"
      />
      <div class="w-full sm:w-52">
        <Select
          :model-value="localFilters.sort"
          :options="sortOptions"
          @update:model-value="handleSortChange"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import Select from '@/Components/ui/Select.vue';
import { router, usePage } from '@inertiajs/vue3';

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const props = defineProps({
  initialFilters: {
    type: Object,
    default: () => ({
      search: '',
      value_type: '',
      sort: 'key',
    }),
  },
  valueTypes: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(['filter-change']);

const localFilters = ref({ ...props.initialFilters });

const typeLabel = (type) => t.value.setting?.types?.[type] || type;

const handleSearchChange = (e) => {
  // The shared picker emits the value; a native select would send an event.
  const eValue = e?.target?.value ?? e;
  localFilters.value.search = eValue;
  debouncedSearch();
};

let searchTimeout = null;
const debouncedSearch = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    applyFilters();
  }, 400);
};

const handleTypeChange = (e) => {
  // The shared picker emits the value; a native select would send an event.
  const eValue = e?.target?.value ?? e;
  localFilters.value.value_type = eValue;
  applyFilters();
};

const sortOptions = computed(() => [
  { value: 'key', label: t.value.setting?.sort_key || 'Sort by key' },
  { value: 'newest', label: t.value.common?.newest || 'Newest first' },
  { value: 'oldest', label: t.value.common?.oldest || 'Oldest first' },
]);

const handleSortChange = (e) => {
  // The shared picker emits the value; a native select would send an event.
  const eValue = e?.target?.value ?? e;
  localFilters.value.sort = eValue;
  applyFilters();
};

const applyFilters = () => {
  emit('filter-change', { ...localFilters.value });
  const params = new URLSearchParams();
  Object.entries(localFilters.value).forEach(([key, value]) => {
    if (value !== '' && value !== null && value !== undefined) {
      params.set(key, value);
    }
  });
  params.set('page', '1');
  router.visit(`${route('admin.setting.list')}?${params.toString()}`, {
    preserveState: true,
    preserveScroll: false,
  });
};

watch(() => props.initialFilters, (newVal) => {
  localFilters.value = { ...newVal };
}, { deep: true });
</script>
