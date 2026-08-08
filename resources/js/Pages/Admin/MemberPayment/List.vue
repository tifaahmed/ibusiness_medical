<template>
  <MemberPaymentListView :payments="payments" />
</template>

<script setup>
import MemberPaymentListView from "./List/MemberPaymentListView.vue";

defineProps({
  payments: { type: Object, required: true },
});
</script>
