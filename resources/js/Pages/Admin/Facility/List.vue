<template>
  <FacilityListView :facilities="facilities" :filters="filters" :facility-types="facilityTypes" :governorates="governorates" />
</template>

<script setup>
import FacilityListView from "./List/FacilityListView.vue";

const props = defineProps({
  facilities: {
    type: Object,
    required: true
  },
  filters: {
    type: Object,
    default: () => ({
      search: '',
      facility_type_id: '',
      governorate_id: ''
    })
  },
  facilityTypes: {
    type: Array,
    default: () => []
  },
  governorates: {
    type: Array,
    default: () => []
  }
});
</script>


