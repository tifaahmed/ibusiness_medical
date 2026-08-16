<template>
  <CompanyLayout>
    <div class="space-y-3 p-3 sm:p-4 lg:p-6 w-full max-w-full">
      <Breadcrumb
        title="Import Companies"
        :breadcrumbs="[
          { label: 'Companies', link: route('admin.company.list'), active: false },
          { label: 'Import', link: '#', active: true },
        ]"
      />

      <!-- Step 1 — Upload -->
      <div v-if="step === 'upload'" class="bg-card border border-border rounded-xl p-6 space-y-5 max-w-2xl mx-auto">
        <div>
          <h2 class="text-lg font-semibold mb-1">Upload companies file</h2>
          <p class="text-sm text-muted-foreground leading-relaxed">
            Upload a CSV or XLSX file. The expected format matches the export exactly
            (<code>ID, Name (English), Name (Arabic), Slug, Created By (Email), Created At, Updated At</code>) —
            so you can export, edit and re-import the same file including the <code>ID</code>.
          </p>
        </div>

        <div>
          <h3 class="text-sm font-semibold mb-2">Import mode</h3>
          <div class="grid sm:grid-cols-3 gap-2">
            <label
              class="flex flex-col gap-1 rounded-md border p-3 cursor-pointer transition-colors"
              :class="mode === 'upsert' ? 'border-primary bg-primary/10' : 'border-border hover:bg-muted/50'"
            >
              <input type="radio" value="upsert" v-model="mode" class="accent-primary" />
              <span class="text-sm font-semibold">Update or create</span>
              <span class="text-xs text-muted-foreground">Matches by ID, then slug, then name. Updates existing, creates new.</span>
            </label>
            <label
              class="flex flex-col gap-1 rounded-md border p-3 cursor-pointer transition-colors"
              :class="mode === 'add_only' ? 'border-primary bg-primary/10' : 'border-border hover:bg-muted/50'"
            >
              <input type="radio" value="add_only" v-model="mode" class="accent-primary" />
              <span class="text-sm font-semibold">Add only</span>
              <span class="text-xs text-muted-foreground">Inserts only new rows. Existing ones (by ID / slug / name) are skipped.</span>
            </label>
            <label
              class="flex flex-col gap-1 rounded-md border p-3 cursor-pointer transition-colors"
              :class="mode === 'clear' ? 'border-destructive bg-destructive/10' : 'border-border hover:bg-muted/50'"
            >
              <input type="radio" value="clear" v-model="mode" class="accent-destructive" />
              <span class="text-sm font-semibold">Delete all then add</span>
              <span class="text-xs text-muted-foreground">Deletes every company first, then inserts the file's rows.</span>
            </label>
          </div>
        </div>

        <div>
          <h3 class="text-sm font-semibold mb-2">Need a file to fill in?</h3>
          <div class="flex flex-wrap gap-2">
            <a
              :href="templateUrl('template')"
              class="inline-flex items-center gap-1.5 rounded-md border border-border bg-background h-8 px-3 text-xs font-medium hover:bg-accent"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 text-emerald-600"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/></svg>
              Download blank template
            </a>
            <a
              :href="templateUrl('example')"
              class="inline-flex items-center gap-1.5 rounded-md border border-border bg-background h-8 px-3 text-xs font-medium hover:bg-accent"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 text-blue-600"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/></svg>
              Download example file
            </a>
          </div>
        </div>

        <div>
          <h3 class="text-sm font-semibold mb-2">Choose a file</h3>
          <input
            type="file"
            accept=".csv,.xlsx,.xls,.txt"
            @change="onFileChange"
            class="block w-full text-sm border border-border rounded-md p-2 file:mr-3 file:px-3 file:py-1.5 file:rounded file:border-0 file:bg-primary file:text-primary-foreground"
          />
          <p v-if="mode === 'clear'" class="mt-2 text-xs text-destructive">
            <strong>Warning:</strong> "Delete all then add" removes existing companies. Companies that still have members are kept (same rule as the delete button).
          </p>
        </div>

        <div v-if="error" class="text-sm text-destructive">{{ error }}</div>

        <div class="flex gap-2 justify-end">
          <Link
            :href="route('admin.company.list')"
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
            <span v-else>Preview &amp; edit</span>
          </button>
        </div>
      </div>

      <!-- Step 2 — Preview / Edit -->
      <div v-if="step === 'preview'" class="space-y-4">
        <div class="bg-card border border-border rounded-xl p-4 flex flex-wrap items-center gap-4">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="px-2.5 py-1 rounded text-xs bg-emerald-600 text-white font-semibold">{{ counts.new }} NEW</span>
            <span class="px-2.5 py-1 rounded text-xs bg-amber-500 text-white font-semibold">{{ counts.update }} UPDATE</span>
            <span class="px-2.5 py-1 rounded text-xs bg-slate-500 text-white font-semibold">{{ counts.unchanged }} UNCHANGED</span>
            <span v-if="counts.error" class="px-2.5 py-1 rounded text-xs bg-red-600 text-white font-semibold">{{ counts.error }} ERROR</span>
          </div>
          <div class="ml-auto flex items-center gap-3 text-sm flex-wrap">
            <label class="flex items-center gap-1.5 cursor-pointer">
              <input type="radio" value="upsert" v-model="mode" /> Update or create
            </label>
            <label class="flex items-center gap-1.5 cursor-pointer">
              <input type="radio" value="add_only" v-model="mode" /> Add only
            </label>
            <label class="flex items-center gap-1.5 cursor-pointer">
              <input type="radio" value="clear" v-model="mode" /> Delete all then add
            </label>
          </div>
        </div>

        <div v-if="mode === 'clear'" class="bg-red-600 text-white border border-red-700 rounded-md p-3 text-sm">
          <strong>Warning:</strong> "Delete all then add" deletes existing companies (those still having members are kept), then inserts the imported rows as new companies.
        </div>

        <div class="bg-card text-card-foreground border border-border rounded-xl overflow-x-auto">
          <table class="text-xs text-foreground" style="min-width: 1300px; width: 100%;">
            <thead class="bg-muted/50">
              <tr class="border-b border-border">
                <th class="px-2 py-2 text-left font-semibold w-10 sticky left-0 bg-muted/80 backdrop-blur z-10">#</th>
                <th class="px-2 py-2 text-left font-semibold w-24">Status</th>
                <th class="px-2 py-2 text-left font-semibold w-24">ID</th>
                <th class="px-2 py-2 text-left font-semibold w-64">Name (English)</th>
                <th class="px-2 py-2 text-left font-semibold w-64">Name (Arabic)</th>
                <th class="px-2 py-2 text-left font-semibold w-44">Slug</th>
                <th class="px-2 py-2 text-left font-semibold w-48">Created By (Email)</th>
                <th class="px-2 py-2 text-left font-semibold w-44">Created At</th>
                <th class="px-2 py-2 text-left font-semibold w-44">Updated At</th>
                <th class="px-2 py-2 text-left font-semibold w-24 sticky right-0 bg-muted/80 backdrop-blur z-10"></th>
              </tr>
            </thead>
            <tbody>
              <template v-for="(row, i) in rows" :key="i">
                <tr class="border-b border-border" :class="rowBgClass(row.status)">
                  <td class="px-2 py-1.5 text-center font-mono sticky left-0 z-[5] bg-card" :class="rowBgClass(row.status)">
                    {{ i + 1 }}
                  </td>
                  <td class="px-2 py-1.5">
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase" :class="statusBadgeClass(row.status)">
                      {{ statusLabel(row.status) }}
                    </span>
                  </td>
                  <td class="px-2 py-1">
                    <input
                      v-model="row.parsed.id"
                      inputmode="numeric"
                      placeholder="—"
                      :disabled="row.status === 'update'"
                      :class="inputCls(row.errors?.id)"
                    />
                  </td>
                  <td class="px-2 py-1"><input v-model="row.parsed.name_en" :class="inputCls(row.errors?.name)" /></td>
                  <td class="px-2 py-1"><input v-model="row.parsed.name_ar" :class="inputCls(row.errors?.name)" /></td>
                  <td class="px-2 py-1"><input v-model="row.parsed.slug" :class="inputCls()" /></td>
                  <td class="px-2 py-1"><input v-model="row.parsed.created_by_email" :class="inputCls()" /></td>
                  <td class="px-2 py-1"><input v-model="row.parsed.created_at" placeholder="YYYY-MM-DD HH:MM:SS" :class="inputCls(row.errors?.created_at)" /></td>
                  <td class="px-2 py-1"><input v-model="row.parsed.updated_at" placeholder="YYYY-MM-DD HH:MM:SS" :class="inputCls(row.errors?.updated_at)" /></td>
                  <td class="px-2 py-1 text-center sticky right-0 z-[5] bg-card" :class="rowBgClass(row.status)">
                    <button
                      v-if="row.status === 'update' && mode === 'upsert' && hasChanges(row)"
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
                  <td colspan="10" class="px-2 pb-2 text-[11px] text-destructive">
                    <span v-for="(msg, key) in row.errors" :key="key" class="mr-3">⚠ <strong>{{ key }}:</strong> {{ msg }}</span>
                  </td>
                </tr>

                <!-- Diff sub-table -->
                <tr v-if="row.showDiff && row.status === 'update' && mode === 'upsert'" :class="rowBgClass(row.status)">
                  <td colspan="10" class="p-3">
                    <div class="bg-card border border-border rounded-md overflow-hidden">
                      <div class="bg-muted/50 px-3 py-2 text-xs font-semibold">
                        Changes for <span class="text-foreground">{{ row.match?.name }}</span>
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
        <div class="text-sm space-x-2">
          <span class="inline-block px-2 py-1 rounded bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 font-semibold">{{ result?.created || 0 }} created</span>
          <span class="inline-block px-2 py-1 rounded bg-amber-500/15 text-amber-700 dark:text-amber-300 font-semibold">{{ result?.updated || 0 }} updated</span>
          <span class="inline-block px-2 py-1 rounded bg-slate-500/15 text-slate-700 dark:text-slate-300 font-semibold">{{ result?.skipped || 0 }} skipped</span>
          <span v-if="result?.deleted" class="inline-block px-2 py-1 rounded bg-destructive/15 text-destructive font-semibold">{{ result.deleted }} deleted</span>
          <span v-if="result?.skippedDelete" class="inline-block px-2 py-1 rounded bg-orange-500/15 text-orange-700 dark:text-orange-300 font-semibold">{{ result.skippedDelete }} kept (has members)</span>
        </div>
        <div class="flex justify-center gap-2">
          <Link :href="route('admin.company.list')" class="inline-flex items-center justify-center rounded-md text-sm font-medium bg-primary text-primary-foreground h-9 px-4 btn-golden">
            Go to companies
          </Link>
          <button @click="reset(); step = 'upload'" class="inline-flex items-center justify-center rounded-md text-sm font-medium border bg-background h-9 px-4 hover:bg-muted">
            Import another file
          </button>
        </div>
      </div>
    </div>
  </CompanyLayout>
