<template>
  <ProductListView :products="products" :filters="filters" />
</template>

<script setup>
import ProductListView from "./List/ProductListView.vue";

const props = defineProps({
  products: {
    type: Object,
    required: true
  },
  filters: {
    type: Object,
    default: () => ({
      search: ''
    })
  }
});
</script>
