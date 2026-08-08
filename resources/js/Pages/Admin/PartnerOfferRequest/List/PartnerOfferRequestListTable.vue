<template>
  <div data-slot="card" class="bg-card text-card-foreground flex flex-col h-full lg:h-auto rounded-xl border border-border shadow-sm">
    <div v-if="requests?.data?.length > 0" class="flex flex-col h-full lg:h-auto min-h-0 lg:min-h-fit">
      <div class="flex-1 min-h-0 lg:min-h-fit overflow-y-auto lg:overflow-y-visible overflow-x-auto">
        <div data-slot="table-container" class="relative w-full py-3 sm:py-4">
          <table data-slot="table" class="w-full caption-bottom text-xs sm:text-sm min-w-full">
            <thead data-slot="table-header" class="[&_tr]:border-b [&_tr]:border-border">
              <tr data-slot="table-row" class="hover:bg-muted/50 data-[state=selected]:bg-muted border-b border-border transition-colors">
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap">
                  {{ t.partner_offer_request?.offer || 'Offer' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap">
                  {{ t.partner_offer_request?.partner || 'Partner' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap">
                  {{ t.partner_offer_request?.phone_number || 'Phone Number' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap">
                  {{ t.partner_offer_request?.requested_at || 'Requested At' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap w-24 text-center">
                  {{ t.common?.status || 'Status' }}
                </th>
              </tr>
            </thead>
            <tbody data-slot="table-body" class="[&_tr:last-child]:border-0">
              <tr
                v-for="request in requests.data"
                :key="request.id"
                data-slot="table-row"
                class="data-[state=selected]:bg-muted border-b border-border transition-colors hover:bg-muted/50 cursor-pointer"
                @click="viewRequest(request.id)"
              >
                <td data-slot="table-cell" class="p-2 sm:p-3 align-middle">
                  <div class="flex items-center gap-2">
                    <span v-if="request.is_unread" class="w-2 h-2 rounded-full bg-sky-400 flex-shrink-0" :title="t.partner_offer_request?.unread || 'Unread'"></span>
                    <span class="text-sm font-medium text-foreground">{{ request.offer?.title || '-' }}</span>
                  </div>
                </td>
                <td data-slot="table-cell" class="p-2 sm:p-3 align-middle">
                  <span class="text-sm text-muted-foreground">{{ request.offer?.partner?.title || '-' }}</span>
                </td>
                <td data-slot="table-cell" class="p-2 sm:p-3 align-middle">
                  <span class="text-sm font-mono text-foreground" dir="ltr">{{ request.phone_number }}</span>
                </td>
                <td data-slot="table-cell" class="p-2 sm:p-3 align-middle">
                  <span class="text-sm text-muted-foreground">{{ request.created_at }}</span>
                </td>
                <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap text-center">
                  <span v-if="request.is_unread" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-sky-400/10 text-sky-400">
                    <span class="w-1.5 h-1.5 rounded-full bg-sky-400"></span>
                    {{ t.partner_offer_request?.new || 'New' }}
                  </span>
                  <span v-else class="text-xs text-muted-foreground">{{ t.partner_offer_request?.read || 'Read' }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="border-t border-border flex-shrink-0">
        <div class="border-t border-border/50 px-3 sm:px-4 md:px-6 py-2.5 sm:py-3 md:py-4 w-full">
          <div class="flex flex-row items-center justify-between gap-2 sm:gap-3 lg:gap-4 w-full flex-wrap">
            <div class="text-xs sm:text-sm text-muted-foreground order-1 flex-shrink-0 min-w-0">
              <span class="hidden sm:inline">{{ (t.common?.showing_results || 'Showing :from to :to of :total results').replace(':from', requests.meta?.from || 0).replace(':to', requests.meta?.to || 0).replace(':total', requests.meta?.total || 0) }}</span>
              <span class="sm:hidden">{{ requests.meta?.from || 0 }}-{{ requests.meta?.to || 0 }}/{{ requests.meta?.total || 0 }}</span>
            </div>
            <div class="flex items-center gap-2 order-2 flex-shrink-0">
              <p class="text-xs sm:text-sm font-medium whitespace-nowrap hidden sm:inline">{{ t.common?.rows_per_page || 'Rows per page' }}</p>
              <p class="text-xs sm:text-sm font-medium whitespace-nowrap sm:hidden">{{ t.common?.per_page || 'Per page' }}</p>
              <select
                :value="requests.meta?.per_page || 15"
                @change="handlePerPageChange"
                dir="ltr"
                translate="no"
                class="border-input focus-visible:border-ring focus-visible:ring-ring/50 rounded-md border bg-transparent px-2 sm:px-3 py-1.5 sm:py-2 text-xs sm:text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] h-7 sm:h-8 w-[60px] sm:w-[70px] cursor-pointer"
              >
                <option value="10">10</option>
                <option value="15">15</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
              </select>
            </div>
            <div class="order-3 flex-shrink-0 min-w-0">
              <Pagination
                v-if="requests?.meta?.links?.length > 0"
                :links="requests?.meta?.links"
              />
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-else data-slot="card-content" class="p-12">
      <div class="text-center max-w-md mx-auto space-y-6">
        <div class="inline-flex items-center justify-center w-20 h-20 sm:w-24 sm:h-24 rounded-full bg-golden-yellow/10 border border-golden-yellow/20 mb-6 shadow-lg shadow-golden-yellow/10">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-10 h-10 sm:w-12 sm:h-12 text-golden-yellow subtle-float">
            <rect x="2" y="4" width="20" height="16" rx="2"/>
            <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
          </svg>
        </div>
        <h3 class="text-xl sm:text-2xl font-bold mb-1 text-foreground">{{ t.partner_offer_request?.not_found || 'No Requests Found' }}</h3>
        <p class="text-muted-foreground text-sm sm:text-base leading-relaxed">{{ t.partner_offer_request?.not_found_message || 'No requests have been submitted yet.' }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import Pagination from "@/Pages/_components/Pagination.vue";
import { router, usePage } from "@inertiajs/vue3";
import { computed } from 'vue';

defineProps({
  requests: {
    type: Object,
    required: true,
  },
});

defineEmits([]);

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const handlePerPageChange = (event) => {
  const perPage = event.target.value;
  const currentUrl = new URL(window.location.href);
  currentUrl.searchParams.set('per_page', perPage);
  currentUrl.searchParams.set('page', '1');
  router.visit(currentUrl.toString(), {
    preserveState: false,
    preserveScroll: false,
  });
};

const viewRequest = (id) => {
  router.visit(route('admin.partner-offer-request.show', id));
};
</script>
