<template>
  <PartnerOfferRequestShowView :request="request" />
</template>

<script setup>
import PartnerOfferRequestShowView from "./Show/PartnerOfferRequestShowView.vue";

defineProps({
  request: {
    type: Object,
    required: true,
  },
});
</script>
