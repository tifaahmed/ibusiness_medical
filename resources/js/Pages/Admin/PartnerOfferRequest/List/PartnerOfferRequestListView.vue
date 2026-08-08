<template>
  <PartnerLayout>
    <div class="flex flex-col h-full lg:h-auto w-full max-w-full overflow-x-hidden">
      <div class="flex-shrink-0 space-y-2 sm:space-y-3 md:space-y-4 p-2 sm:p-3 md:p-4 lg:p-6 pb-2 sm:pb-3 md:pb-4 lg:pb-4 w-full max-w-full overflow-hidden">
        <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-2 sm:gap-3 md:gap-4 rounded-xl border border-border py-2 sm:py-3 md:py-4 shadow-sm overflow-hidden w-full max-w-full">
          <div data-slot="card-header" class="flex flex-row items-center justify-between py-2 px-3 sm:px-4 md:px-6 w-full overflow-hidden gap-2 sm:gap-4">
            <div data-slot="card-title" class="leading-none font-semibold min-w-0 flex-1">
              <div class="title-golden min-w-0 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon sm:w-6 sm:h-6 flex-shrink-0">
                  <rect x="2" y="4" width="20" height="16" rx="2"/>
                  <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                </svg>
                <span class="text-sm sm:text-base truncate block min-w-0">{{ t.partner_offer_request?.management || 'Offer Requests Management' }}</span>
              </div>
            </div>
          </div>

          <div data-slot="card-content" class="px-2 sm:px-4 md:px-6 space-y-2 sm:space-y-3 md:space-y-4 w-full max-w-full overflow-hidden min-w-0">
            <PartnerOfferRequestFilterContent
              :initial-filters="filters"
              @filter-change="handleFilterChange"
            />
          </div>
        </div>
      </div>

      <div class="flex-1 min-h-0 lg:min-h-fit w-full max-w-full px-2 sm:px-3 md:px-4 lg:px-6 pb-2 sm:pb-3 md:pb-4 lg:pb-6 overflow-hidden lg:overflow-visible">
        <PartnerOfferRequestListTable :requests="requests" />
      </div>
    </div>
  </PartnerLayout>
</template>

<script setup>
import PartnerLayout from "../../PartnerOffer/PartnerLayout.vue";
import PartnerOfferRequestFilterContent from "./PartnerOfferRequestFilterContent.vue";
import PartnerOfferRequestListTable from "./PartnerOfferRequestListTable.vue";
import { Link, usePage } from "@inertiajs/vue3";
import { ref, computed } from "vue";

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const props = defineProps({
  requests: {
    type: Object,
    required: true,
  },
  filters: {
    type: Object,
    default: () => ({
      search: '',
      partner_offer_id: '',
    }),
  },
});

const filters = ref(props.filters || { search: '', partner_offer_id: '' });

const handleFilterChange = (newFilters) => {
  filters.value = newFilters;
};
</script>
