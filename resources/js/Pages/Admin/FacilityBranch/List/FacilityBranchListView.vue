<template>
  <FacilityBranchLayout>
    <!-- Mobile: flex column with contained scroll in table | Desktop: normal flow with page scroll -->
    <div class="flex flex-col h-full lg:h-auto w-full max-w-full overflow-x-hidden">
      <div class="flex-shrink-0 space-y-2 sm:space-y-3 md:space-y-4 p-2 sm:p-3 md:p-4 lg:p-6 pb-2 sm:pb-3 md:pb-4 lg:pb-4 w-full max-w-full overflow-hidden">
        <!-- Header Card with Actions and Filters -->
        <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-2 sm:gap-3 md:gap-4 rounded-xl border border-border py-2 sm:py-3 md:py-4 shadow-sm overflow-hidden w-full max-w-full">
          <!-- Two rows: title on top, action buttons wrapping below, so none get clipped -->
          <div data-slot="card-header" class="flex flex-col items-stretch py-2 px-3 sm:px-4 md:px-6 w-full overflow-hidden gap-2 sm:gap-3">
            <div data-slot="card-title" class="leading-none font-semibold min-w-0 w-full">
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
            <div class="flex flex-wrap items-center justify-start gap-1.5 sm:gap-2 w-full min-w-0">
              <!-- The two AI sweeps: read the address a branch already has and
                   fill in what it is missing. Each carries the number of rows
                   still waiting, so the size of the job is on the button. -->
              <!-- Not an AI sweep: it renames every branch to "<facility> -
                   <city>", numbering the ones that would otherwise clash. -->
              <BranchSweepDialog
                v-if="canWrite"
                begin-route="admin.facility-branch.rename.bulk.begin"
                step-route="admin.facility-branch.rename.bulk.step"
                icon="rename"
                :pending="incompleteCounts.duplicate_names || 0"
                :label="t.facility_branch?.rename_bulk || 'Fix branch names'"
                :short-label="t.facility_branch?.rename_bulk_short || 'Names'"
                :hint="t.facility_branch?.rename_bulk_hint || 'Rename every branch to \'facility - city\', numbered so no two branches of one facility share a name'"
                :title="t.facility_branch?.rename_bulk_title || 'Fix branch names'"
                :description="t.facility_branch?.rename_bulk_description || 'Names every branch after its facility and city — \'Mytra Labs - Maadi\'. Where a facility has more than one branch in the same city, the second and later ones are numbered. Web addresses (slugs) are left as they are, so existing links keep working.'"
                :overwrite-label="t.facility_branch?.rename_bulk_careful || 'Only fix duplicates and blanks'"
                :overwrite-hint="t.facility_branch?.rename_bulk_careful_hint || 'Leaves a name alone when it is filled in and clashes with nothing. Off = every branch gets the standard name.'"
                checked-mode="missing"
                unchecked-mode="all"
                :nothing-to-do="t.facility_branch?.rename_bulk_nothing || 'Nothing to do — there are no branches to rename.'"
                :finished="t.facility_branch?.rename_bulk_done || 'Branch names fixed.'"
              />
              <BranchSweepDialog
                v-if="canWrite && placeAiEnabled"
                begin-route="admin.facility-branch.place.bulk.begin"
                step-route="admin.facility-branch.place.bulk.step"
                icon="place"
                :pending="incompleteCounts.no_place || 0"
                :label="t.facility_branch?.place_bulk || 'Fill governorate & city with AI'"
                :short-label="t.facility_branch?.place_bulk_short || 'Place'"
                :hint="t.facility_branch?.place_bulk_hint || 'Read every branch address and fill in the governorate and city it names'"
                :title="t.facility_branch?.place_bulk_title || 'Fill governorate & city with AI'"
                :description="t.facility_branch?.place_bulk_description || 'AI reads each branch address and chooses the governorate and city it names, from the ones that exist here. Branches with no address are skipped.'"
                :overwrite-hint="t.facility_branch?.place_bulk_overwrite_hint || 'Re-reads branches that already have a governorate and city. Off = only the ones missing either.'"
                :nothing-to-do="t.facility_branch?.place_bulk_nothing || 'Nothing to do — every branch with an address already has a governorate and city.'"
                :finished="t.facility_branch?.place_bulk_done || 'Governorate and city sweep finished. Check what it chose.'"
              />
              <BranchSweepDialog
                v-if="canWrite && locationAiEnabled"
                begin-route="admin.facility-branch.location.bulk.begin"
                step-route="admin.facility-branch.location.bulk.step"
                icon="location"
                :pending="incompleteCounts.no_location || 0"
                :label="t.facility_branch?.location_bulk || 'Fill GPS with AI'"
                :short-label="t.facility_branch?.location_bulk_short || 'GPS'"
                :hint="t.facility_branch?.location_bulk_hint || 'Read every branch address and fill in its coordinates and Google Maps link'"
                :title="t.facility_branch?.location_bulk_title || 'Fill branch GPS with AI'"
                :description="t.facility_branch?.location_bulk_description || 'AI reads each branch address, works out its latitude and longitude, and builds the Google Maps link from them. Check the pins afterwards — they are read from the written address, not surveyed.'"
                :overwrite-hint="t.facility_branch?.location_bulk_overwrite_hint || 'Re-reads branches that already have coordinates. Off = only the ones missing coordinates or a map link.'"
                :nothing-to-do="t.facility_branch?.location_bulk_nothing || 'Nothing to do — every branch with an address already has a location.'"
                :finished="t.facility_branch?.location_bulk_done || 'GPS sweep finished. Open a pin or two to check them.'"
              />
              <!-- Fixes the Arabic and English of every name and address that is
                   missing, in the wrong language or a copy of the other side. -->
              <BranchSweepDialog
                v-if="canWrite && translateAiEnabled"
                begin-route="admin.facility-branch.translate.bulk.begin"
                step-route="admin.facility-branch.translate.bulk.step"
                icon="translate"
                hide-overwrite
                :pending="incompleteCounts.no_translation || 0"
                :label="t.facility_branch?.translate_bulk || 'Fix translations with AI'"
                :short-label="t.facility_branch?.translate_bulk_short || 'Translate'"
                :hint="t.facility_branch?.translate_bulk_hint || 'Fill in or correct the Arabic and English of every branch name and address that is missing or in the wrong language'"
                :title="t.facility_branch?.translate_bulk_title || 'Fix branch translations with AI'"
                :description="t.facility_branch?.translate_bulk_description || 'AI fixes the Arabic and English of each branch name and address that is empty, written in the wrong language, or the same text copied into both. Translations that are already right are left alone, and web addresses (slugs) do not change.'"
                :idle-note="t.facility_branch?.translate_bulk_note || 'Only branches with a missing or wrong name or address are processed. Nothing that is already right is rewritten.'"
                :nothing-to-do="t.facility_branch?.translate_bulk_nothing || 'Nothing to do — every branch name and address already has a correct Arabic and English version.'"
                :finished="t.facility_branch?.translate_bulk_done || 'Translation sweep finished. Check a few of the rows it fixed.'"
              />
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
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                  <span class="hidden sm:inline">{{ t.facility_branch?.map_view || 'Map' }}</span>
                </button>
              </div>
              <a
                v-if="canWrite"
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
                v-if="canWrite"
                :href="route('admin.facility-branch.import.page')"
                class="inline-flex items-center cursor-pointer justify-center gap-1.5 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium border bg-background hover:bg-muted h-8 sm:h-9 px-2 sm:px-3 md:px-4 py-2"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                <span class="hidden sm:inline">Import</span>
              </Link>
              <Link
                v-if="canWrite"
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
            <FacilityBranchListFilterContent
              :initial-filters="filters"
              :facilities="facilities"
              :governorates="governorates"
              :cities="cities"
              :facility-types="facilityTypes"
              :incomplete-counts="incompleteCounts"
              @filter-change="handleFilterChange"
            />
          </div>
        </div>

      </div>

      <!-- Table Card - Scrollable on mobile, full height on desktop -->
      <div class="flex-1 min-h-0 lg:min-h-fit w-full max-w-full px-2 sm:px-3 md:px-4 lg:px-6 pb-2 sm:pb-3 md:pb-4 lg:pb-6 overflow-hidden lg:overflow-visible">
        <FacilityBranchListTable v-if="viewMode === 'list'" :facility-branches="facilityBranches" @delete="handleDelete" />
        <FacilityBranchMapView v-else :filters="filters" />
      </div>
    </div>
  </FacilityBranchLayout>
