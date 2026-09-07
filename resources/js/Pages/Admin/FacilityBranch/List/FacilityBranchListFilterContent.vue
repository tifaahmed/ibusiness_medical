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
            class="file:text-foreground placeholder:text-white selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border border-border text-foreground flex h-7 sm:h-8 md:h-9 w-full min-w-0 max-w-full rounded-md bg-transparent px-2 sm:px-2.5 md:px-3 py-1 text-xs sm:text-sm md:text-base shadow-xs transition-all outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm [color-scheme:dark] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:bg-secondary/10 data-[filled=true]:bg-secondary/5 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive pl-7 sm:pl-8 md:pl-9 box-border"
            id="search"
            :placeholder="t.facility_branch?.search_placeholder || 'Search by name, phone...'"
          />
        </div>
      </div>

      <!-- Facility Filter -->
      <div class="w-full sm:w-48">
        <label
          data-slot="label"
          class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 w-full ltr:justify-start rtl:justify-end ltr:text-left rtl:text-right mb-1"
          for="facility_id"
        >
          {{ t.facility?.label || 'Facility' }}
        </label>
        <Select
          :key="`facility-${locale}`"
          id="facility_id"
          v-model="filters.facility_id"
          :options="facilityOptions"
          :placeholder="t.facility?.all || 'All Facilities'"
          @change="handleFilterChange"
        />
      </div>

    </div>

    <!-- The rows nobody can place on a map. A branch with no governorate or
         city is invisible to every place filter on the site, and the migration
         import stops on it — so the number still to fix rides on the switch. -->
    <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
      <span class="text-xs font-medium text-muted-foreground">
        {{ t.facility_branch?.missing_location || 'Missing location' }}:
      </span>
      <button
        type="button"
        @click="toggleMissing('no_governorate')"
        :aria-pressed="filters.no_governorate"
        :class="[
          'inline-flex items-center gap-1.5 rounded-md border px-2 sm:px-2.5 h-7 sm:h-8 text-xs font-medium transition-colors cursor-pointer',
          filters.no_governorate
            ? 'border-emerald-400 bg-emerald-500/30 text-emerald-950 dark:text-emerald-50'
            : 'border-border bg-background hover:bg-muted text-foreground',
        ]"
      >
        {{ t.governorate?.none || 'No governorate' }}
        <span v-if="incompleteCounts.no_governorate" class="rounded bg-emerald-500/30 px-1.5 py-0.5 text-[10px] font-semibold tabular-nums">
          {{ incompleteCounts.no_governorate }}
        </span>
      </button>
      <button
        type="button"
        @click="toggleMissing('no_city')"
        :aria-pressed="filters.no_city"
        :class="[
          'inline-flex items-center gap-1.5 rounded-md border px-2 sm:px-2.5 h-7 sm:h-8 text-xs font-medium transition-colors cursor-pointer',
          filters.no_city
            ? 'border-amber-400 bg-amber-500/30 text-amber-950 dark:text-amber-50'
            : 'border-border bg-background hover:bg-muted text-foreground',
        ]"
      >
        {{ t.city?.none || 'No city' }}
        <span v-if="incompleteCounts.no_city" class="rounded bg-amber-500/30 px-1.5 py-0.5 text-[10px] font-semibold tabular-nums">
          {{ incompleteCounts.no_city }}
        </span>
      </button>
      <span v-if="filters.no_governorate && filters.no_city" class="text-[11px] text-muted-foreground">
        missing <strong>both</strong>
      </span>
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

const props = defineProps({
  initialFilters: {
    type: Object,
    default: () => ({
      search: '',
      facility_id: ''
    })
  },
  facilities: {
    type: Array,
    default: () => []
  },
  // What each switch would find across the whole list, not just this page.
  incompleteCounts: {
    type: Object,
    default: () => ({ no_governorate: 0, no_city: 0 })
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

// Convert facilities to options for select - make it reactive to locale changes
const facilityOptions = computed(() => {
  // Access locale directly in computed to ensure reactivity
  const currentLocale = page.props.locale || 'ar';
  return props.facilities.map(facility => ({
    value: facility.id,
    label: getTranslatedName(facility.name, currentLocale)
  }));
});

const emit = defineEmits(['filter-change']);

// The switches arrive as a query string or as a server prop, so "1" and true
// both have to read as on.
const truthy = (value) => value === true || value === 1 || value === '1' || value === 'true';

const getInitialFilters = () => {
  // First check props (from server)
  if (props.initialFilters) {
    return {
      search: props.initialFilters.search || '',
      facility_id: props.initialFilters.facility_id || props.initialFilters.facility_id === 0 ? '0' : '',
      no_governorate: truthy(props.initialFilters.no_governorate),
      no_city: truthy(props.initialFilters.no_city)
    };
  }

  // Fallback to URL params if props not available
  if (typeof window !== 'undefined') {
    const urlParams = new URLSearchParams(window.location.search);
    return {
      search: urlParams.get('search') || '',
      facility_id: urlParams.get('facility_id') || '',
      no_governorate: truthy(urlParams.get('no_governorate')),
      no_city: truthy(urlParams.get('no_city'))
    };
  }

  return {
    search: '',
    facility_id: '',
    no_governorate: false,
    no_city: false
  };
};

const filters = ref(getInitialFilters());

// Computed property to check if any filter is active
const hasActiveFilters = computed(() => {
  return !!(filters.value.search || filters.value.facility_id
    || filters.value.no_governorate || filters.value.no_city);
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

// Switched on, the list reloads at once: this is a filter reached for to start
// fixing rows, not one tuned before applying.
const toggleMissing = (key) => {
  filters.value[key] = !filters.value[key];
  applyFilters();
};

const handleReset = () => {
  filters.value = {
    search: '',
    facility_id: '',
    no_governorate: false,
    no_city: false
  };
  applyFilters();
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
  if (currentFilters.facility_id && currentFilters.facility_id !== '') {
    params.facility_id = currentFilters.facility_id;
  }
  if (currentFilters.no_governorate) params.no_governorate = 1;
  if (currentFilters.no_city) params.no_city = 1;

  emit('filter-change', currentFilters);

  router.get(route('admin.facility-branch.list'), params, {
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
