<template>
  <FacilityLayout>
    <div class="w-full max-w-full space-y-2 sm:space-y-3 md:space-y-4 p-2 sm:p-3 md:p-4 lg:p-6">
      <!-- Header -->
      <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border py-2 sm:py-3 md:py-4 shadow-sm">
        <div class="flex items-center justify-between py-2 px-3 sm:px-4 md:px-6 gap-3">
          <div class="title-golden flex items-center gap-2 min-w-0 font-semibold">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon flex-shrink-0">
              <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle>
              <path d="m16 11 2 2 4-4"></path>
            </svg>
            <span class="text-sm sm:text-base truncate">Assign facilities to a manager</span>
          </div>
          <Link :href="route('admin.facility.list')" class="inline-flex items-center justify-center whitespace-nowrap rounded-md text-xs sm:text-sm font-medium border bg-background hover:bg-muted h-8 sm:h-9 px-3">
            Back to facilities
          </Link>
        </div>

        <!-- Filters -->
        <div class="px-3 sm:px-4 md:px-6 pt-2">
        <FilterAccordion>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 sm:gap-3 items-end">
          <div>
            <label class="text-xs font-medium mb-1 block">Search</label>
            <input v-model="local.search" @input="debouncedApply" type="text" placeholder="Name or slug…"
              class="h-8 sm:h-9 w-full rounded-md border border-input bg-background px-2 text-sm" />
          </div>
          <div>
            <label class="text-xs font-medium mb-1 block">Facility type</label>
            <select v-model="local.facility_type_id" @change="applyFilters" class="h-8 sm:h-9 w-full rounded-md border border-input bg-background px-2 text-sm">
              <option value="">All types</option>
              <option v-for="o in facilityTypes" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
          </div>
          <div>
            <label class="text-xs font-medium mb-1 block">Managed status</label>
            <select v-model="local.managed" @change="applyFilters" class="h-8 sm:h-9 w-full rounded-md border border-input bg-background px-2 text-sm">
              <option value="">All</option>
              <option value="with">Has a manager</option>
              <option value="without">No manager</option>
            </select>
          </div>
          <div>
            <label class="text-xs font-medium mb-1 block">Managed by</label>
            <select v-model="local.managed_by" @change="applyFilters" class="h-8 sm:h-9 w-full rounded-md border border-input bg-background px-2 text-sm">
              <option value="">Anyone</option>
              <option v-for="o in managerOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
          </div>
        </div>
        <div v-if="hasFilters" class="pt-2">
          <button type="button" @click="clearFilters" class="cursor-pointer text-xs font-medium bg-destructive text-white hover:bg-destructive/90 h-7 rounded-md px-3">Clear filters</button>
        </div>
        </FilterAccordion>
        </div>
        <div class="pb-2"></div>
      </div>

      <!-- Assign bar -->
      <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm p-3 sm:p-4 space-y-3">
        <div class="flex flex-col lg:flex-row lg:items-end gap-3">
          <div class="flex-1 min-w-0 lg:max-w-md">
            <label class="text-xs font-medium mb-1 block">Assign the selected facilities to</label>
            <select v-model="targetId" class="h-8 sm:h-9 w-full rounded-md border border-input bg-background px-2 text-sm">
              <option value="">— Choose an admin user —</option>
              <option v-for="o in managerOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
          </div>
          <div class="flex flex-wrap gap-2">
            <button type="button" :disabled="!selectionCount || !targetId || busy" @click="openConfirm(false)"
              class="cursor-pointer inline-flex items-center gap-1.5 rounded-md text-sm font-medium bg-primary text-primary-foreground hover:bg-primary/90 disabled:opacity-50 disabled:cursor-not-allowed h-8 sm:h-9 px-4 btn-golden">
              Assign {{ selectionCount || '' }}
            </button>
            <button type="button" :disabled="!selectionCount || busy" @click="openConfirm(true)"
              class="cursor-pointer inline-flex items-center gap-1.5 rounded-md text-sm font-medium border border-destructive/40 text-destructive hover:bg-destructive/10 disabled:opacity-50 disabled:cursor-not-allowed h-8 sm:h-9 px-4">
              Remove manager
            </button>
          </div>
        </div>
        <div class="text-xs text-muted-foreground flex flex-wrap items-center gap-x-3 gap-y-1">
          <span><strong class="text-foreground">{{ selectionCount }}</strong> selected</span>
          <button v-if="!allMatching && total > facilities.data.length" type="button" class="underline hover:text-foreground cursor-pointer" @click="selectAllMatching">
            Select all {{ total }} matching the filters
          </button>
          <button v-if="selectionCount" type="button" class="underline hover:text-foreground cursor-pointer" @click="clearSelection">Clear selection</button>
        </div>
      </div>

      <!-- Table -->
      <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
        <div v-if="facilities.data.length" class="overflow-x-auto">
          <table class="w-full text-xs sm:text-sm">
            <thead class="[&_tr]:border-b [&_tr]:border-border">
              <tr>
                <th class="h-10 px-3 w-10 text-left">
                  <input type="checkbox" :checked="pageAllSelected" @change="togglePage" class="h-4 w-4 rounded" />
                </th>
                <th class="h-10 px-3 text-left font-medium">Facility</th>
                <th class="h-10 px-3 text-left font-medium hidden sm:table-cell">Type</th>
                <th class="h-10 px-3 text-left font-medium">Managed by</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="f in facilities.data" :key="f.id"
                :class="['border-b border-border transition-colors', isSelected(f.id) ? 'bg-primary/10' : 'hover:bg-muted/50']">
                <td class="px-3 py-2"><input type="checkbox" :checked="isSelected(f.id)" @change="toggle(f.id)" :disabled="allMatching" class="h-4 w-4 rounded" /></td>
                <td class="px-3 py-2 font-medium">
                  <Link :href="route('admin.facility.edit', f.slug)" class="hover:underline">{{ f.name || `#${f.id}` }}</Link>
                </td>
                <td class="px-3 py-2 text-muted-foreground hidden sm:table-cell">{{ f.type || '—' }}</td>
                <td class="px-3 py-2">
                  <span v-if="f.managed_by" class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-xs font-medium border bg-blue-500/15 text-blue-300 border-blue-500/30" :title="f.managed_by.email">
                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    {{ f.managed_by.name }}
                  </span>
                  <span v-else class="inline-flex items-center rounded-md px-1.5 py-0.5 text-xs border bg-muted text-muted-foreground border-border">No manager</span>
                </td>
              </tr>
            </tbody>
          </table>
          <div class="px-3 sm:px-4 py-3 flex flex-wrap items-center justify-between gap-2 border-t border-border">
            <span class="text-xs text-muted-foreground">Showing {{ facilities.meta.from || 0 }}–{{ facilities.meta.to || 0 }} of {{ total }}</span>
            <Pagination v-if="facilities.meta.links?.length > 0" :links="facilities.meta.links" />
          </div>
        </div>
        <div v-else class="p-12 text-center space-y-3">
          <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-golden-yellow/10 border border-golden-yellow/20">
            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-golden-yellow"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
          </div>
          <h3 class="text-xl font-bold">No facilities match</h3>
          <p class="text-sm text-muted-foreground">Try loosening the filters.</p>
        </div>
      </div>
    </div>

    <!-- Confirmation -->
    <div v-if="confirm.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="confirm.open = false">
      <div class="bg-card text-card-foreground border border-border rounded-xl shadow-xl w-full max-w-lg p-5 space-y-4">
        <h3 class="text-lg font-semibold">
          {{ confirm.clearing ? 'Remove the manager?' : 'Confirm assignment' }}
        </h3>

        <div v-if="confirm.loading" class="text-sm text-muted-foreground">Checking the selection…</div>
        <div v-else-if="confirm.error" class="text-sm text-destructive">{{ confirm.error }}</div>
        <div v-else-if="confirm.data" class="space-y-3 text-sm">
          <p>
            <template v-if="confirm.clearing">
              <strong>{{ confirm.data.total }}</strong> facilities selected. Their manager will be cleared.
            </template>
            <template v-else>
              <strong>{{ confirm.data.total }}</strong> facilities selected will be assigned to
              <strong>{{ confirm.data.target }}</strong>.
            </template>
          </p>

          <!-- All empty: nothing is being taken from anyone -->
          <div v-if="confirm.data.taken_from.length === 0 && confirm.data.empty === confirm.data.total"
            class="rounded-md border border-emerald-500/30 bg-emerald-500/10 text-emerald-300 px-3 py-2">
            All {{ confirm.data.total }} currently have no manager — nothing will be overwritten.
          </div>

          <template v-else>
            <div v-if="confirm.data.empty" class="rounded-md border border-border bg-muted/40 px-3 py-2">
              {{ confirm.data.empty }} currently have no manager.
            </div>
            <div v-if="confirm.data.taken_from.length" class="rounded-md border border-amber-500/40 bg-amber-500/10 text-amber-200 px-3 py-2 space-y-1">
              <div class="font-medium">
                {{ takenTotal }} already {{ takenTotal === 1 ? 'has' : 'have' }} a different manager and will be {{ confirm.clearing ? 'cleared' : 'changed' }}:
              </div>
              <ul class="list-disc ps-5">
                <li v-for="r in confirm.data.taken_from" :key="r.user_id">{{ r.name }} — {{ r.count }}</li>
              </ul>
            </div>
            <div v-if="confirm.data.same" class="rounded-md border border-border bg-muted/40 px-3 py-2">
              {{ confirm.data.same }} already {{ confirm.data.same === 1 ? 'belongs' : 'belong' }} to this user and will be left as is.
            </div>
          </template>
        </div>

        <div class="flex justify-end gap-2 pt-1">
          <button type="button" @click="confirm.open = false" class="cursor-pointer text-sm font-medium border bg-background hover:bg-muted h-9 rounded-md px-4">Cancel</button>
          <button type="button" :disabled="confirm.loading || !!confirm.error || busy || (confirm.data && confirm.data.will_change === 0)" @click="submitAssign"
            :class="['cursor-pointer text-sm font-medium h-9 rounded-md px-4 disabled:opacity-50 disabled:cursor-not-allowed',
              confirm.data?.taken_from.length ? 'bg-amber-500 text-black hover:bg-amber-400' : 'bg-primary text-primary-foreground hover:bg-primary/90']">
            {{ busy ? 'Saving…' : (confirm.data?.taken_from.length ? 'Yes, overwrite' : 'Confirm') }}
          </button>
        </div>
      </div>
    </div>
  </FacilityLayout>
