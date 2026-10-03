<template>
  <StoreCategoryListView :storeCategories="storeCategories" :filters="filters" />
</template>

<script setup>
import StoreCategoryListView from "./List/StoreCategoryListView.vue";

const props = defineProps({
  storeCategories: {
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
