<template>
  <MembershipUsageLayout>
    <div class="flex flex-col h-full lg:h-auto w-full max-w-full overflow-x-hidden">
      <div class="flex-shrink-0 space-y-2 sm:space-y-3 md:space-y-4 p-2 sm:p-3 md:p-4 lg:p-6 pb-2 sm:pb-3 md:pb-4 lg:pb-4 w-full max-w-full overflow-hidden">
        <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-2 sm:gap-3 md:gap-4 rounded-xl border border-border py-2 sm:py-3 md:py-4 shadow-sm overflow-hidden w-full max-w-full">
          <div data-slot="card-header" class="flex flex-row items-center justify-between py-2 px-3 sm:px-4 md:px-6 w-full overflow-hidden gap-2 sm:gap-4">
            <div data-slot="card-title" class="leading-none font-semibold min-w-0 flex-1">
              <div class="title-golden min-w-0 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon sm:w-6 sm:h-6 flex-shrink-0">
                  <path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2z"></path>
                  <path d="M12 8v4l3 3"></path>
                </svg>
                <span class="text-sm sm:text-base truncate block min-w-0">{{ t.title || 'Membership Usages' }}</span>
              </div>
            </div>
            <Link
              :href="route('admin.membership-usage.create')"
              data-slot="button"
              class="inline-flex items-center cursor-pointer justify-center gap-1.5 sm:gap-2 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-8 sm:h-9 px-2 sm:px-3 md:px-4 py-2 flex-shrink-0 btn-golden"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus h-3.5 w-3.5 sm:h-4 sm:w-4">
                <path d="M5 12h14"></path>
                <path d="M12 5v14"></path>
              </svg>
              <span class="hidden sm:inline">{{ t.add_new || 'Add New Usage' }}</span>
              <span class="sm:hidden">{{ t.add_new || 'Add' }}</span>
            </Link>
          </div>

          <div data-slot="card-content" class="px-2 sm:px-4 md:px-6 space-y-2 sm:space-y-3 md:space-y-4 w-full max-w-full overflow-hidden min-w-0">
            <MembershipUsageListFilterContent
              :initial-filters="filters"
              :facilities="facilities"
              :facility-types="facilityTypes"
              @filter-change="handleFilterChange"
            />
          </div>
        </div>
      </div>

      <div class="flex-1 min-h-0 lg:min-h-fit w-full max-w-full px-2 sm:px-3 md:px-4 lg:px-6 pb-2 sm:pb-3 md:pb-4 lg:pb-6 overflow-hidden lg:overflow-visible">
        <MembershipUsageListTable :usages="usages" @delete="handleDelete" />
      </div>
    </div>
  </MembershipUsageLayout>
</template>

<script setup>
import MembershipUsageLayout from "../MembershipUsageLayout.vue";
import MembershipUsageListFilterContent from "./MembershipUsageListFilterContent.vue";
import MembershipUsageListTable from "./MembershipUsageListTable.vue";
import { useMembershipUsageStore } from "../Stores/MembershipUsageStore";
import { Link, usePage } from "@inertiajs/vue3";
import { ref, computed } from "vue";

const props = defineProps({
  usages: { type: Object, required: true },
  filters: {
    type: Object,
    default: () => ({ search: '', facility_id: '', facility_type_id: '' }),
  },
  facilities: { type: Array, default: () => [] },
  facilityTypes: { type: Array, default: () => [] },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin?.membership_usage || {});

const usageStore = useMembershipUsageStore();
usageStore.setUsages(props.usages);

const usages = computed(() => props.usages);
const filters = ref(props.filters || { search: '', facility_id: '', facility_type_id: '' });

const handleDelete = (id) => {
  usageStore.confirmDelete(id);
};

const handleFilterChange = (newFilters) => {
  filters.value = newFilters;
};
</script>
