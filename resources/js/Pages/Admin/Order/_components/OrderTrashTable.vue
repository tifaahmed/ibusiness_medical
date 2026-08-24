<template>
  <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm" :key="`order-trash-${locale}`">
    <div v-if="orders?.data?.length > 0">
      <div class="overflow-x-auto">
        <div data-slot="table-container" class="relative w-full py-3 sm:py-4">
          <table data-slot="table" class="w-full caption-bottom text-xs sm:text-sm">
            <thead data-slot="table-header" class="[&_tr]:border-b [&_tr]:border-border">
              <tr data-slot="table-row" class="border-b border-border">
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap min-w-[150px]">
                  {{ t.order?.order_code || 'Order Code' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap min-w-[180px]">
                  {{ t.order?.customer || 'Customer' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap w-28 text-center hidden md:table-cell">
                  {{ t.order?.paid || 'Paid' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap w-32 text-center hidden md:table-cell">
                  {{ t.order?.order_cost || 'Order cost' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap w-28 text-center">
                  {{ t.order?.payment_status || 'Payment' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap w-36 text-center hidden lg:table-cell">
                  {{ t.order?.deleted_at || 'Deleted at' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap w-32 text-center">
                  {{ t.common?.actions || 'Actions' }}
                </th>
              </tr>
            </thead>
            <tbody data-slot="table-body" class="[&_tr:last-child]:border-0">
              <tr
                v-for="order in orders.data"
                :key="order.id"
                data-slot="table-row"
                class="border-b border-border transition-colors hover:bg-muted/50 opacity-75"
              >
                <!-- Order code + payment type -->
                <td data-slot="table-cell" class="p-2 sm:p-3 align-middle">
                  <div class="min-w-0">
                    <span class="font-mono font-semibold text-sm text-foreground block break-all line-through decoration-muted-foreground/60">
                      {{ order.order_code }}
                    </span>
                    <span
                      data-slot="badge"
                      :class="['relative inline-flex items-center gap-1 rounded-md border px-1.5 py-0.5 text-xs leading-[1.5] w-fit whitespace-nowrap font-medium mt-1', paymentTypeClass[order.payment_type?.value]]"
                    >
                      {{ paymentTypeLabel(order.payment_type) }}
                    </span>
                  </div>
                </td>
                <!-- Customer -->
                <td data-slot="table-cell" class="p-2 sm:p-3 align-middle">
                  <div class="min-w-0">
                    <span class="font-semibold text-sm text-foreground block break-words">{{ order.customer_full_name }}</span>
                    <span class="text-xs text-muted-foreground block mt-0.5" dir="ltr">{{ order.customer_phone }}</span>
                  </div>
                </td>
                <!-- Paid -->
                <td data-slot="table-cell" class="p-2 align-middle text-center hidden md:table-cell">
                  <span v-if="order.total_paid" class="text-sm font-semibold text-foreground">
                    {{ formatPrice(order.total_paid) }}
                  </span>
                  <span v-else class="text-muted-foreground text-xs">—</span>
                </td>
                <!-- Order cost, broken down the way the live list breaks it down -->
                <td data-slot="table-cell" class="p-2 align-middle text-center hidden md:table-cell">
                  <div class="inline-flex flex-col items-center gap-0.5">
                    <span class="text-sm font-semibold text-foreground">{{ formatPrice(order.total_amount) }}</span>
                    <span class="text-[11px] text-muted-foreground whitespace-nowrap" dir="ltr">
                      {{ formatPrice(order.products_total) }} + {{ formatPrice(order.delivery_price) }}
                    </span>
                  </div>
                </td>
                <!-- Payment status -->
                <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap text-center">
                  <span
                    data-slot="badge"
                    :class="['relative inline-flex items-center gap-1 rounded-md border px-1.5 py-0.5 text-xs leading-[1.5] w-fit whitespace-nowrap font-medium capitalize', statusClass.payment[order.payment_status?.value]]"
                  >
                    {{ paymentStatusLabel(order.payment_status) }}
                  </span>
                </td>
                <!-- When it was deleted — what this page is sorted by -->
                <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap text-center hidden lg:table-cell">
                  <span class="text-xs text-muted-foreground tabular-nums">{{ order.deleted_at || '—' }}</span>
                </td>
                <!-- View, restore, erase -->
                <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap text-center">
                  <div class="inline-flex items-center gap-1">
                    <Link
                      :href="route('admin.order.show', order.order_code)"
                      class="inline-flex items-center justify-center rounded-md border border-border bg-background p-1.5 text-muted-foreground transition-colors hover:bg-primary hover:text-primary-foreground"
                      :title="t.order?.view_order || 'View Order'"
                      :aria-label="t.order?.view_order || 'View Order'"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                      </svg>
                    </Link>
                    <button
                      v-if="canWrite"
                      type="button"
                      @click="$emit('restore', order)"
                      class="inline-flex items-center justify-center rounded-md border border-border bg-background p-1.5 text-emerald-600 transition-colors hover:bg-emerald-600/10 dark:text-emerald-400"
                      :title="t.order?.restore_order || 'Restore order'"
                      :aria-label="t.order?.restore_order || 'Restore order'"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path>
                        <path d="M3 3v5h5"></path>
                      </svg>
                    </button>
                    <button
                      v-if="canWrite"
                      type="button"
                      @click="$emit('force-delete', order)"
                      class="inline-flex items-center justify-center rounded-md border border-border bg-background p-1.5 text-destructive transition-colors hover:bg-destructive/10"
                      :title="t.order?.force_delete_order || 'Delete permanently'"
                      :aria-label="t.order?.force_delete_order || 'Delete permanently'"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 6h18"></path>
                        <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                        <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                        <line x1="10" x2="10" y1="11" y2="17"></line>
                        <line x1="14" x2="14" y1="11" y2="17"></line>
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
              <span class="hidden sm:inline">{{ (t.common?.showing_results || 'Showing :from to :to of :total results').replace(':from', orders.meta?.from || 0).replace(':to', orders.meta?.to || 0).replace(':total', orders.meta?.total || 0) }}</span>
              <span class="sm:hidden">{{ orders.meta?.from || 0 }}-{{ orders.meta?.to || 0 }}/{{ orders.meta?.total || 0 }}</span>
            </div>
            <div class="flex items-center gap-2 order-2 flex-shrink-0">
              <p class="text-xs sm:text-sm font-medium whitespace-nowrap hidden sm:inline">{{ t.common?.rows_per_page || 'Rows per page' }}</p>
              <p class="text-xs sm:text-sm font-medium whitespace-nowrap sm:hidden">{{ t.common?.per_page || 'Per page' }}</p>
              <select
                :value="orders.meta?.per_page || 15"
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
                v-if="orders?.meta?.links?.length > 0"
                :links="orders?.meta?.links"
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
            <path d="M3 6h18"></path>
            <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
            <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
            <line x1="10" x2="10" y1="11" y2="17"></line>
            <line x1="14" x2="14" y1="11" y2="17"></line>
          </svg>
        </div>
        <h3 class="text-xl sm:text-2xl font-bold mb-1 text-foreground">{{ t.order?.trash_empty || 'Trash is Empty' }}</h3>
        <p class="text-muted-foreground text-sm sm:text-base leading-relaxed">{{ t.order?.trash_empty_message || 'No deleted orders found.' }}</p>
        <Link
          :href="route('admin.order.list')"
          class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2 btn-golden"
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
import { computed } from "vue";
import { usePermissions } from "@/composables/usePermissions";

defineProps({
  orders: {
    type: Object,
    required: true,
  },
});

defineEmits(['restore', 'force-delete']);

const page = usePage();
const locale = computed(() => page.props.locale || 'ar');
const t = computed(() => page.props.translations?.admin || {});

/* Restoring and erasing are writes. A `view orders` account can read the
   trash — the same as it reads the list — but not act on it. */
const { canManage } = usePermissions();
const canWrite = computed(() => canManage('manage orders', 'manage own orders'));

const statusClass = {
  payment: {
    pending: 'border-amber-500/30 bg-amber-500/10 text-amber-500',
    accepted: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-500',
    rejected: 'border-red-500/30 bg-red-500/10 text-red-500',
    canceled: 'border-zinc-500/30 bg-zinc-500/10 text-zinc-400',
  },
};

const paymentTypeClass = {
  cod: 'border-sky-500/30 bg-sky-500/10 text-sky-500',
  'transfer-wallet': 'border-violet-500/30 bg-violet-500/10 text-violet-500',
};

const paymentStatusLabel = (status) => {
  const map = {
    pending: t.value.order?.status_pending,
    accepted: t.value.order?.status_accepted,
    rejected: t.value.order?.status_rejected,
    canceled: t.value.order?.status_canceled,
  };
  return map[status?.value] || status?.label || status?.value || '—';
};

const paymentTypeLabel = (type) => {
  const map = {
    cod: t.value.order?.type_cod,
    'transfer-wallet': t.value.order?.type_transfer_wallet,
  };
  return map[type?.value] || type?.label || type?.value || '';
};

const formatPrice = (price) => {
  if (price === null || price === undefined) return '';
  return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(price);
};

const handlePerPageChange = (event) => {
  const currentUrl = new URL(window.location.href);
  currentUrl.searchParams.set('per_page', event.target.value);
  currentUrl.searchParams.set('page', '1');
  router.visit(currentUrl.toString(), {
    preserveState: false,
    preserveScroll: false,
  });
};
</script>
