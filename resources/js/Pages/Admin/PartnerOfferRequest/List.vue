<template>
  <PartnerOfferRequestListView :requests="requests" :filters="filters" />
</template>

<script setup>
import PartnerOfferRequestListView from "./List/PartnerOfferRequestListView.vue";

defineProps({
  requests: {
    type: Object,
    required: true,
  },
  filters: {
    type: Object,
    default: () => ({
      search: '',
      partner_offer_id: '',
    }),
  },
});
</script>
