<template>
  <FacilityListView :facilities="facilities" :filters="filters" :facility-types="facilityTypes" :governorates="governorates" :cities="cities" :sales-options="salesOptions" :incomplete-counts="incompleteCounts" />
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
      governorate_id: '',
      city_id: '',
      sales_id: ''
    })
  },
  facilityTypes: {
    type: Array,
    default: () => []
  },
  governorates: {
    type: Array,
    default: () => []
  },
  cities: {
    type: Array,
    default: () => []
  },
  salesOptions: {
    type: Array,
    default: () => []
  },
  incompleteCounts: {
    type: Object,
    default: () => ({ governorate: 0, city: 0, either: 0 })
  }
});
</script>


