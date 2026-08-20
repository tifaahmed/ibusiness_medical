<template>
  <div class="w-full min-w-0 overflow-x-hidden space-y-2">
    <div class="flex flex-col sm:flex-row sm:items-end gap-2 sm:gap-3 w-full">
      <!-- Search -->
      <div class="flex-1 min-w-0">
        <label
          data-slot="label"
          class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 w-full ltr:justify-start rtl:justify-end ltr:text-left rtl:text-right mb-1"
          for="search"
        >
          {{ t.common?.search || 'Search' }}
        </label>
        <div class="relative">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="24"
            height="24"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            class="lucide lucide-search absolute left-2 sm:left-2.5 md:left-3 top-1/2 -translate-y-1/2 h-3 w-3 sm:h-3.5 sm:w-3.5 md:h-4 md:w-4 text-muted-foreground pointer-events-none z-10"
          >
            <circle cx="11" cy="11" r="8"></circle>
            <path d="m21 21-4.3-4.3"></path>
          </svg>
          <input
            data-slot="input"
            v-model="filters.search"
            @input="handleSearch"
            class="file:text-foreground placeholder:text-gray-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border border-border text-foreground flex h-7 sm:h-8 md:h-9 w-full min-w-0 max-w-full rounded-md bg-transparent px-2 sm:px-2.5 md:px-3 py-1 text-xs sm:text-sm md:text-base shadow-xs transition-all outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm [color-scheme:dark] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:bg-secondary/10 data-[filled=true]:bg-secondary/5 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive pl-7 sm:pl-8 md:pl-9 box-border"
            id="search"
            :placeholder="t.common?.search || 'Search...'"
          />
        </div>
      </div>

      <!-- Facility Type Filter -->
      <div class="w-full sm:w-48">
        <label
          data-slot="label"
          class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 w-full ltr:justify-start rtl:justify-end ltr:text-left rtl:text-right mb-1"
          for="facility_type_id"
        >
          {{ t.facility_type?.label || 'Facility Type' }}
        </label>
        <Select
          :key="`facility-type-${locale}`"
          id="facility_type_id"
          v-model="filters.facility_type_id"
          :options="facilityTypeOptions"
          :placeholder="t.facility_type?.all || 'All Facility Types'"
          @change="handleFilterChange"
        />
      </div>

      <!-- Sales Filter -->
      <div class="w-full sm:w-48">
        <label
          data-slot="label"
          class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 w-full ltr:justify-start rtl:justify-end ltr:text-left rtl:text-right mb-1"
          for="sales_id"
        >
          {{ t.sales?.name || 'Sales' }}
        </label>
        <SearchableSelect
          :key="`sales-${locale}`"
          id="sales_id"
          v-model="filters.sales_id"
          :options="salesSelectOptions"
          :placeholder="t.facility?.all_sales || 'All Sales'"
          @change="handleFilterChange"
        />
      </div>

      <!-- Governorate Filter -->
      <div class="w-full sm:w-48">
        <label
          data-slot="label"
          class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 w-full ltr:justify-start rtl:justify-end ltr:text-left rtl:text-right mb-1"
          for="governorate_id"
        >
          {{ t.governorate?.label || 'Governorate' }}
        </label>
        <SearchableSelect
          :key="`governorate-${locale}`"
          id="governorate_id"
          v-model="filters.governorate_id"
          :options="governorateOptions"
          :placeholder="t.governorate?.all || 'All Governorates'"
          @change="handleGovernorateChange"
        />
      </div>

      <!-- City Filter -->
      <div class="w-full sm:w-48">
        <label
          data-slot="label"
          class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 w-full ltr:justify-start rtl:justify-end ltr:text-left rtl:text-right mb-1"
          for="city_id"
        >
          {{ t.city?.label || 'City' }}
        </label>
        <SearchableSelect
          :key="`city-${locale}-${filters.governorate_id || 'all'}`"
          id="city_id"
          v-model="filters.city_id"
          :options="filteredCityOptions"
          :placeholder="t.city?.all || 'All Cities'"
          @change="handleFilterChange"
        />
      </div>

    </div>

    <!-- Reset Filter - Only show if there's an active filter -->
    <button
      v-if="hasActiveFilters"
      data-slot="button"
      @click="handleReset"
      class="cursor-pointer justify-center whitespace-nowrap text-xs font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive bg-destructive text-white shadow-xs hover:bg-destructive/90 focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40 dark:bg-destructive/60 h-7 sm:h-8 rounded-md px-2 sm:px-3 has-[>svg]:px-2 inline-flex items-center gap-1.5 sm:gap-2"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        width="24"
        height="24"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
        class="lucide lucide-x h-3 w-3 sm:h-3.5 sm:w-3.5 md:h-4 md:w-4"
      >
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
import Select from '@/Components/ui/Select.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

