<template>
  <div class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
    <div v-if="products?.data?.length > 0" class="p-0">
      <div class="overflow-x-auto">
        <div class="relative w-full overflow-x-auto">
          <table class="w-full caption-bottom text-sm min-w-full">
            <thead class="[&_tr]:border-b [&_tr]:border-border">
              <tr class="hover:bg-muted/50 data-[state=selected]:bg-muted border-b transition-colors border-border">
                <th class="text-foreground h-10 px-2 text-left align-middle font-medium whitespace-nowrap min-w-[280px]">
                  Name (English)
                </th>
                <th class="text-foreground h-10 px-2 text-left align-middle font-medium whitespace-nowrap min-w-[220px]">
                  Name (Arabic)
                </th>
                <th class="text-foreground h-10 px-2 text-left align-middle font-medium whitespace-nowrap">
                  Type
                </th>
                <th class="text-foreground h-10 px-2 text-left align-middle font-medium whitespace-nowrap">
                  Prices
                </th>
                <th class="text-foreground h-10 px-2 text-left align-middle font-medium whitespace-nowrap">
                  Tags
                </th>
                <th class="text-foreground h-10 px-2 align-middle font-medium whitespace-nowrap w-24 text-center">
                  Banner
                </th>
                <th class="text-foreground h-10 px-2 text-left align-middle font-medium whitespace-nowrap">
                  Created
                </th>
                <th class="text-foreground h-10 px-2 align-middle font-medium whitespace-nowrap w-28 text-center">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody class="[&_tr:last-child]:border-0">
              <tr
                v-for="product in products.data"
                :key="product.id"
                class="data-[state=selected]:bg-muted border-b border-border transition-colors hover:bg-muted/50"
              >
                <td class="p-2 align-middle whitespace-nowrap">
                  <div class="flex items-center gap-3">
                    <img
                      v-if="product.small_image"
                      :src="product.small_image"
                      :alt="getTranslatedName(product.name)"
                      class="w-10 h-10 rounded-md object-cover border border-border"
                    />
                    <div v-else class="w-10 h-10 rounded-md bg-muted flex items-center justify-center border border-border">
                      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-muted-foreground">
                        <rect width="18" height="18" x="3" y="3" rx="2" ry="2"></rect>
                        <circle cx="9" cy="9" r="2"></circle>
                        <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"></path>
                      </svg>
                    </div>
                    <div class="flex-1 min-w-0 space-y-1">
                      <Link
                        :href="getShowRoute(product.slug)"
                        dir="ltr"
                        class="font-semibold text-sm text-foreground hover:text-golden-yellow transition-colors cursor-pointer block max-w-[250px] break-words whitespace-normal"
                        :title="nameIn(product.name, 'en')"
                      >
                        {{ nameIn(product.name, 'en') || '-' }}
                      </Link>
                      <p v-if="nameIn(product.short_subject, 'en')" dir="ltr" class="text-xs text-muted-foreground truncate max-w-[250px]">
                        {{ nameIn(product.short_subject, 'en') }}
                      </p>
                    </div>
                  </div>
                </td>
                <td class="p-2 align-middle whitespace-nowrap">
                  <div class="flex-1 min-w-0 space-y-1">
                    <span
                      dir="rtl"
                      class="font-semibold text-sm text-foreground block max-w-[220px] break-words whitespace-normal"
                      :title="nameIn(product.name, 'ar')"
                    >
                      {{ nameIn(product.name, 'ar') || '-' }}
                    </span>
                    <p v-if="nameIn(product.short_subject, 'ar')" dir="rtl" class="text-xs text-muted-foreground truncate max-w-[220px]">
                      {{ nameIn(product.short_subject, 'ar') }}
                    </p>
                  </div>
                </td>
                <td class="p-2 align-middle whitespace-nowrap">
                  <span v-if="product.product_type" class="chip chip-accent">
                    {{ getTranslatedName(product.product_type.name) }}
                  </span>
                  <span v-else class="text-xs text-muted-foreground">—</span>
                </td>
                <td class="p-2 align-middle whitespace-nowrap">
                  <div class="flex flex-col gap-0.5">
                    <span v-if="product.new_price" class="text-sm font-semibold text-emerald-600 dark:text-emerald-400">
                      {{ formatPrice(product.new_price) }}
                    </span>
                    <span v-if="product.old_price" class="text-xs text-muted-foreground line-through">
                      {{ formatPrice(product.old_price) }}
                    </span>
                    <span v-if="!product.new_price && !product.old_price" class="text-xs text-muted-foreground">—</span>
                  </div>
                </td>
                <td class="p-2 align-middle whitespace-nowrap">
                  <div v-if="product.tags?.length" class="flex flex-wrap gap-1">
                    <span
                      v-for="tag in product.tags.slice(0, 3)"
                      :key="tag.id"
                      class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium"
                      :style="tag.color ? { backgroundColor: tag.color + '20', color: tag.color } : {}"
                    >
                      {{ tag.icon }} {{ tag.name }}
                    </span>
                    <span v-if="product.tags.length > 3" class="text-[10px] text-muted-foreground">+{{ product.tags.length - 3 }}</span>
                  </div>
                  <span v-else class="text-xs text-muted-foreground">—</span>
                </td>
                <td class="p-2 align-middle text-center">
                  <div v-if="product.banner_config?.enabled" class="flex flex-col items-center gap-0.5">
                    <span v-if="product.banner_config?.message_ar" dir="rtl" class="text-xs font-medium text-foreground leading-tight">
                      {{ product.banner_config.message_ar }}
                    </span>
                    <span v-if="product.banner_config?.message_en" dir="ltr" class="text-xs font-medium text-foreground leading-tight">
                      {{ product.banner_config.message_en }}
                    </span>
                    <span v-if="formatCountdown(product.banner_end_date)" class="text-[10px] text-muted-foreground font-mono">
                      {{ formatCountdown(product.banner_end_date) }}
                    </span>
                    <span v-else-if="product.banner_days_left === 0" class="text-[10px] text-red-400 font-medium">
                      Expired
                    </span>
                  </div>
                  <span v-else class="text-xs text-muted-foreground">—</span>
                </td>
                <td class="p-2 align-middle whitespace-nowrap">
                  <span class="text-xs text-muted-foreground">{{ product.created_at }}</span>
                </td>
                <td class="p-2 align-middle whitespace-nowrap text-center">
                  <div class="flex items-center justify-center gap-2">
                    <Link
                      :href="getShowRoute(product.slug)"
                      class="inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 text-emerald-bright hover:!bg-emerald-bright/10 hover:!text-emerald-bright"
                      title="View"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                      </svg>
                    </Link>
                    <Link
                      :href="getEditRoute(product.slug)"
                      class="inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 text-emerald-bright hover:!bg-emerald-bright/10 hover:!text-emerald-bright"
                      title="Edit"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"></path>
                      </svg>
                    </Link>
                    <button
                      @click="handleDelete(product.slug)"
                      class="inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all border bg-background shadow-xs hover:bg-destructive hover:text-white dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 text-destructive hover:!bg-destructive/10 hover:!text-destructive"
                      title="Delete"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
                Showing {{ products.meta?.from || 0 }} to {{ products.meta?.to || 0 }} of {{ products.meta?.total || 0 }} results
              </div>
            </div>
            <div>
              <div class="flex items-center gap-2 order-1 sm:order-2">
                <p class="text-xs sm:text-sm font-medium whitespace-nowrap">Rows per page</p>
                <select
                  :value="products.meta?.per_page || 15"
                  @change="handlePerPageChange"
                  dir="ltr"
                  translate="no"
                  class="border-input rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 h-8 w-[70px] cursor-pointer"
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
              v-if="products?.meta?.links?.length > 0"
              :links="products?.meta?.links"
              class="order-3"
            />
          </div>
        </div>
      </div>
    </div>

    <div v-else class="p-12">
      <div class="text-center max-w-md mx-auto space-y-6">
        <div class="inline-flex items-center justify-center w-20 h-20 sm:w-24 sm:h-24 rounded-full bg-golden-yellow/10 border border-golden-yellow/20 mb-6 shadow-lg shadow-golden-yellow/10">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-10 h-10 sm:w-12 sm:h-12 text-golden-yellow subtle-float">
            <path d="m7.5 4.27 9 5.15"></path>
            <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"></path>
            <path d="m3.3 7 8.7 5 8.7-5"></path>
            <path d="M12 22V12"></path>
          </svg>
        </div>
        <h3 class="text-xl sm:text-2xl font-bold mb-1 text-foreground">No Products Found</h3>
        <p class="text-muted-foreground text-sm sm:text-base leading-relaxed">No products match your current filters. Try adjusting your search criteria.</p>
        <Link
          v-if="canWrite"
          :href="route('admin.product.create')"
          class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2 btn-golden"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
            <path d="M5 12h14"></path>
            <path d="M12 5v14"></path>
          </svg>
          Add Product
        </Link>
      </div>
    </div>
  </div>
