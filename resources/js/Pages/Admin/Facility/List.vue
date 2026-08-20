<template>
  <FacilityListView :facilities="facilities" :filters="filters" :facility-types="facilityTypes" :governorates="governorates" :cities="cities" :sales-options="salesOptions" />
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
  }
});
</script>