const props = defineProps({
  initialFilters: {
    type: Object,
    default: () => ({
      search: '',
      facility_type_id: '',
      sales_id: '',
      governorate_id: '',
      city_id: '',
    })
  },
  facilityTypes: {
    type: Array,
    default: () => []
  },
  // Already shaped as { value, label } by the controller.
  salesOptions: {
    type: Array,
    default: () => []
  },
  governorates: {
    type: Array,
    default: () => []
  },
  cities: {
    type: Array,
    default: () => []
  }
});

const page = usePage();
// Make locale reactive by accessing it from page.props directly in computed
const locale = computed(() => {
  return page.props.locale || 'ar';
});
const t = computed(() => page.props.translations?.admin || {});

const getTranslatedName = (name, currentLocale = null) => {
  const loc = currentLocale !== null ? currentLocale : locale.value;
  if (typeof name === 'string') return name;
  if (typeof name === 'object' && name !== null) {
    // Try current locale first, then fallback to ar, then en, then first available
    return name[loc] || name['ar'] || name['en'] || Object.values(name)[0] || '';
  }
  return '';
};

// Convert facility types to options for select - make it reactive to locale changes
const facilityTypeOptions = computed(() => {
  // Access locale directly in computed to ensure reactivity
  const currentLocale = page.props.locale || 'ar';
  return props.facilityTypes.map(type => ({
    value: type.id,
    label: getTranslatedName(type.name, currentLocale)
  }));
});

const salesSelectOptions = computed(() =>
  props.salesOptions.map(option => ({
    value: option.value,
    label: getTranslatedName(option.label),
  }))
);

const governorateOptions = computed(() =>
  props.governorates.map(g => ({
    value: g.id,
    label: getTranslatedName(g.name),
  }))
);

const filteredCityOptions = computed(() => {
  const govId = filters.value.governorate_id;
  const cities = govId
    ? props.cities.filter(c => String(c.governorate_id) === String(govId))
    : props.cities;
  return cities.map(c => ({
    value: c.id,
    label: getTranslatedName(c.name),
  }));
});

const emit = defineEmits(['filter-change']);

const getInitialFilters = () => {
  if (props.initialFilters) {
    return {
      search: props.initialFilters.search || '',
      facility_type_id: props.initialFilters.facility_type_id || props.initialFilters.facility_type_id === 0 ? '0' : '',
      sales_id: props.initialFilters.sales_id || '',
      governorate_id: props.initialFilters.governorate_id || '',
      city_id: props.initialFilters.city_id || '',
    };
  }
  if (typeof window !== 'undefined') {
    const urlParams = new URLSearchParams(window.location.search);
    return {
      search: urlParams.get('search') || '',
      facility_type_id: urlParams.get('facility_type_id') || '',
      sales_id: urlParams.get('sales_id') || '',
      governorate_id: urlParams.get('governorate_id') || '',
      city_id: urlParams.get('city_id') || '',
    };
  }
  return { search: '', facility_type_id: '', sales_id: '', governorate_id: '', city_id: '' };
};

const filters = ref(getInitialFilters());

// Computed property to check if any filter is active
const hasActiveFilters = computed(() => {
  return !!(filters.value.search || filters.value.facility_type_id || filters.value.sales_id || filters.value.governorate_id || filters.value.city_id);
});

let searchTimeout = null;
const debouncedSearch = (value) => {
  if (searchTimeout) {
    clearTimeout(searchTimeout);
  }
  searchTimeout = setTimeout(() => {
    applyFilters({ ...filters.value, search: value });
  }, 300);
};

const handleSearch = (event) => {
  filters.value.search = event.target.value;
  debouncedSearch(event.target.value);
};

const handleReset = () => {
  filters.value = { search: '', facility_type_id: '', sales_id: '', governorate_id: '', city_id: '' };
  applyFilters();
};

const handleGovernorateChange = () => {
  filters.value.city_id = '';
  setTimeout(() => {
    applyFilters();
  }, 0);
};

const handleFilterChange = () => {
  // Small delay to ensure v-model is updated
  setTimeout(() => {
    applyFilters();
  }, 0);
};

const applyFilters = (filterValues = null) => {
  const currentFilters = filterValues || filters.value;
  
  const params = {};
  if (currentFilters.search && currentFilters.search.trim()) {
    params.search = currentFilters.search;
  }
  if (currentFilters.facility_type_id && currentFilters.facility_type_id !== '') {
    params.facility_type_id = currentFilters.facility_type_id;
  }
  if (currentFilters.sales_id && currentFilters.sales_id !== '') {
    params.sales_id = currentFilters.sales_id;
  }
  if (currentFilters.governorate_id && currentFilters.governorate_id !== '') {
    params.governorate_id = currentFilters.governorate_id;
  }
  if (currentFilters.city_id && currentFilters.city_id !== '') {
    params.city_id = currentFilters.city_id;
  }
  
  emit('filter-change', currentFilters);
  
  router.get(route('admin.facility.list'), params, {
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

// Watch for locale changes to ensure options update
watch(() => locale.value, () => {
  // Force reactivity update when locale changes
  // The computed properties should automatically update, but this ensures it
}, { immediate: false });
</script>