</template>

<script setup>
import FacilityBranchLayout from "../FacilityBranchLayout.vue";
import FacilityBranchListFilterContent from "./FacilityBranchListFilterContent.vue";
import FacilityBranchListTable from "./FacilityBranchListTable.vue";
import FacilityBranchMapView from "./FacilityBranchMapView.vue";
import BranchSweepDialog from "./BranchSweepDialog.vue";
import { useFacilityBranchStore } from "../Stores/FacilityBranchStore";
import { Link, usePage } from "@inertiajs/vue3";
import { storeToRefs } from "pinia";
import { ref, computed, watch } from "vue";
import { usePermissions } from '@/composables/usePermissions';

const { canManage } = usePermissions();
// Create/export/import are writes: hidden from read-only accounts,
// and refused by the routes behind them either way.
const canWrite = computed(() => canManage('manage facility branches', 'manage own facility branches'));


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
      facility_id: '',
      governorate_id: '',
      city_id: '',
      facility_type_id: '',
      no_governorate: false,
      no_city: false,
      no_address: false,
      no_gps: false
    })
  },
  facilities: {
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
  },
  facilityTypes: {
    type: Array,
    default: () => []
  },
  incompleteCounts: {
    type: Object,
    default: () => ({ no_governorate: 0, no_city: 0, no_address: 0, no_gps: 0, no_place: 0, no_location: 0, duplicate_names: 0 })
  },
  // False when GEMINI_API_KEY is unset on the server — the sweep buttons are
  // hidden rather than offered and then refused by the routes behind them.
  placeAiEnabled: {
    type: Boolean,
    default: false
  },
  locationAiEnabled: {
    type: Boolean,
    default: false
  },
  translateAiEnabled: {
    type: Boolean,
    default: false
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

