<template>
  <ServiceListView :services="services" :filters="filters" />
</template>

<script setup>
import ServiceListView from "./List/ServiceListView.vue";

defineProps({
  services: {
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
