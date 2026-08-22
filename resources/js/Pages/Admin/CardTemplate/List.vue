<template>
  <AppLayout>
    <div class="p-3 sm:p-4 md:p-6 space-y-4">
      <div class="flex items-center justify-between gap-3">
        <h1 class="text-lg sm:text-xl font-semibold flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="4" width="18" height="16" rx="2" /><circle cx="8.5" cy="9.5" r="1.8" /><line x1="13" y1="9" x2="18" y2="9" /><line x1="13" y1="13" x2="18" y2="13" /><line x1="6" y1="16" x2="18" y2="16" />
          </svg>
          Card Templates
        </h1>
        <Link
          v-if="canWrite"
          :href="route('admin.card-templates.create')"
          class="btn-golden inline-flex items-center gap-2 rounded-md h-9 px-3 text-sm font-medium bg-primary text-primary-foreground hover:bg-primary/90"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14" /><path d="M12 5v14" /></svg>
          New template
        </Link>
      </div>

      <p class="text-xs text-muted-foreground">
        A template is the blank artwork plus where every generated field lands on it. Cards created from a
        template start from its layout; a batch can still tweak positions on top without changing the template.
      </p>

      <div v-if="rows.length === 0" class="rounded-xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
        No card templates yet.
        <Link v-if="canWrite" :href="route('admin.card-templates.create')" class="text-primary hover:underline">Create the first one</Link>
      </div>

      <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        <div
          v-for="tpl in rows"
          :key="tpl.id"
          class="rounded-xl border border-border bg-card text-card-foreground overflow-hidden flex flex-col"
        >
          <div class="bg-muted/40 aspect-[1.586/1] flex items-center justify-center overflow-hidden">
            <img
              v-if="tpl.sample_card_url || tpl.card_empty_url"
              :src="tpl.sample_card_url || tpl.card_empty_url"
              :alt="displayName(tpl)"
              class="w-full h-full object-contain"
            />
            <span v-else class="text-xs text-muted-foreground">No artwork uploaded</span>
          </div>

          <div class="p-3 space-y-2 flex-1 flex flex-col">
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <div class="text-sm font-semibold truncate">{{ displayName(tpl) }}</div>
                <div class="text-[11px] text-muted-foreground font-mono truncate">{{ tpl.slug }}</div>
              </div>
              <span
                class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-medium"
                :class="tpl.status === 'with_partner' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700'"
              >
                {{ statusLabel(tpl.status) }}
              </span>
            </div>

            <div class="text-[11px] text-muted-foreground">
              {{ Object.keys(tpl.layout || {}).length }} fields
              <span v-if="tpl.hidden_fields?.length"> · {{ tpl.hidden_fields.length }} hidden by status</span>
            </div>

            <div class="flex flex-wrap gap-1.5 pt-1 mt-auto">
              <Link
                :href="route('admin.card-templates.show', tpl.id)"
                class="inline-flex items-center rounded-md h-8 px-2.5 text-xs font-medium border border-border bg-background hover:bg-accent"
              >
                View
              </Link>
              <Link
                v-if="canWrite"
                :href="route('admin.card-templates.edit', tpl.id)"
                class="inline-flex items-center rounded-md h-8 px-2.5 text-xs font-medium bg-primary text-primary-foreground hover:bg-primary/90"
              >
                Edit layout
              </Link>
              <button
                v-if="canWrite"
                type="button"
                class="inline-flex items-center rounded-md h-8 px-2.5 text-xs font-medium bg-slate-700 text-white hover:bg-slate-800 disabled:opacity-50 cursor-pointer"
                :disabled="busyId === tpl.id"
                @click="duplicate(tpl)"
              >
                Duplicate
              </button>
              <button
                v-if="canWrite"
                type="button"
                class="inline-flex items-center rounded-md h-8 px-2.5 text-xs font-medium bg-red-600 text-white hover:bg-red-700 disabled:opacity-50 cursor-pointer"
                :disabled="busyId === tpl.id"
                @click="destroy(tpl)"
              >
                Delete
              </button>
            </div>
          </div>
        </div>
      </div>

      <div v-if="error" class="rounded-md bg-red-50 border border-red-200 text-red-700 px-3 py-2 text-sm">
        {{ error }}
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { AppLayout } from '@/Pages/Admin/Layout/Layout.js';
import { usePermissions } from '@/composables/usePermissions';

const props = defineProps({
  templates: { type: Array, default: () => [] },
  statuses: { type: Array, default: () => [] },
});

const { canManage } = usePermissions();
// Read-only accounts get the View link and nothing else.
const canWrite = computed(() => canManage('manage card templates'));

const rows = ref([...props.templates]);
const busyId = ref(null);
const error = ref('');

// `name` is translatable, so it arrives as an object when the API serialises
// all locales and as a plain string when Laravel resolved the active one.
function displayName(tpl) {
  const name = tpl?.name;
  if (!name) return '—';
  if (typeof name === 'string') return name;
  return name.en || name.ar || '—';
}

function statusLabel(value) {
  return props.statuses.find((s) => s.value === value)?.label ?? value ?? '—';
}

async function duplicate(tpl) {
  if (busyId.value) return;
  busyId.value = tpl.id;
  error.value = '';
  try {
    await axios.post(route('admin.card-templates.duplicate', tpl.id));
    router.reload({ only: ['templates'] });
  } catch (e) {
    error.value = e?.response?.data?.message || e?.message || 'Could not duplicate the template.';
  } finally {
    busyId.value = null;
  }
}

async function destroy(tpl) {
  if (busyId.value) return;
  if (!window.confirm(`Delete "${displayName(tpl)}"? Batches already created from it keep their saved layout.`)) {
    return;
  }
  busyId.value = tpl.id;
  error.value = '';
  try {
    await axios.delete(route('admin.card-templates.destroy', tpl.id));
    rows.value = rows.value.filter((r) => r.id !== tpl.id);
  } catch (e) {
    error.value = e?.response?.data?.message || e?.message || 'Could not delete the template.';
  } finally {
    busyId.value = null;
  }
}
</script>
