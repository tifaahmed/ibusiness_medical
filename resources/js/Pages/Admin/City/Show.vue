<template>
  <AppLayout>
    <div class="flex flex-col w-full max-w-full overflow-x-hidden p-2 sm:p-3 md:p-4 lg:p-6 gap-3 sm:gap-4">
      <!-- Header: the city and where it sits -->
      <div class="bg-card text-card-foreground flex flex-col gap-3 rounded-xl border border-border py-3 sm:py-4 shadow-sm">
        <div class="flex flex-wrap items-center justify-between px-3 sm:px-6 gap-3">
          <div class="title-golden min-w-0 flex items-center gap-2 font-semibold">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon flex-shrink-0"><path d="M3 21h18"></path><path d="M5 21V7l8-4v18"></path><path d="M19 21V11l-6-4"></path></svg>
            <span class="text-sm sm:text-base truncate">{{ nameOf(name) }}</span>
            <span v-if="city.is_unmarked" class="text-[10px] px-1.5 py-0.5 rounded bg-slate-500/15 text-slate-600">{{ t.city?.unmarked || 'unmarked' }}</span>
          </div>
          <div class="flex flex-wrap items-center gap-2 text-xs">
            <Link :href="route('admin.governorate.show', governorateSlug)" class="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 hover:bg-accent" v-if="governorateSlug">
              <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-blue-400"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
              {{ nameOf(city.governorate.name) }}
            </Link>
            <span class="inline-flex items-center gap-1 rounded-full border border-sky-500/40 bg-sky-500/10 text-sky-600 px-2 py-0.5 font-medium">{{ areas.length }} {{ t.city?.areas || 'Areas' }}</span>
            <span class="text-muted-foreground">{{ city.facilities_count }} {{ t.city?.facilities || 'facilities' }} · {{ city.branches_count }} {{ t.city?.branches || 'branches' }}</span>
            <Link :href="route('admin.city.list', { governorate_id: city.governorate.id })" class="rounded-md border px-2 py-1 hover:bg-accent">{{ t.city?.back || 'All cities' }}</Link>
          </div>
        </div>

        <!-- The city's own name (and delete) -->
        <form v-if="canManage" class="mx-3 sm:mx-6 p-4 bg-accent/50 rounded-lg border border-border space-y-3" @submit.prevent="saveCity">
          <FormTranslatableInput v-model="cityForm" :label="t.common?.name || 'Name'" :error="cityError" :placeholder="t.city?.name_placeholder || 'Enter city name'" :locales="['ar', 'en']" />
          <div class="flex flex-wrap justify-between gap-2">
            <button type="button" class="inline-flex items-center gap-1 rounded-md border border-red-500/40 px-3 py-1.5 text-sm text-red-600 hover:bg-red-500/10" @click="removeCity">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
              {{ t.city?.delete || 'Delete city' }}
            </button>
            <button type="submit" :disabled="savingCity" class="h-9 px-4 rounded-md bg-primary text-primary-foreground text-sm disabled:opacity-50">{{ savingCity ? (t.city?.saving || 'Saving…') : (t.city?.save || 'Save name') }}</button>
          </div>
        </form>
      </div>

      <!-- Areas (their own permission) -->
      <div v-if="canViewAreas" class="bg-card text-card-foreground rounded-xl border border-border py-3 sm:py-4 shadow-sm">
        <div class="flex items-center justify-between px-3 sm:px-6 gap-3 pb-3">
          <div class="title-golden flex items-center gap-2 font-semibold">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon"><path d="M3 3h7v7H3z"></path><path d="M14 3h7v7h-7z"></path><path d="M14 14h7v7h-7z"></path><path d="M3 14h7v7H3z"></path></svg>
            <span class="text-sm sm:text-base">{{ t.area?.management || 'Areas' }}</span>
            <span class="text-xs text-muted-foreground font-normal">({{ areas.length }})</span>
          </div>
          <button v-if="canManageAreas && !areaForm.open" type="button" class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded-md bg-primary text-primary-foreground hover:bg-primary/90 transition-colors" @click="openAreaForm(null)">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
            {{ t.area?.add || 'Add Area' }}
          </button>
        </div>

        <!-- Add / rename an area -->
        <form v-if="areaForm.open" class="mx-3 sm:mx-6 mb-3 p-4 bg-accent/50 rounded-lg border border-border space-y-3" @submit.prevent="saveArea">
          <h3 class="text-sm font-semibold">{{ areaForm.id ? (t.area?.edit || 'Edit Area') : (t.area?.add_new || 'Add New Area') }}</h3>
          <FormTranslatableInput v-model="areaForm.name" :label="t.common?.name || 'Name'" :error="areaForm.error" :placeholder="t.area?.name_placeholder || 'Enter area name'" :locales="['ar', 'en']" />
          <div v-if="!areaForm.id">
            <label class="text-xs text-muted-foreground">{{ t.area?.pcode_label || 'Code (optional — one is generated when left empty)' }}</label>
            <input v-model="areaForm.pcode" type="text" maxlength="40" class="border-input mt-1 flex h-9 w-full rounded-md border bg-transparent px-3 text-sm font-mono shadow-xs outline-none" />
            <p v-if="areaForm.pcodeError" class="text-xs text-red-600 mt-1">{{ areaForm.pcodeError }}</p>
          </div>
          <div class="flex justify-end gap-2">
            <button type="button" class="h-9 px-4 rounded-md border text-sm" @click="areaForm.open = false">{{ t.common?.cancel || 'Cancel' }}</button>
            <button type="submit" :disabled="areaForm.saving" class="h-9 px-4 rounded-md bg-primary text-primary-foreground text-sm disabled:opacity-50">{{ areaForm.saving ? (t.city?.saving || 'Saving…') : (t.common?.save || 'Save') }}</button>
          </div>
        </form>

        <div v-if="areas.length" class="overflow-x-auto">
          <table class="w-full text-sm min-w-full">
            <thead class="[&_tr]:border-b [&_tr]:border-border">
              <tr>
                <th class="h-10 px-3 text-left font-medium">{{ t.area?.name || 'Area' }}</th>
                <th class="h-10 px-3 text-center font-medium">{{ t.area?.border || 'Border' }}</th>
                <th class="h-10 px-3 text-right font-medium"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="a in areas" :key="a.id" class="border-b border-border last:border-0 hover:bg-muted/50 transition-colors">
                <td class="px-3 py-2">
                  <div class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-sky-500 flex-shrink-0"><path d="M3 3h7v7H3z"></path><path d="M14 3h7v7h-7z"></path><path d="M14 14h7v7h-7z"></path><path d="M3 14h7v7H3z"></path></svg>
                    <div class="min-w-0">
                      <div class="font-semibold text-foreground break-words">{{ nameOf(a.name) }}</div>
                      <div class="font-mono text-xs text-muted-foreground">{{ a.pcode }}</div>
                    </div>
                  </div>
                </td>
                <td class="px-3 py-2 text-center">
                  <span class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium" :class="a.has_border ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-600' : 'border-amber-500/40 bg-amber-500/10 text-amber-600'">
                    <svg v-if="a.has_border" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                    <svg v-else xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                    {{ a.has_border ? (t.area?.border_stored || 'Border stored') : (t.area?.no_border || 'No border') }}
                  </span>
                </td>
                <td class="px-3 py-2 text-right whitespace-nowrap">
                  <button type="button" class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs hover:bg-accent" @click="showOnMap(a)">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21"></polygon></svg>
                    {{ canManageAreas ? (t.area?.edit_border || 'Border') : (t.area?.show_on_map || 'On map') }}
                  </button>
                  <template v-if="canManageAreas">
                    <button type="button" class="ms-1 inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs hover:bg-accent" @click="openAreaForm(a)">
                      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"></path></svg>
                      {{ t.common?.edit || 'Edit' }}
                    </button>
                    <button type="button" class="ms-1 inline-flex items-center gap-1 rounded-md border border-red-500/40 px-2 py-1 text-xs text-red-600 hover:bg-red-500/10" @click="removeArea(a)">
                      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                      {{ t.common?.delete || 'Delete' }}
                    </button>
                  </template>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Empty state -->
        <div v-else class="flex flex-col items-center justify-center text-center py-10 px-4">
          <div class="rounded-full bg-muted p-4 mb-3">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-muted-foreground"><path d="M3 3h7v7H3z"></path><path d="M14 3h7v7h-7z"></path><path d="M14 14h7v7h-7z"></path><path d="M3 14h7v7H3z"></path></svg>
          </div>
          <h3 class="text-lg font-bold mb-1 text-foreground">{{ t.city?.no_areas || 'No areas in this city yet' }}</h3>
          <p v-if="insideArea" class="text-sm text-muted-foreground max-w-xl">
            {{ t.city?.inside_area || 'This city sits inside the area' }}
            <span class="font-semibold text-foreground">{{ nameOf(insideArea.name) }}</span>
            <span class="font-mono text-xs">({{ insideArea.pcode }})</span>,
            {{ t.city?.filed_under || 'which is filed under' }}
            <Link :href="route('admin.city.show', insideArea.city.id)" class="font-semibold text-foreground underline">{{ nameOf(insideArea.city.name) }}</Link>.
            {{ t.city?.inside_area_hint || 'The official census units here are larger than the city, so it has none of its own.' }}
          </p>
          <p class="text-sm text-muted-foreground">{{ canManageAreas ? (t.city?.no_areas_hint || 'Add one with the button above, then draw its border on the map.') : '' }}</p>
        </div>
      </div>

      <!-- Map -->
      <div ref="mapCard" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
        <div class="py-2 px-6">
          <div class="title-golden leading-none font-semibold flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon"><polygon points="3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21"></polygon><line x1="9" y1="3" x2="9" y2="18"></line><line x1="15" y1="6" x2="15" y2="21"></line></svg>
            {{ t.governorate?.border_title || 'Borders on the map' }}
          </div>
          <p class="text-xs text-muted-foreground mt-1">{{ (canManage || canManageAreas) ? (t.city?.map_hint || 'The city and each of its areas are listed on the left. Pick one, then drag a point to move it, click a point to remove it, or click a small circle on an edge to add one. The dashed grey outlines are the governorate and the neighbouring cities, for orientation only.') : (t.city?.map_hint_readonly || 'The city and its areas. The dashed grey outlines are the governorate and the neighbouring cities.') }}</p>
        </div>
        <div class="px-6">
          <BorderMap
            ref="borderMap"
            :targets="targets"
            :context="context"
            :readonly="!canManage && !canManageAreas"
            :loading="mapLoading"
            :load-error="mapError"
            :error-extra="{ city_id: city.id }"
            @saved="onBorderSaved"
          />
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { AppLayout } from '@/Pages/Admin/Layout/Layout.js';
import { FormTranslatableInput } from '@/Components/form';
import { BorderMap } from '@/Pages/Admin/Governorate/_components/Form';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, reactive, ref, shallowRef } from 'vue';
import axios from 'axios';
import { useNotification } from '@/composables/useNotification';

