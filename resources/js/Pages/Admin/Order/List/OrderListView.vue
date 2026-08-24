<template>
  <OrderLayout>
    <div class="flex flex-col h-full lg:h-auto w-full max-w-full overflow-x-hidden">
      <div class="flex-shrink-0 space-y-2 sm:space-y-3 md:space-y-4 p-2 sm:p-3 md:p-4 lg:p-6 pb-2 sm:pb-3 md:pb-4 lg:pb-4 w-full max-w-full overflow-hidden">
        <div class="bg-card text-card-foreground flex flex-col gap-2 sm:gap-3 md:gap-4 rounded-xl border border-border py-2 sm:py-3 md:py-4 shadow-sm overflow-hidden w-full max-w-full">
          <div class="flex flex-row items-center justify-between py-2 px-3 sm:px-4 md:px-6 w-full overflow-hidden gap-2 sm:gap-4">
            <div class="leading-none font-semibold min-w-0 flex-1">
              <div class="title-golden min-w-0 flex items-center gap-2">
                <!-- Page-title icon -->
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon sm:w-6 sm:h-6 flex-shrink-0">
                  <circle cx="8" cy="21" r="1"></circle>
                  <circle cx="19" cy="21" r="1"></circle>
                  <path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"></path>
                </svg>
                <span class="text-sm sm:text-base truncate block min-w-0">{{ t.order?.management || 'Orders Management' }}</span>
              </div>
            </div>

            <div class="flex items-center gap-2 flex-shrink-0">
              <Link
                :href="route('admin.order.trash')"
                class="inline-flex items-center cursor-pointer justify-center gap-1.5 sm:gap-2 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 sm:h-9 px-2 sm:px-3 md:px-4 py-2 flex-shrink-0 btn-view-trash"
                :title="t.order?.trash_title || 'Trash - Deleted Orders'"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 sm:h-4 sm:w-4">
                  <path d="M3 6h18"></path>
                  <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                  <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                  <line x1="10" x2="10" y1="11" y2="17"></line>
                  <line x1="14" x2="14" y1="11" y2="17"></line>
                </svg>
                <span class="hidden sm:inline">{{ t.actions?.view_trash_long || 'View Trash' }}</span>
                <span class="sm:hidden">{{ t.actions?.view_trash_short || 'Trash' }}</span>
              </Link>

              <button
                v-if="canWrite"
                ref="exportTriggerRef"
                type="button"
                @click="toggleExportMenu"
                class="inline-flex items-center cursor-pointer justify-center gap-1.5 sm:gap-2 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 sm:h-9 px-2 sm:px-3 md:px-4 py-2"
                :title="exportButtonTitle"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 sm:h-4 sm:w-4">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                  <polyline points="7 10 12 15 17 10"></polyline>
                  <line x1="12" x2="12" y1="15" y2="3"></line>
                </svg>
                <span class="hidden sm:inline">{{ em.button || 'Export' }}</span>
                <span class="sm:hidden">{{ em.button_short || 'Exp' }}</span>
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3 w-3 opacity-70">
                  <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
              </button>

              <Teleport to="body">
                <div
                  v-if="exportMenuOpen"
                  ref="exportMenuRef"
                  :style="exportMenuStyle"
                  class="fixed z-[1000] w-96 rounded-md border border-border bg-popover text-popover-foreground shadow-2xl p-3 space-y-3"
                >
                  <div>
                    <div class="text-[11px] font-semibold uppercase text-muted-foreground mb-1.5">{{ em.include_label || 'Include' }}</div>
                    <div class="grid grid-cols-2 gap-1">
                      <button
                        type="button"
                        @click="includeProducts = false"
                        :class="includeProducts ? 'border-border bg-background text-foreground' : 'border-primary bg-primary text-primary-foreground'"
                        class="text-xs px-2 py-1.5 rounded border font-medium transition-colors"
                      >
                        {{ em.orders_only || 'Orders' }}
                      </button>
                      <button
                        type="button"
                        @click="includeProducts = true"
                        :class="includeProducts ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-background text-foreground'"
                        class="text-xs px-2 py-1.5 rounded border font-medium transition-colors"
                      >
                        {{ em.with_products || '+ Products' }}
                      </button>
                    </div>
                  </div>

                  <div>
                    <div class="text-[11px] font-semibold uppercase text-muted-foreground mb-1.5">{{ em.split_label || 'Split into files of' }}</div>
                    <div class="grid grid-cols-5 gap-1 mb-1.5">
                      <button
                        v-for="opt in [0, 100, 200, 300, 500]"
                        :key="opt"
                        type="button"
                        @click="chunkSize = opt"
                        :class="chunkSize === opt ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-background text-foreground'"
                        class="text-[11px] px-1 py-1.5 rounded border font-medium transition-colors"
                      >
                        {{ opt === 0 ? (em.no_split || 'None') : opt }}
                      </button>
                    </div>
                    <div class="flex items-center gap-2">
                      <span class="text-[11px] text-muted-foreground whitespace-nowrap">{{ em.custom_label || 'Custom:' }}</span>
                      <input
                        type="number"
                        min="1"
                        step="1"
                        v-model.number="chunkSize"
                        :placeholder="em.custom_placeholder || 'rows / file'"
                        class="flex-1 h-7 px-2 text-xs rounded border border-input bg-background text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-primary"
                      />
                    </div>
                  </div>

                  <div>
                    <div class="flex items-center justify-between mb-1.5">
                      <span class="text-[11px] font-semibold uppercase text-muted-foreground">{{ em.columns_label || 'Columns' }}</span>
                      <button
                        type="button"
                        @click="toggleAllColumns"
                        class="text-[10px] text-muted-foreground hover:text-foreground underline"
                      >
                        {{ allColumnsSelected ? (em.deselect_all || 'Deselect all') : (em.select_all || 'Select all') }}
                      </button>
                    </div>
                    <div class="max-h-56 overflow-y-auto grid grid-cols-2 gap-x-2 gap-y-0.5">
                      <label
                        v-for="col in exportColumnOptions"
                        :key="col.key"
                        class="flex items-center gap-1.5 py-0.5 cursor-pointer"
                      >
                        <input
                          type="checkbox"
                          :value="col.key"
                          v-model="selectedColumns"
                          class="h-3 w-3 rounded border-border accent-primary"
                        />
                        <span class="text-[11px] text-foreground truncate">{{ col.label }}</span>
                      </label>
                    </div>
                  </div>

                  <div class="pt-2 border-t border-border">
                    <a
                      :href="exportComputedUrl"
                      @click="exportMenuOpen = false"
                      class="block w-full text-center bg-primary text-primary-foreground rounded-md px-3 py-2 text-xs font-semibold hover:bg-primary/90 btn-golden"
                    >
                      {{ chunkSize ? (em.download_zip || 'Download ZIP') : (em.download_excel || 'Download Excel') }}
                    </a>
                  </div>
                </div>
              </Teleport>
            </div>
          </div>

          <div class="px-2 sm:px-4 md:px-6 space-y-2 sm:space-y-3 md:space-y-4 w-full max-w-full overflow-hidden min-w-0">
            <OrderListFilterContent :initial-filters="filters" @filter-change="handleFilterChange" />
          </div>
        </div>
      </div>

      <div class="flex-1 min-h-0 lg:flex-none w-full max-w-full px-2 sm:px-3 md:px-4 lg:px-6 pb-2 sm:pb-3 md:pb-4 lg:pb-6 overflow-hidden lg:overflow-visible">
        <OrderListTable :orders="orders" :order-statuses="orderStatuses" />
      </div>
    </div>
  </OrderLayout>
