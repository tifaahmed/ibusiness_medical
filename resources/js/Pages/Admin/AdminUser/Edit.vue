<template>
  <AdminUserEditView
    :admin="admin"
    :assignable-roles="assignable_roles"
    :all-permissions="all_permissions"
    :partner-options="partner_options"
  />
</template>

<script setup>
import AdminUserEditView from "./Edit/AdminUserEditView.vue";

defineProps({
  admin: { type: Object, required: true },
  assignable_roles: { type: Array, required: true },
  all_permissions: { type: Array, required: true },
  partner_options: { type: Array, default: () => [] },
});
</script>
