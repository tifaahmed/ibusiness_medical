<template>
  <div class="w-full min-w-0 overflow-x-hidden space-y-2">
    <div class="flex flex-col sm:flex-row sm:items-end gap-2 sm:gap-3 w-full">
      <div class="flex-1 min-w-0">
        <label class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full ltr:justify-start rtl:justify-end ltr:text-left rtl:text-right mb-1" for="search">
          Search
        </label>
        <div class="relative">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-search absolute left-2 sm:left-2.5 md:left-3 top-1/2 -translate-y-1/2 h-3 w-3 sm:h-3.5 sm:w-3.5 md:h-4 md:w-4 text-muted-foreground pointer-events-none z-10">
            <circle cx="11" cy="11" r="8"></circle>
            <path d="m21 21-4.3-4.3"></path>
          </svg>
          <input
            v-model="filters.search"
            @input="handleSearch"
            id="search"
            placeholder="Search role name..."
            class="placeholder:text-gray-foreground dark:bg-input/30 border border-border text-foreground flex h-7 sm:h-8 md:h-9 w-full min-w-0 max-w-full rounded-md bg-transparent px-2 sm:px-2.5 md:px-3 py-1 text-xs sm:text-sm md:text-base shadow-xs transition-all outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 [color-scheme:dark] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] pl-7 sm:pl-8 md:pl-9 box-border"
          />
        </div>
      </div>
    </div>

    <button
      v-if="hasActiveFilters"
      type="button"
      @click="handleReset"
      class="cursor-pointer justify-center whitespace-nowrap text-xs font-medium transition-all bg-destructive text-white shadow-xs hover:bg-destructive/90 h-7 sm:h-8 rounded-md px-2 sm:px-3 inline-flex items-center gap-1.5 sm:gap-2"
    >
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x h-3 w-3 sm:h-3.5 sm:w-3.5 md:h-4 md:w-4">
        <path d="M18 6 6 18"></path>
        <path d="m6 6 12 12"></path>
      </svg>
      <span class="hidden sm:inline">Clear</span>
    </button>
  </div>
</template>

<script setup>
import { ref, computed, watch } from "vue";
import { router } from "@inertiajs/vue3";

const props = defineProps({
  initialFilters: { type: Object, default: () => ({ search: "" }) },
});

const emit = defineEmits(["filter-change"]);

const filters = ref({ search: props.initialFilters?.search || "" });
const hasActiveFilters = computed(() => !!filters.value.search);

let searchTimeout = null;
const handleSearch = (event) => {
  filters.value.search = event.target.value;
  if (searchTimeout) clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => applyFilters(), 300);
};

const handleReset = () => {
  filters.value = { search: "" };
  applyFilters();
};

const applyFilters = () => {
  const params = {};
  if (filters.value.search?.trim()) params.search = filters.value.search;
  emit("filter-change", filters.value);
  router.get(route("admin.roles.index"), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

watch(
  () => props.initialFilters,
  (newFilters) => {
    if (newFilters) filters.value = { search: newFilters.search || "" };
  },
  { deep: true }
);
</script>
