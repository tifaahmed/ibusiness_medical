<template>
  <FacilityBranchLayout>
    <!-- Mobile: flex column with contained scroll in table | Desktop: normal flow with page scroll -->
    <div class="flex flex-col h-full lg:h-auto w-full max-w-full overflow-x-hidden">
      <div class="flex-shrink-0 space-y-2 sm:space-y-3 md:space-y-4 p-2 sm:p-3 md:p-4 lg:p-6 pb-2 sm:pb-3 md:pb-4 lg:pb-4 w-full max-w-full overflow-hidden">
        <!-- Header Card with Actions and Filters -->
        <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-2 sm:gap-3 md:gap-4 rounded-xl border border-border py-2 sm:py-3 md:py-4 shadow-sm overflow-hidden w-full max-w-full">
          <div data-slot="card-header" class="flex flex-row items-center justify-between py-2 px-3 sm:px-4 md:px-6 w-full overflow-hidden gap-2 sm:gap-4">
            <div data-slot="card-title" class="leading-none font-semibold min-w-0 flex-1">
              <div class="title-golden min-w-0 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-git-branch title-icon sm:w-6 sm:h-6 flex-shrink-0">
                  <line x1="6" x2="6" y1="3" y2="15"></line>
                  <circle cx="18" cy="6" r="3"></circle>
                  <circle cx="6" cy="18" r="3"></circle>
                  <path d="M18 9a9 9 0 0 1-9 9"></path>
                </svg>
                <span class="text-sm sm:text-base truncate block min-w-0">{{ t.facility_branch?.management || 'Facility Branches Management' }}</span>
              </div>
            </div>
            <div class="flex items-center gap-1.5 sm:gap-2 flex-shrink-0">
              <a
                :href="exportUrl"
                target="_blank"
                rel="noopener"
                class="inline-flex items-center cursor-pointer justify-center gap-1.5 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium border bg-background hover:bg-muted h-8 sm:h-9 px-2 sm:px-3 md:px-4 py-2"
                title="Export current filtered list"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                <span class="hidden sm:inline">Export</span>
              </a>
              <Link
                :href="route('admin.facility-branch.import.page')"
                class="inline-flex items-center cursor-pointer justify-center gap-1.5 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium border bg-background hover:bg-muted h-8 sm:h-9 px-2 sm:px-3 md:px-4 py-2"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                <span class="hidden sm:inline">Import</span>
              </Link>
              <Link
                :href="route('admin.facility-branch.create')"
                data-slot="button"
                class="inline-flex items-center cursor-pointer justify-center gap-1.5 sm:gap-2 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-8 sm:h-9 px-2 sm:px-3 md:px-4 py-2 flex-shrink-0 btn-golden"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus h-3.5 w-3.5 sm:h-4 sm:w-4">
                  <path d="M5 12h14"></path>
                  <path d="M12 5v14"></path>
                </svg>
                <span class="hidden sm:inline">{{ t.facility_branch?.add_new || 'Add New Branch' }}</span>
                <span class="sm:hidden">{{ t.common?.add || 'Add' }}</span>
              </Link>
            </div>
          </div>

          <!-- Filter Content -->
          <div data-slot="card-content" class="px-2 sm:px-4 md:px-6 space-y-2 sm:space-y-3 md:space-y-4 w-full max-w-full overflow-hidden min-w-0">
            <FacilityBranchListFilterContent :initial-filters="filters" :facilities="facilities" @filter-change="handleFilterChange" />
          </div>
        </div>

      </div>

      <!-- Table Card - Scrollable on mobile, full height on desktop -->
      <div class="flex-1 min-h-0 lg:min-h-fit w-full max-w-full px-2 sm:px-3 md:px-4 lg:px-6 pb-2 sm:pb-3 md:pb-4 lg:pb-6 overflow-hidden lg:overflow-visible">
        <FacilityBranchListTable :facility-branches="facilityBranches" @delete="handleDelete" />
      </div>
    </div>
  </FacilityBranchLayout>
</template>

<script setup>
import FacilityBranchLayout from "../FacilityBranchLayout.vue";
import FacilityBranchListFilterContent from "./FacilityBranchListFilterContent.vue";
import FacilityBranchListTable from "./FacilityBranchListTable.vue";
import { useFacilityBranchStore } from "../Stores/FacilityBranchStore";
import { Link, usePage } from "@inertiajs/vue3";
import { storeToRefs } from "pinia";
import { ref, computed } from "vue";

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const props = defineProps({
  facilityBranches: {
    type: Object,
    required: true
  },
  filters: {
    type: Object,
    default: () => ({
      search: '',
      facility_id: ''
    })
  },
  facilities: {
    type: Array,
    default: () => []
  }
});

const facilityBranchStore = useFacilityBranchStore();
const { facilityBranches: storeFacilityBranches } = storeToRefs(facilityBranchStore);

facilityBranchStore.setFacilityBranches(props.facilityBranches);

const facilityBranches = computed(() => props.facilityBranches);

const filters = ref(props.filters || {
  search: '',
  facility_id: ''
});

const handleDelete = (facilityBranchSlug) => {
  facilityBranchStore.confirmDelete(facilityBranchSlug);
};

const handleFilterChange = (newFilters) => {
  filters.value = newFilters;
};

const exportUrl = computed(() => {
  const params = new URLSearchParams();
  const f = filters.value || {};
  if (f.search) params.set('search', f.search);
  if (f.facility_id) params.set('facility_id', f.facility_id);
  const qs = params.toString();
  return route('admin.facility-branch.export') + (qs ? '?' + qs : '');
});
</script>

