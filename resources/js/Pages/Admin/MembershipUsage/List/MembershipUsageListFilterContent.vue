<template>
  <div class="w-full min-w-0 overflow-x-hidden space-y-2">
    <div class="flex flex-col sm:flex-row sm:items-end gap-2 sm:gap-3 w-full">
      <!-- Search -->
      <div class="flex-1 min-w-0">
        <label data-slot="label" class="flex items-center gap-1.5 text-xs leading-none font-medium select-none mb-1" for="search">
          {{ page.props.translations?.admin?.common?.search || 'Search' }}
        </label>
        <div class="relative">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="absolute left-2 sm:left-2.5 top-1/2 -translate-y-1/2 h-3 w-3 sm:h-3.5 sm:w-3.5 text-muted-foreground pointer-events-none z-10">
            <circle cx="11" cy="11" r="8"></circle>
            <path d="m21 21-4.3-4.3"></path>
          </svg>
          <input
            data-slot="input"
            v-model="filters.search"
            @input="handleSearch"
            class="placeholder:text-gray-foreground dark:bg-input/30 border border-border text-foreground flex h-7 sm:h-8 md:h-9 w-full min-w-0 max-w-full rounded-md bg-transparent px-2 sm:px-2.5 py-1 text-xs sm:text-sm shadow-xs transition-all outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] pl-7 sm:pl-8 box-border"
            id="search"
            :placeholder="t.search_placeholder || 'Search by membership number or name...'"
          />
        </div>
      </div>

      <!-- Facility Filter -->
      <div class="w-full sm:w-48">
        <label data-slot="label" class="flex items-center text-xs leading-none font-medium select-none mb-1" for="facility_id">
          {{ t.facility || 'Facility' }}
        </label>
        <Select
          id="facility_id"
          v-model="filters.facility_id"
          :options="facilityOptions"
          :placeholder="t.all_facilities || 'All Facilities'"
          @change="handleFilterChange"
        />
      </div>

      <!-- Facility Type Filter -->
      <div class="w-full sm:w-48">
        <label data-slot="label" class="flex items-center text-xs leading-none font-medium select-none mb-1" for="facility_type_id">
          {{ t.facility_type || 'Facility Type' }}
        </label>
        <Select
          id="facility_type_id"
          v-model="filters.facility_type_id"
          :options="facilityTypeOptions"
          :placeholder="t.all_types || 'All Types'"
          @change="handleFilterChange"
        />
      </div>
    </div>

    <button
      v-if="hasActiveFilters"
      data-slot="button"
      @click="handleReset"
      class="cursor-pointer justify-center whitespace-nowrap text-xs font-medium transition-all bg-destructive text-white shadow-xs hover:bg-destructive/90 h-7 sm:h-8 rounded-md px-2 sm:px-3 inline-flex items-center gap-1.5"
    >
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3 w-3 sm:h-3.5 sm:w-3.5">
        <path d="M18 6 6 18"></path>
        <path d="m6 6 12 12"></path>
      </svg>
      <span class="hidden sm:inline">Clear</span>
    </button>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Select from '@/Components/ui/Select.vue';

const props = defineProps({
  initialFilters: {
    type: Object,
    default: () => ({ search: '', facility_id: '', facility_type_id: '' }),
  },
  facilities: { type: Array, default: () => [] },
  facilityTypes: { type: Array, default: () => [] },
});

const emit = defineEmits(['filter-change']);

const page = usePage();
const t = computed(() => page.props.translations?.admin?.membership_usage || {});

const facilityOptions = computed(() =>
  props.facilities.map(f => ({ value: String(f.id), label: f.name }))
);

const facilityTypeOptions = computed(() =>
  props.facilityTypes.map(t => ({ value: String(t.id), label: t.name }))
);

const getInitialFilters = () => ({
  search: props.initialFilters?.search || '',
  facility_id: props.initialFilters?.facility_id ?? '',
  facility_type_id: props.initialFilters?.facility_type_id ?? '',
});

const filters = ref(getInitialFilters());

const hasActiveFilters = computed(() =>
  !!(filters.value.search || filters.value.facility_id !== '' || filters.value.facility_type_id !== '')
);

let searchTimeout = null;

const handleSearch = (event) => {
  filters.value.search = event.target.value;
  if (searchTimeout) clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => applyFilters(), 300);
};

const handleFilterChange = () => {
  setTimeout(() => applyFilters(), 0);
};

const handleReset = () => {
  filters.value = { search: '', facility_id: '', facility_type_id: '' };
  applyFilters();
};

const applyFilters = () => {
  const params = {};
  if (filters.value.search?.trim()) params.search = filters.value.search;
  if (filters.value.facility_id !== '' && filters.value.facility_id !== null) params.facility_id = filters.value.facility_id;
  if (filters.value.facility_type_id !== '' && filters.value.facility_type_id !== null) params.facility_type_id = filters.value.facility_type_id;

  emit('filter-change', filters.value);
  router.get(route('admin.membership-usage.list'), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

watch(() => props.initialFilters, (newFilters) => {
  if (newFilters) filters.value = { ...filters.value, ...newFilters };
}, { deep: true });
</script>
