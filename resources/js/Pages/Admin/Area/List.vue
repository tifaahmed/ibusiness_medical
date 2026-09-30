<template>
  <AppLayout>
    <div class="flex flex-col w-full max-w-full overflow-x-hidden p-2 sm:p-3 md:p-4 lg:p-6 gap-3 sm:gap-4">
      <!-- Header + filters -->
      <div class="bg-card text-card-foreground flex flex-col gap-3 rounded-xl border border-border py-3 sm:py-4 shadow-sm">
        <div class="flex items-center justify-between px-3 sm:px-6 gap-3">
          <div class="title-golden min-w-0 flex items-center gap-2 font-semibold">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon flex-shrink-0"><path d="M3 3h7v7H3z"></path><path d="M14 3h7v7h-7z"></path><path d="M14 14h7v7h-7z"></path><path d="M3 14h7v7H3z"></path></svg>
            <span class="text-sm sm:text-base truncate">{{ t.area?.management || 'Areas' }}</span>
          </div>
          <span class="text-xs text-muted-foreground flex-shrink-0">{{ t.area?.total || 'Total' }}: {{ areas.total ?? 0 }}</span>
        </div>

        <div class="px-3 sm:px-6 grid grid-cols-1 md:grid-cols-4 gap-3">
          <div class="relative md:col-span-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
            <input v-model="form.search" @input="onSearch" type="text" :placeholder="t.area?.search_placeholder || 'Search areas by name or code...'" class="border-input flex h-9 w-full rounded-md border bg-transparent pl-9 pr-3 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50" />
          </div>
          <select v-model="form.governorate_id" @change="onGovernorate" class="border-input h-9 rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none">
            <option value="">{{ t.area?.all_governorates || 'All governorates' }}</option>
            <option v-for="g in governorates" :key="g.id" :value="g.id" class="text-black">{{ nameOf(g.name) }}</option>
          </select>
          <select v-model="form.city_id" @change="apply" :disabled="!form.governorate_id" class="border-input h-9 rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none disabled:opacity-50">
            <option value="">{{ t.area?.all_cities || 'All cities' }}</option>
            <option v-for="c in cities" :key="c.id" :value="c.id" class="text-black">{{ nameOf(c.name) }}</option>
          </select>
        </div>
      </div>

      <!-- Table -->
      <div class="bg-card text-card-foreground rounded-xl border border-border py-2 shadow-sm">
        <div v-if="areas.data?.length" class="overflow-x-auto">
          <table class="w-full text-sm min-w-full">
            <thead class="[&_tr]:border-b [&_tr]:border-border">
              <tr>
                <th class="h-10 px-3 text-left font-medium">{{ t.area?.name || 'Area' }}</th>
                <th class="h-10 px-3 text-left font-medium">{{ t.area?.city || 'City' }}</th>
                <th class="h-10 px-3 text-left font-medium">{{ t.area?.governorate || 'Governorate' }}</th>
                <th class="h-10 px-3 text-center font-medium">{{ t.area?.border || 'Border' }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="a in areas.data" :key="a.id" class="border-b border-border last:border-0 hover:bg-muted/50 transition-colors">
                <td class="px-3 py-2">
                  <div class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-sky-500 flex-shrink-0"><path d="M3 3h7v7H3z"></path><path d="M14 3h7v7h-7z"></path><path d="M14 14h7v7h-7z"></path><path d="M3 14h7v7H3z"></path></svg>
                    <div class="min-w-0">
                      <div dir="rtl" class="font-semibold text-foreground break-words">{{ nameOf(a.name) }}</div>
                      <div class="font-mono text-xs text-muted-foreground">{{ a.pcode }}</div>
                    </div>
                  </div>
                </td>
                <td class="px-3 py-2">
                  <span class="inline-flex items-center gap-1.5">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-emerald-500 flex-shrink-0"><path d="M3 21h18"></path><path d="M5 21V7l8-4v18"></path><path d="M19 21V11l-6-4"></path></svg>
                    {{ nameOf(a.city.name) }}
                  </span>
                </td>
                <td class="px-3 py-2">
                  <span class="inline-flex items-center gap-1.5">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-blue-400 flex-shrink-0"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                    {{ nameOf(a.governorate.name) }}
                  </span>
                </td>
                <td class="px-3 py-2 text-center">
                  <span
                    class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium"
                    :class="a.has_border ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-600' : 'border-amber-500/40 bg-amber-500/10 text-amber-600'"
                  >
                    <svg v-if="a.has_border" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                    <svg v-else xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                    {{ a.has_border ? (t.area?.border_stored || 'Border stored') : (t.area?.no_border || 'No border') }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
          <div class="px-3 pt-3 pb-1">
            <Pagination v-if="areas.links?.length" :links="areas.links" />
          </div>
        </div>

        <!-- Empty state -->
        <div v-else class="flex flex-col items-center justify-center text-center py-12 px-4">
          <div class="rounded-full bg-muted p-4 mb-3">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-muted-foreground"><path d="M3 3h7v7H3z"></path><path d="M14 3h7v7h-7z"></path><path d="M14 14h7v7h-7z"></path><path d="M3 14h7v7H3z"></path></svg>
          </div>
          <h3 class="text-xl font-bold mb-1 text-foreground">{{ t.area?.not_found || 'No Areas Found' }}</h3>
          <p class="text-sm text-muted-foreground">{{ t.area?.not_found_message || 'No areas match your current filters.' }}</p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { AppLayout } from '@/Pages/Admin/Layout/Layout.js';
import Pagination from '@/Pages/_components/Pagination.vue';
import { router, usePage } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps({
  areas: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
  governorates: { type: Array, default: () => [] },
  cities: { type: Array, default: () => [] },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const form = reactive({
  search: props.filters.search || '',
  governorate_id: props.filters.governorate_id || '',
  city_id: props.filters.city_id || '',
});

const nameOf = (name) => {
  if (typeof name === 'string') return name;
  if (name && typeof name === 'object') {
    const locale = page.props.locale || 'ar';
    return name[locale] || name.ar || name.en || Object.values(name)[0] || '';
  }
  return '';
};

const apply = () => {
  const params = {};
  if (form.search) params.search = form.search;
  if (form.governorate_id) params.governorate_id = form.governorate_id;
  if (form.city_id) params.city_id = form.city_id;
  router.get(route('admin.area.list'), params, { preserveState: true, preserveScroll: true, replace: true });
};

// A new governorate invalidates the chosen city.
const onGovernorate = () => {
  form.city_id = '';
  apply();
};

let timer = null;
const onSearch = () => {
  clearTimeout(timer);
  timer = setTimeout(apply, 350);
};
</script>
