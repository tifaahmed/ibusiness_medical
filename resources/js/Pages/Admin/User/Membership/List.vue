<template>
  <MemberListView :members="users" :filters="filters" :chart-data="chartData" :chart-days="chartDays" />
</template>

<script setup>
import { computed } from 'vue';
import MemberListView from "../Member/List/MemberListView.vue";

const props = defineProps({
  users: {
    type: Object,
    required: true
  },
  filters: {
    type: Object,
    default: () => ({
      search: '',
      is_active: null
    })
  },
  chartData: {
    type: Object,
    default: () => ({ daily: {}, monthly: {} })
  }
});

const chartDays = computed(() => props.filters?.chart_days || null);
</script>

