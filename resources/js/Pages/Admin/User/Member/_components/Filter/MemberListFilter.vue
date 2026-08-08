<template>
  <div data-slot="card-content" class="px-6 space-y-4">
    <div class="space-y-4">
      <!-- Search and Role Filter -->
      <div class="flex flex-col lg:flex-row gap-4 items-start lg:items-end">
        <div class="w-full lg:flex-1 lg:max-w-md xl:max-w-lg space-y-2">
          <label data-slot="label" class="flex items-center gap-2 text-sm leading-none font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50" for="search">Search</label>
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
              placeholder="Search users by name or email..."
            />
          </div>
        </div>
       
      </div>

      <!-- Quick Filters -->
      <div class="flex flex-col sm:flex-row gap-2">
        <div class="text-sm font-medium text-muted-foreground mb-2 sm:mb-0 sm:mr-3 sm:self-center">Quick filters:</div>
        <div class="flex flex-wrap gap-2 items-center">
          <button
            data-slot="button"
            @click="handleFilter('accepted')"
            :class="[
              'cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*=\'size-\'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md px-3 has-[>svg]:px-2.5 flex items-center gap-2',
              filters.status === 'accepted' ? 'bg-primary text-primary-foreground' : ''
            ]"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-circle-check-big h-4 w-4">
              <path d="M21.801 10A10 10 0 1 1 17 3.335"></path>
              <path d="m9 11 3 3L22 4"></path>
            </svg>
            <span>Accepted</span>
          </button>
          <button
            data-slot="button"
            @click="handleFilter('blocked')"
            :class="[
              'cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*=\'size-\'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md px-3 has-[>svg]:px-2.5 flex items-center gap-2',
              filters.status === 'blocked' ? 'bg-primary text-primary-foreground' : ''
            ]"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-circle-x h-4 w-4">
              <circle cx="12" cy="12" r="10"></circle>
              <path d="m15 9-6 6"></path>
              <path d="m9 9 6 6"></path>
            </svg>
            <span>Blocked</span>
          </button>
        </div>
        <button
          data-slot="button"
          @click="handleReset"
          class="cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive bg-destructive text-white shadow-xs hover:bg-destructive/90 focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40 dark:bg-destructive/60 h-8 rounded-md px-3 has-[>svg]:px-2.5 flex items-center gap-2 ml-auto"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-rotate-ccw h-4 w-4">
            <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path>
            <path d="M3 3v5h5"></path>
          </svg>
          Reset Filters
        </button>
      </div>
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
      search: '',
      role: '',
      status: ''
    })
  }
});

const emit = defineEmits(['filter-change']);

const filters = ref({
  search: props.initialFilters?.search || '',
  role: props.initialFilters?.role || '',
  status: props.initialFilters?.status || ''
});

// Simple debounce implementation
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

const handleFilter = (status) => {
  // Toggle filter: if already selected, deselect it
  if (filters.value.status === status) {
    filters.value.status = '';
  } else {
    filters.value.status = status;
  }
  applyFilters();
};

const handleReset = () => {
  filters.value = {
    search: '',
    role: '',
    status: ''
  };
  applyFilters();
};

const applyFilters = (filterValues = null) => {
  const currentFilters = filterValues || filters.value;
  
  // Build query parameters
  const params = {};
  if (currentFilters.search) params.search = currentFilters.search;
  if (currentFilters.role) params.role = currentFilters.role;
  if (currentFilters.status) params.status = currentFilters.status;
  
  // Emit event for parent component
  emit('filter-change', currentFilters);
  
  // Apply filters via Inertia
  router.get(route('admin.user.membership.list'), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true
  });
};

// Watch for external filter changes
watch(() => props.initialFilters, (newFilters) => {
  if (newFilters) {
    filters.value = { ...filters.value, ...newFilters };
  }
}, { deep: true });
</script>

