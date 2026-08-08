<template>
  <div data-slot="card" class="bg-card text-card-foreground flex flex-col h-full lg:h-auto rounded-xl border border-border shadow-sm" :key="`contract-table-${locale}`">
    <div v-if="contracts?.data?.length > 0" class="flex flex-col h-full lg:h-auto min-h-0 lg:min-h-fit">
      <div class="flex-1 min-h-0 lg:min-h-fit overflow-y-auto lg:overflow-y-visible overflow-x-auto">
        <div data-slot="table-container" class="relative w-full py-3 sm:py-4">
          <table data-slot="table" class="w-full caption-bottom text-xs sm:text-sm min-w-full">
            <thead data-slot="table-header" class="[&_tr]:border-b [&_tr]:border-border">
              <tr data-slot="table-row" class="hover:bg-muted/50 data-[state=selected]:bg-muted border-b border-border transition-colors">
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap min-w-[200px] sm:min-w-[300px]">
                  {{ t.contract?.details || 'Contract Details' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap w-24 text-center hidden sm:table-cell">
                  {{ t.contract?.status || 'Status' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap w-24 text-center hidden md:table-cell">
                  {{ t.contract?.sort_order || 'Order' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap w-20 sm:w-28 text-center">
                  {{ t.common?.actions || 'Actions' }}
                </th>
              </tr>
            </thead>
            <tbody data-slot="table-body" class="[&_tr:last-child]:border-0">
              <tr
                v-for="contract in contracts.data"
                :key="contract.id"
                data-slot="table-row"
                class="data-[state=selected]:bg-muted border-b border-border transition-colors hover:bg-muted/50"
              >
                <td data-slot="table-cell" class="p-2 sm:p-3 align-middle">
                  <div class="flex items-start gap-2 sm:gap-4">
                    <div v-if="contract.image" class="flex-shrink-0 hidden sm:block">
                      <img :src="contract.image" :alt="getTranslatedName(contract.name)" class="w-16 h-16 object-cover rounded-lg border border-border" />
                    </div>
                    <div class="flex-1 min-w-0 space-y-1 sm:space-y-1.5">
                      <Link
                        :href="getEditRoute(contract.slug)"
                        class="font-semibold text-sm sm:text-base text-foreground hover:text-golden-yellow transition-colors cursor-pointer block max-w-[200px] sm:max-w-[250px] break-words"
                        :title="getTranslatedName(contract.name)"
                      >
                        {{ getTranslatedName(contract.name) || '-' }}
                      </Link>
                      <!-- Show status on mobile -->
                      <div class="sm:hidden text-xs text-muted-foreground">
                        <span :class="[
                          'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset',
                          contract.is_active ? 'bg-emerald-500/10 text-emerald-500 ring-emerald-500/20' : 'bg-red-500/10 text-red-500 ring-red-500/20'
                        ]">
                          {{ contract.is_active ? (t.common?.active || 'Active') : (t.common?.inactive || 'Inactive') }}
                        </span>
                      </div>
                      <div v-if="getTranslatedName(contract.description)" class="text-xs text-muted-foreground max-w-[200px] truncate" v-html="getTranslatedName(contract.description)"></div>
                      <!-- Phones -->
                      <div v-if="contract.phones && contract.phones.length" class="flex flex-wrap gap-1.5 mt-1">
                        <a
                          v-for="(phone, idx) in contract.phones"
                          :key="idx"
                          :href="phone.type === 'whatsapp' ? `https://wa.me/${phone.number.replace(/[^0-9]/g, '')}` : `tel:${phone.number}`"
                          :target="phone.type === 'whatsapp' ? '_blank' : undefined"
                          :rel="phone.type === 'whatsapp' ? 'noopener noreferrer' : undefined"
                          class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset transition-colors"
                          :class="phone.type === 'whatsapp'
                            ? 'bg-emerald-500/10 text-emerald-400 ring-emerald-500/20 hover:bg-emerald-500/20'
                            : 'bg-sky-500/10 text-sky-400 ring-sky-500/20 hover:bg-sky-500/20'"
                        >
                          <svg v-if="phone.type === 'whatsapp'" width="12" height="12" viewBox="0 0 24 24" fill="currentColor" class="flex-shrink-0"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                          <svg v-else width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                          {{ phone.number }}
                        </a>
                      </div>
                    </div>
                  </div>
                </td>
                <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap text-center hidden sm:table-cell">
                  <span :class="[
                    'inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset',
                    contract.is_active ? 'bg-emerald-500/10 text-emerald-500 ring-emerald-500/20' : 'bg-red-500/10 text-red-500 ring-red-500/20'
                  ]">
                    {{ contract.is_active ? (t.common?.active || 'Active') : (t.common?.inactive || 'Inactive') }}
                  </span>
                </td>
                <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap text-center hidden md:table-cell">
                  <span class="text-sm text-muted-foreground">{{ contract.sort_order }}</span>
                </td>
                <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap text-center">
                  <div class="flex items-center justify-center gap-2">
                    <Link
                      :href="getEditRoute(contract.slug)"
                      class="inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5 flex items-center gap-2 text-emerald-bright hover:!bg-emerald-bright/10 hover:!text-emerald-bright"
                      :class="{ 'opacity-50 cursor-not-allowed pointer-events-none': !contract.slug }"
                      title="Edit"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3">
                        <path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"></path>
                      </svg>
                    </Link>
                    <button
                      :disabled="!contract.slug"
                      @click="contract.slug && $emit('delete', contract.slug)"
                      class="inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all border bg-background shadow-xs hover:bg-destructive hover:text-white dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5 flex items-center gap-2 text-destructive hover:!bg-destructive/10 hover:!text-destructive"
                      title="Delete"
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
              <span class="hidden sm:inline">{{ (t.common?.showing_results || 'Showing :from to :to of :total results').replace(':from', contracts.meta?.from || 0).replace(':to', contracts.meta?.to || 0).replace(':total', contracts.meta?.total || 0) }}</span>
              <span class="sm:hidden">{{ contracts.meta?.from || 0 }}-{{ contracts.meta?.to || 0 }}/{{ contracts.meta?.total || 0 }}</span>
            </div>
            <div class="flex items-center gap-2 order-2 flex-shrink-0">
              <p class="text-xs sm:text-sm font-medium whitespace-nowrap hidden sm:inline">{{ t.common?.rows_per_page || 'Rows per page' }}</p>
              <p class="text-xs sm:text-sm font-medium whitespace-nowrap sm:hidden">{{ t.common?.per_page || 'Per page' }}</p>
              <select
                :value="contracts.meta?.per_page || 15"
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
                v-if="contracts?.meta?.links?.length > 0"
                :links="contracts?.meta?.links"
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
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
            <polyline points="14 2 14 8 20 8"></polyline>
            <line x1="16" y1="13" x2="8" y2="13"></line>
            <line x1="16" y1="17" x2="8" y2="17"></line>
          </svg>
        </div>
        <h3 class="text-xl sm:text-2xl font-bold mb-1 text-foreground">{{ t.contract?.not_found || 'No Contracts Found' }}</h3>
        <p class="text-muted-foreground text-sm sm:text-base leading-relaxed">{{ t.contract?.not_found_message || 'No contracts match your current filters. Try adjusting your search criteria.' }}</p>
        <Link
          :href="route('admin.contract.create')"
          data-slot="button"
          class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2 has-[>svg]:px-3 btn-golden"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
            <path d="M5 12h14"></path>
            <path d="M12 5v14"></path>
          </svg>
          {{ t.contract?.add || 'Add Contract' }}
        </Link>
      </div>
    </div>
  </div>
</template>

<script setup>
import Pagination from "@/Pages/_components/Pagination.vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import { computed } from 'vue';

const props = defineProps({
  contracts: {
    type: Object,
    required: true
  }
});

defineEmits(['delete']);

const page = usePage();
const locale = computed(() => page.props.locale || 'ar');
const t = computed(() => page.props.translations?.admin || {});

const getTranslatedName = (name) => {
  if (typeof name === 'string') return name;
  if (typeof name === 'object' && name !== null) {
    const currentLocale = page.props.locale || 'ar';
    let translatedValue = name[currentLocale] || '';
    if (currentLocale === 'en') {
      if (translatedValue && /[\u0600-\u06FF]/.test(translatedValue)) return '';
      if (!translatedValue || translatedValue.trim() === '') return '';
      return translatedValue;
    }
    if (currentLocale === 'ar') {
      return name['ar'] || name['en'] || Object.values(name)[0] || '';
    }
    return translatedValue || name['ar'] || name['en'] || Object.values(name)[0] || '';
  }
  return '';
};

const getEditRoute = (slug) => {
  if (!slug) return route('admin.contract.list');
  try {
    return route('admin.contract.edit', slug);
  } catch (error) {
    return route('admin.contract.list');
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