</template>

<script setup>
import OrderLayout from "../OrderLayout.vue";
import OrderListFilterContent from "./OrderListFilterContent.vue";
import OrderListTable from "./OrderListTable.vue";
import { Link, usePage } from "@inertiajs/vue3";
import { computed, ref, onMounted, onUnmounted } from "vue";
import { usePermissions } from '@/composables/usePermissions';

const props = defineProps({
  orders: {
    type: Object,
    required: true
  },
  filters: {
    type: Object,
    default: () => ({
      search: '',
      payment_status: '',
      delivery_status: '',
      order_status: '',
      payment_type: '',
      created_from: '',
      created_to: ''
    })
  },
  orderStatuses: {
    type: Array,
    default: () => []
  }
});

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});
const em = computed(() => t.value.order?.export_menu || {});

// Export is a write, same as elsewhere in the admin — a `view orders`
// account keeps the list and the detail pages without the download.
const { canManage } = usePermissions();
const canWrite = computed(() => canManage('manage orders', 'manage own orders'));

const filters = ref(props.filters || {
  search: '',
  payment_status: '',
  delivery_status: '',
  order_status: '',
  payment_type: '',
  created_from: '',
  created_to: ''
});

const handleFilterChange = (newFilters) => {
  filters.value = newFilters;
};