</template>

<script setup>
import { Link } from "@inertiajs/vue3";
import CompanyLayout from "../CompanyLayout.vue";
import { Breadcrumb } from "@/Pages/Admin/Layout/Layout.js";
import { computed, ref } from "vue";

const file = ref(null);
const step = ref('upload');
const rows = ref([]);
const mode = ref('upsert');
const loading = ref(false);
const error = ref('');
const result = ref(null);

const counts = computed(() => {
  const c = { new: 0, update: 0, unchanged: 0, error: 0 };
  for (const r of rows.value) c[r.status] = (c[r.status] || 0) + 1;
  return c;
});

const templateUrl = (type) => {
  const base = route('admin.company.import.template');
  const params = new URLSearchParams({ type });
  return `${base}?${params.toString()}`;
};

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
    const { data } = await axios.post(route('admin.company.import.preview'), fd);
    rows.value = (data.rows || []).map(r => ({
      ...r,
      showDiff: false,
    }));
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
        id: r.parsed.id !== '' && r.parsed.id != null ? Number(r.parsed.id) : null,
        name_en: r.parsed.name_en || null,
        name_ar: r.parsed.name_ar || null,
        slug: r.parsed.slug || null,
        created_by_email: r.parsed.created_by_email || null,
        created_at: r.parsed.created_at || null,
        updated_at: r.parsed.updated_at || null,
      })),
    };
    const { data } = await axios.post(route('admin.company.import.run'), payload);
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

const hasChanges = (row) => (row.diff || []).some(d => d.changed);
const changedFields = (row) => (row.diff || []).filter(d => d.changed);

const statusLabel = (status) => ({
  new: 'new',
  update: 'update',
  unchanged: 'unchanged',
  error: 'error',
}[status] || status);

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
