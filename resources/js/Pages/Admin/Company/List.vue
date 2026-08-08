<template>
  <CompanyListView :companies="companies" :filters="filters" />
</template>

<script setup>
import CompanyListView from './List/CompanyListView.vue';
defineProps({
  companies: { type: Object, required: true },
  filters: { type: Object, default: () => ({ search: '' }) },
});
</script>
