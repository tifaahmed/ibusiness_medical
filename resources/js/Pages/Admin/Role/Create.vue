<template>
  <RoleCreateView :all-permissions="all_permissions" />
</template>

<script setup>
import RoleCreateView from "./Create/RoleCreateView.vue";

defineProps({
  all_permissions: { type: Array, required: true },
});
</script>
