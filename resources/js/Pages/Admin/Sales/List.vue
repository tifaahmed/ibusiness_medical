<template>
  <SalesListView :sales="sales" :filters="filters" :filter-options="filterOptions" />
</template>

<script setup>
import SalesListView from "./List/SalesListView.vue";

defineProps({
  sales: {
    type: Object,
    required: true,
  },
  filters: {
    type: Object,
    default: () => ({
      search: '',
    }),
  },
  filterOptions: {
    type: Object,
    default: () => ({ admins: [], creators: [] }),
  },
});
</script>