const props = defineProps({
  city: { type: Object, required: true },
  areas: { type: Array, default: () => [] },
  canManage: { type: Boolean, default: false },
  canViewAreas: { type: Boolean, default: false },
  insideArea: { type: Object, default: null },
  canManageAreas: { type: Boolean, default: false },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});
const notify = useNotification();

const nameOf = (name) => {
  if (typeof name === 'string') return name;
  if (name && typeof name === 'object') {
    const locale = page.props.locale || 'ar';
    return name[locale] || name.ar || name.en || Object.values(name)[0] || '';
  }
  return '';
};

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
        extra: { step, city_id: props.city.id, ...extra },
      }),
    }).catch(() => {});
  } catch {
    // Reporting is never worth a second error.
  }
};

const failMessage = (error, fallback) => error?.response?.data?.message
  || Object.values(error?.response?.data?.errors || {})[0]?.[0]
  || fallback;

// ---- State -----------------------------------------------------------------------

const name = ref(props.city.name);
const governorateSlug = computed(() => props.city.governorate?.slug || '');
const areas = ref(props.areas.map((a) => ({ ...a })));

// ---- The city's name -------------------------------------------------------------

const cityForm = reactive({ ar: props.city.name?.ar || '', en: props.city.name?.en || '' });
const cityError = ref('');
const savingCity = ref(false);

