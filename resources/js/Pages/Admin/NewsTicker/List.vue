<template>
  <NewsTickerListView :news-tickers="newsTickers" :filters="filters" />
</template>

<script setup>
import NewsTickerListView from "./List/NewsTickerListView.vue";

defineProps({
  newsTickers: {
    type: Object,
    required: true,
  },
  filters: {
    type: Object,
    default: () => ({
      search: '',
      is_active: '',
      category: '',
    }),
  },
});
</script>
