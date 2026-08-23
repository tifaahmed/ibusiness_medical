<template>
  <OrderShowView :order="order" />
</template>

<script setup>
import OrderShowView from "./Show/OrderShowView.vue";

defineProps({
  order: {
    type: Object,
    required: true,
  },
});
</script>
