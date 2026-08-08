<template>
  <PartnerLayout>
    <div class="flex flex-col h-full w-full max-w-full overflow-x-hidden">
      <div class="flex-shrink-0 space-y-2 sm:space-y-3 md:space-y-4 p-2 sm:p-3 md:p-4 lg:p-6 pb-2 sm:pb-3 md:pb-4 lg:pb-4 w-full max-w-full overflow-hidden">
        <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-2 sm:gap-3 md:gap-4 rounded-xl border border-border py-2 sm:py-3 md:py-4 shadow-sm overflow-hidden w-full max-w-full">
          <div data-slot="card-header" class="flex flex-row items-center justify-between py-2 px-3 sm:px-4 md:px-6 w-full overflow-hidden gap-2 sm:gap-4">
            <div data-slot="card-title" class="leading-none font-semibold min-w-0 flex-1">
              <div class="title-golden min-w-0 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon sm:w-6 sm:h-6 flex-shrink-0">
                  <rect x="2" y="4" width="20" height="16" rx="2"/>
                  <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                </svg>
                <span class="text-sm sm:text-base truncate block min-w-0">{{ t.partner_offer_request?.view_request || 'Request Details' }}</span>
              </div>
            </div>
            <Link
              :href="route('admin.partner-offer-request.list')"
              data-slot="button"
              class="inline-flex items-center cursor-pointer justify-center gap-1.5 sm:gap-2 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 sm:h-9 px-2 sm:px-3 md:px-4 py-2 has-[>svg]:px-3 flex-shrink-0"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5 sm:w-4 sm:h-4">
                <path d="m12 19-7-7 7-7"></path><path d="M19 12H5"></path>
              </svg>
              <span class="hidden sm:inline">{{ t.common?.back || 'Back' }}</span>
            </Link>
          </div>
        </div>
      </div>

      <div class="flex-1 min-h-0 w-full max-w-full px-2 sm:px-3 md:px-4 lg:px-6 pb-2 sm:pb-3 md:pb-4 lg:pb-6 overflow-hidden">
        <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm overflow-hidden">
          <div data-slot="card-content" class="p-4 sm:p-6 md:p-8 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label class="block text-xs font-medium text-muted-foreground mb-1">{{ t.partner_offer_request?.offer || 'Offer' }}</label>
                <p class="text-sm font-semibold text-foreground">{{ requestData.offer?.title || '-' }}</p>
              </div>
              <div>
                <label class="block text-xs font-medium text-muted-foreground mb-1">{{ t.partner_offer_request?.partner || 'Partner' }}</label>
                <p class="text-sm font-semibold text-foreground">{{ requestData.offer?.partner?.title || '-' }}</p>
              </div>
              <div>
                <label class="block text-xs font-medium text-muted-foreground mb-1">{{ t.partner_offer_request?.phone_number || 'Phone Number' }}</label>
                <p class="text-sm font-mono font-semibold text-foreground" dir="ltr">{{ requestData.phone_number }}</p>
              </div>
              <div>
                <label class="block text-xs font-medium text-muted-foreground mb-1">{{ t.partner_offer_request?.requested_at || 'Requested At' }}</label>
                <p class="text-sm font-semibold text-foreground">{{ requestData.created_at }}</p>
              </div>
              <div>
                <label class="block text-xs font-medium text-muted-foreground mb-1">{{ t.partner_offer_request?.read_at || 'Read At' }}</label>
                <p v-if="requestData.read_at" class="text-sm font-semibold text-foreground">{{ requestData.read_at }}</p>
                <p v-else class="text-sm text-muted-foreground italic">{{ t.partner_offer_request?.not_read_yet || 'Not read yet' }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </PartnerLayout>
</template>

<script setup>
import PartnerLayout from "../../PartnerOffer/PartnerLayout.vue";
import { Link, usePage } from "@inertiajs/vue3";
import { computed } from "vue";

const props = defineProps({
  request: {
    type: Object,
    required: true,
  },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const requestData = computed(() => props.request || {});
</script>
