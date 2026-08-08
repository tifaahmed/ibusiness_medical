<template>
  <AdminUserShowView :admin="admin" />
</template>

<script setup>
import AdminUserShowView from "./Show/AdminUserShowView.vue";

defineProps({
  admin: { type: Object, required: true },
});
</script>
