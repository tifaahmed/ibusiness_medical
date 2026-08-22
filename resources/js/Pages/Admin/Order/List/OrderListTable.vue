<template>
  <div data-slot="card" class="bg-card text-card-foreground flex flex-col h-full lg:h-auto rounded-xl border border-border shadow-sm" :key="`order-table-${locale}`">
    <div v-if="orders?.data?.length > 0" class="flex flex-col h-full lg:h-auto min-h-0 lg:min-h-fit">
      <div class="flex-1 min-h-0 lg:min-h-fit overflow-y-auto lg:overflow-y-visible overflow-x-auto">
        <div data-slot="table-container" class="relative w-full py-3 sm:py-4">
          <table data-slot="table" class="w-full caption-bottom text-xs sm:text-sm min-w-full">
            <thead data-slot="table-header" class="[&_tr]:border-b [&_tr]:border-border">
              <tr data-slot="table-row" class="hover:bg-muted/50 data-[state=selected]:bg-muted border-b border-border transition-colors">
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap min-w-[170px]">
                  {{ t.order?.order_code || 'Order Code' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap min-w-[180px]">
                  {{ t.order?.customer || 'Customer' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap w-32 text-center hidden md:table-cell">
                  {{ t.order?.membership_number || 'Membership No.' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap w-44 text-center hidden md:table-cell">
                  {{ t.order?.amounts || 'Amounts' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap w-28 text-center">
                  {{ t.order?.payment_status || 'Payment' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap w-32 text-center">
                  {{ t.order?.delivery_status || 'Delivery' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap w-36 text-center hidden lg:table-cell">
                  {{ t.order?.created_at || 'Created At' }}
                </th>
              </tr>
            </thead>
            <tbody data-slot="table-body" class="[&_tr:last-child]:border-0">
              <tr
                v-for="order in orders.data"
                :key="order.id"
                data-slot="table-row"
                class="data-[state=selected]:bg-muted border-b border-border transition-colors hover:bg-muted/50"
              >
                <!-- Order code + payment type -->
                <td data-slot="table-cell" class="p-2 sm:p-3 align-middle">
                  <div class="flex items-start gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-golden-yellow shrink-0 mt-0.5">
                      <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
                    </svg>
                    <div class="min-w-0">
                      <span class="font-mono font-semibold text-sm text-foreground block break-all">{{ order.order_code }}</span>
                      <span
                        data-slot="badge"
                        :class="['relative inline-flex items-center gap-1 rounded-md border px-1.5 py-0.5 text-xs leading-[1.5] w-fit whitespace-nowrap font-medium mt-1', paymentTypeClass[order.payment_type?.value]]"
                        :title="order.payment_type?.label"
                      >
                        <svg v-if="order.payment_type?.value === 'cod'" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                          <rect width="20" height="12" x="2" y="6" rx="2"></rect>
                          <circle cx="12" cy="12" r="2"></circle>
                          <path d="M6 12h.01M18 12h.01"></path>
                        </svg>
                        <svg v-else xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                          <path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"></path>
                          <path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"></path>
                        </svg>
                        {{ paymentTypeLabel(order.payment_type) }}
                      </span>
                    </div>
                  </div>
                </td>
                <!-- Customer name + phone -->
                <td data-slot="table-cell" class="p-2 sm:p-3 align-middle">
                  <div class="min-w-0">
                    <span class="font-semibold text-sm text-foreground block break-words">{{ order.customer_full_name }}</span>
                    <span class="text-xs text-muted-foreground block mt-0.5" dir="ltr">{{ order.customer_phone }}</span>
                  </div>
                </td>
                <!-- Membership number -->
                <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap text-center hidden md:table-cell">
                  <span
                    v-if="order.membership_number"
                    class="font-mono text-sm text-foreground"
                  >{{ order.membership_number }}</span>
                  <span v-else class="text-muted-foreground text-xs">—</span>
                </td>
                <!-- Amounts -->
                <td data-slot="table-cell" class="p-2 align-middle text-center hidden md:table-cell">
                  <div class="inline-flex flex-col items-center gap-0.5">
                    <span
                      v-if="order.total_amount_before_discount !== null && order.total_amount_before_discount !== undefined && savedAmount(order)"
                      class="text-xs text-muted-foreground line-through"
                    >
                      {{ formatPrice(order.total_amount_before_discount) }}
                    </span>
                    <span class="text-sm font-semibold text-foreground">{{ formatPrice(order.total_amount) }}</span>
                    <span
                      v-if="savedAmount(order)"
                      class="text-[11px] font-medium text-emerald-500"
                      :title="t.order?.membership_discount || 'Membership discount'"
                    >
                      −{{ formatPrice(savedAmount(order)) }}
                    </span>
                  </div>
                </td>
                <!-- Payment status badge -->
                <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap text-center">
                  <span
                    data-slot="badge"
                    :class="['relative inline-flex items-center gap-1 rounded-md border px-1.5 py-0.5 text-xs leading-[1.5] w-fit whitespace-nowrap font-medium capitalize', statusClass.payment[order.payment_status?.value]]"
                    :title="order.cancel_reason || ''"
                  >
                    <svg v-if="order.payment_status?.value === 'pending'" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                    <svg v-else-if="order.payment_status?.value === 'accepted'" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M20 6 9 17l-5-5"></path>
                    </svg>
                    <svg v-else-if="order.payment_status?.value === 'rejected'" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M18 6 6 18"></path><path d="m6 6 12 12"></path>
                    </svg>
                    <svg v-else xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <circle cx="12" cy="12" r="10"></circle><path d="m4.9 4.9 14.2 14.2"></path>
                    </svg>
                    {{ paymentStatusLabel(order.payment_status) }}
                  </span>
                </td>
                <!-- Delivery status badge -->
                <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap text-center">
                  <span
                    data-slot="badge"
                    :class="['relative inline-flex items-center gap-1 rounded-md border px-1.5 py-0.5 text-xs leading-[1.5] w-fit whitespace-nowrap font-medium capitalize', statusClass.delivery[order.delivery_status?.value]]"
                  >
                    <svg v-if="order.delivery_status?.value === 'pending'" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                    <svg v-else-if="order.delivery_status?.value === 'processing'" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M21 12a9 9 0 1 1-6.22-8.56"></path>
                    </svg>
                    <svg v-else-if="order.delivery_status?.value === 'on-delivery'" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"></path>
                      <path d="M15 18H9"></path>
                      <path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.62l-3.48-4.35A1 1 0 0 0 17.52 8H14"></path>
                      <circle cx="17" cy="18" r="2"></circle>
                      <circle cx="7" cy="18" r="2"></circle>
                    </svg>
                    <svg v-else xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M18 6 7 17l-5-5"></path><path d="m22 10-7.5 7.5L13 16"></path>
                    </svg>
                    {{ deliveryStatusLabel(order.delivery_status) }}
                  </span>
                </td>
                <!-- Created at -->
                <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap text-center hidden lg:table-cell">
                  <span class="text-xs text-muted-foreground tabular-nums">{{ order.created_at }}</span>
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

    <!-- Empty state -->
    <div v-else data-slot="card-content" class="p-12">
      <div class="text-center max-w-md mx-auto space-y-6">
        <div class="inline-flex items-center justify-center w-20 h-20 sm:w-24 sm:h-24 rounded-full bg-golden-yellow/10 border border-golden-yellow/20 mb-6 shadow-lg shadow-golden-yellow/10">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-10 h-10 sm:w-12 sm:h-12 text-golden-yellow subtle-float">
            <circle cx="8" cy="21" r="1"></circle>
            <circle cx="19" cy="21" r="1"></circle>
            <path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"></path>
          </svg>
        </div>
        <h3 class="text-xl sm:text-2xl font-bold mb-1 text-foreground">{{ t.order?.not_found || 'No Orders Found' }}</h3>
        <p class="text-muted-foreground text-sm sm:text-base leading-relaxed">{{ t.order?.not_found_message || 'No orders match your current filters. Try adjusting your search criteria.' }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import Pagination from "@/Pages/_components/Pagination.vue";
import { router, usePage } from "@inertiajs/vue3";
import { computed } from 'vue';

const props = defineProps({
  orders: {
    type: Object,
    required: true,
  },
});

const page = usePage();
const locale = computed(() => page.props.locale || 'ar');
const t = computed(() => page.props.translations?.admin || {});

// Badge palettes per AGENTS.md: matching colors for every state.
const statusClass = {
  payment: {
    pending: 'border-amber-500/30 bg-amber-500/10 text-amber-500',
    accepted: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-500',
    rejected: 'border-red-500/30 bg-red-500/10 text-red-500',
    canceled: 'border-zinc-500/30 bg-zinc-500/10 text-zinc-400',
  },
  delivery: {
    pending: 'border-amber-500/30 bg-amber-500/10 text-amber-500',
    processing: 'border-blue-500/30 bg-blue-500/10 text-blue-500',
    'on-delivery': 'border-violet-500/30 bg-violet-500/10 text-violet-500',
    completed: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-500',
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

const deliveryStatusLabel = (status) => {
  const map = {
    pending: t.value.order?.delivery_pending,
    processing: t.value.order?.delivery_processing,
    'on-delivery': t.value.order?.delivery_on_delivery,
    completed: t.value.order?.delivery_completed,
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

// What the membership saved the customer: before-discount minus payable.
const savedAmount = (order) => {
  const before = Number(order.total_amount_before_discount);
  const after = Number(order.total_amount);
  if (!Number.isFinite(before) || !Number.isFinite(after)) return null;
  const diff = Math.round((before - after) * 100) / 100;
  return diff > 0 ? diff : null;
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
