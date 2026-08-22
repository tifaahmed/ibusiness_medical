<template>
  <div class="space-y-4">
    <div class="flex flex-col lg:flex-row gap-4 items-start lg:items-end">
      <div class="w-full lg:flex-1 lg:max-w-md xl:max-w-lg space-y-2">
        <label class="text-sm leading-none font-medium" for="order-search">{{ t.common?.search || 'Search' }}</label>
        <div class="relative">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground">
            <circle cx="11" cy="11" r="8"></circle>
            <path d="m21 21-4.3-4.3"></path>
          </svg>
          <input
            v-model="filters.search"
            @input="handleSearch"
            class="flex h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm pl-9"
            id="order-search"
            :placeholder="t.order?.search_placeholder || 'Search by order code, customer, phone or membership no...'"
          />
        </div>
      </div>

      <div class="w-full sm:w-auto space-y-2">
        <label class="text-sm leading-none font-medium block">{{ t.order?.payment_status || 'Payment Status' }}</label>
        <select
          v-model="filters.payment_status"
          @change="applyFilters()"
          class="border-input focus-visible:border-ring focus-visible:ring-ring/50 rounded-md border bg-transparent px-3 py-1.5 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] h-9 cursor-pointer"
        >
          <option value="">{{ t.order?.all_statuses || 'All' }}</option>
          <option v-for="option in paymentStatusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
      </div>

      <div class="w-full sm:w-auto space-y-2">
        <label class="text-sm leading-none font-medium block">{{ t.order?.delivery_status || 'Delivery Status' }}</label>
        <select
          v-model="filters.delivery_status"
          @change="applyFilters()"
          class="border-input focus-visible:border-ring focus-visible:ring-ring/50 rounded-md border bg-transparent px-3 py-1.5 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] h-9 cursor-pointer"
        >
          <option value="">{{ t.order?.all_statuses || 'All' }}</option>
          <option v-for="option in deliveryStatusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
      </div>

      <div class="w-full sm:w-auto space-y-2">
        <label class="text-sm leading-none font-medium block">{{ t.order?.payment_type || 'Payment Type' }}</label>
        <select
          v-model="filters.payment_type"
          @change="applyFilters()"
          class="border-input focus-visible:border-ring focus-visible:ring-ring/50 rounded-md border bg-transparent px-3 py-1.5 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] h-9 cursor-pointer"
        >
          <option value="">{{ t.order?.all_statuses || 'All' }}</option>
          <option v-for="option in paymentTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
      </div>
    </div>

    <div class="flex flex-col sm:flex-row gap-2">
      <button
        @click="handleReset"
        class="cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all bg-destructive text-white shadow-xs hover:bg-destructive/90 h-8 rounded-md px-3 flex items-center gap-2 ml-auto"
      >
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
          <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path>
          <path d="M3 3v5h5"></path>
        </svg>
        {{ t.common?.reset_filters || 'Reset Filters' }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps({
  initialFilters: {
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

const paymentStatusOptions = computed(() => [
  { value: 'pending', label: t.value.order?.status_pending || 'Pending' },
  { value: 'accepted', label: t.value.order?.status_accepted || 'Accepted' },
  { value: 'rejected', label: t.value.order?.status_rejected || 'Rejected' },
  { value: 'canceled', label: t.value.order?.status_canceled || 'Canceled' },
]);

const deliveryStatusOptions = computed(() => [
  { value: 'pending', label: t.value.order?.delivery_pending || 'Pending' },
  { value: 'processing', label: t.value.order?.delivery_processing || 'Processing' },
  { value: 'on-delivery', label: t.value.order?.delivery_on_delivery || 'On Delivery' },
  { value: 'completed', label: t.value.order?.delivery_completed || 'Completed' },
]);

const paymentTypeOptions = computed(() => [
  { value: 'cod', label: t.value.order?.type_cod || 'Cash on Delivery' },
  { value: 'transfer-wallet', label: t.value.order?.type_transfer_wallet || 'Transfer Wallet' },
]);

const getInitialFilters = () => {
  const defaults = {
    search: '',
    payment_status: '',
    delivery_status: '',
    payment_type: ''
  };

  if (props.initialFilters) {
    return { ...defaults, ...props.initialFilters };
  }
  if (typeof window !== 'undefined') {
    const urlParams = new URLSearchParams(window.location.search);
    return {
      ...defaults,
      search: urlParams.get('search') || '',
      payment_status: urlParams.get('payment_status') || '',
      delivery_status: urlParams.get('delivery_status') || '',
      payment_type: urlParams.get('payment_type') || ''
    };
  }
  return defaults;
};

const filters = ref(getInitialFilters());

let searchTimeout = null;
const debouncedSearch = (value) => {
  if (searchTimeout) clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    applyFilters({ ...filters.value, search: value });
  }, 300);
};

const handleSearch = (event) => {
  filters.value.search = event.target.value;
  debouncedSearch(event.target.value);
};

const handleReset = () => {
  filters.value = { search: '', payment_status: '', delivery_status: '', payment_type: '' };
  applyFilters();
};

const applyFilters = (filterValues = null) => {
  const currentFilters = filterValues || filters.value;
  const params = {};
  if (currentFilters.search) params.search = currentFilters.search;
  if (currentFilters.payment_status) params.payment_status = currentFilters.payment_status;
  if (currentFilters.delivery_status) params.delivery_status = currentFilters.delivery_status;
  if (currentFilters.payment_type) params.payment_type = currentFilters.payment_type;

  router.get(route('admin.order.list'), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true
  });
};

watch(() => props.initialFilters, (newFilters) => {
  if (newFilters) {
    filters.value = { ...filters.value, ...newFilters };
  }
}, { deep: true });
</script>
