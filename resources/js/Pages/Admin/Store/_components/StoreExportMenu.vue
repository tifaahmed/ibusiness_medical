<template>
  <div class="relative" ref="root">
    <button
      type="button"
      class="inline-flex h-8 sm:h-9 items-center gap-1.5 rounded-md border bg-background px-3 text-xs sm:text-sm font-medium hover:bg-muted"
      title="Export stores to XLSX"
      @click="open = !open"
    >
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline points="7 10 12 15 17 10" /><line x1="12" y1="15" x2="12" y2="3" /></svg>
      <span class="hidden sm:inline">Export</span>
    </button>

    <div v-if="open" class="absolute end-0 z-30 mt-2 w-[min(92vw,26rem)] max-h-[75vh] space-y-3 overflow-y-auto rounded-xl border border-border bg-card p-4 text-card-foreground shadow-xl">
      <div class="space-y-1.5">
        <p class="text-xs font-semibold">Also include</p>
        <label class="flex items-center gap-2 text-xs"><input v-model="includeBranches" type="checkbox" /> Branches sheet</label>
        <label class="flex items-center gap-2 text-xs"><input v-model="includeManagers" type="checkbox" /> Contacts (managers) sheet</label>
      </div>

      <div class="space-y-2 border-t border-border pt-3">
        <p class="text-xs font-semibold">Filters <span class="font-normal text-muted-foreground">(search: "{{ search || '—' }}")</span></p>
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
          <label class="space-y-1 text-[11px] font-medium">Sales rep
            <select v-model="salesId" :class="ctl"><option value="">All</option><option v-for="s in options.sales" :key="s.value" :value="s.value">{{ s.label }}</option></select>
          </label>
          <label class="space-y-1 text-[11px] font-medium">Has sales rep
            <select v-model="salesPresence" :class="ctl"><option value="">Any</option><option value="with">With a rep</option><option value="without">Without a rep</option></select>
          </label>
          <label class="space-y-1 text-[11px] font-medium">Has contacts
            <select v-model="managersPresence" :class="ctl"><option value="">Any</option><option value="with">With contacts</option><option value="without">Without contacts</option></select>
          </label>
          <label class="space-y-1 text-[11px] font-medium">Has contract date
            <select v-model="contractPresence" :class="ctl"><option value="">Any</option><option value="with">With a date</option><option value="without">Without a date</option></select>
          </label>
          <label class="space-y-1 text-[11px] font-medium">Online only
            <select v-model="onlineOnly" :class="ctl"><option value="">Any</option><option value="yes">Online only</option><option value="no">Has branches</option></select>
          </label>
          <label class="space-y-1 text-[11px] font-medium">Governorate
            <select v-model="governorateId" :class="ctl" @change="cityId = ''"><option value="">All</option><option v-for="g in options.governorates" :key="g.id" :value="g.id">{{ label(g.name) }}</option></select>
          </label>
          <label class="space-y-1 text-[11px] font-medium sm:col-span-2">City
            <select v-model="cityId" :class="ctl"><option value="">All</option><option v-for="c in cities" :key="c.id" :value="c.id">{{ label(c.name) }}</option></select>
          </label>
          <label class="space-y-1 text-[11px] font-medium">Created from<input v-model="createdFrom" type="date" dir="ltr" :class="ctl" /></label>
          <label class="space-y-1 text-[11px] font-medium">Created to<input v-model="createdTo" type="date" dir="ltr" :class="ctl" /></label>
        </div>
        <div v-if="options.categories?.length" class="space-y-1">
          <p class="text-[11px] font-medium">Categories</p>
          <div class="flex flex-wrap gap-1.5">
            <label v-for="c in options.categories" :key="c.id" class="inline-flex items-center gap-1 rounded-full border border-border px-2 py-0.5 text-[11px]" :class="categoryIds.includes(c.id) ? 'bg-primary/15 border-primary' : ''">
              <input v-model="categoryIds" type="checkbox" :value="c.id" class="size-3" /> {{ label(c.name) }}
            </label>
          </div>
        </div>
      </div>

      <div class="space-y-1.5 border-t border-border pt-3">
        <div class="flex items-center justify-between">
          <p class="text-xs font-semibold">Columns</p>
          <button type="button" class="text-[11px] text-primary hover:underline" @click="columns = allColumns.length === columns.length ? [] : [...allColumns]">{{ columns.length === allColumns.length ? 'Clear' : 'All' }}</button>
        </div>
        <div class="grid grid-cols-2 gap-x-3 gap-y-1">
          <label v-for="c in options.columns" :key="c.key" class="flex items-center gap-1.5 text-[11px]"><input v-model="columns" type="checkbox" :value="c.key" /> {{ c.label }}</label>
        </div>
      </div>

      <div class="space-y-1 border-t border-border pt-3">
        <label class="space-y-1 text-[11px] font-medium">Split into files of (rows, optional — gives a ZIP)
          <input v-model="chunkSize" type="number" min="1" max="10000" placeholder="e.g. 500" :class="ctl" />
        </label>
      </div>

      <a :href="url" class="block w-full rounded-md bg-primary px-3 py-2 text-center text-xs font-semibold text-primary-foreground hover:bg-primary/90" @click="open = false">
        {{ chunkSize ? 'Download ZIP' : 'Download Excel' }}
      </a>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
  search: { type: String, default: '' },
  options: { type: Object, default: () => ({ categories: [], sales: [], governorates: [], cities: [], columns: [] }) },
});

