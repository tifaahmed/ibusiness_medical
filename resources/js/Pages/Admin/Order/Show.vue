<template>
  <OrderShowView :order="order" :abs-configured="absConfigured" />
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
  absConfigured: {
    type: Boolean,
    default: false,
  },
});
</script>