</template>

<script setup>
import { computed, reactive, ref } from "vue";
import { Link, router } from "@inertiajs/vue3";
import FacilityLayout from "../FacilityLayout.vue";
import FilterAccordion from "@/Components/ui/FilterAccordion.vue";
import Pagination from "@/Pages/_components/Pagination.vue";
import { useNotification } from "@/composables/useNotification";

const props = defineProps({
  facilities: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
  facilityTypes: { type: Array, default: () => [] },
  managerOptions: { type: Array, default: () => [] },
});

const local = reactive({
  search: props.filters.search || "",
  facility_type_id: props.filters.facility_type_id ?? "",
  managed: props.filters.managed || "",
  managed_by: props.filters.managed_by ?? "",
});

const total = computed(() => props.facilities.meta?.total || 0);
const hasFilters = computed(() => Object.values(local).some((v) => v !== "" && v !== null));

function applyFilters() {
  const params = {};
  if (local.search.trim()) params.search = local.search.trim();
  if (local.facility_type_id !== "") params.facility_type_id = local.facility_type_id;
  if (local.managed) params.managed = local.managed;
  if (local.managed_by !== "") params.managed_by = local.managed_by;
  // A different filter means a different set: selecting across it would be a guess.
  clearSelection();
  router.get(route("admin.facility.managed-by.page"), params, { preserveState: true, preserveScroll: true, replace: true });
}
let timer = null;
function debouncedApply() {
  clearTimeout(timer);
  timer = setTimeout(applyFilters, 300);
}
function clearFilters() {
  Object.assign(local, { search: "", facility_type_id: "", managed: "", managed_by: "" });
  applyFilters();
}

