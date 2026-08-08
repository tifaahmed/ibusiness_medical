<template>
  <ContractListView :contracts="contracts" :filters="filters" />
</template>

<script setup>
import ContractListView from "./List/ContractListView.vue";

const props = defineProps({
  contracts: {
    type: Object,
    required: true
  },
  filters: {
    type: Object,
    default: () => ({
      search: '',
      is_active: ''
    })
  }
});
</script>
