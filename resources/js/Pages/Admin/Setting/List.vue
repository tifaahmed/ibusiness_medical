<template>
  <SettingListView :settings="settings" :filters="filters" :value-types="valueTypes" />
</template>

<script setup>
import SettingListView from "./List/SettingListView.vue";

defineProps({
  settings: {
    type: Object,
    required: true,
  },
  filters: {
    type: Object,
    default: () => ({
      search: '',
      value_type: '',
      sort: 'key',
    }),
  },
  valueTypes: {
    type: Array,
    default: () => [],
  },
});
</script>
