<template>
  <PartnerOfferListView :offers="offers" :filters="filters" />
</template>

<script setup>
import PartnerOfferListView from "./List/PartnerOfferListView.vue";

defineProps({
  offers: {
    type: Object,
    required: true,
  },
  filters: {
    type: Object,
    default: () => ({
      search: '',
      partner_id: '',
    }),
  },
});
</script>
