<template>
  <div data-slot="card" class="bg-card text-card-foreground flex flex-col h-full lg:h-auto rounded-xl border border-border shadow-sm" :key="`facility-branch-table-${locale}`">
    <div v-if="facilityBranches?.data?.length > 0" class="flex flex-col h-full lg:h-auto min-h-0 lg:min-h-fit">
      <div class="flex-1 min-h-0 lg:min-h-fit overflow-y-auto lg:overflow-y-visible p-3 sm:p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 sm:gap-4">
          <div
            v-for="branch in facilityBranches.data"
            :key="branch.id"
            class="group relative flex flex-col gap-3 rounded-lg border border-border bg-background/40 p-3 sm:p-4 transition-colors hover:border-golden-yellow/50 hover:bg-muted/40"
          >
            <div class="flex items-start justify-between gap-2">
              <Link
                :href="getEditRoute(branch.slug)"
                class="font-semibold text-sm sm:text-base text-foreground hover:text-golden-yellow transition-colors cursor-pointer break-words min-w-0 flex-1"
                :title="getTranslatedName(branch.name)"
              >
                {{ getTranslatedName(branch.name) || '-' }}
              </Link>
              <div class="flex items-center gap-1.5 flex-shrink-0">
                <Link
                  :href="getEditRoute(branch.slug)"
                  class="inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 w-8 rounded-md text-emerald-bright hover:!bg-emerald-bright/10 hover:!text-emerald-bright"
                  :class="{ 'opacity-50 cursor-not-allowed pointer-events-none': !branch.slug }"
                  title="Edit"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-square-pen w-3.5 h-3.5">
                    <path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                    <path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"></path>
                  </svg>
                </Link>
                <button
                  :disabled="!branch.slug"
                  @click="branch.slug && $emit('delete', branch.slug)"
                  class="inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background shadow-xs hover:bg-destructive hover:text-white dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 w-8 rounded-md text-destructive hover:!bg-destructive/10 hover:!text-destructive"
                  title="Delete"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash-2 w-3.5 h-3.5">
                    <path d="M3 6h18"></path>
                    <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                    <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                  </svg>
                </button>
              </div>
            </div>

            <div class="grid grid-cols-1 gap-1.5 text-xs sm:text-sm">
              <div class="flex items-center gap-2 min-w-0">
                <span class="text-muted-foreground flex-shrink-0">{{ t.facility?.label || 'Facility' }}:</span>
                <span class="text-foreground truncate">{{ getTranslatedName(branch.facility?.name) || '-' }}</span>
              </div>
              <!-- Three different things that all used to read as one line of
                   grey text: the governorate, the city inside it, and the
                   street address somebody typed. Each gets its own colour so a
                   glance tells them apart — and a missing one is red, because
                   that is a row still to be fixed. -->
              <div class="flex flex-wrap items-center gap-1.5">
                <span
                  v-if="governorateNames(branch).length"
                  class="inline-flex items-center gap-1 rounded-md border border-emerald-400/50 bg-emerald-500/25 px-2 py-0.5 text-[11px] font-semibold text-emerald-950 dark:text-emerald-100"
                  :title="t.governorate?.label || 'Governorate'"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0"><path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/></svg>
                  <template v-for="(place, index) in governorateNames(branch)" :key="place.locale">
                    <span v-if="index" class="opacity-40" aria-hidden="true">·</span>
                    <span :dir="place.locale === 'ar' ? 'rtl' : 'ltr'" :lang="place.locale">{{ place.value }}</span>
                  </template>
                </span>
                <span
                  v-else
                  class="inline-flex items-center rounded-md border border-red-400/60 bg-red-500/30 px-2 py-0.5 text-[11px] font-semibold text-red-50"
                >
                  {{ t.governorate?.none || 'No governorate' }}
                </span>

                <span
                  v-if="cityNames(branch).length"
                  class="inline-flex items-center gap-1 rounded-md border border-amber-400/50 bg-amber-500/25 px-2 py-0.5 text-[11px] font-semibold text-amber-950 dark:text-amber-100"
                  :title="t.city?.label || 'City'"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0"><path d="M18 21V4a1 1 0 0 0-1-1H7a1 1 0 0 0-1 1v17"/><path d="M2 21h20"/><path d="M10 9h4"/><path d="M10 13h4"/><path d="M10 17h4"/></svg>
                  <template v-for="(place, index) in cityNames(branch)" :key="place.locale">
                    <span v-if="index" class="opacity-40" aria-hidden="true">·</span>
                    <span :dir="place.locale === 'ar' ? 'rtl' : 'ltr'" :lang="place.locale">{{ place.value }}</span>
                  </template>
                </span>
                <span
                  v-else
                  class="inline-flex items-center rounded-md border border-red-400/60 bg-red-500/30 px-2 py-0.5 text-[11px] font-semibold text-red-50"
                >
                  {{ t.city?.none || 'No city' }}
                </span>
              </div>
              <!-- The typed address: free text somebody wrote, not a row picked
                   from a table — a quiet ground of its own so it never reads as
                   a third place chip. Sits with the two chips it belongs with,
                   above the facility type. -->
              <div
                v-if="addressNames(branch).length"
                class="flex flex-col gap-1 rounded-md border border-white/25 bg-slate-900/35 px-2 py-1 text-xs text-white/90"
              >
                <div v-for="place in addressNames(branch)" :key="place.locale" class="flex items-start gap-1.5">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-map-pin h-3 w-3 flex-shrink-0 mt-0.5">
                    <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path>
                    <circle cx="12" cy="10" r="3"></circle>
                  </svg>
                  <span class="line-clamp-2" :dir="place.locale === 'ar' ? 'rtl' : 'ltr'" :lang="place.locale">{{ place.value }}</span>
                </div>
              </div>
              <!-- Red, like a missing governorate or city: an address the "No
                   address" filter finds is a row still to be fixed, and the
                   branch cannot be saved again until it is. -->
              <div
                v-if="missingAddressLocales(branch).length"
                class="flex items-start gap-1.5 rounded-md border border-red-400/60 bg-red-500/30 px-2 py-1 text-[11px] font-semibold text-red-50"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-map-pin h-3 w-3 flex-shrink-0 mt-0.5">
                  <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path>
                  <circle cx="12" cy="10" r="3"></circle>
                </svg>
                <span>
                  {{ t.facility_branch?.no_address || 'No address' }}
                  ({{ missingAddressLocales(branch).map(l => l.toUpperCase()).join(' + ') }})
                </span>
              </div>

              <div class="flex items-center gap-2 min-w-0">
                <span class="text-muted-foreground flex-shrink-0">{{ t.facility_type?.label || 'Facility Type' }}:</span>
                <span class="text-foreground truncate">{{ getTranslatedName(branch.facility?.facility_type?.name) || '-' }}</span>
              </div>
            </div>

            <div v-if="getPhonesArray(branch.phone).length > 0" class="flex flex-col gap-1 text-xs text-muted-foreground border-t border-border/60 pt-2">
              <div v-if="getPhonesArray(branch.phone).length > 0" class="flex flex-col gap-0.5">
                <div v-for="(phone, index) in getPhonesArray(branch.phone)" :key="index" class="flex items-center gap-1.5">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-phone h-3 w-3 flex-shrink-0">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                  </svg>
                  <span class="truncate" dir="ltr">{{ phone.number }}</span>
                  <span class="truncate text-muted-foreground/70">· {{ phoneLabel(phone.type) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="border-t border-border flex-shrink-0">
        <div class="border-t border-border/50 px-3 sm:px-4 md:px-6 py-2.5 sm:py-3 md:py-4 w-full">
          <div class="flex flex-row items-center justify-between gap-2 sm:gap-3 lg:gap-4 w-full flex-wrap">
            <div class="text-xs sm:text-sm text-muted-foreground order-1 flex-shrink-0 min-w-0">
              <span class="hidden sm:inline">{{ (t.common?.showing_results || 'Showing :from to :to of :total results').replace(':from', facilityBranches.meta?.from || 0).replace(':to', facilityBranches.meta?.to || 0).replace(':total', facilityBranches.meta?.total || 0) }}</span>
              <span class="sm:hidden">{{ facilityBranches.meta?.from || 0 }}-{{ facilityBranches.meta?.to || 0 }}/{{ facilityBranches.meta?.total || 0 }}</span>
            </div>
            <div class="flex items-center gap-2 order-2 flex-shrink-0">
              <p class="text-xs sm:text-sm font-medium whitespace-nowrap hidden sm:inline">{{ t.common?.rows_per_page || 'Rows per page' }}</p>
              <p class="text-xs sm:text-sm font-medium whitespace-nowrap sm:hidden">{{ t.common?.per_page || 'Per page' }}</p>
              <PerPageSelect
                :model-value="facilityBranches.meta?.per_page || 15"
                @update:model-value="handlePerPageChange"
              />
            </div>
            <div class="order-3 flex-shrink-0 min-w-0">
              <Pagination
                v-if="facilityBranches?.meta?.links?.length > 0"
                :links="facilityBranches?.meta?.links"
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
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-git-branch w-10 h-10 sm:w-12 sm:h-12 text-golden-yellow subtle-float">
            <line x1="6" x2="6" y1="3" y2="15"></line>
            <circle cx="18" cy="6" r="3"></circle>
            <circle cx="6" cy="18" r="3"></circle>
            <path d="M18 9a9 9 0 0 1-9 9"></path>
          </svg>
        </div>
        <h3 class="text-xl sm:text-2xl font-bold mb-1 text-foreground">{{ t.facility_branch?.not_found || 'No Facility Branches Found' }}</h3>
        <p class="text-muted-foreground text-sm sm:text-base leading-relaxed">{{ t.facility_branch?.not_found_message || 'No facility branches match your current filters. Try adjusting your search criteria.' }}</p>
        <Link
          v-if="canWrite"
          :href="route('admin.facility-branch.create')"
          data-slot="button"
          class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2 has-[>svg]:px-3 btn-golden"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus w-4 h-4">
            <path d="M5 12h14"></path>
            <path d="M12 5v14"></path>
          </svg>
          {{ t.facility_branch?.add || 'Add Branch' }}
        </Link>
      </div>
    </div>
  </div>
</template>

<script setup>
import Pagination from "@/Pages/_components/Pagination.vue";
import PerPageSelect from '@/Components/ui/PerPageSelect.vue';
import { Link, router, usePage } from "@inertiajs/vue3";
import { computed } from 'vue';
import { usePermissions } from '@/composables/usePermissions';
import { normalizePhoneEntries, phoneTypeLabel } from '@/lib/branchPhones';

const { canManage } = usePermissions();
// Create/export/import are writes: hidden from read-only accounts,
// and refused by the routes behind them either way.
const canWrite = computed(() => canManage('manage facility branches', 'manage own facility branches'));


const props = defineProps({
  facilityBranches: {
    type: Object,
    required: true
  }
});

defineEmits(['delete']);

const page = usePage();
// Make locale reactive by accessing it directly from page.props in computed
const locale = computed(() => {
  return page.props.locale || 'ar';
});
const t = computed(() => page.props.translations?.admin || {});

const getTranslatedName = (name) => {
  if (typeof name === 'string') return name;
  if (typeof name === 'object' && name !== null) {
    const currentLocale = page.props.locale || 'ar';

    return name[currentLocale] || name['ar'] || name['en'] || Object.values(name)[0] || '';
  }
  return '';
};

// A place name in every language it was stored in, the reader's own first.
// Two entries where the Arabic and the English differ, one where they were
// typed the same, none at all where the place itself is missing.
const placeNames = (value) => {
  if (typeof value === 'string') {
    return value ? [{ locale: page.props.locale || 'ar', value }] : [];
  }
  if (typeof value !== 'object' || value === null) return [];

  const primary = (page.props.locale || 'ar') === 'en' ? 'en' : 'ar';
  const names = [];
  for (const locale of [primary, primary === 'en' ? 'ar' : 'en']) {
    const text = value[locale];
    if (text && !names.some((n) => n.value === text)) {
      names.push({ locale, value: text });
    }
  }
  return names;
};

const governorateNames = (branch) => placeNames(branch?.governorate?.name);
const cityNames = (branch) => placeNames(branch?.city?.name);

// The address reads the same way — every language it was written in.
const addressNames = (branch) => placeNames(branch?.address);

// The languages it was NOT written in, which is what the "No address" filter
// on this page selects for.
const missingAddressLocales = (branch) => {
  const address = branch?.address;
  const written = (lang) => {
    if (typeof address === 'string') return address.trim() !== '';
    if (!address || typeof address !== 'object') return false;
    return String(address[lang] || '').trim() !== '';
  };

  return ['ar', 'en'].filter(lang => !written(lang));
};


const getPhonesArray = (phone) => normalizePhoneEntries(phone);

const phoneLabel = (type) => t.value?.facility_branch?.phone_types?.[type] || phoneTypeLabel(type);

const getEditRoute = (slug) => {
  if (!slug) {
    return route('admin.facility-branch.list');
  }
  try {
    return route('admin.facility-branch.edit', slug);
  } catch (error) {
    console.error('Error generating edit route:', error);
    return route('admin.facility-branch.list');
  }
};

const handlePerPageChange = (event) => {
  // The shared picker emits the value; a native select would send an event.
  const perPage = event?.target?.value ?? event;
  const currentUrl = new URL(window.location.href);
  currentUrl.searchParams.set('per_page', perPage);
  currentUrl.searchParams.set('page', '1'); // Reset to first page
  router.visit(currentUrl.toString(), {
    preserveState: false,
    preserveScroll: false,
  });
};
</script>
