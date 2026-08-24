<template>
  <OrderListView :orders="orders" :filters="filters" :order-statuses="orderStatuses" />
</template>

<script setup>
import OrderListView from "./List/OrderListView.vue";

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
</script>
