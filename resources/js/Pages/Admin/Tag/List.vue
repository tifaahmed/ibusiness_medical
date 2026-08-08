<template>
  <TagListView :tags="tags" :filters="filters" />
</template>

<script setup>
import TagListView from "./List/TagListView.vue";

defineProps({
  tags: {
    type: Object,
    required: true,
  },
  filters: {
    type: Object,
    default: () => ({
      search: '',
    }),
  },
});
</script>
