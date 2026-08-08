<template>
  <MemberPaymentFormView :payment="payment" :memberships="memberships" />
</template>

<script setup>
import MemberPaymentFormView from "./Form/MemberPaymentFormView.vue";

defineProps({
  payment: { type: Object, required: true },
  memberships: { type: Array, default: () => [] },
});
</script>
