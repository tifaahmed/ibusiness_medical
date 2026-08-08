<template>
  <MemberLogsView :member="member" :logs="logs" :filters="filters" :admin-options="adminOptions" />
</template>

<script setup>
import MemberLogsView from "../Member/Logs/MemberLogsView.vue";

defineProps({
  member: { type: Object, required: true },
  logs: { type: Object, required: true },
  filters: { type: Object, default: () => ({ admin_id: null }) },
  adminOptions: { type: Array, default: () => [] },
});
</script>
