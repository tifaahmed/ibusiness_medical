<template>
  <div class="space-y-4">
    <div class="flex flex-col lg:flex-row gap-4 items-start lg:items-end">
      <div class="w-full lg:flex-1 lg:max-w-md xl:max-w-lg space-y-2">
        <label class="text-sm leading-none font-medium" for="product-search">Search</label>
        <div class="relative">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground">
            <circle cx="11" cy="11" r="8"></circle>
            <path d="m21 21-4.3-4.3"></path>
          </svg>
          <input
            v-model="filters.search"
            @input="handleSearch"
            class="flex h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm pl-9"
            id="product-search"
            placeholder="Search products by name or slug..."
          />
        </div>
      </div>
    </div>

    <div class="flex flex-col sm:flex-row gap-2">
      <button
        @click="handleReset"
        class="cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all bg-destructive text-white shadow-xs hover:bg-destructive/90 h-8 rounded-md px-3 flex items-center gap-2 ml-auto"
      >
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
          <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path>
          <path d="M3 3v5h5"></path>
        </svg>
        Reset Filters
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  initialFilters: {
    type: Object,
    default: () => ({
      search: ''
    })
  }
});

const getInitialFilters = () => {
  if (props.initialFilters && props.initialFilters.search) {
    return { search: props.initialFilters.search || '' };
  }
  if (typeof window !== 'undefined') {
    const urlParams = new URLSearchParams(window.location.search);
    return { search: urlParams.get('search') || '' };
  }
  return { search: '' };
};

const filters = ref(getInitialFilters());

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
  if (currentFilters.search) params.search = currentFilters.search;

  router.get(route('admin.product.list'), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true
  });
};

watch(() => props.initialFilters, (newFilters) => {
  if (newFilters) {
    filters.value = { ...filters.value, ...newFilters };
  }
}, { deep: true });
</script>
