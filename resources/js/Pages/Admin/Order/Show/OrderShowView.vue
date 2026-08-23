<template>
  <OrderLayout>
    <div class="space-y-3 p-3 sm:p-4 lg:p-6 w-full max-w-full">
      <Breadcrumb
        :title="t.order?.view_order || 'View Order'"
        :breadcrumbs="[
          { label: t.order?.management || 'Orders', link: route('admin.order.list'), active: false },
          { label: order.order_code, link: '#', active: true },
        ]"
      />

      <div class="max-w-7xl mx-auto mt-2 space-y-3">
        <!-- Header: the code, what state it is in, and what can be done to it -->
        <div class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
          <div class="p-3 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-2">
              <div class="flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-golden-yellow shrink-0">
                  <circle cx="8" cy="21" r="1"></circle>
                  <circle cx="19" cy="21" r="1"></circle>
                  <path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"></path>
                </svg>
                <span class="font-mono text-lg font-semibold break-all">{{ order.order_code }}</span>
              </div>
              <div class="flex flex-wrap items-center gap-1.5">
                <span :class="['inline-flex items-center gap-1 rounded-md border px-1.5 py-0.5 text-xs font-medium', statusClass.payment[order.payment_status?.value]]">
                  {{ paymentStatusLabel(t, order.payment_status) }}
                </span>
                <span :class="['inline-flex items-center gap-1 rounded-md border px-1.5 py-0.5 text-xs font-medium', statusClass.delivery[order.delivery_status?.value]]">
                  {{ deliveryStatusLabel(t, order.delivery_status) }}
                </span>
                <span :class="['inline-flex items-center gap-1 rounded-md border px-1.5 py-0.5 text-xs font-medium', paymentTypeClass[order.payment_type?.value]]">
                  {{ paymentTypeLabel(t, order.payment_type) }}
                </span>
                <span v-if="order.source" class="inline-flex items-center gap-1 rounded-md border border-border bg-muted/50 px-1.5 py-0.5 text-xs font-medium text-muted-foreground">
                  {{ order.source }}
                </span>
                <span
                  v-if="order.awaiting_receipt"
                  class="inline-flex items-center gap-1 rounded-md border border-amber-500/30 bg-amber-500/10 px-1.5 py-0.5 text-xs font-medium text-amber-500"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 9v4"></path><path d="M12 17h.01"></path>
                    <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                  </svg>
                  {{ t.order?.awaiting_receipt || 'Awaiting receipt' }}
                </span>
              </div>
              <p v-if="order.cancel_reason" class="text-sm text-destructive">
                <span class="text-xs font-medium text-muted-foreground me-1">{{ t.order?.cancel_reason || 'Cancel Reason' }}:</span>
                {{ order.cancel_reason }}
              </p>
            </div>

            <div class="flex flex-wrap gap-2 shrink-0">
              <Link
                :href="route('admin.order.list')"
                class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 px-3 py-1.5"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                  <path d="M15 18l-6-6 6-6"></path>
                </svg>
                {{ t.common?.back || 'Back to List' }}
              </Link>
              <Link
                v-if="canManage"
                :href="route('admin.order.edit', order.order_code)"
                class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-8 px-3 py-1.5"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
                {{ t.order?.edit_order || 'Edit Order' }}
              </Link>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
          <!-- Customer -->
          <div class="bg-card text-card-foreground rounded-xl border border-border shadow-sm lg:col-span-2">
            <div class="p-3 border-b border-border flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-golden-yellow">
                <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle>
              </svg>
              <h2 class="text-lg font-semibold">{{ t.order?.customer || 'Customer' }}</h2>
            </div>
            <div class="p-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="text-xs font-medium text-muted-foreground">{{ t.order?.customer_name || 'Full Name' }}</label>
                <p class="text-sm font-medium mt-0.5">{{ order.customer_full_name || '—' }}</p>
              </div>
              <div>
                <label class="text-xs font-medium text-muted-foreground">{{ t.order?.customer_phone || 'Phone' }}</label>
                <p class="text-sm font-medium mt-0.5" dir="ltr">{{ order.customer_phone || '—' }}</p>
              </div>
              <div>
                <label class="text-xs font-medium text-muted-foreground">{{ t.order?.membership_number || 'Membership No.' }}</label>
                <p class="text-sm font-mono mt-0.5">{{ order.membership_number || '—' }}</p>
              </div>
              <div>
                <label class="text-xs font-medium text-muted-foreground">{{ t.order?.created_at || 'Created At' }}</label>
                <p class="text-sm mt-0.5 tabular-nums">{{ order.created_at || '—' }}</p>
              </div>
              <div class="sm:col-span-2">
                <label class="text-xs font-medium text-muted-foreground">{{ t.order?.customer_address || 'Address' }}</label>
                <p class="text-sm mt-0.5 whitespace-pre-wrap">{{ order.customer_address || '—' }}</p>
              </div>
              <div v-if="order.notes" class="sm:col-span-2">
                <label class="text-xs font-medium text-muted-foreground">{{ t.order?.notes || 'Notes' }}</label>
                <p class="panel-note mt-1 text-sm whitespace-pre-wrap">{{ order.notes }}</p>
              </div>
            </div>
          </div>

          <!-- Amounts -->
          <div class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
            <div class="p-3 border-b border-border flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-golden-yellow">
                <line x1="12" x2="12" y1="2" y2="22"></line>
                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
              </svg>
              <h2 class="text-lg font-semibold">{{ t.order?.amounts || 'Amounts' }}</h2>
            </div>
            <div class="p-3 space-y-3">
              <div v-if="order.total_amount_before_discount !== null && order.total_amount_before_discount !== undefined">
                <label class="text-xs font-medium text-muted-foreground">{{ t.order?.total_amount_before_discount || 'Before Discount' }}</label>
                <p class="text-sm mt-0.5 value-muted line-through">{{ formatPrice(order.total_amount_before_discount) }}</p>
              </div>
              <div>
                <label class="text-xs font-medium text-muted-foreground">{{ t.order?.total_amount || 'Total Amount' }}</label>
                <p class="text-lg font-semibold mt-0.5">{{ formatPrice(order.total_amount) }}</p>
              </div>
              <div v-if="saved">
                <label class="text-xs font-medium text-muted-foreground">{{ t.order?.membership_discount || 'Membership discount' }}</label>
                <p class="text-sm font-medium mt-0.5 text-emerald-500">−{{ formatPrice(saved) }}</p>
              </div>
              <div>
                <label class="text-xs font-medium text-muted-foreground">{{ t.order?.total_paid || 'Total Paid' }}</label>
                <p class="text-sm font-medium mt-0.5" :class="paidInFull ? 'text-emerald-500' : 'text-amber-500'">
                  {{ formatPrice(order.total_paid) }}
                </p>
              </div>
              <!-- The gap the courier or the wallet still has to close. -->
              <div v-if="outstanding > 0" class="rounded-md border border-amber-500/30 bg-amber-500/10 px-2 py-1.5">
                <p class="text-xs font-medium text-amber-500">
                  {{ t.order?.outstanding || 'Outstanding' }}: {{ formatPrice(outstanding) }}
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Lines -->
        <div class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
          <div class="p-3 border-b border-border flex items-center justify-between gap-2">
            <div class="flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-golden-yellow">
                <path d="m7.5 4.27 9 5.15"></path>
                <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"></path>
                <path d="m3.3 7 8.7 5 8.7-5"></path><path d="M12 22V12"></path>
              </svg>
              <h2 class="text-lg font-semibold">{{ t.order?.products || 'Products' }}</h2>
            </div>
            <span class="text-xs text-muted-foreground">{{ order.products?.length || 0 }} {{ t.order?.lines || 'lines' }}</span>
          </div>

          <div v-if="order.products?.length" class="overflow-x-auto">
            <table class="w-full caption-bottom text-xs sm:text-sm">
              <thead class="[&_tr]:border-b [&_tr]:border-border">
                <tr class="border-b border-border">
                  <th class="text-foreground h-9 px-3 text-left align-middle font-medium whitespace-nowrap">{{ t.order?.product || 'Product' }}</th>
                  <th class="text-foreground h-9 px-3 text-center align-middle font-medium whitespace-nowrap w-20">{{ t.order?.quantity || 'Qty' }}</th>
                  <th class="text-foreground h-9 px-3 text-center align-middle font-medium whitespace-nowrap w-28">{{ t.order?.unit_price || 'Unit Price' }}</th>
                  <th class="text-foreground h-9 px-3 text-center align-middle font-medium whitespace-nowrap w-28 hidden md:table-cell">{{ t.order?.cost_price || 'Cost' }}</th>
                  <th class="text-foreground h-9 px-3 text-center align-middle font-medium whitespace-nowrap w-28 hidden md:table-cell">{{ t.order?.profit_price || 'Profit' }}</th>
                  <th class="text-foreground h-9 px-3 text-center align-middle font-medium whitespace-nowrap w-28">{{ t.order?.line_total || 'Line Total' }}</th>
                </tr>
              </thead>
              <tbody class="[&_tr:last-child]:border-0">
                <tr v-for="line in order.products" :key="line.id" class="border-b border-border transition-colors hover:bg-muted/50">
                  <td class="p-3 align-middle">
                    <div class="flex items-center gap-2 min-w-0">
                      <img
                        v-if="line.image"
                        :src="line.image"
                        :alt="translatedName(line.name, locale)"
                        class="w-10 h-10 rounded-md border border-border object-cover shrink-0 cursor-zoom-in"
                        @click="openLightbox(line.image)"
                      />
                      <div class="min-w-0">
                        <span class="font-medium block break-words">{{ translatedName(line.name, locale) }}</span>
                        <!-- The catalogue row, while it still exists: an order
                             outlives the product it was sold from. -->
                        <Link
                          v-if="line.product_exists && line.product_slug"
                          :href="route('admin.product.show', line.product_slug)"
                          class="text-xs text-primary hover:underline"
                        >
                          {{ t.order?.view_product || 'View product' }}
                        </Link>
                        <span v-else class="text-xs text-muted-foreground">{{ t.order?.product_removed || 'Archived line' }}</span>
                      </div>
                    </div>
                  </td>
                  <td class="p-3 align-middle text-center tabular-nums">{{ line.quantity }}</td>
                  <td class="p-3 align-middle text-center">
                    <div class="inline-flex flex-col items-center leading-tight">
                      <span v-if="line.old_price" class="text-xs text-muted-foreground line-through">{{ formatPrice(line.old_price) }}</span>
                      <span class="tabular-nums">{{ formatPrice(line.new_price) }}</span>
                    </div>
                  </td>
                  <td class="p-3 align-middle text-center tabular-nums hidden md:table-cell">{{ formatPrice(line.cost_price) }}</td>
                  <td class="p-3 align-middle text-center tabular-nums hidden md:table-cell text-emerald-500">{{ formatPrice(line.profit_price) }}</td>
                  <td class="p-3 align-middle text-center font-semibold tabular-nums">{{ formatPrice(line.line_total) }}</td>
                </tr>
              </tbody>
              <tfoot>
                <tr class="border-t border-border bg-muted/30">
                  <td class="p-3 font-medium" colspan="5">{{ t.order?.lines_total || 'Lines total' }}</td>
                  <td class="p-3 text-center font-semibold tabular-nums">{{ formatPrice(linesTotal) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>

          <div v-else class="p-6 text-center text-sm text-muted-foreground">
            {{ t.order?.no_products || 'This order has no product lines.' }}
          </div>
        </div>

        <!-- Receipts -->
        <div v-if="order.receipts?.length" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
          <div class="p-3 border-b border-border flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-golden-yellow">
              <path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"></path>
              <path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"></path><path d="M12 17.5v-11"></path>
            </svg>
            <h2 class="text-lg font-semibold">{{ t.order?.receipts || 'Transfer Receipts' }}</h2>
            <!-- A receipt is a claim, never a confirmation: the payment status
                 is moved by an admin who checked it against the wallet. -->
            <span class="text-xs text-muted-foreground">{{ t.order?.receipt_hint || 'A receipt is a claim — confirm it against the wallet.' }}</span>
          </div>
          <div class="p-3 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
            <figure v-for="receipt in order.receipts" :key="receipt.id" class="space-y-1">
              <img
                :src="receipt.url"
                :alt="receipt.name"
                class="w-full h-32 rounded-lg border border-border object-cover cursor-zoom-in transition hover:opacity-90"
                @click="openLightbox(receipt.url)"
              />
              <figcaption class="text-[11px] text-muted-foreground truncate" :title="receipt.name">{{ receipt.name }}</figcaption>
            </figure>
          </div>
        </div>

        <!-- Where the order came from -->
        <div class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
          <div class="p-3 border-b border-border flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-golden-yellow">
              <circle cx="12" cy="12" r="10"></circle><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"></path><path d="M2 12h20"></path>
            </svg>
            <h2 class="text-lg font-semibold">{{ t.order?.origin || 'Origin' }}</h2>
          </div>
          <div class="p-3 grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
              <label class="text-xs font-medium text-muted-foreground">{{ t.order?.source || 'Source' }}</label>
              <p class="text-sm mt-0.5">{{ order.source || '—' }}</p>
            </div>
            <div>
              <!-- The BUYER's address as the storefront forwarded it, not the
                   caller's — the storefront server would be the same IP on
                   every order. -->
              <label class="text-xs font-medium text-muted-foreground">{{ t.order?.visitor_ip || 'Visitor IP' }}</label>
              <p class="text-sm mt-0.5 font-mono" dir="ltr">{{ order.ip_address || '—' }}</p>
            </div>
            <div>
              <label class="text-xs font-medium text-muted-foreground">{{ t.order?.updated_at || 'Last Updated' }}</label>
              <p class="text-sm mt-0.5 tabular-nums">{{ order.updated_at || '—' }}</p>
            </div>
            <div class="sm:col-span-3">
              <label class="text-xs font-medium text-muted-foreground">{{ t.order?.user_agent || 'User Agent' }}</label>
              <p class="text-xs mt-0.5 text-muted-foreground break-all" dir="ltr">{{ order.user_agent || '—' }}</p>
            </div>
          </div>
        </div>

        <OrderLogTimeline :logs="order.logs || []" />
      </div>
    </div>

    <ImageLightbox :images="lightboxImages" v-model:index="lightboxIndex" />
  </OrderLayout>
</template>

<script setup>
import { Link, usePage } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import OrderLayout from "../OrderLayout.vue";
import OrderLogTimeline from "./OrderLogTimeline.vue";
import ImageLightbox from "@/Components/ui/ImageLightbox.vue";
import { Breadcrumb } from "@/Pages/Admin/Layout/Layout.js";
import {
  deliveryStatusLabel,
  formatPrice,
  paymentStatusLabel,
  paymentTypeClass,
  paymentTypeLabel,
  savedAmount,
  statusClass,
  translatedName,
} from "../orderDisplay.js";

const props = defineProps({
  order: {
    type: Object,
    required: true,
  },
});

const page = usePage();
const locale = computed(() => page.props.locale || 'ar');
const t = computed(() => page.props.translations?.admin || {});

/* The edit button is hidden from a view-only admin rather than shown and
   refused: the route is gated on `manage orders` either way, so this only
   decides whether the button is worth offering.

   `authUserPermissions` / `authUserRoles` are the props the Inertia middleware
   shares for every request; `auth.user.permissions` is only present when the
   relation happens to be loaded, so it is a fallback rather than the source. */
const canManage = computed(() => {
  const roles = page.props.authUserRoles || page.props.auth?.user?.roles || [];
  if (roles.includes('super_admin')) return true;

  const permissions = page.props.authUserPermissions || page.props.auth?.user?.permissions || [];
  return permissions.includes('manage orders') || permissions.includes('manage own orders');
});

const saved = computed(() => savedAmount(props.order));

const linesTotal = computed(() =>
  (props.order.products || []).reduce((sum, line) => sum + (Number(line.line_total) || 0), 0)
);

const paidInFull = computed(() => {
  const paid = Number(props.order.total_paid);
  const due = Number(props.order.total_amount);
  return Number.isFinite(paid) && Number.isFinite(due) && paid >= due;
});

const outstanding = computed(() => {
  const paid = Number(props.order.total_paid) || 0;
  const due = Number(props.order.total_amount) || 0;
  return Math.round((due - paid) * 100) / 100;
});

// One viewer for every picture on the page, so paging walks receipts and line
// images together.
const lightboxImages = computed(() => [
  ...(props.order.products || [])
    .filter(line => line.image)
    .map(line => ({ url: line.image, alt: translatedName(line.name, locale.value) })),
  ...(props.order.receipts || []).map(receipt => ({ url: receipt.url, alt: receipt.name })),
]);

const lightboxIndex = ref(null);

const openLightbox = (url) => {
  const at = lightboxImages.value.findIndex(img => img.url === url);
  if (at !== -1) lightboxIndex.value = at;
};
</script>
