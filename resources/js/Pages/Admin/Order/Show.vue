<template>
  <OrderShowView :order="order" :abs-configured="absConfigured" :shipments="shipments" :invoice-all-url="invoiceAllUrl" />
</template>

<script setup>
import OrderShowView from "./Show/OrderShowView.vue";

defineProps({
  order: {
    type: Object,
    required: true,
  },
  /* Whether an ABS key is configured at all. False hides the ship button
     rather than offering one that can only fail. */
  shipments: { type: Array, default: () => [] },
  invoiceAllUrl: { type: String, default: '' },
  absConfigured: {
    type: Boolean,
    default: false,
  },
});
</script>