// ---- selection ----
const selected = ref(new Set());
const allMatching = ref(false);
const isSelected = (id) => allMatching.value || selected.value.has(id);
const selectionCount = computed(() => (allMatching.value ? total.value : selected.value.size));
const pageAllSelected = computed(() => props.facilities.data.length > 0 && props.facilities.data.every((f) => isSelected(f.id)));

function toggle(id) {
  const s = new Set(selected.value);
  s.has(id) ? s.delete(id) : s.add(id);
  selected.value = s;
}
function togglePage() {
  if (allMatching.value) return clearSelection();
  const s = new Set(selected.value);
  if (pageAllSelected.value) props.facilities.data.forEach((f) => s.delete(f.id));
  else props.facilities.data.forEach((f) => s.add(f.id));
  selected.value = s;
}
function selectAllMatching() {
  allMatching.value = true;
}
function clearSelection() {
  selected.value = new Set();
  allMatching.value = false;
}

// ---- assign ----
const targetId = ref("");
const busy = ref(false);
const confirm = reactive({ open: false, loading: false, error: "", data: null, clearing: false });
const takenTotal = computed(() => (confirm.data?.taken_from || []).reduce((n, r) => n + r.count, 0));

function selectionPayload() {
  const body = allMatching.value
    ? { all_matching: true, search: local.search, facility_type_id: local.facility_type_id, managed: local.managed, managed_by: local.managed_by }
    : { facility_ids: [...selected.value] };
  return body;
}

async function openConfirm(clearing) {
  Object.assign(confirm, { open: true, loading: true, error: "", data: null, clearing });
  try {
    const { data } = await axios.post(route("admin.facility.managed-by.preview"), {
      ...selectionPayload(),
      target: clearing ? "" : targetId.value,
    });
    confirm.data = data;
  } catch (e) {
    confirm.error = e?.response?.data?.message || "Could not check the selection.";
    reportError("managed-by preview failed", e);
  } finally {
    confirm.loading = false;
  }
}

async function submitAssign() {
  busy.value = true;
  try {
    const { data } = await axios.post(route("admin.facility.managed-by.assign"), {
      ...selectionPayload(),
      target: confirm.clearing ? "" : targetId.value,
    });
    useNotification().success(`${data.changed} facilities updated.`);
    confirm.open = false;
    clearSelection();
    router.reload({ preserveScroll: true });
  } catch (e) {
    confirm.error = e?.response?.data?.message || "Could not save the assignment.";
    reportError("managed-by assign failed", e);
  } finally {
    busy.value = false;
  }
}

function reportError(message, e) {
  try {
    axios.post("/api/v1/client-errors", {
      message,
      stack: e?.stack || String(e),
      route: window.location.pathname,
      extra: { step: "facility-managed-by", status: e?.response?.status },
    }).catch(() => {});
  } catch {
    /* reporting must never break the page */
  }
}
</script>