const saveCity = async () => {
  cityError.value = '';
  savingCity.value = true;
  try {
    const { data } = await axios.put(route('admin.city.update', props.city.id), { name: { ...cityForm } });
    name.value = data.name;
    updateTarget('c', { name: data.name });
    notify.success(t.value.city?.saved || 'Saved.');
  } catch (error) {
    cityError.value = failMessage(error, 'Could not save the city.');
    if (!error?.response?.data?.errors) reportClientError('City update failed', error, 'update-city', { status: error?.response?.status });
  } finally {
    savingCity.value = false;
  }
};

const removeCity = async () => {
  const message = (t.value.city?.delete_confirm || 'Delete :name and its :count areas?')
    .replace(':name', nameOf(name.value)).replace(':count', props.city.areas_count ?? areas.value.length);
  if (!window.confirm(message)) return;

  try {
    const { data } = await axios.delete(route('admin.city.destroy', props.city.id));
    router.visit(data.redirect);
  } catch (error) {
    notify.error(failMessage(error, 'Could not delete the city.'));
    if (error?.response?.status !== 422) reportClientError('City delete failed', error, 'delete-city', { status: error?.response?.status });
  }
};

// ---- Areas -----------------------------------------------------------------------

const areaForm = reactive({ open: false, id: null, name: { ar: '', en: '' }, pcode: '', saving: false, error: '', pcodeError: '' });

const openAreaForm = (area) => {
  areaForm.open = true;
  areaForm.id = area?.id || null;
  areaForm.name = { ar: area?.name?.ar || '', en: area?.name?.en || '' };
  areaForm.pcode = '';
  areaForm.error = '';
  areaForm.pcodeError = '';
};

