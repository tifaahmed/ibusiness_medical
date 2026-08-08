<template>
  <SalesListView :sales="sales" />
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
});
</script>
