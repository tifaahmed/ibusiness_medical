<template>
  <div class="w-full min-w-0 overflow-x-hidden space-y-2">
    <div class="flex flex-col sm:flex-row sm:items-end gap-2 sm:gap-3 w-full">
      <div class="flex-1 min-w-0">
        <label
          data-slot="label"
          class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full ltr:justify-start rtl:justify-end ltr:text-left rtl:text-right mb-1"
          for="search"
        >
          {{ t.common?.search || 'Search' }}
        </label>
        <div class="relative">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="absolute left-2 sm:left-2.5 md:left-3 top-1/2 -translate-y-1/2 h-3 w-3 sm:h-3.5 sm:w-3.5 md:h-4 md:w-4 text-muted-foreground pointer-events-none z-10">
            <circle cx="11" cy="11" r="8"></circle>
            <path d="m21 21-4.3-4.3"></path>
          </svg>
          <input
            data-slot="input"
            v-model="filters.search"
            @input="handleSearch"
            class="placeholder:text-gray-foreground dark:bg-input/30 border border-border text-foreground flex h-7 sm:h-8 md:h-9 w-full min-w-0 max-w-full rounded-md bg-transparent px-2 sm:px-2.5 md:px-3 py-1 text-xs sm:text-sm md:text-base shadow-xs transition-all outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] pl-7 sm:pl-8 md:pl-9 box-border"
            id="search"
            :placeholder="t.partner?.search_placeholder || 'Search by title...'"
          />
        </div>
      </div>
    </div>

    <button
      v-if="hasActiveFilters"
      data-slot="button"
      @click="handleReset"
      class="cursor-pointer justify-center whitespace-nowrap text-xs font-medium transition-all bg-destructive text-white shadow-xs hover:bg-destructive/90 h-7 sm:h-8 rounded-md px-2 sm:px-3 has-[>svg]:px-2 inline-flex items-center gap-1.5 sm:gap-2"
    >
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3 w-3 sm:h-3.5 sm:w-3.5 md:h-4 md:w-4">
        <path d="M18 6 6 18"></path>
        <path d="m6 6 12 12"></path>
      </svg>
      <span class="hidden sm:inline">{{ t.common?.clear || 'Clear' }}</span>
    </button>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps({
  initialFilters: {
    type: Object,
    default: () => ({ search: '' }),
  },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const emit = defineEmits(['filter-change']);

const getInitialFilters = () => ({
  search: props.initialFilters?.search || '',
});

const filters = ref(getInitialFilters());

const hasActiveFilters = computed(() => !!filters.value.search);

let searchTimeout = null;
const debouncedSearch = (value) => {
  if (searchTimeout) clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    applyFilters({ ...filters.value, search: value });
  }, 300);
};

const handleSearch = (event) => {
  filters.value.search = event.target.value;
  debouncedSearch(event.target.value);
};

const handleReset = () => {
  filters.value = { search: '' };
  applyFilters();
};

const applyFilters = (filterValues = null) => {
  const currentFilters = filterValues || filters.value;
  const params = {};
  if (currentFilters.search && currentFilters.search.trim()) params.search = currentFilters.search;
  emit('filter-change', currentFilters);
  router.get(route('admin.partner.list'), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

watch(() => props.initialFilters, (newFilters) => {
  if (newFilters) filters.value = { ...filters.value, ...newFilters };
}, { deep: true });
</script>