const saveArea = async () => {
  areaForm.error = '';
  areaForm.pcodeError = '';
  areaForm.saving = true;
  try {
    if (areaForm.id) {
      const { data } = await axios.put(route('admin.area.update', areaForm.id), { name: areaForm.name });
      areas.value = areas.value.map((a) => (a.id === data.id ? { ...a, name: data.name } : a));
      updateTarget(`a${data.id}`, { name: data.name });
    } else {
      const { data } = await axios.post(route('admin.area.store', props.city.id), { name: areaForm.name, pcode: areaForm.pcode || null });
      areas.value = [...areas.value, data];
      targets.value = [...targets.value, areaTarget(data, null)];
    }
    areaForm.open = false;
    notify.success(t.value.city?.saved || 'Saved.');
  } catch (error) {
    const errors = error?.response?.data?.errors;
    areaForm.error = errors?.['name.ar']?.[0] || errors?.['name.en']?.[0] || (errors ? '' : failMessage(error, 'Could not save the area.'));
    areaForm.pcodeError = errors?.pcode?.[0] || '';
    if (!errors) reportClientError('Area save failed', error, 'save-area', { status: error?.response?.status });
  } finally {
    areaForm.saving = false;
  }
};

const removeArea = async (area) => {
  const message = (t.value.area?.delete_confirm || 'Delete the area :name?').replace(':name', nameOf(area.name));
  if (!window.confirm(message)) return;

  try {
    await axios.delete(route('admin.area.destroy', area.id));
    areas.value = areas.value.filter((a) => a.id !== area.id);
    targets.value = targets.value.filter((x) => x.key !== `a${area.id}`);
    notify.success(t.value.area?.deleted || 'Area deleted.');
  } catch (error) {
    notify.error(failMessage(error, 'Could not delete the area.'));
    reportClientError('Area delete failed', error, 'delete-area', { area_id: area.id, status: error?.response?.status });
  }
};

// ---- The map ---------------------------------------------------------------------

const borderMap = ref(null);
const mapCard = ref(null);
const mapLoading = ref(true);
const mapError = ref('');
const targets = shallowRef([]);
const context = shallowRef([]);

const areaColor = (index) => `hsla(${Math.round((index * 137.508 + 200) % 360)}, 70%, 42%, __A__)`;

const areaTarget = (area, geometry, index = areas.value.length - 1) => ({
  key: `a${area.id}`,
  kind: 'area',
  id: area.id,
  name: area.name,
  sub: area.pcode,
  locked: !props.canManageAreas,
  color: areaColor(index),
  geometry,
  saveUrl: route('admin.area.boundary.update', area.id),
});

const updateTarget = (key, patch) => {
  targets.value = targets.value.map((x) => (x.key === key ? { ...x, ...patch } : x));
};

const onBorderSaved = ({ key, kind, id, geometry }) => {
  // The map already holds the new border; keep this page's copies in step.
  targets.value = targets.value.map((x) => (x.key === key ? { ...x, geometry } : x));
  if (kind === 'area') areas.value = areas.value.map((a) => (a.id === id ? { ...a, has_border: !!geometry } : a));
};

const showOnMap = async (area) => {
  await nextTick();
  mapCard.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  borderMap.value?.startEdit(`a${area.id}`);
};

onMounted(async () => {
  try {
    const [feed, areaFeed] = await Promise.all([
      axios.get(route('admin.governorate.city-borders', props.city.governorate.id)),
      // Areas have their own permission; without it the map shows the city alone.
      props.canViewAreas ? axios.get(route('admin.city.area-borders', props.city.id)) : Promise.resolve({ data: { areas: [] } }),
    ]);

    const own = (feed.data.cities || []).find((c) => c.id === props.city.id);
    const geometries = new Map((areaFeed.data.areas || []).map((a) => [a.id, a.geometry || null]));

    targets.value = [
      // The city is a bare outline so the areas inside it stay clickable.
      { key: 'c', kind: 'city', id: props.city.id, name: name.value, outline: true, locked: !props.canManage, color: 'hsla(215, 25%, 25%, __A__)', geometry: own?.geometry || null, saveUrl: route('admin.city.boundary.update', props.city.id) },
      ...areas.value.map((a, i) => areaTarget(a, geometries.get(a.id) || null, i)),
    ];
    context.value = [
      { name: 'governorate', geometry: feed.data.governorate_geometry || null },
      ...(feed.data.cities || []).filter((c) => c.id !== props.city.id).map((c) => ({ name: c.name, geometry: c.geometry || null })),
    ];
  } catch (error) {
    console.error('Failed to load the city map:', error);
    mapError.value = t.value.governorate?.border_load_failed || 'The borders could not be loaded.';
    reportClientError('City map failed to load', error, 'load-city-map');
  } finally {
    mapLoading.value = false;
  }
});
</script>
