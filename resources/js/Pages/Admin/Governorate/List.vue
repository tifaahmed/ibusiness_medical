<template>
  <GovernorateListView :governorates="governorates" :filters="filters" />
</template>

<script setup>
import GovernorateListView from "./List/GovernorateListView.vue";

const props = defineProps({
  governorates: {
    type: Object,
    required: true
  },
  filters: {
    type: Object,
    default: () => ({
      search: ''
    })
  }
});
</script>



