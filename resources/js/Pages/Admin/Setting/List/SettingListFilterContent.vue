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
      <select
        :value="localFilters.value_type"
        @change="handleTypeChange"
        class="border-input focus-visible:border-ring focus-visible:ring-ring/50 rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] w-full sm:w-auto cursor-pointer"
      >
        <option value="">{{ t.setting?.all_types || 'All types' }}</option>
        <option v-for="type in valueTypes" :key="type" :value="type">{{ typeLabel(type) }}</option>
      </select>
      <select
        :value="localFilters.sort"
        @change="handleSortChange"
        class="border-input focus-visible:border-ring focus-visible:ring-ring/50 rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] w-full sm:w-auto cursor-pointer"
      >
        <option value="key">{{ t.setting?.sort_key || 'Sort by key' }}</option>
        <option value="newest">{{ t.common?.newest || 'Newest first' }}</option>
        <option value="oldest">{{ t.common?.oldest || 'Oldest first' }}</option>
      </select>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
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
  localFilters.value.search = e.target.value;
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
  localFilters.value.value_type = e.target.value;
  applyFilters();
};

const handleSortChange = (e) => {
  localFilters.value.sort = e.target.value;
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
