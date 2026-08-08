<template>
  <RoleEditView :role="role" :all-permissions="all_permissions" />
</template>

<script setup>
import RoleEditView from "./Edit/RoleEditView.vue";

defineProps({
  role: { type: Object, required: true },
  all_permissions: { type: Array, required: true },
});
</script>
