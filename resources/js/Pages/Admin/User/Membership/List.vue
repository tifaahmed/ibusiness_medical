<template>
  <MemberListView :members="users" :filters="filters" :chart-data="chartData" :chart-days="chartDays" :otp-policy="otpPolicy" />
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
  },
  /* The storefront login policy, straight from the list controller. */
  otpPolicy: {
    type: Object,
    default: null
  }
});

const chartDays = computed(() => props.filters?.chart_days || null);
</script>

