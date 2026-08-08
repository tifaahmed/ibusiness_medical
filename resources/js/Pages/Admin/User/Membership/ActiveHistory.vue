<template>
  <MemberActiveHistoriesView :member="member" :histories="histories" />
</template>

<script setup>
import MemberActiveHistoriesView from "../Member/ActiveHistory/MemberActiveHistoriesView.vue";

defineProps({
  member: { type: Object, required: true },
  histories: { type: Object, required: true },
});
</script>