</template>

<script setup>
import Pagination from "@/Pages/_components/Pagination.vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import { computed, onMounted, onUnmounted, ref } from "vue";
import { useNotification } from "@/composables/useNotification";
import { usePermissions } from '@/composables/usePermissions';

const { canManage } = usePermissions();
// Create/export/import are writes: hidden from read-only accounts,
// and refused by the routes behind them either way.
const canWrite = computed(() => canManage('manage own products', 'manage products'));


const props = defineProps({
  products: {
    type: Object,
    required: true
  }
});

const page = usePage();
const locale = page.props.locale || 'ar';

const getTranslatedName = (name) => {
  if (typeof name === 'string') return name;
  if (typeof name === 'object' && name !== null) {
    return name[locale] || name['ar'] || name['en'] || Object.values(name)[0] || '';
  }
  return '';
};

// Live countdown ticker for the banner column
const now = ref(Date.now());
let countdownInterval = null;

onMounted(() => {
  countdownInterval = setInterval(() => {
    now.value = Date.now();
  }, 1000);
});

onUnmounted(() => {
  if (countdownInterval) {
    clearInterval(countdownInterval);
  }
});

const formatCountdown = (endDateStr) => {
  if (!endDateStr) return '';

  const diff = new Date(endDateStr).getTime() - now.value;
  if (diff <= 0) return '';

  const days = Math.floor(diff / (1000 * 60 * 60 * 24));
  const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
  const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
  const seconds = Math.floor((diff % (1000 * 60)) / 1000);

  const parts = [];
  if (days > 0) parts.push(`${days}d`);
  if (hours > 0) parts.push(`${hours}h`);
  if (minutes > 0) parts.push(`${minutes}m`);
  parts.push(`${seconds}s`);

  return parts.join(' ');
};

const formatPrice = (price) => {
  if (price === null || price === undefined) return '';
  return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2 }).format(price);
};

// One specific locale, with no fallback: the table shows AR and EN side by side,
// so an empty translation must read as empty instead of echoing the other language.
const nameIn = (value, lang) => {
  if (typeof value === 'string') return lang === locale ? value : '';
  if (typeof value === 'object' && value !== null) return value[lang] || '';
  return '';
};

const getShowRoute = (slug) => {
  if (!slug) return route('admin.product.list');
  try { return route('admin.product.show', slug); } catch { return route('admin.product.list'); }
};

const getEditRoute = (slug) => {
  if (!slug) return route('admin.product.list');
  try { return route('admin.product.edit', slug); } catch { return route('admin.product.list'); }
};

const handleDelete = (slug) => {
  if (!slug) return;
  if (confirm('Are you sure you want to delete this product? This action cannot be undone.')) {
    router.delete(route('admin.product.destroy', slug), {
      preserveScroll: true,
      onSuccess: () => {
        useNotification().success('Product deleted successfully');
        router.reload({ only: ['products'] });
      },
      onError: () => {
        useNotification().error('Failed to delete product');
      }
    });
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
