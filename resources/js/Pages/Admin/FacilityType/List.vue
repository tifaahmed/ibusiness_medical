<template>
  <FacilityTypeListView :facilityTypes="facilityTypes" :filters="filters" />
</template>

<script setup>
import FacilityTypeListView from "./List/FacilityTypeListView.vue";

const props = defineProps({
  facilityTypes: {
    type: Object,
    required: true
  },
  filters: {
    type: Object,
    default: () => ({
      search: ''
    })
  }
});
</script>



