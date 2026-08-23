<template>
  <div class="w-full min-w-0 overflow-x-hidden space-y-2">
    <!-- Unified responsive grid: 1 col mobile / 2 col tablet / 4 col desktop -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 sm:gap-3 w-full">
      <!-- Search — always visible -->
      <div class="min-w-0">
        <label
          data-slot="label"
          class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full ltr:justify-start rtl:justify-end ltr:text-left rtl:text-right mb-1"
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
            id="search"
            data-slot="input"
            autocomplete="off"
            type="text"
            v-model="filters.search"
            @input="handleSearch"
            class="placeholder:text-white dark:bg-input/30 border border-border text-foreground flex h-7 sm:h-8 md:h-9 w-full min-w-0 max-w-full rounded-md bg-transparent px-2 sm:px-2.5 md:px-3 py-1 text-xs sm:text-sm md:text-base shadow-xs transition-all outline-none [color-scheme:dark] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:bg-secondary/10 pl-7 sm:pl-8 md:pl-9 box-border"
            :placeholder="t.tag?.search_placeholder || 'Search by name...'"
          />
        </div>
      </div>

      <!-- Sort Filter -->
      <div class="min-w-0">
        <label
          data-slot="label"
          class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full ltr:justify-start rtl:justify-end ltr:text-left rtl:text-right mb-1"
          for="tag-sort"
        >
          {{ t.common?.sort || 'Sort' }}
        </label>
        <Select
          v-model="filters.sort"
          :options="sortOptions"
          :placeholder="t.tag?.sort_newest || 'Newest first'"
          id="tag-sort"
          @change="applyFilters()"
        />
      </div>

      <!-- Usage Filter -->
      <div class="min-w-0">
        <label
          data-slot="label"
          class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full ltr:justify-start rtl:justify-end ltr:text-left rtl:text-right mb-1"
          for="tag-used"
        >
          {{ t.tag?.usage || 'Usage' }}
        </label>
        <Select
          v-model="filters.used"
          :options="usedOptions"
          :placeholder="t.common?.all || 'All tags'"
          id="tag-used"
          @change="applyFilters()"
        />
      </div>
    </div>

    <!-- Reset Filter - Only show if there's an active filter -->
    <button
      v-if="hasActiveFilters"
      data-slot="button"
      @click="handleReset"
      class="cursor-pointer justify-center whitespace-nowrap text-xs font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive bg-destructive text-white shadow-xs hover:bg-destructive/90 focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40 dark:bg-destructive/60 h-7 sm:h-8 rounded-md px-2 sm:px-3 has-[>svg]:px-2 inline-flex items-center gap-1.5 sm:gap-2 ml-auto"
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
      sort: 'newest',
      used: ''
    })
  }
});

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const sortOptions = computed(() => [
  { value: 'newest', label: t.value.tag?.sort_newest || 'Newest first' },
  { value: 'most_used', label: t.value.tag?.sort_most_used || 'Most used' },
  { value: 'least_used', label: t.value.tag?.sort_least_used || 'Least used' },
]);

const usedOptions = computed(() => [
  { value: '', label: t.value.common?.all || 'All tags' },
  { value: '1', label: t.value.tag?.used_before || 'Used before' },
  { value: '0', label: t.value.tag?.never_used || 'Never used' },
]);

const emit = defineEmits(['filter-change']);

const getInitialFilters = () => {
  if (props.initialFilters) {
    return {
      search: props.initialFilters.search || '',
      sort: props.initialFilters.sort || 'newest',
      used: props.initialFilters.used ?? ''
    };
  }
  return { search: '', sort: 'newest', used: '' };
};

const filters = ref(getInitialFilters());

const hasActiveFilters = computed(() =>
  !!filters.value.search || filters.value.sort !== 'newest' || (filters.value.used !== '' && filters.value.used !== null)
);

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
  filters.value = { search: '', sort: 'newest', used: '' };
  applyFilters();
};

const applyFilters = (filterValues = null) => {
  const currentFilters = filterValues || filters.value;

  const params = {};
  if (currentFilters.search && currentFilters.search.trim()) {
    params.search = currentFilters.search;
  }
  // Defaults are omitted so URLs stay clean.
  if (currentFilters.sort && currentFilters.sort !== 'newest') {
    params.sort = currentFilters.sort;
  }
  if (currentFilters.used !== '' && currentFilters.used !== null && currentFilters.used !== undefined) {
    params.used = currentFilters.used;
  }

  emit('filter-change', currentFilters);

  router.get(route('admin.tag.list'), params, {
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
