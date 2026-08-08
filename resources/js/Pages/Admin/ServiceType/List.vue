<template>
  <ServiceTypeListView :service-types="serviceTypes" :filters="filters" />
</template>

<script setup>
import ServiceTypeListView from "./List/ServiceTypeListView.vue";

defineProps({
  serviceTypes: {
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
