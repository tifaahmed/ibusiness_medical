<template>
  <NewsTickerLayout>
    <div class="flex flex-col h-full lg:h-auto w-full max-w-full overflow-x-hidden">
      <div class="flex-shrink-0 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 sm:gap-4 p-2 sm:p-3 md:p-4 lg:p-6 pb-0 w-full max-w-full overflow-hidden">
        <Link
          :href="route('admin.news-ticker.create')"
          data-slot="button"
          class="inline-flex items-center cursor-pointer justify-center gap-1.5 sm:gap-2 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-8 sm:h-9 px-2 sm:px-3 md:px-4 py-2 flex-shrink-0 btn-golden"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus h-3.5 w-3.5 sm:h-4 sm:w-4">
            <path d="M5 12h14"></path>
            <path d="M12 5v14"></path>
          </svg>
          <span class="hidden sm:inline">{{ t.news_ticker?.add_new || 'Add New News' }}</span>
          <span class="sm:hidden">{{ t.common?.add || 'Add' }}</span>
        </Link>
      </div>

      <div class="flex-1 min-h-0 lg:min-h-fit w-full max-w-full px-2 sm:px-3 md:px-4 lg:px-6 pb-2 sm:pb-3 md:pb-4 lg:pb-6 overflow-hidden lg:overflow-visible">
        <NewsTickerListTable :news-tickers="newsTickers" @delete="handleDelete" />
      </div>
    </div>
  </NewsTickerLayout>
</template>

<script setup>
import NewsTickerLayout from "../NewsTickerLayout.vue";
import NewsTickerListTable from "./NewsTickerListTable.vue";
import { useNewsTickerStore } from "../Stores/NewsTickerStore";
import { Link, usePage } from "@inertiajs/vue3";
import { computed } from "vue";

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

defineProps({
  newsTickers: {
    type: Object,
    required: true,
  },
});

const newsTickerStore = useNewsTickerStore();

const handleDelete = (id) => {
  newsTickerStore.confirmDelete(id);
};
</script>
