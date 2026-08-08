<template>
  <FaqListView :faqs="faqs" :filters="filters" />
</template>

<script setup>
import FaqListView from "./List/FaqListView.vue";

defineProps({
  faqs: {
    type: Object,
    required: true,
  },
  filters: {
    type: Object,
    default: () => ({
      search: '',
      is_active: '',
    }),
  },
});
</script>
