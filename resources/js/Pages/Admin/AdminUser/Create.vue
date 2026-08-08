<template>
  <AdminUserCreateView
    :assignable-roles="assignable_roles"
    :all-permissions="all_permissions"
    :partner-options="partner_options"
  />
</template>

<script setup>
import AdminUserCreateView from "./Create/AdminUserCreateView.vue";

defineProps({
  assignable_roles: { type: Array, required: true },
  all_permissions: { type: Array, required: true },
  partner_options: { type: Array, default: () => [] },
});
</script>
