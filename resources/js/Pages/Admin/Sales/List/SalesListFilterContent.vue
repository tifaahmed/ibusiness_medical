<template>
  <div class="w-full min-w-0 space-y-3" data-testid="sales-filters">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
      <div class="sm:col-span-2 min-w-0">
        <label class="block text-xs font-medium mb-1" for="search">{{ t.common?.search || 'Search' }}</label>
        <input id="search" v-model="filters.search" @input="debounced" :placeholder="t.sales?.search_placeholder || 'Name, linked admin name or e-mail…'" class="h-9 w-full min-w-0 rounded-md border border-border bg-transparent px-3 text-sm" />
      </div>

      <div class="min-w-0">
        <label class="block text-xs font-medium mb-1">{{ t.sales?.linked_admin || 'Linked admin' }}</label>
        <SearchableSelect v-model="filters.admin_id" :options="adminChoices" :placeholder="t.sales?.any_admin || 'Any admin'" @update:modelValue="apply()" />
      </div>

      <div v-for="f in yesNo" :key="f.key" class="min-w-0">
        <label class="block text-xs font-medium mb-1">{{ f.label }}</label>
        <select v-model="filters[f.key]" @change="apply()" class="h-9 w-full min-w-0 rounded-md border border-border bg-background px-2 text-sm" :data-testid="'sf-' + f.key">
          <option value="">{{ t.common?.all || 'All' }}</option>
          <option value="yes">{{ t.common?.yes || 'Yes' }}</option>
          <option value="no">{{ t.common?.no || 'No' }}</option>
        </select>
      </div>

      <div v-if="options.creators?.length" class="min-w-0">
        <label class="block text-xs font-medium mb-1">{{ t.sales?.created_by || 'Created by' }}</label>
        <SearchableSelect v-model="filters.created_by" :options="creatorChoices" :placeholder="t.common?.all || 'All'" @update:modelValue="apply()" />
      </div>

      <div class="min-w-0">
        <label class="block text-xs font-medium mb-1">{{ t.common?.from || 'Created from' }}</label>
        <input type="date" v-model="filters.created_from" @change="apply()" class="h-9 w-full min-w-0 rounded-md border border-border bg-transparent px-2 text-sm" />
      </div>
      <div class="min-w-0">
        <label class="block text-xs font-medium mb-1">{{ t.common?.to || 'Created to' }}</label>
        <input type="date" v-model="filters.created_to" @change="apply()" class="h-9 w-full min-w-0 rounded-md border border-border bg-transparent px-2 text-sm" />
      </div>

      <div class="min-w-0">
        <label class="block text-xs font-medium mb-1">{{ t.common?.sort || 'Sort' }}</label>
        <select v-model="filters.sort" @change="apply()" class="h-9 w-full min-w-0 rounded-md border border-border bg-background px-2 text-sm" data-testid="sf-sort">
          <option v-for="o in sorts" :key="o.v" :value="o.v">{{ o.l }}</option>
        </select>
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <button v-if="hasActiveFilters" type="button" @click="reset" class="inline-flex h-8 items-center gap-1.5 rounded-md bg-destructive px-3 text-xs font-medium text-white hover:bg-destructive/90" data-testid="sf-clear">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        {{ t.common?.clear || 'Clear' }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

const props = defineProps({
  initialFilters: { type: Object, default: () => ({}) },
  options: { type: Object, default: () => ({ admins: [], creators: [] }) },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const BLANK = { search: '', admin: '', admin_id: null, has_image: '', has_facilities: '', has_members: '', has_stores: '', created_by: null, created_from: '', created_to: '', sort: 'newest' };
const filters = ref({ ...BLANK, ...(props.initialFilters || {}) });

const yesNo = computed(() => [
  { key: 'admin', label: t.value.sales?.has_admin || 'Has a linked admin' },
  { key: 'has_image', label: t.value.sales?.has_image || 'Has an image' },
  { key: 'has_facilities', label: t.value.sales?.has_facilities || 'Has facilities' },
  { key: 'has_members', label: t.value.sales?.has_members || 'Has members' },
  { key: 'has_stores', label: t.value.sales?.has_stores || 'Has stores' },
]);
const sorts = computed(() => [
  { v: 'newest', l: t.value.sales?.sort_newest || 'Newest first' },
  { v: 'oldest', l: t.value.sales?.sort_oldest || 'Oldest first' },
  { v: 'name', l: t.value.sales?.sort_name || 'Name' },
  { v: 'facilities', l: t.value.sales?.sort_facilities || 'Most facilities' },
  { v: 'members', l: t.value.sales?.sort_members || 'Most members' },
  { v: 'stores', l: t.value.sales?.sort_stores || 'Most stores' },
]);
const adminChoices = computed(() => [{ value: null, label: t.value.sales?.any_admin || 'Any admin' }, ...(props.options.admins || [])]);
const creatorChoices = computed(() => [{ value: null, label: t.value.common?.all || 'All' }, ...(props.options.creators || [])]);

const hasActiveFilters = computed(() => Object.keys(BLANK).some((k) => k !== 'sort' ? filters.value[k] !== BLANK[k] && filters.value[k] !== null && filters.value[k] !== '' : filters.value.sort !== 'newest'));

let timer = null;
const debounced = () => { clearTimeout(timer); timer = setTimeout(() => apply(), 300); };

const apply = () => {
  const params = {};
  Object.entries(filters.value).forEach(([k, v]) => {
    if (v === null || v === '' || v === undefined) return;
    if (k === 'sort' && v === 'newest') return;
    params[k] = typeof v === 'string' ? v.trim() : v;
  });
  router.get(route('admin.sales.list'), params, { preserveState: true, preserveScroll: true, replace: true });
};

const reset = () => { filters.value = { ...BLANK }; apply(); };
</script>
