<template>
  <div data-slot="card" class="bg-card text-card-foreground flex flex-col h-full lg:h-auto rounded-xl border border-border shadow-sm" :key="`offer-table-${locale}`">
    <div v-if="offers?.data?.length > 0" class="flex flex-col h-full lg:h-auto min-h-0 lg:min-h-fit">
      <div class="flex-1 min-h-0 lg:min-h-fit overflow-y-auto lg:overflow-y-visible overflow-x-auto">
        <div data-slot="table-container" class="relative w-full py-3 sm:py-4">
          <table data-slot="table" class="w-full caption-bottom text-xs sm:text-sm min-w-full">
            <thead data-slot="table-header" class="[&_tr]:border-b [&_tr]:border-border">
              <tr data-slot="table-row" class="hover:bg-muted/50 data-[state=selected]:bg-muted border-b border-border transition-colors">
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] min-w-[200px] sm:min-w-[300px]">
                  <button data-slot="button" class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive hover:text-accent-foreground dark:hover:bg-accent/50 has-[>svg]:px-3 h-auto p-0 font-semibold hover:bg-transparent">
                    {{ t.offer?.details || 'Offer Details' }}
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up-down h-4 w-4">
                      <path d="m21 16-4 4-4-4"></path>
                      <path d="M17 20V4"></path>
                      <path d="m3 8 4-4 4 4"></path>
                      <path d="M7 4v16"></path>
                    </svg>
                  </button>
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] w-32 sm:w-40 text-center hidden sm:table-cell">
                  {{ t.offer?.attached_to || 'Attached To' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] w-24 sm:w-32 text-center hidden md:table-cell">
                  {{ t.offer?.price || 'Price' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] w-20 sm:w-24 text-center hidden lg:table-cell">
                  {{ t.offer?.discount || 'Discount' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] w-20 sm:w-28 text-center">
                  {{ t.common?.actions || 'Actions' }}
                </th>
              </tr>
            </thead>
            <tbody data-slot="table-body" class="[&_tr:last-child]:border-0">
              <tr
                v-for="offer in offers.data"
                :key="offer.id"
                data-slot="table-row"
                class="data-[state=selected]:bg-muted border-b border-border transition-colors hover:bg-muted/50"
              >
                <td data-slot="table-cell" class="p-2 sm:p-3 align-middle [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px]">
                  <div class="flex items-start gap-2 sm:gap-4">
                    <!-- Thumbnail Image -->
                    <div v-if="offer.thumbnail" class="flex-shrink-0 hidden sm:block">
                      <img :src="offer.thumbnail" :alt="getTranslatedName(offer.title)" class="w-16 h-16 object-cover rounded-lg border border-border" />
                    </div>
                    <div class="flex-1 min-w-0 space-y-1 sm:space-y-1.5">
                      <Link
                        :href="getEditRoute(offer.slug)"
                        class="font-semibold text-sm sm:text-base text-foreground hover:text-golden-yellow transition-colors cursor-pointer block max-w-[200px] sm:max-w-[250px] break-words"
                        :title="getTranslatedName(offer.title)"
                      >
                        {{ getTranslatedName(offer.title) || '-' }}
                      </Link>
                      <!-- Show attached to on mobile (since column is hidden) -->
                      <div class="sm:hidden text-xs text-muted-foreground">
                        <span :class="[
                          'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset',
                          getOfferableTypeBadge(offer.offerable_type).class
                        ]">
                          {{ getOfferableTypeBadge(offer.offerable_type).label }}
                        </span>
                        <span class="ml-1">{{ getTranslatedName(offer.offerable?.name) || '-' }}</span>
                      </div>
                      <div v-if="getTranslatedName(offer.short_description) || offer.phone" class="flex flex-col gap-1 text-xs text-muted-foreground">
                        <div v-if="offer.phone" class="flex items-center gap-1">
                          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-phone h-3 w-3">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                          </svg>
                          <span>{{ offer.phone }}</span>
                        </div>
                        <div v-if="getTranslatedName(offer.short_description)" class="max-w-[200px] truncate">
                          {{ getTranslatedName(offer.short_description) }}
                        </div>
                      </div>
                      <!-- Show price on mobile (since column is hidden) -->
                      <div class="md:hidden text-xs">
                        <span v-if="hasDiscount(offer)" class="text-muted-foreground line-through mr-2">
                          {{ formatPrice(offer.old_price) }}
                        </span>
                        <span class="text-emerald-500 font-semibold">
                          {{ formatPrice(offer.price) }}
                        </span>
                      </div>
                    </div>
                  </div>
                </td>
                <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] text-center hidden sm:table-cell">
                  <div class="flex flex-col items-center gap-1 text-sm">
                    <span :class="[
                      'inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset',
                      getOfferableTypeBadge(offer.offerable_type).class
                    ]">
                      {{ getOfferableTypeBadge(offer.offerable_type).label }}
                    </span>
                    <span class="text-muted-foreground">{{ getTranslatedName(offer.offerable?.name) || '-' }}</span>
                  </div>
                </td>
                <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] text-center hidden md:table-cell">
                  <div class="flex flex-col items-center gap-1 text-sm">
                    <span v-if="hasDiscount(offer)" class="text-muted-foreground line-through text-xs">
                      {{ formatPrice(offer.old_price) }}
                    </span>
                    <span class="text-emerald-500 font-semibold">
                      {{ formatPrice(offer.price) }}
                    </span>
                  </div>
                </td>
                <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] text-center hidden lg:table-cell">
                  <div v-if="hasDiscount(offer)" class="flex items-center justify-center">
                    <span class="inline-flex items-center rounded-full bg-emerald-500/10 px-2 py-1 text-xs font-medium text-emerald-500 ring-1 ring-inset ring-emerald-500/20">
                      {{ getDiscountPercentage(offer) }}% {{ t.offer?.off || 'OFF' }}
                    </span>
                  </div>
                  <span v-else class="text-muted-foreground text-xs">-</span>
                </td>
                <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] text-center">
                  <div class="flex items-center justify-center gap-2">
                    <Link
                      :href="getEditRoute(offer.slug)"
                      class="inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5 flex items-center gap-2 text-emerald-bright hover:!bg-emerald-bright/10 hover:!text-emerald-bright"
                      :class="{ 'opacity-50 cursor-not-allowed pointer-events-none': !offer.slug }"
                      :title="t.common?.edit || 'Edit'"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-square-pen w-3 h-3">
                        <path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"></path>
                      </svg>
                    </Link>
                    <button
                      :disabled="!offer.slug"
                      @click="offer.slug && $emit('delete', offer.slug)"
                      class="inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background shadow-xs hover:bg-destructive hover:text-white dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5 flex items-center gap-2 text-destructive hover:!bg-destructive/10 hover:!text-destructive"
                      :title="t.common?.delete || 'Delete'"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash-2 w-3 h-3">
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
                class="border-input data-[placeholder]:text-gray-foreground [&_svg:not([class*='text-'])]:text-gray-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:bg-input/30 dark:hover:bg-input/50 rounded-md border bg-transparent px-2 sm:px-3 py-1.5 sm:py-2 text-xs sm:text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 h-7 sm:h-8 w-[60px] sm:w-[70px] cursor-pointer"
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

    <!-- Empty State -->
    <div v-else data-slot="card-content" class="p-12">
      <div class="text-center max-w-md mx-auto space-y-6">
        <div class="inline-flex items-center justify-center w-20 h-20 sm:w-24 sm:h-24 rounded-full bg-golden-yellow/10 border border-golden-yellow/20 mb-6 shadow-lg shadow-golden-yellow/10">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-tag w-10 h-10 sm:w-12 sm:h-12 text-golden-yellow subtle-float">
            <path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z"></path>
            <path d="M7 7h.01"></path>
          </svg>
        </div>
        <h3 class="text-xl sm:text-2xl font-bold mb-1 text-foreground">{{ t.offer?.not_found || 'No Offers Found' }}</h3>
        <p class="text-muted-foreground text-sm sm:text-base leading-relaxed">{{ t.offer?.not_found_message || 'No offers match your current filters. Try adjusting your search criteria.' }}</p>
        <Link
          :href="route('admin.offer.create')"
          data-slot="button"
          class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2 has-[>svg]:px-3 btn-golden"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus w-4 h-4">
            <path d="M5 12h14"></path>
            <path d="M12 5v14"></path>
          </svg>
          {{ t.offer?.add || 'Add Offer' }}
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

