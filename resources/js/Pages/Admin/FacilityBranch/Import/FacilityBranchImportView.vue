<template>
  <FacilityBranchLayout>
    <div class="space-y-3 p-3 sm:p-4 lg:p-6 w-full max-w-full">
      <Breadcrumb
        title="Import Branches"
        :breadcrumbs="[
          { label: 'Facility Branches', link: route('admin.facility-branch.list'), active: false },
          { label: 'Import', link: '#', active: true },
        ]"
      />

      <!-- Step 1 — Upload -->
      <div v-if="step === 'upload'" class="bg-card border border-border rounded-xl p-6 space-y-4 max-w-2xl mx-auto">
        <h2 class="text-lg font-semibold">Upload branches file</h2>
        <p class="text-sm text-muted-foreground">
          Upload a CSV or XLSX file. The expected format matches the export — the same column headers
          (<code>Facility name, Facility slug, Branch name, Branch name (AR), Address, Phone, Governorate, City, Latitude, Longitude</code>).
        </p>
        <input
          type="file"
          accept=".csv,.xlsx,.xls,.txt"
          @change="onFileChange"
          class="block w-full text-sm border border-border rounded-md p-2 file:mr-3 file:px-3 file:py-1.5 file:rounded file:border-0 file:bg-primary file:text-primary-foreground"
        />
        <div v-if="error" class="text-sm text-destructive">{{ error }}</div>
        <div class="flex gap-2 justify-end">
          <Link
            :href="route('admin.facility-branch.list')"
            class="inline-flex items-center justify-center rounded-md text-sm font-medium border bg-background h-9 px-4 hover:bg-muted"
          >
            Cancel
          </Link>
          <button
            type="button"
            @click="uploadFile"
            :disabled="!file || loading"
            class="inline-flex items-center justify-center rounded-md text-sm font-medium bg-primary text-primary-foreground h-9 px-4 disabled:opacity-50 btn-golden"
          >
            <span v-if="loading">Parsing…</span>
            <span v-else>Preview</span>
          </button>
        </div>
      </div>

      <!-- Step 2 — Preview / Edit -->
      <div v-if="step === 'preview'" class="space-y-4">
        <div class="bg-card border border-border rounded-xl p-4 flex flex-wrap items-center gap-4">
          <div class="flex items-center gap-2">
            <span class="px-2.5 py-1 rounded text-xs bg-emerald-600 text-white font-semibold">{{ counts.new }} NEW</span>
            <span class="px-2.5 py-1 rounded text-xs bg-amber-500 text-white font-semibold">{{ counts.update }} UPDATE</span>
            <span class="px-2.5 py-1 rounded text-xs bg-slate-500 text-white font-semibold">{{ counts.unchanged }} UNCHANGED</span>
            <span v-if="counts.error" class="px-2.5 py-1 rounded text-xs bg-red-600 text-white font-semibold">{{ counts.error }} ERROR</span>
          </div>
          <div class="ml-auto flex items-center gap-3 text-sm">
            <label class="flex items-center gap-1.5 cursor-pointer">
              <input type="radio" value="upsert" v-model="mode" /> Update existing &amp; insert new
            </label>
            <label class="flex items-center gap-1.5 cursor-pointer">
              <input type="radio" value="clear" v-model="mode" /> Clear table &amp; add new
            </label>
          </div>
        </div>

        <div v-if="mode === 'clear'" class="bg-red-600 text-white border border-red-700 rounded-md p-3 text-sm">
          <strong>Warning:</strong> "Clear table" deletes every existing branch. Imported rows are then inserted as new branches.
        </div>

        <div class="bg-card text-card-foreground border border-border rounded-xl overflow-x-auto">
          <table class="text-xs text-foreground" style="min-width: 1700px; width: 100%;">
            <thead class="bg-muted/50">
              <tr class="border-b border-border">
                <th class="px-2 py-2 text-left font-semibold w-12 sticky left-0 bg-muted/80 backdrop-blur z-10">#</th>
                <th class="px-2 py-2 text-left font-semibold w-24">Status</th>
                <th class="px-2 py-2 text-left font-semibold w-44">Facility</th>
                <th class="px-2 py-2 text-left font-semibold w-44">Branch name</th>
                <th class="px-2 py-2 text-left font-semibold w-40">Name (AR)</th>
                <th class="px-2 py-2 text-left font-semibold w-56">Address</th>
                <th class="px-2 py-2 text-left font-semibold w-40">Phone</th>
                <th class="px-2 py-2 text-left font-semibold w-40">Governorate</th>
                <th class="px-2 py-2 text-left font-semibold w-40">City</th>
                <th class="px-2 py-2 text-left font-semibold w-28">Lat</th>
                <th class="px-2 py-2 text-left font-semibold w-28">Lng</th>
                <th class="px-2 py-2 text-left font-semibold w-24 sticky right-0 bg-muted/80 backdrop-blur z-10"></th>
              </tr>
            </thead>
            <tbody>
              <template v-for="(row, i) in rows" :key="i">
                <tr class="border-b border-border" :class="rowBgClass(row.status)">
                  <td class="px-2 py-1.5 text-center font-mono sticky left-0 z-[5] bg-card" :class="rowBgClass(row.status)">{{ i + 1 }}</td>
                  <td class="px-2 py-1.5">
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase" :class="statusBadgeClass(row.status)">{{ row.status }}</span>
                  </td>
                  <td class="px-2 py-1">
                    <select v-model="row.parsed.facility_id" :class="inputCls(row.errors?.facility)">
                      <option :value="null">— None —</option>
                      <option v-for="o in facilityOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                  </td>
                  <td class="px-2 py-1"><input v-model="row.parsed.name" :class="inputCls(row.errors?.name)" /></td>
                  <td class="px-2 py-1"><input v-model="row.parsed.name_ar" :class="inputCls()" /></td>
                  <td class="px-2 py-1"><input v-model="row.parsed.address" :class="inputCls()" /></td>
                  <td class="px-2 py-1"><input v-model="row.parsed.phone" :class="inputCls()" /></td>
                  <td class="px-2 py-1">
                    <select v-model="row.parsed.governorate_id" :class="inputCls(row.errors?.governorate)">
                      <option :value="null">— None —</option>
                      <option v-for="o in governorateOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                  </td>
                  <td class="px-2 py-1">
                    <select v-model="row.parsed.city_id" :class="inputCls(row.errors?.city)">
                      <option :value="null">— None —</option>
                      <option v-for="o in citiesForRow(row)" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                  </td>
                  <td class="px-2 py-1"><input type="number" step="any" v-model="row.parsed.latitude" :class="inputCls(row.errors?.latitude)" /></td>
                  <td class="px-2 py-1"><input type="number" step="any" v-model="row.parsed.longitude" :class="inputCls(row.errors?.longitude)" /></td>
                  <td class="px-2 py-1 text-center sticky right-0 z-[5] bg-card" :class="rowBgClass(row.status)">
                    <button
                      v-if="row.status === 'update' && hasChanges(row)"
                      type="button"
                      @click="row.showDiff = !row.showDiff"
                      class="text-blue-600 hover:underline text-[11px]"
                    >
                      {{ row.showDiff ? 'hide' : 'diff' }}
                    </button>
                    <button
                      type="button"
                      @click="rows.splice(i, 1)"
                      class="text-destructive hover:underline text-[11px] ml-2"
                      title="Skip this row"
                    >
                      skip
                    </button>
                  </td>
                </tr>
                <tr v-if="row.errors && Object.keys(row.errors).length" :class="rowBgClass(row.status)">
                  <td colspan="12" class="px-2 pb-2 text-[11px] text-destructive">
                    <span v-for="(msg, key) in row.errors" :key="key" class="mr-3">⚠ <strong>{{ key }}:</strong> {{ msg }}</span>
                  </td>
                </tr>

                <tr v-if="row.showDiff && row.status === 'update' && mode === 'upsert'" :class="rowBgClass(row.status)">
                  <td colspan="12" class="p-3">
                    <div class="bg-card border border-border rounded-md overflow-hidden">
                      <div class="bg-muted/50 px-3 py-2 text-xs font-semibold">
                        Changes for <span class="text-foreground">{{ row.match?.name }}</span>
                        <span class="text-muted-foreground"> ({{ row.match?.facility_name }})</span>
                      </div>
                      <table class="w-full text-[11px]">
                        <thead class="bg-muted/30">
                          <tr>
                            <th class="px-3 py-1.5 text-left font-semibold w-40">Field</th>
                            <th class="px-3 py-1.5 text-left font-semibold">Current</th>
                            <th class="px-3 py-1.5 text-left font-semibold">After import</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="d in changedFields(row)" :key="d.field" class="border-t border-border">
                            <td class="px-3 py-1.5 font-semibold text-foreground">{{ d.label }}</td>
                            <td class="px-3 py-1.5 bg-red-500/15 text-red-700 dark:text-red-300 line-through">{{ formatVal(d.old) }}</td>
                            <td class="px-3 py-1.5 bg-emerald-500/20 text-emerald-700 dark:text-emerald-200 font-bold">{{ formatVal(d.new) }}</td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <div class="sticky bottom-0 z-10 bg-card border border-border rounded-lg p-3 flex flex-wrap items-center gap-3">
          <button
            type="button"
            @click="step = 'upload'; reset()"
            class="inline-flex items-center justify-center rounded-md text-sm font-medium border bg-background h-9 px-4 hover:bg-muted"
          >
            Back
          </button>
          <div v-if="counts.error" class="text-xs text-destructive">
            {{ counts.error }} row(s) have errors. Fix or skip them before confirming.
          </div>
          <button
            type="button"
            @click="commit"
            :disabled="loading || counts.error > 0 || rows.length === 0"
            class="ml-auto inline-flex items-center justify-center rounded-md text-sm font-medium bg-primary text-primary-foreground h-9 px-4 disabled:opacity-50 btn-golden"
          >
            <span v-if="loading">Saving…</span>
            <span v-else>Confirm &amp; import</span>
          </button>
        </div>
      </div>

      <!-- Step 3 — Done -->
      <div v-if="step === 'done'" class="bg-card border border-border rounded-xl p-6 max-w-2xl mx-auto text-center space-y-4">
        <h2 class="text-lg font-semibold">Import complete</h2>
        <div class="text-sm">
          <span class="px-2 py-1 rounded bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 font-semibold mr-2">{{ result?.created || 0 }} created</span>
          <span class="px-2 py-1 rounded bg-amber-500/15 text-amber-700 dark:text-amber-300 font-semibold mr-2">{{ result?.updated || 0 }} updated</span>
          <span v-if="result?.cleared" class="px-2 py-1 rounded bg-destructive/15 text-destructive font-semibold">{{ result.cleared }} cleared</span>
        </div>
        <div class="flex justify-center gap-2">
          <Link :href="route('admin.facility-branch.list')" class="inline-flex items-center justify-center rounded-md text-sm font-medium bg-primary text-primary-foreground h-9 px-4 btn-golden">
            Go to branches
          </Link>
          <button @click="reset(); step = 'upload'" class="inline-flex items-center justify-center rounded-md text-sm font-medium border bg-background h-9 px-4 hover:bg-muted">
            Import another file
          </button>
        </div>
      </div>
    </div>
  </FacilityBranchLayout>
