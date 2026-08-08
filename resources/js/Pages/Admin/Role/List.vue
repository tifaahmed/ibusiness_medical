<template>
  <RoleListView :roles="roles" :filters="filters" />
</template>

<script setup>
import RoleListView from "./List/RoleListView.vue";

defineProps({
  roles: { type: Object, required: true },
  filters: { type: Object, default: () => ({ search: "" }) },
});
</script>
