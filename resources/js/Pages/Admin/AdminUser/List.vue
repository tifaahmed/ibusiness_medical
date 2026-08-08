<template>
  <AdminUserListView :admins="admins" :filters="filters" />
</template>

<script setup>
import AdminUserListView from "./List/AdminUserListView.vue";

defineProps({
  admins: { type: Object, required: true },
  filters: {
    type: Object,
    default: () => ({ search: "", role: null }),
  },
});
</script>