</template>

<script setup>
import { Link } from "@inertiajs/vue3";
import FacilityBranchLayout from "../FacilityBranchLayout.vue";
import { Breadcrumb } from "@/Pages/Admin/Layout/Layout.js";
import { computed, ref } from "vue";

const file = ref(null);
const step = ref('upload');
const rows = ref([]);
const facilityOptions = ref([]);
const governorateOptions = ref([]);
const cityOptions = ref([]);
const mode = ref('upsert');
const loading = ref(false);
const error = ref('');
const result = ref(null);

const counts = computed(() => {
  const c = { new: 0, update: 0, unchanged: 0, error: 0 };
  for (const r of rows.value) c[r.status] = (c[r.status] || 0) + 1;
  return c;
});

const onFileChange = (e) => {
  file.value = e.target.files?.[0] || null;
  error.value = '';
};

const reset = () => {
  file.value = null;
  rows.value = [];
  error.value = '';
  result.value = null;
};

const uploadFile = async () => {
  if (!file.value) return;
  error.value = '';
  loading.value = true;
  try {
    const fd = new FormData();
    fd.append('file', file.value);
    const { data } = await axios.post(route('admin.facility-branch.import.preview'), fd);
    rows.value = (data.rows || []).map(r => ({
      ...r,
      showDiff: false,
    }));
    facilityOptions.value = data.facilityOptions || [];
    governorateOptions.value = data.governorateOptions || [];
    cityOptions.value = data.cityOptions || [];
    step.value = 'preview';
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to parse the file.';
  } finally {
    loading.value = false;
  }
};

