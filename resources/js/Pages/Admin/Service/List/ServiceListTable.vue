<template>
  <div data-slot="card" class="bg-card text-card-foreground flex flex-col h-full lg:h-auto rounded-xl border border-border shadow-sm">
    <div v-if="services?.data?.length > 0" class="flex flex-col h-full lg:h-auto min-h-0 lg:min-h-fit">
      <div class="flex-1 min-h-0 lg:min-h-fit overflow-y-auto lg:overflow-y-visible overflow-x-auto">
        <div data-slot="table-container" class="relative w-full py-3 sm:py-4">
          <table data-slot="table" class="w-full caption-bottom text-xs sm:text-sm min-w-full">
            <thead data-slot="table-header" class="[&_tr]:border-b [&_tr]:border-border">
              <tr data-slot="table-row" class="hover:bg-muted/50 border-b border-border transition-colors">
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap min-w-[200px] sm:min-w-[300px]">
                  {{ t.service?.details || 'Service Details' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap w-32 sm:w-40 text-center hidden sm:table-cell">
                  {{ t.service?.category || 'Category' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap w-24 sm:w-32 text-center hidden md:table-cell">
                  {{ t.service?.price || 'Price' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap w-20 sm:w-24 text-center hidden lg:table-cell">
                  {{ t.service?.discount || 'Discount' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap w-20 sm:w-28 text-center">
                  {{ t.common?.actions || 'Actions' }}
                </th>
              </tr>
            </thead>
            <tbody data-slot="table-body" class="[&_tr:last-child]:border-0">
              <tr
                v-for="service in services.data"
                :key="service.id"
                data-slot="table-row"
                class="border-b border-border transition-colors hover:bg-muted/50"
              >
                <td data-slot="table-cell" class="p-2 sm:p-3 align-middle">
                  <div class="flex items-start gap-2 sm:gap-4">
                    <div v-if="service.image" class="flex-shrink-0 hidden sm:block">
                      <img :src="service.image" :alt="service.title" class="w-16 h-16 object-cover rounded-lg border border-border" />
                    </div>
                    <div class="flex-1 min-w-0 space-y-1 sm:space-y-1.5">
                      <div class="flex items-center gap-2 flex-wrap">
                        <Link
                          :href="getEditRoute(service.slug)"
                          class="font-semibold text-sm sm:text-base text-foreground hover:text-golden-yellow transition-colors cursor-pointer max-w-[200px] sm:max-w-[250px] break-words"
                          :title="service.title"
                        >
                          {{ service.title || '-' }}
                        </Link>
                        <span
                          v-if="service.tag"
                          :class="tagBadgeClass(service.tag)"
                          class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium ring-1 ring-inset whitespace-nowrap"
                        >
                          {{ tagLabel(service.tag) }}
                        </span>
                        <span
                          v-for="tagItem in (service.tags || [])"
                          :key="tagItem.id"
                          class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium ring-1 ring-inset whitespace-nowrap"
                          :style="tagChipStyle(tagItem)"
                        >
                          <span v-if="tagItem.icon">{{ tagItem.icon }}</span>
                          {{ tagItem.name }}
                        </span>
                      </div>
                      <div class="sm:hidden text-xs text-muted-foreground">
                        {{ service.category?.name || '-' }}
                      </div>
                      <div v-if="service.short_subject" class="max-w-[200px] truncate text-xs text-muted-foreground">
                        {{ service.short_subject }}
                      </div>
                      <div class="md:hidden text-xs">
                        <span v-if="hasDiscount(service)" class="text-muted-foreground line-through mr-2">
                          {{ formatPrice(service.old_price) }}
                        </span>
                        <span class="text-emerald-500 font-semibold">
                          {{ formatPrice(service.new_price) }}
                        </span>
                      </div>
                    </div>
                  </div>
                </td>
                <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap text-center hidden sm:table-cell">
                  <span class="text-muted-foreground">{{ service.category?.name || '-' }}</span>
                </td>
                <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap text-center hidden md:table-cell">
                  <div class="flex flex-col items-center gap-1 text-sm">
                    <span v-if="hasDiscount(service)" class="text-muted-foreground line-through text-xs">
                      {{ formatPrice(service.old_price) }}
                    </span>
                    <span class="text-emerald-500 font-semibold">
                      {{ formatPrice(service.new_price) }}
                    </span>
                  </div>
                </td>
                <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap text-center hidden lg:table-cell">
                  <div v-if="hasDiscount(service)" class="flex items-center justify-center">
                    <span class="inline-flex items-center rounded-full bg-emerald-500/10 px-2 py-1 text-xs font-medium text-emerald-500 ring-1 ring-inset ring-emerald-500/20">
                      {{ getDiscountPercentage(service) }}% {{ t.service?.off || 'OFF' }}
                    </span>
                  </div>
                  <span v-else class="text-muted-foreground text-xs">-</span>
                </td>
                <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap text-center">
                  <div class="flex items-center justify-center gap-2">
                    <Link
                      :href="getEditRoute(service.slug)"
                      class="inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 flex items-center gap-2 text-emerald-bright hover:!bg-emerald-bright/10 hover:!text-emerald-bright"
                      :class="{ 'opacity-50 cursor-not-allowed pointer-events-none': !service.slug }"
                      :title="t.common?.edit || 'Edit'"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3">
                        <path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"></path>
                      </svg>
                    </Link>
                    <button
                      :disabled="!service.slug"
                      @click="service.slug && $emit('delete', service.slug)"
                      class="inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 border bg-background shadow-xs hover:bg-destructive hover:text-white dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 flex items-center gap-2 text-destructive hover:!bg-destructive/10 hover:!text-destructive"
                      :title="t.common?.delete || 'Delete'"
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
              <span class="hidden sm:inline">{{ (t.common?.showing_results || 'Showing :from to :to of :total results').replace(':from', services.meta?.from || 0).replace(':to', services.meta?.to || 0).replace(':total', services.meta?.total || 0) }}</span>
              <span class="sm:hidden">{{ services.meta?.from || 0 }}-{{ services.meta?.to || 0 }}/{{ services.meta?.total || 0 }}</span>
            </div>
            <div class="flex items-center gap-2 order-2 flex-shrink-0">
              <p class="text-xs sm:text-sm font-medium whitespace-nowrap hidden sm:inline">{{ t.common?.rows_per_page || 'Rows per page' }}</p>
              <p class="text-xs sm:text-sm font-medium whitespace-nowrap sm:hidden">{{ t.common?.per_page || 'Per page' }}</p>
              <select
                :value="services.meta?.per_page || 15"
                @change="handlePerPageChange"
                dir="ltr"
                translate="no"
                class="border-input rounded-md border bg-transparent px-2 sm:px-3 py-1.5 sm:py-2 text-xs sm:text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 h-7 sm:h-8 w-[60px] sm:w-[70px] cursor-pointer"
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
                v-if="services?.meta?.links?.length > 0"
                :links="services?.meta?.links"
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
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-10 h-10 sm:w-12 sm:h-12 text-golden-yellow subtle-float">
            <rect x="3" y="7" width="18" height="13" rx="2"></rect>
            <path d="M9 7V5a2 2 0 012-2h2a2 2 0 012 2v2"></path>
            <line x1="3" y1="12" x2="21" y2="12"></line>
          </svg>
        </div>
        <h3 class="text-xl sm:text-2xl font-bold mb-1 text-foreground">{{ t.service?.not_found || 'No Services Found' }}</h3>
        <p class="text-muted-foreground text-sm sm:text-base leading-relaxed">{{ t.service?.not_found_message || 'No services match your current filters. Try adjusting your search criteria.' }}</p>
        <Link
          v-if="canWrite"
          :href="route('admin.service.create')"
          data-slot="button"
          class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2 btn-golden"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
            <path d="M5 12h14"></path>
            <path d="M12 5v14"></path>
          </svg>
          {{ t.service?.add || 'Add Service' }}
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
import { usePermissions } from '@/composables/usePermissions';

const { canManage } = usePermissions();
// Create/export/import are writes: hidden from read-only accounts,
// and refused by the routes behind them either way.
const canWrite = computed(() => canManage('manage own services', 'manage services'));


const { formatPrice } = usePriceFormat();

const props = defineProps({
  services: {
    type: Object,
    required: true
  }
});

defineEmits(['delete']);

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const hasDiscount = (service) => {
  return service.old_price && service.new_price && service.old_price > service.new_price;
};

const getDiscountPercentage = (service) => {
  if (!hasDiscount(service)) return 0;
  return Math.round(((service.old_price - service.new_price) / service.old_price) * 100);
};

const tagLabels = {
  new: 'New',
  soon: 'Coming Soon',
  available: 'Available',
};

const tagBadgeClasses = {
  new: 'bg-emerald-500/10 text-emerald-500 ring-emerald-500/20',
  soon: 'bg-amber-500/10 text-amber-500 ring-amber-500/20',
  available: 'bg-sky-500/10 text-sky-500 ring-sky-500/20',
};

const tagLabel = (tag) => tagLabels[tag] || tag;
const tagBadgeClass = (tag) => tagBadgeClasses[tag] || 'bg-muted/50 text-muted-foreground ring-border';

const tagChipStyle = (tagItem) => {
  const color = tagItem.color || '#6B7280';
  return {
    backgroundColor: `${color}1A`,
    color,
    borderColor: `${color}33`,
  };
};

const getEditRoute = (slug) => {
  if (!slug) return route('admin.service.list');
  try {
    return route('admin.service.edit', slug);
  } catch (error) {
    console.error('Error generating edit route:', error);
    return route('admin.service.list');
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