const chunkSize = ref(0);
const includeProducts = ref(false);
const exportMenuOpen = ref(false);
const exportTriggerRef = ref(null);
const exportMenuRef = ref(null);
const exportMenuStyle = ref({});

// Keys must match AdminOrderExportController::getColumnDefinitions().
const exportColumnOptions = computed(() => {
  const o = t.value.order || {};
  const x = t.value.order_export || {};
  return [
    { key: 'index', label: '#' },
    { key: 'order_code', label: x.col_order_code || o.order_code || 'Order code' },
    { key: 'created_at', label: x.col_created_at || o.created_at || 'Created at' },
    { key: 'customer_full_name', label: x.col_customer_name || o.customer || 'Customer' },
    { key: 'customer_phone', label: x.col_customer_phone || o.customer_phone || 'Phone' },
    { key: 'membership_number', label: x.col_membership_number || o.membership_number || 'Membership #' },
    { key: 'payment_status', label: x.col_payment_status || o.payment_status || 'Payment status' },
    { key: 'delivery_status', label: x.col_delivery_status || o.delivery_status || 'Delivery status' },
    { key: 'order_status', label: x.col_order_status || o.order_status || 'Order status' },
    { key: 'payment_type', label: x.col_payment_type || o.payment_type || 'Payment type' },
    { key: 'total_paid', label: x.col_total_paid || o.total_paid || 'Total paid' },
    { key: 'total_amount', label: x.col_total_amount || o.total_amount || 'Total amount' },
    { key: 'total_amount_before_discount', label: x.col_total_before_discount || 'Before discount' },
    { key: 'discount', label: x.col_discount || 'Discount' },
    { key: 'outstanding', label: x.col_outstanding || o.outstanding || 'Outstanding' },
    { key: 'delivery_cost', label: x.col_delivery_cost || o.delivery_cost || 'Delivery cost' },
    { key: 'delivery_price', label: x.col_delivery_price || o.delivery_price || 'Delivery price' },
    { key: 'delivery_profit', label: x.col_delivery_profit || o.delivery_profit || 'Delivery profit' },
    { key: 'products_count', label: x.col_products_count || 'Lines' },
    { key: 'products', label: x.col_products || o.products || 'Products' },
    { key: 'customer_address_type', label: x.col_address_type || o.address_type || 'Address type' },
    { key: 'customer_governorate', label: x.col_governorate || o.customer_governorate || 'Governorate' },
    { key: 'customer_city', label: x.col_city || o.customer_city || 'City' },
    { key: 'customer_street', label: x.col_street || o.customer_street || 'Street' },
    { key: 'customer_building_number', label: x.col_building_number || o.customer_building_number || 'Building no.' },
    { key: 'customer_apartment_number', label: x.col_apartment_number || o.customer_apartment_number || 'Apartment no.' },
    { key: 'customer_floor_number', label: x.col_floor_number || o.customer_floor_number || 'Floor no.' },
    { key: 'customer_special_mark', label: x.col_special_mark || o.customer_special_mark || 'Special mark' },
    { key: 'customer_address', label: x.col_address || o.customer_address || 'Address' },
    { key: 'notes', label: x.col_notes || o.notes || 'Notes' },
    { key: 'cancel_reason', label: x.col_cancel_reason || o.cancel_reason || 'Cancel reason' },
    { key: 'source', label: x.col_source || 'Source' },
    { key: 'updated_at', label: x.col_updated_at || 'Updated at' },
  ];
});
const allColumnKeys = computed(() => exportColumnOptions.value.map(c => c.key));
const selectedColumns = ref(exportColumnOptions.value.map(c => c.key));
const allColumnsSelected = computed(() => selectedColumns.value.length === allColumnKeys.value.length);
const toggleAllColumns = () => {
  selectedColumns.value = allColumnsSelected.value ? [] : [...allColumnKeys.value];
};

