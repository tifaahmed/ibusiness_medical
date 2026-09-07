<template>
  <FacilityBranchListView :facilityBranches="facilityBranches" :filters="filters" :facilities="facilities" :incomplete-counts="incompleteCounts" />
</template>

<script setup>
import FacilityBranchListView from "./List/FacilityBranchListView.vue";

const props = defineProps({
  facilityBranches: {
    type: Object,
    required: true
  },
  filters: {
    type: Object,
    default: () => ({
      search: '',
      facility_id: '',
      no_governorate: false,
      no_city: false
    })
  },
  incompleteCounts: {
    type: Object,
    default: () => ({ no_governorate: 0, no_city: 0 })
  },
  facilities: {
    type: Array,
    default: () => []
  }
});
</script>
