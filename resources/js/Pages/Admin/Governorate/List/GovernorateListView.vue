<template>
  <GovernorateLayout>
    <!-- Mobile: flex column with contained scroll in table | Desktop: normal flow with page scroll -->
    <div class="flex flex-col h-full lg:h-auto w-full max-w-full overflow-x-hidden">
      <div class="flex-shrink-0 space-y-2 sm:space-y-3 md:space-y-4 p-2 sm:p-3 md:p-4 lg:p-6 pb-2 sm:pb-3 md:pb-4 lg:pb-4 w-full max-w-full overflow-hidden">
        <!-- Header Card with Actions and Filters -->
        <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-2 sm:gap-3 md:gap-4 rounded-xl border border-border py-2 sm:py-3 md:py-4 shadow-sm overflow-hidden w-full max-w-full">
          <div data-slot="card-header" class="flex flex-row items-center justify-between py-2 px-3 sm:px-4 md:px-6 w-full overflow-hidden gap-2 sm:gap-4">
            <div data-slot="card-title" class="leading-none font-semibold min-w-0 flex-1">
              <div class="title-golden min-w-0 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-map-pin title-icon sm:w-6 sm:h-6 flex-shrink-0">
                  <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path>
                  <circle cx="12" cy="10" r="3"></circle>
                </svg>
                <span class="text-sm sm:text-base truncate block min-w-0">{{ t.governorate?.management || 'Governorates Management' }}</span>
              </div>
            </div>
            <div class="flex items-center gap-1.5 sm:gap-2 flex-shrink-0">
              <GovernorateEnglishBulkDialog v-if="canWrite" />
              <div class="inline-flex items-center rounded-md border border-border bg-background p-0.5 flex-shrink-0" role="group">
                <button
                  type="button"
                  @click="viewMode = 'list'"
                  :aria-pressed="viewMode === 'list'"
                  class="inline-flex items-center gap-1.5 rounded-[5px] px-2 sm:px-3 h-7 sm:h-8 text-xs font-medium transition-colors cursor-pointer"
                  :class="viewMode === 'list' ? 'bg-primary text-primary-foreground' : 'hover:bg-muted text-foreground'"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                  <span class="hidden sm:inline">{{ t.common?.list || 'List' }}</span>
                </button>
                <button
                  type="button"
                  @click="viewMode = 'map'"
                  :aria-pressed="viewMode === 'map'"
                  class="inline-flex items-center gap-1.5 rounded-[5px] px-2 sm:px-3 h-7 sm:h-8 text-xs font-medium transition-colors cursor-pointer"
                  :class="viewMode === 'map' ? 'bg-primary text-primary-foreground' : 'hover:bg-muted text-foreground'"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"></polygon><line x1="8" y1="2" x2="8" y2="18"></line><line x1="16" y1="6" x2="16" y2="22"></line></svg>
                  <span class="hidden sm:inline">{{ t.governorate?.map_view || 'Map' }}</span>
                </button>
              </div>
              <Link
                v-if="canWrite"
                :href="route('admin.governorate.create')"
                data-slot="button"
                class="inline-flex items-center cursor-pointer justify-center gap-1.5 sm:gap-2 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-8 sm:h-9 px-2 sm:px-3 md:px-4 py-2 flex-shrink-0 btn-golden"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus h-3.5 w-3.5 sm:h-4 sm:w-4">
                  <path d="M5 12h14"></path>
                  <path d="M12 5v14"></path>
                </svg>
                <span class="hidden sm:inline">{{ t.governorate?.add_new || 'Add New Governorate' }}</span>
                <span class="sm:hidden">{{ t.common?.add || 'Add' }}</span>
              </Link>
            </div>
          </div>

          <!-- Filter Content -->
          <div data-slot="card-content" class="px-2 sm:px-4 md:px-6 space-y-2 sm:space-y-3 md:space-y-4 w-full max-w-full overflow-hidden min-w-0">
            <GovernorateListFilterContent v-if="viewMode === 'list'" :initial-filters="filters" @filter-change="handleFilterChange" />
          </div>
        </div>

      </div>

      <!-- Table Card - Scrollable on mobile, full height on desktop -->
      <div class="flex-1 min-h-0 lg:flex-none w-full max-w-full px-2 sm:px-3 md:px-4 lg:px-6 pb-2 sm:pb-3 md:pb-4 lg:pb-6 overflow-hidden lg:overflow-visible">
        <GovernorateListTable v-if="viewMode === 'list'" :governorates="governorates" @delete="handleDelete" />
        <GovernorateMapView v-else />
      </div>
    </div>
  </GovernorateLayout>
</template>

<script setup>
import GovernorateLayout from "../GovernorateLayout.vue";
import GovernorateListFilterContent from "./GovernorateListFilterContent.vue";
import GovernorateListTable from "./GovernorateListTable.vue";
import GovernorateEnglishBulkDialog from "./GovernorateEnglishBulkDialog.vue";
import GovernorateMapView from "./GovernorateMapView.vue";
import { useGovernorateStore } from "../Stores/GovernorateStore";
import { Link, usePage } from "@inertiajs/vue3";
import { storeToRefs } from "pinia";
import { ref, computed, watch } from "vue";
import { usePermissions } from '@/composables/usePermissions';

const { canManage } = usePermissions();
// Create/export/import are writes: hidden from read-only accounts,
// and refused by the routes behind them either way.
const canWrite = computed(() => canManage('manage governorates', 'manage own governorates'));


const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const props = defineProps({
  governorates: {
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

const governorateStore = useGovernorateStore();
const { governorates: storeGovernorates } = storeToRefs(governorateStore);

governorateStore.setGovernorates(props.governorates);

const governorates = computed(() => props.governorates);

const filters = ref(props.filters || {
  search: ''
});

// List or map, kept in the URL (?view=map) so a reload or a shared link opens the same view.
const viewMode = ref(
  typeof window !== 'undefined' && new URLSearchParams(window.location.search).get('view') === 'map' ? 'map' : 'list'
);

watch(viewMode, (mode) => {
  const url = new URL(window.location.href);
  if (mode === 'map') {
    url.searchParams.set('view', 'map');
  } else {
    url.searchParams.delete('view');
  }
  // Keep Inertia's own history state; only the address changes, nothing reloads.
  window.history.replaceState(window.history.state, '', url);
});

const handleDelete = (governorateSlug) => {
  governorateStore.confirmDelete(governorateSlug);
};

const handleFilterChange = (newFilters) => {
  filters.value = newFilters;
};
</script>