const ctl = 'flex h-8 w-full rounded-md border border-input bg-transparent px-2 text-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50';
const root = ref(null);
const open = ref(false);

const includeBranches = ref(false);
const includeManagers = ref(false);
const salesId = ref('');
const salesPresence = ref('');
const managersPresence = ref('');
const contractPresence = ref('');
const onlineOnly = ref('');
const governorateId = ref('');
const cityId = ref('');
const createdFrom = ref('');
const createdTo = ref('');
const categoryIds = ref([]);
const chunkSize = ref('');
const allColumns = computed(() => (props.options.columns || []).map(c => c.key));
const columns = ref([...(props.options.columns || []).map(c => c.key)]);

const locale = computed(() => (usePage().props.locale === 'en' ? 'en' : 'ar'));
const label = (name) => (name && typeof name === 'object' ? name[locale.value] || name.en || name.ar || '' : String(name || ''));
const cities = computed(() => (props.options.cities || []).filter(c => !governorateId.value || String(c.governorate_id) === String(governorateId.value)));

const url = computed(() => {
  const q = {
    search: props.search || undefined,
    include_branches: includeBranches.value ? 1 : 0,
    include_managers: includeManagers.value ? 1 : 0,
    sales_id: salesId.value || undefined,
    sales_presence: salesPresence.value || undefined,
    managers_presence: managersPresence.value || undefined,
    contract_presence: contractPresence.value || undefined,
    online_only: onlineOnly.value || undefined,
    governorate_id: governorateId.value || undefined,
    city_id: cityId.value || undefined,
    created_from: createdFrom.value || undefined,
    created_to: createdTo.value || undefined,
    category_ids: categoryIds.value.length ? categoryIds.value.join(',') : undefined,
    chunk_size: chunkSize.value || undefined,
    // All columns is the default; only send a list when it was narrowed.
    columns: columns.value.length && columns.value.length !== allColumns.value.length ? columns.value.join(',') : undefined,
  };
  return route('admin.store.export', q);
});

const onDocClick = (e) => { if (open.value && root.value && !root.value.contains(e.target)) open.value = false; };
onMounted(() => document.addEventListener('click', onDocClick));
onBeforeUnmount(() => document.removeEventListener('click', onDocClick));
</script>
