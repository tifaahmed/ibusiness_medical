<template>
  <FacilityBranchListView
    :facilityBranches="facilityBranches"
    :filters="filters"
    :facilities="facilities"
    :governorates="governorates"
    :cities="cities"
    :facility-types="facilityTypes"
    :incomplete-counts="incompleteCounts"
    :place-ai-enabled="placeAiEnabled"
    :location-ai-enabled="locationAiEnabled"
  />
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
      governorate_id: '',
      city_id: '',
      facility_type_id: '',
      no_governorate: false,
      no_city: false,
      no_address: false
    })
  },
  incompleteCounts: {
    type: Object,
    default: () => ({ no_governorate: 0, no_city: 0, no_address: 0, no_place: 0, no_location: 0, duplicate_names: 0 })
  },
  // False when GEMINI_API_KEY is unset on the server.
  placeAiEnabled: {
    type: Boolean,
    default: false
  },
  locationAiEnabled: {
    type: Boolean,
    default: false
  },
  facilities: {
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
  facilityTypes: {
    type: Array,
    default: () => []
  }
});
</script>
