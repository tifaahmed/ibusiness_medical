<template>
  <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
    <div v-if="governorates?.data?.length > 0" data-slot="card-content" class="p-0">
      <div class="overflow-x-auto">
        <div data-slot="table-container" class="relative w-full overflow-x-auto">
          <table data-slot="table" class="w-full caption-bottom text-sm min-w-full">
            <thead data-slot="table-header" class="[&_tr]:border-b [&_tr]:border-border">
              <tr data-slot="table-row" class="hover:bg-muted/50 data-[state=selected]:bg-muted border-b border-border transition-colors">
                <th data-slot="table-head" class="text-foreground h-10 px-2 text-left align-middle font-medium whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] min-w-[300px]">
                  <button data-slot="button" class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive hover:text-accent-foreground dark:hover:bg-accent/50 has-[>svg]:px-3 h-auto p-0 font-semibold hover:bg-transparent">
                    {{ t.governorate?.details || 'Governorate Details' }}
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up-down h-4 w-4">
                      <path d="m21 16-4 4-4-4"></path>
                      <path d="M17 20V4"></path>
                      <path d="m3 8 4-4 4 4"></path>
                      <path d="M7 4v16"></path>
                    </svg>
                  </button>
                </th>
                <th data-slot="table-head" class="text-foreground h-10 px-2 align-middle font-medium whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] w-32 text-center">
                  {{ t.common?.facilities || 'Facilities' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-10 px-2 align-middle font-medium whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] w-32 text-center">
                  {{ t.common?.cities || 'Cities' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-10 px-2 align-middle font-medium whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] w-28 text-center">
                  {{ t.common?.actions || 'Actions' }}
                </th>
              </tr>
            </thead>
            <tbody data-slot="table-body" class="[&_tr:last-child]:border-0">
              <tr
                v-for="governorate in governorates.data"
                :key="governorate.id"
                data-slot="table-row"
                class="data-[state=selected]:bg-muted border-b border-border transition-colors hover:bg-muted/50"
              >
                <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px]">
                  <div class="flex items-center gap-4">
                    <div class="flex-1 min-w-0 space-y-2">
                      <Link
                        :href="getEditRoute(governorate.slug)"
                        class="font-semibold text-base text-foreground hover:text-golden-yellow transition-colors cursor-pointer block max-w-[250px] break-words whitespace-normal"
                        :title="getTranslatedName(governorate.name)"
                      >
                        {{ getTranslatedName(governorate.name) }}
                      </Link>
                      <div class="flex items-center gap-2 mt-1">
                        <div class="flex items-center gap-1 text-xs text-muted-foreground">
                          <span class="font-mono">{{ governorate.slug }}</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </td>
                <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] text-center">
                  <div class="flex justify-center">
                    <GovernorateFacilitiesDialog :governorate="governorate" />
                  </div>
                </td>
                <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] text-center">
                  <div class="flex items-center gap-1 text-sm justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-map-pin w-4 h-4 text-emerald-bright">
                      <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path>
                      <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                    <span>{{ governorate.cities_count || 0 }}</span>
                  </div>
                </td>
                <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] text-center">
                  <div class="flex items-center justify-center gap-2">
                    <Link
                      :href="getEditRoute(governorate.slug)"
                      class="inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5 flex items-center gap-2 text-emerald-bright hover:!bg-emerald-bright/10 hover:!text-emerald-bright"
                      :class="{ 'opacity-50 cursor-not-allowed pointer-events-none': !governorate.slug }"
                      title="Edit"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-square-pen w-3 h-3">
                        <path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"></path>
                      </svg>
                    </Link>
                    <button
                      :disabled="!governorate.slug"
                      @click="governorate.slug && $emit('delete', governorate.slug)"
                      class="inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background shadow-xs hover:bg-destructive hover:text-white dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5 flex items-center gap-2 text-destructive hover:!bg-destructive/10 hover:!text-destructive"
                      title="Delete"
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
      <div class="border-t">
        <div class="border-t border-border/50 px-3 sm:px-6 py-3 sm:py-4 w-full">
          <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between w-full">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-6 w-full">
              <div class="text-xs sm:text-sm text-muted-foreground order-2 sm:order-1">
                {{ (t.common?.showing_results || 'Showing :from to :to of :total results').replace(':from', governorates.meta?.from || 0).replace(':to', governorates.meta?.to || 0).replace(':total', governorates.meta?.total || 0) }}
              </div>
            </div>
            <div>
              <div class="flex items-center gap-2 order-1 sm:order-2">
                <p class="text-xs sm:text-sm font-medium whitespace-nowrap">{{ t.common?.rows_per_page || 'Rows per page' }}</p>
                <select 
                  :value="governorates.meta?.per_page || 15"
                  @change="handlePerPageChange"
                  dir="ltr" 
                  translate="no" 
                  class="border-input data-[placeholder]:text-gray-foreground [&_svg:not([class*='text-'])]:text-gray-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:bg-input/30 dark:hover:bg-input/50 rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 h-8 w-[70px] cursor-pointer"
                >
                  <option value="10">10</option>
                  <option value="15">15</option>
                  <option value="25">25</option>
                  <option value="50">50</option>
                  <option value="100">100</option>
                </select>
              </div>
            </div>
            <Pagination
              v-if="governorates?.meta?.links?.length > 0"
              :links="governorates?.meta?.links"
              class="order-3"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else data-slot="card-content" class="p-12">
      <div class="text-center max-w-md mx-auto space-y-6">
        <div class="inline-flex items-center justify-center w-20 h-20 sm:w-24 sm:h-24 rounded-full bg-golden-yellow/10 border border-golden-yellow/20 mb-6 shadow-lg shadow-golden-yellow/10">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-map-pin w-10 h-10 sm:w-12 sm:h-12 text-golden-yellow subtle-float">
            <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path>
            <circle cx="12" cy="10" r="3"></circle>
          </svg>
        </div>
        <h3 class="text-xl sm:text-2xl font-bold mb-1 text-foreground">{{ t.governorate?.not_found || 'No Governorates Found' }}</h3>
        <p class="text-muted-foreground text-sm sm:text-base leading-relaxed">No governorates match your current filters. Try adjusting your search criteria.</p>
        <Link
          v-if="canWrite"
          :href="route('admin.governorate.create')"
          data-slot="button"
          class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2 has-[>svg]:px-3 btn-golden"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus w-4 h-4">
            <path d="M5 12h14"></path>
            <path d="M12 5v14"></path>
          </svg>
          {{ t.governorate?.add || 'Add Governorate' }}
        </Link>
      </div>
    </div>
  </div>
