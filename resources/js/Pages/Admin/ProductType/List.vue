<template>
  <ProductTypeListView :productTypes="productTypes" :filters="filters" />
</template>

<script setup>
import ProductTypeListView from "./List/ProductTypeListView.vue";

const props = defineProps({
  productTypes: {
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
