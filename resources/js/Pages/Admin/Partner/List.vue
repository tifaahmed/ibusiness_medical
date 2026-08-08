<template>
  <PartnerListView :partners="partners" :filters="filters" />
</template>

<script setup>
import PartnerListView from "./List/PartnerListView.vue";

defineProps({
  partners: {
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
