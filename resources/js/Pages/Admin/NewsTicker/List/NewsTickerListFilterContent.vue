<template>
  <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 sm:gap-4">
    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-2 sm:gap-3 w-full sm:w-auto">
      <div class="flex items-center gap-2 w-full sm:w-auto">
        <input
          type="text"
          :value="localFilters.category"
          @input="handleCategoryChange"
          :placeholder="t.news_ticker?.category_placeholder || 'Filter by category...'"
          class="w-full sm:w-48 py-2 px-3 border border-border bg-transparent text-foreground placeholder:text-muted-foreground/60 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-ring/50 focus:border-ring transition-colors"
        />
      </div>
    </div>
    <div class="flex items-center gap-2 w-full sm:w-auto">
      <select
        :value="localFilters.is_active"
        @change="handleActiveChange"
        class="border-input focus-visible:border-ring focus-visible:ring-ring/50 rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] w-full sm:w-auto cursor-pointer"
      >
        <option value="">{{ t.common?.all_status || 'All Status' }}</option>
        <option value="1">{{ t.common?.active || 'Active' }}</option>
        <option value="0">{{ t.common?.inactive || 'Inactive' }}</option>
      </select>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const props = defineProps({
  initialFilters: {
    type: Object,
    default: () => ({
      search: '',
      is_active: '',
      category: '',
    }),
  },
});

const emit = defineEmits(['filter-change']);

const localFilters = ref({ ...props.initialFilters });

const handleCategoryChange = (e) => {
  localFilters.value.category = e.target.value;
  debouncedCategory();
};

let categoryTimeout = null;
const debouncedCategory = () => {
  clearTimeout(categoryTimeout);
  categoryTimeout = setTimeout(() => {
    applyFilters();
  }, 400);
};

const handleActiveChange = (e) => {
  localFilters.value.is_active = e.target.value;
  applyFilters();
};

const applyFilters = () => {
  emit('filter-change', { ...localFilters.value });
  const params = new URLSearchParams();
  Object.entries(localFilters.value).forEach(([key, value]) => {
    if (value !== '' && value !== null && value !== undefined) {
      params.set(key, value);
    }
  });
  params.set('page', '1');
  router.visit(`${route('admin.news-ticker.list')}?${params.toString()}`, {
    preserveState: true,
    preserveScroll: false,
  });
};

watch(() => props.initialFilters, (newVal) => {
  localFilters.value = { ...newVal };
}, { deep: true });
</script>