const MARGIN = 8;
const POPOVER_W = 384; // w-96

const positionExportMenu = () => {
  const r = exportTriggerRef.value?.getBoundingClientRect();
  if (!r) return;
  const right = Math.max(
    MARGIN,
    Math.min(window.innerWidth - r.right, window.innerWidth - POPOVER_W - MARGIN)
  );
  exportMenuStyle.value = {
    top: `${r.bottom + 6}px`,
    right: `${right}px`,
  };
};

const toggleExportMenu = () => {
  exportMenuOpen.value = !exportMenuOpen.value;
  if (exportMenuOpen.value) {
    // Position after the menu is in the DOM.
    requestAnimationFrame(positionExportMenu);
  }
};

const handleClickOutside = (e) => {
  if (!exportMenuOpen.value) return;
  if (exportTriggerRef.value?.contains(e.target)) return;
  if (exportMenuRef.value?.contains(e.target)) return;
  exportMenuOpen.value = false;
};
const closeOnScrollOrResize = () => {
  if (exportMenuOpen.value) positionExportMenu();
};
onMounted(() => {
  document.addEventListener('click', handleClickOutside);
  window.addEventListener('resize', closeOnScrollOrResize);
  window.addEventListener('scroll', closeOnScrollOrResize, true);
});
onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
  window.removeEventListener('resize', closeOnScrollOrResize);
  window.removeEventListener('scroll', closeOnScrollOrResize, true);
});

// The download carries exactly the filters on screen — including the date
// range — so the file matches the list the admin is looking at.
const buildExportUrl = (extraParams = {}) => {
  const params = new URLSearchParams();
  const f = filters.value || {};
  if (f.search && String(f.search).trim()) params.set('search', String(f.search).trim());
  if (f.payment_status) params.set('payment_status', f.payment_status);
  if (f.delivery_status) params.set('delivery_status', f.delivery_status);
  if (f.order_status) params.set('order_status', f.order_status);
  if (f.payment_type) params.set('payment_type', f.payment_type);
  if (f.created_from) params.set('created_from', f.created_from);
  if (f.created_to) params.set('created_to', f.created_to);
  if (selectedColumns.value.length < allColumnKeys.value.length) {
    params.set('columns', selectedColumns.value.join(','));
  }
  for (const [k, v] of Object.entries(extraParams)) params.set(k, v);
  if (chunkSize.value > 0) params.set('chunk_size', chunkSize.value);
  const qs = params.toString();
  const base = route('admin.order.export');
  return qs ? `${base}?${qs}` : base;
};

const exportComputedUrl = computed(() => buildExportUrl(includeProducts.value ? { include_products: 1 } : {}));

const exportButtonTitle = computed(() => {
  const m = em.value;
  const parts = [includeProducts.value
    ? (m.tooltip_with_products || 'with products')
    : (m.tooltip_orders_only || 'orders only')];
  if (chunkSize.value) {
    const split = m.tooltip_split || 'split / :size';
    parts.push(split.replace(':size', chunkSize.value));
  }
  return `${m.tooltip_prefix || 'Export'} — ${parts.join(', ')}`;
});
</script>
