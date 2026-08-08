<template>
  <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
    <div v-if="offers?.data?.length > 0">
      <div class="overflow-x-auto">
        <div data-slot="table-container" class="relative w-full py-3 sm:py-4">
          <table data-slot="table" class="w-full caption-bottom text-xs sm:text-sm">
            <thead data-slot="table-header" class="[&_tr]:border-b [&_tr]:border-border">
              <tr data-slot="table-row" class="hover:bg-muted/50 data-[state=selected]:bg-muted border-b border-border transition-colors">
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap w-20 text-center">
                  {{ t.partner_offer?.image || 'Image' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap min-w-[200px]">
                  {{ t.partner_offer?.title || 'Title' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap">
                  {{ t.partner_offer?.partner || 'Partner' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap text-center">
                  {{ t.partner_offer?.new_price || 'New Price' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap w-20 sm:w-28 text-center">
                  {{ t.common?.actions || 'Actions' }}
                </th>
              </tr>
            </thead>
            <tbody data-slot="table-body" class="[&_tr:last-child]:border-0">
              <tr
                v-for="offer in offers.data"
                :key="offer.id"
                data-slot="table-row"
                class="data-[state=selected]:bg-muted border-b border-border transition-colors hover:bg-muted/50 opacity-75"
              >
                <td data-slot="table-cell" class="p-2 sm:p-3 align-middle text-center">
                  <div class="inline-flex items-center justify-center w-12 h-12 sm:w-14 sm:h-14 rounded-md bg-muted overflow-hidden border border-border">
                    <img
                      v-if="offer.header_image"
                      :src="offer.header_image"
                      :alt="offer.title"
                      class="w-full h-full object-cover"
                      loading="lazy"
                    />
                    <svg v-else xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-6 h-6 text-muted-foreground">
                      <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/>
                      <circle cx="9" cy="9" r="2"/>
                      <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
                    </svg>
                  </div>
                </td>
                <td data-slot="table-cell" class="p-2 sm:p-3 align-middle">
                  <span class="font-semibold text-sm sm:text-base text-foreground block break-words">
                    {{ offer.title || '-' }}
                  </span>
                </td>
                <td data-slot="table-cell" class="p-2 sm:p-3 align-middle">
                  <span class="text-sm text-muted-foreground">{{ offer.partner?.title || '-' }}</span>
                </td>
                <td data-slot="table-cell" class="p-2 sm:p-3 align-middle text-center">
                  <span v-if="offer.new_price" class="text-sm font-semibold text-emerald-400">
                    {{ formatPrice(offer.new_price) }}
                  </span>
                  <span v-else class="text-sm text-muted-foreground">-</span>
                </td>
                <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap text-center">
                  <div class="flex items-center justify-center gap-2">
                    <button
                      @click="$emit('restore', offer.id)"
                      class="inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5 flex items-center gap-2 text-green-600 hover:!bg-green-600/10 hover:!text-green-600 dark:text-green-400"
                      :title="t.common?.restore || 'Restore'"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3">
                        <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path>
                        <path d="M3 3v5h5"></path>
                      </svg>
                    </button>
                    <button
                      @click="$emit('force-delete', offer.id)"
                      class="inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all border bg-background shadow-xs hover:bg-destructive hover:text-white dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5 flex items-center gap-2 text-destructive hover:!bg-destructive/10 hover:!text-destructive"
                      :title="t.common?.force_delete || 'Permanently Delete'"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3">
                        <path d="M3 6h18"></path>
                        <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                        <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                      </svg>
                    </button>
                  </div>
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
              <span class="hidden sm:inline">{{ (t.common?.showing_results || 'Showing :from to :to of :total results').replace(':from', offers.meta?.from || 0).replace(':to', offers.meta?.to || 0).replace(':total', offers.meta?.total || 0) }}</span>
              <span class="sm:hidden">{{ offers.meta?.from || 0 }}-{{ offers.meta?.to || 0 }}/{{ offers.meta?.total || 0 }}</span>
            </div>
            <div class="flex items-center gap-2 order-2 flex-shrink-0">
              <p class="text-xs sm:text-sm font-medium whitespace-nowrap hidden sm:inline">{{ t.common?.rows_per_page || 'Rows per page' }}</p>
              <p class="text-xs sm:text-sm font-medium whitespace-nowrap sm:hidden">{{ t.common?.per_page || 'Per page' }}</p>
              <select
                :value="offers.meta?.per_page || 15"
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
                v-if="offers?.meta?.links?.length > 0"
                :links="offers?.meta?.links"
              />
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-else data-slot="card-content" class="p-12">
      <div class="text-center max-w-md mx-auto space-y-6">
        <div class="inline-flex items-center justify-center w-20 h-20 sm:w-24 sm:h-24 rounded-full bg-golden-yellow/10 border border-golden-yellow/20 mb-6 shadow-lg shadow-golden-yellow/10">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash2 w-10 h-10 sm:w-12 sm:h-12 text-golden-yellow subtle-float">
            <path d="M3 6h18"></path>
            <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
            <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
            <line x1="10" x2="10" y1="11" y2="17"></line>
            <line x1="14" x2="14" y1="11" y2="17"></line>
          </svg>
        </div>
        <h3 class="text-xl sm:text-2xl font-bold mb-1 text-foreground">{{ t.partner_offer?.trash_empty || 'Trash is Empty' }}</h3>
        <p class="text-muted-foreground text-sm sm:text-base leading-relaxed">{{ t.partner_offer?.trash_empty_message || 'No deleted offers found.' }}</p>
        <Link
          :href="route('admin.partner-offer.list')"
          data-slot="button"
          class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2 has-[>svg]:px-3 btn-golden"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
            <path d="m12 19-7-7 7-7"></path>
            <path d="M19 12H5"></path>
          </svg>
          {{ t.common?.back_to_list || 'Back to List' }}
        </Link>
      </div>
    </div>
  </div>
</template>

<script setup>
import Pagination from "@/Pages/_components/Pagination.vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import { computed } from 'vue';
import { usePriceFormat } from '@/composables/usePriceFormat';

const { formatPrice } = usePriceFormat();

defineProps({
  offers: {
    type: Object,
    required: true,
  },
});

defineEmits(['restore', 'force-delete']);

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
</script>
