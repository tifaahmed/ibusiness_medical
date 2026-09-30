<template>
  <AppLayout>
    <div class="flex flex-col w-full max-w-full overflow-x-hidden p-2 sm:p-3 md:p-4 lg:p-6 gap-3 sm:gap-4">
      <!-- Header + filters -->
      <div class="bg-card text-card-foreground flex flex-col gap-3 rounded-xl border border-border py-3 sm:py-4 shadow-sm">
        <div class="flex items-center justify-between px-3 sm:px-6 gap-3">
          <div class="title-golden min-w-0 flex items-center gap-2 font-semibold">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon flex-shrink-0"><path d="M3 21h18"></path><path d="M5 21V7l8-4v18"></path><path d="M19 21V11l-6-4"></path></svg>
            <span class="text-sm sm:text-base truncate">{{ t.city?.management || 'Cities' }}</span>
          </div>
          <div class="flex items-center gap-3 flex-shrink-0">
            <span class="text-xs text-muted-foreground">{{ t.city?.total || 'Total' }}: {{ cities.total ?? 0 }}</span>
            <button v-if="canManage && !showAdd" type="button" class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded-md bg-primary text-primary-foreground hover:bg-primary/90 transition-colors" @click="showAdd = true">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
              {{ t.city?.add || 'Add City' }}
            </button>
          </div>
        </div>

        <!-- Add a city -->
        <form v-if="showAdd" class="mx-3 sm:mx-6 p-4 bg-accent/50 rounded-lg border border-border space-y-3" @submit.prevent="create">
          <select v-model="add.governorate_id" class="border-input h-9 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none">
            <option value="" disabled>{{ t.city?.select_governorate || 'Select a governorate' }}</option>
            <option v-for="g in governorates" :key="g.id" :value="g.id" class="text-black">{{ nameOf(g.name) }}</option>
          </select>
          <FormTranslatableInput v-model="add.name" :label="t.common?.name || 'Name'" :error="addErrors.name" :placeholder="t.city?.name_placeholder || 'Enter city name'" :locales="['ar', 'en']" />
          <p v-if="addErrors.general" class="text-xs text-red-600">{{ addErrors.general }}</p>
          <div class="flex justify-end gap-2">
            <button type="button" class="h-9 px-4 rounded-md border text-sm" @click="showAdd = false">{{ t.common?.cancel || 'Cancel' }}</button>
            <button type="submit" :disabled="adding" class="h-9 px-4 rounded-md bg-primary text-primary-foreground text-sm disabled:opacity-50">{{ adding ? (t.city?.saving || 'Saving…') : (t.city?.create || 'Create city') }}</button>
          </div>
        </form>

        <div class="px-3 sm:px-6 grid grid-cols-1 md:grid-cols-3 gap-3">
          <div class="relative md:col-span-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
            <input v-model="form.search" @input="onSearch" type="text" :placeholder="t.city?.search_placeholder || 'Search cities by name...'" class="border-input flex h-9 w-full rounded-md border bg-transparent pl-9 pr-3 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50" />
          </div>
          <select v-model="form.governorate_id" @change="apply" class="border-input h-9 rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none">
            <option value="">{{ t.city?.all_governorates || 'All governorates' }}</option>
            <option v-for="g in governorates" :key="g.id" :value="g.id" class="text-black">{{ nameOf(g.name) }}</option>
          </select>
        </div>
      </div>

      <!-- Table -->
      <div class="bg-card text-card-foreground rounded-xl border border-border py-2 shadow-sm">
        <div v-if="cities.data?.length" class="overflow-x-auto">
          <table class="w-full text-sm min-w-full">
            <thead class="[&_tr]:border-b [&_tr]:border-border">
              <tr>
                <th class="h-10 px-3 text-left font-medium">{{ t.city?.label || 'City' }}</th>
                <th class="h-10 px-3 text-left font-medium">{{ t.area?.governorate || 'Governorate' }}</th>
                <th class="h-10 px-3 text-center font-medium">{{ t.city?.areas || 'Areas' }}</th>
                <th class="h-10 px-3 text-center font-medium">{{ t.city?.in_use || 'In use' }}</th>
                <th class="h-10 px-3 text-center font-medium">{{ t.area?.border || 'Border' }}</th>
                <th class="h-10 px-3 text-right font-medium"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in cities.data" :key="c.id" class="border-b border-border last:border-0 hover:bg-muted/50 transition-colors">
                <td class="px-3 py-2">
                  <div class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-emerald-500 flex-shrink-0"><path d="M3 21h18"></path><path d="M5 21V7l8-4v18"></path><path d="M19 21V11l-6-4"></path></svg>
                    <div class="min-w-0">
                      <Link :href="route('admin.city.show', c.id)" class="font-semibold text-foreground hover:underline break-words">{{ nameOf(c.name) }}</Link>
                      <div class="text-xs text-muted-foreground">{{ otherName(c.name) }}</div>
                    </div>
                    <span v-if="c.is_unmarked" class="text-[10px] px-1.5 py-0.5 rounded bg-slate-500/15 text-slate-600 flex-shrink-0">{{ t.city?.unmarked || 'unmarked' }}</span>
                  </div>
                </td>
                <td class="px-3 py-2">
                  <span class="inline-flex items-center gap-1.5">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-blue-400 flex-shrink-0"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                    {{ nameOf(c.governorate.name) }}
                  </span>
                </td>
                <td class="px-3 py-2 text-center">
                  <span class="inline-flex items-center gap-1 rounded-full border border-sky-500/40 bg-sky-500/10 text-sky-600 px-2 py-0.5 text-xs font-medium">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h7v7H3z"></path><path d="M14 3h7v7h-7z"></path><path d="M14 14h7v7h-7z"></path><path d="M3 14h7v7H3z"></path></svg>
                    {{ c.areas_count }}
                  </span>
                </td>
                <td class="px-3 py-2 text-center">
                  <span class="text-xs text-muted-foreground">{{ c.facilities_count }} {{ t.city?.facilities || 'facilities' }} · {{ c.branches_count }} {{ t.city?.branches || 'branches' }}</span>
                </td>
                <td class="px-3 py-2 text-center">
                  <span class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium" :class="c.has_border ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-600' : 'border-amber-500/40 bg-amber-500/10 text-amber-600'">
                    <svg v-if="c.has_border" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                    <svg v-else xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                    {{ c.has_border ? (t.area?.border_stored || 'Border stored') : (t.area?.no_border || 'No border') }}
                  </span>
                </td>
                <td class="px-3 py-2 text-right whitespace-nowrap">
                  <Link :href="route('admin.city.show', c.id)" class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs hover:bg-accent">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21"></polygon></svg>
                    {{ canManage ? (t.city?.manage || 'Manage') : (t.city?.open || 'Open') }}
                  </Link>
                  <button v-if="canManage" type="button" class="ms-1 inline-flex items-center gap-1 rounded-md border border-red-500/40 px-2 py-1 text-xs text-red-600 hover:bg-red-500/10" @click="remove(c)">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                    {{ t.common?.delete || 'Delete' }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
          <div class="px-3 pt-3 pb-1">
            <Pagination v-if="cities.links?.length" :links="cities.links" />
          </div>
        </div>

        <!-- Empty state -->
        <div v-else class="flex flex-col items-center justify-center text-center py-12 px-4">
          <div class="rounded-full bg-muted p-4 mb-3">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-muted-foreground"><path d="M3 21h18"></path><path d="M5 21V7l8-4v18"></path><path d="M19 21V11l-6-4"></path></svg>
          </div>
          <h3 class="text-xl font-bold mb-1 text-foreground">{{ t.city?.not_found || 'No Cities Found' }}</h3>
          <p class="text-sm text-muted-foreground">{{ t.city?.not_found_message || 'No cities match your current filters.' }}</p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { AppLayout } from '@/Pages/Admin/Layout/Layout.js';
import Pagination from '@/Pages/_components/Pagination.vue';
import { FormTranslatableInput } from '@/Components/form';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import axios from 'axios';
import { useNotification } from '@/composables/useNotification';

const props = defineProps({
  cities: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
  governorates: { type: Array, default: () => [] },
  canManage: { type: Boolean, default: false },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const form = reactive({
  search: props.filters.search || '',
  governorate_id: props.filters.governorate_id || '',
});

const nameOf = (name) => {
  if (typeof name === 'string') return name;
  if (name && typeof name === 'object') {
    const locale = page.props.locale || 'ar';
    return name[locale] || name.ar || name.en || Object.values(name)[0] || '';
  }
  return '';
};

// The name in the other language, small under the main one.
const otherName = (name) => {
  const shown = nameOf(name);
  return Object.values(name || {}).find((v) => v && v !== shown) || '';
};

const apply = () => {
  const params = {};
  if (form.search) params.search = form.search;
  if (form.governorate_id) params.governorate_id = form.governorate_id;
  router.get(route('admin.city.list'), params, { preserveState: true, preserveScroll: true, replace: true });
};

let timer = null;
const onSearch = () => {
  clearTimeout(timer);
  timer = setTimeout(apply, 350);
};

/**
 * Ship a failure to the client-error endpoint so it shows up in
 * /admin/client-error-logs, not only in the admin's face.
 */
const reportClientError = (message, error, step, extra = {}) => {
  try {
    fetch('/api/v1/client-errors', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        message,
        stack: error?.stack || String(error?.message || error || ''),
        route: window.location.pathname,
        fatal: false,
        extra: { step, ...extra },
      }),
    }).catch(() => {});
  } catch {
    // Reporting is never worth a second error.
  }
};

