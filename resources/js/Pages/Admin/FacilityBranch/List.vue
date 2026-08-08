<template>
  <FacilityBranchListView :facilityBranches="facilityBranches" :filters="filters" :facilities="facilities" />
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
      facility_id: ''
    })
  },
  facilities: {
    type: Array,
    default: () => []
  }
});
</script>
