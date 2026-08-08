<template>
  <OfferListView :offers="offers" :filters="filters" />
</template>

<script setup>
import OfferListView from "./List/OfferListView.vue";

const props = defineProps({
  offers: {
    type: Object,
    required: true
  },
  filters: {
    type: Object,
    default: () => ({
      search: '',
      offerable_type: ''
    })
  }
});
</script>
