<template>
  <MembershipUsageListView
    :usages="usages"
    :filters="filters"
    :facilities="facilities"
    :facility-types="facilityTypes"
  />
</template>

<script setup>
import MembershipUsageListView from "./List/MembershipUsageListView.vue";

defineProps({
  usages: { type: Object, required: true },
  filters: { type: Object, default: () => ({ search: '', facility_id: '', facility_type_id: '' }) },
  facilities: { type: Array, default: () => [] },
  facilityTypes: { type: Array, default: () => [] },
});
</script>