// ---- Add a city ------------------------------------------------------------------

const showAdd = ref(false);
const adding = ref(false);
const add = reactive({ governorate_id: props.filters.governorate_id || '', name: { ar: '', en: '' } });
const addErrors = reactive({ name: '', general: '' });

const create = async () => {
  addErrors.name = '';
  addErrors.general = '';
  if (!add.governorate_id) {
    addErrors.general = t.value.city?.select_governorate || 'Select a governorate';
    return;
  }
  adding.value = true;
  try {
    const { data } = await axios.post(route('admin.city.store'), { governorate_id: add.governorate_id, name: add.name });
    router.visit(data.redirect);
  } catch (error) {
    const errors = error?.response?.data?.errors;
    if (errors) {
      addErrors.name = errors['name.ar']?.[0] || errors['name.en']?.[0] || '';
      addErrors.general = errors.governorate_id?.[0] || '';
    } else {
      addErrors.general = error?.response?.data?.message || 'Could not create the city.';
      reportClientError('City create failed', error, 'create-city', { status: error?.response?.status });
    }
  } finally {
    adding.value = false;
  }
};

// ---- Delete ----------------------------------------------------------------------

const remove = async (city) => {
  const message = (t.value.city?.delete_confirm || 'Delete :name and its :count areas?')
    .replace(':name', nameOf(city.name)).replace(':count', city.areas_count);
  if (!window.confirm(message)) return;

  try {
    await axios.delete(route('admin.city.destroy', city.id));
    useNotification().success(t.value.city?.deleted || 'City deleted.');
    router.reload({ only: ['cities'] });
  } catch (error) {
    useNotification().error(error?.response?.data?.message || 'Could not delete the city.');
    if (error?.response?.status !== 422) reportClientError('City delete failed', error, 'delete-city', { city_id: city.id, status: error?.response?.status });
  }
};
</script>
