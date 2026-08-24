<template>
  <OrderLayout>
    <div class="flex flex-col h-full lg:h-auto w-full max-w-full overflow-x-hidden">
      <div class="flex-shrink-0 space-y-2 sm:space-y-3 md:space-y-4 p-2 sm:p-3 md:p-4 lg:p-6 pb-2 sm:pb-3 md:pb-4 lg:pb-4 w-full max-w-full overflow-hidden">
        <div class="bg-card text-card-foreground flex flex-col gap-2 sm:gap-3 md:gap-4 rounded-xl border border-border py-2 sm:py-3 md:py-4 shadow-sm overflow-hidden w-full max-w-full">
          <div class="flex flex-row items-center justify-between py-2 px-3 sm:px-4 md:px-6 w-full overflow-hidden gap-2 sm:gap-4">
            <div class="leading-none font-semibold min-w-0 flex-1">
              <div class="title-golden min-w-0 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon sm:w-6 sm:h-6 flex-shrink-0">
                  <path d="M3 6h18"></path>
                  <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                  <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                  <line x1="10" x2="10" y1="11" y2="17"></line>
                  <line x1="14" x2="14" y1="11" y2="17"></line>
                </svg>
                <span class="text-sm sm:text-base truncate block min-w-0">{{ t.order?.trash_title || 'Trash - Deleted Orders' }}</span>
              </div>
            </div>

            <Link
              :href="route('admin.order.list')"
              class="inline-flex items-center cursor-pointer justify-center gap-1.5 sm:gap-2 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 sm:h-9 px-2 sm:px-3 md:px-4 py-2 flex-shrink-0"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 sm:h-4 sm:w-4">
                <path d="m12 19-7-7 7-7"></path>
                <path d="M19 12H5"></path>
              </svg>
              <span class="hidden sm:inline">{{ t.common?.back_to_list || 'Back to List' }}</span>
              <span class="sm:hidden">{{ t.common?.back || 'Back' }}</span>
            </Link>
          </div>

          <div class="px-2 sm:px-4 md:px-6 space-y-2 sm:space-y-3 md:space-y-4 w-full max-w-full overflow-hidden min-w-0">
            <!-- The list's own filters, pointed at this page. -->
            <OrderListFilterContent :initial-filters="filters" route-name="admin.order.trash" />
          </div>
        </div>
      </div>

      <div class="flex-1 min-h-0 lg:flex-none w-full max-w-full px-2 sm:px-3 md:px-4 lg:px-6 pb-2 sm:pb-3 md:pb-4 lg:pb-6 overflow-hidden lg:overflow-visible">
        <OrderTrashTable :orders="orders" @restore="handleRestore" @force-delete="handleForceDelete" />
      </div>
    </div>
  </OrderLayout>
</template>

<script setup>
import OrderLayout from "./OrderLayout.vue";
import OrderListFilterContent from "./List/OrderListFilterContent.vue";
import OrderTrashTable from "./_components/OrderTrashTable.vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import { useNotification } from "@/composables/useNotification";

defineProps({
  orders: {
    type: Object,
    required: true,
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
      created_to: '',
    }),
  },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});
const notification = useNotification();

/* Guards both actions: an admin double-clicking Restore must not send the
   request twice, and the second one would 404 against an order already out
   of the trash. */
const working = ref(false);

const handleRestore = (order) => {
  if (working.value) return;

  const question = (t.value.order?.confirm_restore || 'Restore order :code back to the list?')
    .replace(':code', order.order_code);

  if (!confirm(question)) return;

  working.value = true;
  router.post(route('admin.order.restore', order.id), {}, {
    preserveScroll: true,
    onError: (errors) => {
      notification.error(
        Object.values(errors || {})[0]
          || t.value.order?.restored_failed
          || 'Could not restore the order.'
      );
    },
    onFinish: () => { working.value = false; },
  });
};

const handleForceDelete = (order) => {
  if (working.value) return;

  /* Spells the code out, because this is the one action on the order that
     nothing undoes — the confirm is the last place to notice it is the
     wrong row. */
  const question = (t.value.order?.confirm_force_delete
      || 'Permanently delete order :code? Its lines and receipts go with it and this cannot be undone.')
    .replace(':code', order.order_code);

  if (!confirm(question)) return;

  working.value = true;
  router.delete(route('admin.order.force-delete', order.id), {
    preserveScroll: true,
    onError: (errors) => {
      notification.error(
        Object.values(errors || {})[0]
          || t.value.order?.force_deleted_failed
          || 'Could not permanently delete the order.'
      );
    },
    onFinish: () => { working.value = false; },
  });
};
</script>
