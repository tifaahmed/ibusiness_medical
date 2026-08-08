<template>
  <div class="space-y-4">
    <div class="flex flex-col lg:flex-row gap-4 items-start lg:items-end">
      <div class="w-full lg:flex-1 lg:max-w-md xl:max-w-lg space-y-2">
        <label class="flex items-center gap-2 text-sm leading-none font-medium select-none" for="company-search">Search</label>
        <div class="relative">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground">
            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
          </svg>
          <input
            data-slot="input"
            v-model="filters.search"
            @input="handleSearch"
            class="file:text-foreground placeholder:text-gray-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-all outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] pl-9"
            id="company-search"
            placeholder="Search companies by name..."
          />
        </div>
      </div>
    </div>
    <div class="flex flex-col sm:flex-row gap-2">
      <button
        @click="handleReset"
        class="cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 bg-destructive text-white shadow-xs hover:bg-destructive/90 h-8 rounded-md px-3 flex items-center gap-2 ml-auto"
      >
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
          <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>
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
  initialFilters: { type: Object, default: () => ({ search: '' }) }
});
const emit = defineEmits(['filter-change']);

const filters = ref({ search: props.initialFilters?.search || '' });

let searchTimeout = null;
const handleSearch = (event) => {
  filters.value.search = event.target.value;
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => applyFilters(), 300);
};

const handleReset = () => {
  filters.value = { search: '' };
  applyFilters();
};

const applyFilters = () => {
  const params = {};
  if (filters.value.search) params.search = filters.value.search;
  emit('filter-change', filters.value);
  router.get(route('admin.company.list'), params, { preserveState: true, preserveScroll: true, replace: true });
};

watch(() => props.initialFilters, (v) => { if (v) filters.value = { ...filters.value, ...v }; }, { deep: true });
</script>
