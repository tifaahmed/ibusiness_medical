<template>
  <MembershipUsageFormView
    :usage="usage"
    :memberships="memberships"
    :facilities="facilities"
    :facility-branches="facilityBranches"
    :facility-types="facilityTypes"
  />
</template>

<script setup>
import MembershipUsageFormView from "./Form/MembershipUsageFormView.vue";

defineProps({
  usage: { type: Object, default: null },
  memberships: { type: Array, default: () => [] },
  facilities: { type: Array, default: () => [] },
  facilityBranches: { type: Array, default: () => [] },
  facilityTypes: { type: Array, default: () => [] },
});
</script>
