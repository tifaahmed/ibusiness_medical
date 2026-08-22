<template>
  <OrderListView :orders="orders" :filters="filters" />
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
      payment_type: ''
    })
  }
});
</script>
