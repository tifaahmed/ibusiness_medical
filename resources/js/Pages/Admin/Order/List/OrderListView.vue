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
          </div>

          <div class="px-2 sm:px-4 md:px-6 space-y-2 sm:space-y-3 md:space-y-4 w-full max-w-full overflow-hidden min-w-0">
            <OrderListFilterContent :initial-filters="filters" />
          </div>
        </div>
      </div>

      <div class="flex-1 min-h-0 lg:flex-none w-full max-w-full px-2 sm:px-3 md:px-4 lg:px-6 pb-2 sm:pb-3 md:pb-4 lg:pb-6 overflow-hidden lg:overflow-visible">
        <OrderListTable :orders="orders" />
      </div>
    </div>
  </OrderLayout>
</template>

<script setup>
import OrderLayout from "../OrderLayout.vue";
import OrderListFilterContent from "./OrderListFilterContent.vue";
import OrderListTable from "./OrderListTable.vue";
import { usePage } from "@inertiajs/vue3";
import { computed, ref } from "vue";

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
      payment_type: ''
    })
  }
});

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const filters = ref(props.filters || { search: '', payment_status: '', delivery_status: '', payment_type: '' });
</script>
