<template>
  <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm w-full max-w-full overflow-hidden">
    <div data-slot="card-content" class="px-3 sm:px-6 space-y-4">
      <div class="space-y-4">
        <!-- Search Filter -->
        <div class="flex flex-col lg:flex-row gap-4 items-start lg:items-end">
          <div class="w-full lg:flex-1 lg:max-w-md xl:max-w-lg space-y-2">
            <label data-slot="label" class="flex items-center gap-2 text-sm leading-none font-medium select-none" for="search">{{ t.common?.search || 'Search' }}</label>
            <div class="relative">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-search absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground">
                <circle cx="11" cy="11" r="8"></circle>
                <path d="m21 21-4.3-4.3"></path>
              </svg>
              <input
                data-slot="input"
                v-model="filters.search"
                @input="handleSearch"
                class="file:text-foreground placeholder:text-gray-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-all outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm [color-scheme:dark] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:bg-secondary/10 data-[filled=true]:bg-secondary/5 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive pl-9"
                id="search"
                :placeholder="t.facility_type?.search_placeholder || 'Search facility types by name...'"
              />
            </div>
          </div>
        </div>

        <!-- Reset Filter -->
        <div class="flex flex-col sm:flex-row gap-2">
          <button
            data-slot="button"
            @click="handleReset"
            class="cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive bg-destructive text-white shadow-xs hover:bg-destructive/90 focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40 dark:bg-destructive/60 h-8 rounded-md px-3 has-[>svg]:px-2.5 flex items-center gap-2 ml-auto"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-rotate-ccw h-4 w-4">
              <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path>
              <path d="M3 3v5h5"></path>
            </svg>
            {{ t.common?.reset_filters || 'Reset Filters' }}
          </button>
        </div>
      </div>
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
      search: ''
    })
  }
});

const emit = defineEmits(['filter-change']);

const getInitialFilters = () => {
  if (props.initialFilters && props.initialFilters.search) {
    return {
      search: props.initialFilters.search || ''
    };
  }
  
  if (typeof window !== 'undefined') {
    const urlParams = new URLSearchParams(window.location.search);
    return {
      search: urlParams.get('search') || ''
    };
  }
  
  return {
    search: ''
  };
};

const filters = ref(getInitialFilters());

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
  filters.value = {
    search: ''
  };
  applyFilters();
};

const applyFilters = (filterValues = null) => {
  const currentFilters = filterValues || filters.value;
  
  const params = {};
  if (currentFilters.search) params.search = currentFilters.search;
  
  emit('filter-change', currentFilters);
  
  router.get(route('admin.facility-type.list'), params, {
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