</template>

<script setup>
import Pagination from "@/Pages/_components/Pagination.vue";
import GovernorateFacilitiesDialog from "./GovernorateFacilitiesDialog.vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import { computed } from "vue";
import { usePermissions } from '@/composables/usePermissions';

const { canManage } = usePermissions();
// Create/export/import are writes: hidden from read-only accounts,
// and refused by the routes behind them either way.
const canWrite = computed(() => canManage('manage governorates', 'manage own governorates'));


const props = defineProps({
  governorates: {
    type: Object,
    required: true
  }
});

defineEmits(['delete']);

const page = usePage();
const locale = page.props.locale || 'ar';
const t = computed(() => page.props.translations?.admin || {});

const getTranslatedName = (name) => {
  if (typeof name === 'string') return name;
  if (typeof name === 'object' && name !== null) {
    return name[locale] || name['ar'] || name['en'] || Object.values(name)[0] || '';
  }
  return '';
};

const getEditRoute = (slug) => {
  if (!slug) {
    return route('admin.governorate.list');
  }
  try {
    return route('admin.governorate.edit', slug);
  } catch (error) {
    console.error('Error generating edit route:', error);
    return route('admin.governorate.list');
  }
};

const handlePerPageChange = (event) => {
  const perPage = event.target.value;
  const currentUrl = new URL(window.location.href);
  currentUrl.searchParams.set('per_page', perPage);
  currentUrl.searchParams.set('page', '1'); // Reset to first page
  router.visit(currentUrl.toString(), {
    preserveState: false,
    preserveScroll: false,
  });
};
</script>

