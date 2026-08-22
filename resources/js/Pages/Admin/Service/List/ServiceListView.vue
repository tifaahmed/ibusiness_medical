<template>
  <ServiceLayout>
    <div class="flex flex-col h-full lg:h-auto w-full max-w-full overflow-x-hidden">
      <div class="flex-shrink-0 space-y-2 sm:space-y-3 md:space-y-4 p-2 sm:p-3 md:p-4 lg:p-6 pb-2 sm:pb-3 md:pb-4 lg:pb-4 w-full max-w-full overflow-hidden">
        <!-- Header Card with Actions and Filters -->
        <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-2 sm:gap-3 md:gap-4 rounded-xl border border-border py-2 sm:py-3 md:py-4 shadow-sm overflow-hidden w-full max-w-full">
          <div data-slot="card-header" class="flex flex-row items-center justify-between py-2 px-3 sm:px-4 md:px-6 w-full overflow-hidden gap-2 sm:gap-4">
            <div data-slot="card-title" class="leading-none font-semibold min-w-0 flex-1">
              <div class="title-golden min-w-0 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sm:w-6 sm:h-6 flex-shrink-0">
                  <rect x="3" y="7" width="18" height="13" rx="2"></rect>
                  <path d="M9 7V5a2 2 0 012-2h2a2 2 0 012 2v2"></path>
                  <line x1="3" y1="12" x2="21" y2="12"></line>
                </svg>
                <span class="text-sm sm:text-base truncate block min-w-0">{{ t.service?.management || 'Services Management' }}</span>
              </div>
            </div>
            <Link
              v-if="canWrite"
              :href="route('admin.service.create')"
              data-slot="button"
              class="inline-flex items-center cursor-pointer justify-center gap-1.5 sm:gap-2 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-8 sm:h-9 px-2 sm:px-3 md:px-4 py-2 flex-shrink-0 btn-golden"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 sm:h-4 sm:w-4">
                <path d="M5 12h14"></path>
                <path d="M12 5v14"></path>
              </svg>
              <span class="hidden sm:inline">{{ t.service?.add_new || 'Add New Service' }}</span>
              <span class="sm:hidden">{{ t.common?.add || 'Add' }}</span>
            </Link>
          </div>

          <div data-slot="card-content" class="px-2 sm:px-4 md:px-6 space-y-2 sm:space-y-3 md:space-y-4 w-full max-w-full overflow-hidden min-w-0">
            <ServiceListFilterContent
              :initial-filters="filters"
              @filter-change="handleFilterChange"
            />
          </div>
        </div>
      </div>

      <div class="flex-1 min-h-0 lg:min-h-fit w-full max-w-full px-2 sm:px-3 md:px-4 lg:px-6 pb-2 sm:pb-3 md:pb-4 lg:pb-6 overflow-hidden lg:overflow-visible">
        <ServiceListTable :services="services" @delete="handleDelete" />
      </div>
    </div>
  </ServiceLayout>
</template>

<script setup>
import ServiceLayout from "../ServiceLayout.vue";
import ServiceListFilterContent from "./ServiceListFilterContent.vue";
import ServiceListTable from "./ServiceListTable.vue";
import { useServiceStore } from "../Stores/ServiceStore";
import { Link, usePage } from "@inertiajs/vue3";
import { ref, computed } from "vue";
import { usePermissions } from '@/composables/usePermissions';

const { canManage } = usePermissions();
// Create/export/import are writes: hidden from read-only accounts,
// and refused by the routes behind them either way.
const canWrite = computed(() => canManage('manage own services', 'manage services'));


const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const props = defineProps({
  services: {
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

const serviceStore = useServiceStore();
serviceStore.setServices(props.services);

const services = computed(() => props.services);

const filters = ref(props.filters || {
  search: ''
});

const handleDelete = (serviceSlug) => {
  serviceStore.confirmDelete(serviceSlug);
};

const handleFilterChange = (newFilters) => {
  filters.value = newFilters;
};
</script>
