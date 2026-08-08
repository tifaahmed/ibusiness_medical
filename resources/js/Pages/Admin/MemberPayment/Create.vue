<template>
  <MemberPaymentFormView :memberships="memberships" />
</template>

<script setup>
import MemberPaymentFormView from "./Form/MemberPaymentFormView.vue";

defineProps({
  memberships: { type: Array, default: () => [] },
});
</script>