const props = defineProps({
  offers: {
    type: Object,
    required: true
  }
});

defineEmits(['delete']);

const page = usePage();
const locale = computed(() => {
  return page.props.locale || 'ar';
});
const t = computed(() => page.props.translations?.admin || {});

const getTranslatedName = (name) => {
  if (typeof name === 'string') return name;
  if (typeof name === 'object' && name !== null) {
    const currentLocale = page.props.locale || 'ar';

    let translatedValue = name[currentLocale] || '';

    if (currentLocale === 'en') {
      if (translatedValue && /[\u0600-\u06FF]/.test(translatedValue)) {
        return '';
      }
      if (!translatedValue || translatedValue.trim() === '') {
        return '';
      }
      return translatedValue;
    }

    if (currentLocale === 'ar') {
      return name['ar'] || name['en'] || Object.values(name)[0] || '';
    }

    return translatedValue || name['ar'] || name['en'] || Object.values(name)[0] || '';
  }
  return '';
};

const hasDiscount = (offer) => {
  return offer.old_price && offer.price && offer.old_price > offer.price;
};

const getDiscountPercentage = (offer) => {
  if (!hasDiscount(offer)) return 0;
  return Math.round(((offer.old_price - offer.price) / offer.old_price) * 100);
};

const getOfferableTypeBadge = (type) => {
  if (type === 'App\\Models\\Facility') {
    return {
      label: t.value.facility?.label || 'Facility',
      class: 'bg-blue-500/10 text-blue-500 ring-blue-500/20'
    };
  } else if (type === 'App\\Models\\FacilityBranch') {
    return {
      label: t.value.offer?.branch || t.value.facility_branch?.label || 'Branch',
      class: 'bg-purple-500/10 text-purple-500 ring-purple-500/20'
    };
  }
  return {
    label: t.value.offer?.unknown || 'Unknown',
    class: 'bg-gray-500/10 text-gray-500 ring-gray-500/20'
  };
};

const getEditRoute = (slug) => {
  if (!slug) {
    return route('admin.offer.list');
  }
  try {
    return route('admin.offer.edit', slug);
  } catch (error) {
    console.error('Error generating edit route:', error);
    return route('admin.offer.list');
  }
};

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