const commit = async () => {
  loading.value = true;
  try {
    const payload = {
      mode: mode.value,
      rows: rows.value.map(r => ({
        facility_id: r.parsed.facility_id,
        name: r.parsed.name,
        name_ar: r.parsed.name_ar || null,
        address: r.parsed.address || null,
        address_ar: r.parsed.address_ar || null,
        phone: r.parsed.phone || null,
        governorate_id: r.parsed.governorate_id || null,
        city_id: r.parsed.city_id || null,
        latitude: r.parsed.latitude !== '' && r.parsed.latitude !== null ? Number(r.parsed.latitude) : null,
        longitude: r.parsed.longitude !== '' && r.parsed.longitude !== null ? Number(r.parsed.longitude) : null,
      })),
    };
    const { data } = await axios.post(route('admin.facility-branch.import.commit'), payload);
    result.value = data;
    step.value = 'done';
  } catch (e) {
    const msg = e.response?.data?.message || 'Import failed.';
    const errs = e.response?.data?.errors;
    error.value = errs ? `${msg} ${JSON.stringify(errs)}` : msg;
    alert(error.value);
  } finally {
    loading.value = false;
  }
};

const citiesForRow = (row) => {
  const gov = row.parsed.governorate_id;
  if (!gov) return cityOptions.value;
  return cityOptions.value.filter(c => String(c.governorate_id) === String(gov));
};

const hasChanges = (row) => (row.diff || []).some(d => d.changed);
const changedFields = (row) => (row.diff || []).filter(d => d.changed);

const rowBgClass = (status) => ({
  'bg-emerald-500/15': status === 'new',
  'bg-amber-500/15': status === 'update',
  'bg-muted/40': status === 'unchanged',
  'bg-red-500/20': status === 'error',
});

const statusBadgeClass = (status) => ({
  'bg-emerald-600 text-white': status === 'new',
  'bg-amber-500 text-white': status === 'update',
  'bg-slate-500 text-white': status === 'unchanged',
  'bg-red-600 text-white': status === 'error',
}[status]);

const inputCls = (err) => [
  'w-full px-1.5 py-1 rounded border bg-background text-foreground placeholder:text-muted-foreground text-xs focus:outline-none focus:ring-1',
  err ? 'border-destructive focus:ring-destructive' : 'border-input focus:ring-primary',
];

const formatVal = (v) => v === null || v === undefined || v === '' ? '—' : String(v);
</script>
