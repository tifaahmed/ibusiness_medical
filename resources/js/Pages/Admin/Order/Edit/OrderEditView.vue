<template>
  <OrderLayout>
    <div class="space-y-2 sm:space-y-3 md:space-y-4 p-2 sm:p-3 md:p-4 lg:p-6 w-full max-w-full">
      <Breadcrumb
        :title="t.order?.edit_order || 'Edit Order'"
        :breadcrumbs="[
          { label: t.order?.management || 'Orders', link: route('admin.order.list'), active: false },
          { label: order.order_code, link: route('admin.order.show', order.order_code), active: false },
          { label: t.common?.edit || 'Edit', link: '#', active: true },
        ]"
      />

      <div class="max-w-7xl mx-auto">
        <form @submit.prevent="handleSubmit" class="space-y-2 sm:space-y-3 md:space-y-4">
          <OrderForm
            :order="order"
            :membership="membership"
            :payment-statuses="paymentStatuses"
            :delivery-statuses="deliveryStatuses"
            :order-statuses="orderStatuses"
            :payment-types="paymentTypes"
            :products="products"
          />

          <div class="sticky bottom-0 z-10 bg-card border border-border rounded-lg shadow-sm">
            <div class="flex flex-col sm:flex-row p-2 sm:p-3 md:p-4 gap-2 sm:gap-3">
              <!-- Every save writes to order_logs; saying so here is cheaper
                   than explaining an audit row to someone later. -->
              <p class="text-xs text-muted-foreground flex-1 self-center">
                {{ t.order?.save_logged_hint || 'Saving records who changed what in the order log.' }}
              </p>
              <div class="flex gap-2 sm:gap-3 justify-end">
                <Link
                  :href="route('admin.order.show', order.order_code)"
                  class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2"
                >
                  {{ t.common?.cancel || 'Cancel' }}
                </Link>
                <button
                  type="submit"
                  :disabled="orderStore.isLoading"
                  class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2 min-w-[140px]"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                    <path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"></path>
                    <path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"></path>
                    <path d="M7 3v4a1 1 0 0 0 1 1h7"></path>
                  </svg>
                  {{ orderStore.isLoading
                    ? (t.common?.saving || 'Saving…')
                    : (t.order?.update_order || 'Update Order') }}
                </button>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
  </OrderLayout>
</template>

<script setup>
import { Link, usePage } from "@inertiajs/vue3";
import { computed, watch } from "vue";
import OrderLayout from "../OrderLayout.vue";
import { Breadcrumb } from "@/Pages/Admin/Layout/Layout.js";
import { OrderForm } from "../_components/Form";
import { useOrderStore } from "../Stores/OrderStore";

const props = defineProps({
  order: { type: Object, required: true },
  /* Resolved server-side from `order.membership_number` — see
     AdminOrderMembershipResource for why it hangs off the order. */
  membership: {
    type: Object,
    default: () => ({ status: 'none', number: null, earns_member_price: false, card: null }),
  },
  paymentStatuses: { type: Array, default: () => [] },
  deliveryStatuses: { type: Array, default: () => [] },
  orderStatuses: { type: Array, default: () => [] },
  paymentTypes: { type: Array, default: () => [] },
  products: { type: Array, default: () => [] },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const orderStore = useOrderStore();

/* Reseeded whenever the server sends the order back — a successful save
   returns the stored row, and the form has to show what was actually kept
   (a recomputed line total, a cancel reason that was cleared) rather than
   what was typed. */
watch(() => props.order, (order) => {
  if (order?.id) orderStore.setOrder(order);
}, { immediate: true, deep: true });

const handleSubmit = () => orderStore.updateOrder();
</script>
