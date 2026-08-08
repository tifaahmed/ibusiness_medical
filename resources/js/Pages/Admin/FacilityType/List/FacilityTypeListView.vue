<template>
  <FacilityTypeLayout>
    <!-- Mobile: flex column with contained scroll in table | Desktop: normal flow with page scroll -->
    <div class="flex flex-col h-full lg:h-auto w-full max-w-full overflow-x-hidden">
      <div class="flex-shrink-0 space-y-2 sm:space-y-3 md:space-y-4 p-2 sm:p-3 md:p-4 lg:p-6 pb-2 sm:pb-3 md:pb-4 lg:pb-4 w-full max-w-full overflow-hidden">
        <!-- Header Card with Actions and Filters -->
        <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-2 sm:gap-3 md:gap-4 rounded-xl border border-border py-2 sm:py-3 md:py-4 shadow-sm overflow-hidden w-full max-w-full">
          <div data-slot="card-header" class="flex flex-row items-center justify-between py-2 px-3 sm:px-4 md:px-6 w-full overflow-hidden gap-2 sm:gap-4">
            <div data-slot="card-title" class="leading-none font-semibold min-w-0 flex-1">
              <div class="title-golden min-w-0 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-building-2 title-icon sm:w-6 sm:h-6 flex-shrink-0">
                  <path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"></path>
                  <path d="M6 12h12"></path>
                  <path d="M6 8h12"></path>
                  <path d="M6 16h12"></path>
                </svg>
                <span class="text-sm sm:text-base truncate block min-w-0">{{ t.facility_type?.management || 'Facility Types Management' }}</span>
              </div>
            </div>
            <Link
              :href="route('admin.facility-type.create')"
              data-slot="button"
              class="inline-flex items-center cursor-pointer justify-center gap-1.5 sm:gap-2 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-8 sm:h-9 px-2 sm:px-3 md:px-4 py-2 flex-shrink-0 btn-golden"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus h-3.5 w-3.5 sm:h-4 sm:w-4">
                <path d="M5 12h14"></path>
                <path d="M12 5v14"></path>
              </svg>
              <span class="hidden sm:inline">{{ t.facility_type?.add_new || 'Add New Facility Type' }}</span>
              <span class="sm:hidden">{{ t.common?.add || 'Add' }}</span>
            </Link>
          </div>

          <!-- Filter Content -->
          <div data-slot="card-content" class="px-2 sm:px-4 md:px-6 space-y-2 sm:space-y-3 md:space-y-4 w-full max-w-full overflow-hidden min-w-0">
            <FacilityTypeListFilterContent :initial-filters="filters" @filter-change="handleFilterChange" />
          </div>
        </div>

      </div>

      <!-- Table Card - Scrollable on mobile, full height on desktop -->
      <div class="flex-1 min-h-0 lg:flex-none w-full max-w-full px-2 sm:px-3 md:px-4 lg:px-6 pb-2 sm:pb-3 md:pb-4 lg:pb-6 overflow-hidden lg:overflow-visible">
        <FacilityTypeListTable :facility-types="facilityTypes" @delete="handleDelete" />
      </div>
    </div>
  </FacilityTypeLayout>
</template>

<script setup>
import FacilityTypeLayout from "../FacilityTypeLayout.vue";
import FacilityTypeListFilterContent from "./FacilityTypeListFilterContent.vue";
import FacilityTypeListTable from "./FacilityTypeListTable.vue";
import { useFacilityTypeStore } from "../Stores/FacilityTypeStore";
import { Link, usePage } from "@inertiajs/vue3";
import { storeToRefs } from "pinia";
import { ref, computed } from "vue";

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const props = defineProps({
  facilityTypes: {
    type: Object,
    required: true
  },
  filters: {
    type: Object,
    default: () => ({
      search: ''
    })
  }
});

const facilityTypeStore = useFacilityTypeStore();
const { facilityTypes: storeFacilityTypes } = storeToRefs(facilityTypeStore);

facilityTypeStore.setFacilityTypes(props.facilityTypes);

const facilityTypes = computed(() => props.facilityTypes);

const filters = ref(props.filters || {
  search: ''
});

const handleDelete = (facilityTypeSlug) => {
  facilityTypeStore.confirmDelete(facilityTypeSlug);
};

const handleFilterChange = (newFilters) => {
  filters.value = newFilters;
};
</script>

