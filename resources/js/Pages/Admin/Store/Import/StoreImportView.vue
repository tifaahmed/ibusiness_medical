<template>
  <AppLayout title="Import Stores">
    <div class="w-full max-w-full">
      <div class="space-y-3 p-2 sm:p-3 md:p-4 lg:p-6 w-full max-w-full">
        <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border py-3 shadow-sm w-full">
          <div class="flex flex-wrap items-center justify-between gap-2 px-4">
            <h2 class="flex items-center gap-2 text-base font-semibold">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-orange-400"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline points="7 10 12 15 17 10" transform="rotate(180 12 12)" /><line x1="12" y1="3" x2="12" y2="15" /></svg>
              Import Stores
            </h2>
            <Link :href="route('admin.store.list')" class="text-sm text-muted-foreground hover:text-foreground">Back to stores</Link>
          </div>
        </div>

        <!-- Step 1 — Upload -->
        <div v-if="step === 'upload'" class="mx-auto max-w-2xl space-y-4 rounded-xl border border-border bg-card p-6">
          <h3 class="text-lg font-semibold">Upload a stores file</h3>
          <p class="text-sm text-muted-foreground">
            Upload an XLSX (or CSV) in the layout the export produces. The <code>Stores</code> sheet needs a <code>Title</code> column;
            optional <code>Branches</code> and <code>Managers</code> sheets are matched to a store by <code>Store title</code> or <code>Store slug</code>.
            Rows are matched to existing stores by slug, then by title. By default nothing is deleted and a blank cell never overwrites a stored value.
          </p>
          <div class="flex flex-wrap gap-2">
            <a :href="route('admin.store.import.template.blank')" class="inline-flex h-8 items-center gap-1.5 rounded-md border border-border bg-background px-3 text-xs font-medium hover:bg-muted">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline points="7 10 12 15 17 10" /><line x1="12" y1="15" x2="12" y2="3" /></svg>
              Blank template
            </a>
            <a :href="route('admin.store.import.template.example')" class="inline-flex h-8 items-center gap-1.5 rounded-md border border-border bg-background px-3 text-xs font-medium hover:bg-muted">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline points="7 10 12 15 17 10" /><line x1="12" y1="15" x2="12" y2="3" /></svg>
              Example file
            </a>
          </div>
          <input
            type="file" accept=".csv,.xlsx,.xls,.txt" @change="onFileChange"
            class="block w-full rounded-md border border-border p-2 text-sm file:mr-3 file:rounded file:border-0 file:bg-primary file:px-3 file:py-1.5 file:text-primary-foreground"
          />
          <div v-if="error" class="text-sm text-destructive">{{ error }}</div>
          <div class="flex justify-end gap-2">
            <Link :href="route('admin.store.list')" class="inline-flex h-9 items-center justify-center rounded-md border bg-background px-4 text-sm font-medium hover:bg-muted">Cancel</Link>
            <button type="button" :disabled="!file || loading" @click="uploadFile" class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground disabled:opacity-50">
              {{ loading ? 'Parsing…' : 'Preview' }}
            </button>
          </div>
        </div>

        <!-- Step 2 — Preview / edit -->
        <div v-if="step === 'preview'" class="space-y-4">
          <div class="flex flex-wrap items-center gap-2 rounded-xl border border-border bg-card p-4">
            <span class="rounded bg-emerald-600 px-2.5 py-1 text-xs font-semibold text-white">{{ counts.new }} NEW</span>
            <span class="rounded bg-amber-500 px-2.5 py-1 text-xs font-semibold text-white">{{ counts.update }} UPDATE</span>
            <span class="rounded bg-slate-500 px-2.5 py-1 text-xs font-semibold text-white">{{ counts.unchanged }} UNCHANGED</span>
            <span v-if="counts.error" class="rounded bg-red-600 px-2.5 py-1 text-xs font-semibold text-white">{{ counts.error }} ERROR</span>
            <span v-if="totalBranches" class="rounded bg-violet-600 px-2.5 py-1 text-xs font-semibold text-white">{{ totalBranches }} branches</span>
            <span v-if="totalManagers" class="rounded bg-sky-600 px-2.5 py-1 text-xs font-semibold text-white">{{ totalManagers }} contacts</span>
            <div class="ms-auto flex flex-wrap items-center gap-3 text-sm">
              <label class="flex cursor-pointer items-center gap-1.5"><input v-model="mode" type="radio" value="upsert" /> Update existing &amp; add new</label>
              <label v-if="canClear" class="flex cursor-pointer items-center gap-1.5"><input v-model="mode" type="radio" value="clear" /> Clear stores &amp; add new</label>
            </div>
          </div>

          <div v-if="mode === 'clear'" class="space-y-2 rounded-md border border-red-700 bg-red-600 p-3 text-sm text-white">
            <p><strong>Warning:</strong> "Clear" deletes every existing store (its branches, contacts, images and gallery) and then adds the rows below. Stores that still have products are kept.</p>
            <p class="text-xs text-white/90">A complete backup package of every store, with images, is saved on the server and verified first — if it cannot be made, nothing is deleted. You can restore it from the Migration page.</p>
            <label class="block text-xs font-medium">Type <code class="rounded bg-black/30 px-1">DELETE ALL STORES</code> to confirm
              <input v-model="confirmText" type="text" dir="ltr" autocomplete="off" class="mt-1 w-full rounded border border-white/40 bg-black/20 px-2 py-1.5 text-sm text-white placeholder:text-white/40 focus:outline-none" placeholder="DELETE ALL STORES" />
            </label>
          </div>

          <div v-for="(row, i) in rows" :key="i" class="space-y-3 rounded-xl border border-border p-3" :class="rowBgClass(row.status)">
            <div class="flex flex-wrap items-center gap-2">
              <span class="font-mono text-xs text-muted-foreground">#{{ i + 1 }}</span>
              <span class="rounded px-2 py-0.5 text-[10px] font-bold uppercase" :class="statusBadgeClass(row.status)">{{ row.status }}</span>
              <span v-if="row.match" class="text-xs text-muted-foreground">→ {{ row.match.name }} <span dir="ltr">({{ row.match.slug }})</span></span>
              <div class="ms-auto flex items-center gap-3">
                <button v-if="row.status === 'update' && hasChanges(row)" type="button" class="text-[11px] text-blue-600 hover:underline" @click="row.showDiff = !row.showDiff">{{ row.showDiff ? 'hide diff' : 'diff' }}</button>
                <button type="button" class="text-[11px] text-destructive hover:underline" title="Skip this row" @click="rows.splice(i, 1)">skip</button>
              </div>
            </div>

            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
              <label class="space-y-1 text-xs font-medium">Title<input v-model="row.parsed.title" :class="inputCls(row.errors?.title)" /></label>
              <label class="space-y-1 text-xs font-medium">Title (AR)<input v-model="row.parsed.title_ar" :class="inputCls()" /></label>
              <label class="space-y-1 text-xs font-medium">Contracted date<input v-model="row.parsed.contracted_at" type="date" dir="ltr" :class="inputCls(row.errors?.contracted_at)" /></label>
              <label class="space-y-1 text-xs font-medium">Online only
                <select v-model="row.parsed.online_only" :class="inputCls()">
                  <option :value="null">— unchanged / no —</option>
                  <option :value="true">Yes</option>
                  <option :value="false">No</option>
                </select>
              </label>
            </div>
            <p class="text-xs text-muted-foreground">
              <span v-if="row.parsed.sales_input">Sales rep: <strong>{{ row.parsed.sales_input }}</strong><span v-if="!row.parsed.sales_id" class="text-amber-600"> (not found)</span> · </span>
              <span v-if="row.parsed.category_input">Categories: <strong>{{ row.parsed.category_input }}</strong> ({{ row.parsed.category_ids.length }} matched)</span>
            </p>

            <div v-if="Object.keys(row.errors || {}).length" class="text-[11px] text-destructive">
              <span v-for="(msg, key) in row.errors" :key="key" class="me-3">⚠ <strong>{{ key }}:</strong> {{ msg }}</span>
            </div>
            <ul v-if="row.warnings?.length" class="list-disc space-y-0.5 ps-5 text-[11px] text-amber-600">
              <li v-for="(w, wi) in row.warnings" :key="wi">{{ w }}</li>
            </ul>

            <div v-if="row.showDiff" class="overflow-hidden rounded-md border border-border bg-card">
              <table class="w-full text-[11px]">
                <thead class="bg-muted/40"><tr><th class="px-3 py-1.5 text-left font-semibold">Field</th><th class="px-3 py-1.5 text-left font-semibold">Current</th><th class="px-3 py-1.5 text-left font-semibold">After import</th></tr></thead>
                <tbody>
                  <tr v-for="d in changedFields(row)" :key="d.field" class="border-t border-border">
                    <td class="px-3 py-1.5 font-semibold">{{ d.label }}</td>
                    <td class="bg-red-500/15 px-3 py-1.5 text-red-700 line-through dark:text-red-300">{{ formatVal(d.old) }}</td>
                    <td class="bg-emerald-500/20 px-3 py-1.5 font-bold text-emerald-700 dark:text-emerald-200">{{ formatVal(d.new) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <details v-if="row.branches?.length" class="rounded-md border border-violet-500/60 bg-card" open>
              <summary class="cursor-pointer bg-violet-600 px-3 py-1.5 text-xs font-semibold text-white">Branches ({{ row.branches.length }})</summary>
              <div class="overflow-x-auto">
                <table class="w-full min-w-[820px] text-[11px]">
                  <thead class="bg-muted"><tr>
                    <th class="px-2 py-1.5 text-left font-semibold">Name</th><th class="px-2 py-1.5 text-left font-semibold">Name (AR)</th>
                    <th class="px-2 py-1.5 text-left font-semibold">Address</th><th class="px-2 py-1.5 text-left font-semibold">Phone</th>
                    <th class="px-2 py-1.5 text-left font-semibold">Lat</th><th class="px-2 py-1.5 text-left font-semibold">Lng</th><th class="w-12"></th>
                  </tr></thead>
                  <tbody>
                    <tr v-for="(br, bi) in row.branches" :key="bi" class="border-t border-border">
                      <td class="px-2 py-1"><input v-model="br.name" :class="inputCls()" /></td>
                      <td class="px-2 py-1"><input v-model="br.name_ar" :class="inputCls()" /></td>
                      <td class="px-2 py-1"><input v-model="br.address" :class="inputCls()" /></td>
                      <td class="px-2 py-1"><input v-model="br.phone" dir="ltr" :class="inputCls()" /></td>
                      <td class="px-2 py-1"><input v-model="br.latitude" type="number" step="any" :class="inputCls()" /></td>
                      <td class="px-2 py-1"><input v-model="br.longitude" type="number" step="any" :class="inputCls()" /></td>
                      <td class="px-2 py-1 text-center"><button type="button" class="text-[10px] text-red-600 hover:underline" @click="row.branches.splice(bi, 1)">remove</button></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </details>

            <details v-if="row.managers?.length" class="rounded-md border border-sky-500/60 bg-card" open>
              <summary class="cursor-pointer bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white">Contacts ({{ row.managers.length }})</summary>
              <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-[11px]">
                  <thead class="bg-muted"><tr>
                    <th class="px-2 py-1.5 text-left font-semibold">Name</th><th class="px-2 py-1.5 text-left font-semibold">Position</th>
                    <th class="px-2 py-1.5 text-left font-semibold">Phones</th><th class="w-12"></th>
                  </tr></thead>
                  <tbody>
                    <tr v-for="(m, mi) in row.managers" :key="mi" class="border-t border-border">
                      <td class="px-2 py-1"><input v-model="m.name" :class="inputCls()" /></td>
                      <td class="px-2 py-1"><input v-model="m.position" :class="inputCls()" /></td>
                      <td class="px-2 py-1"><input v-model="m.phones" dir="ltr" :class="inputCls()" /></td>
                      <td class="px-2 py-1 text-center"><button type="button" class="text-[10px] text-red-600 hover:underline" @click="row.managers.splice(mi, 1)">remove</button></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </details>
          </div>

          <div v-if="!rows.length" class="rounded-xl border border-border bg-card p-8 text-center text-sm text-muted-foreground">No rows left to import.</div>

          <div class="sticky bottom-0 z-10 flex flex-wrap items-center gap-3 rounded-lg border border-border bg-card p-3">
            <button type="button" class="inline-flex h-9 items-center justify-center rounded-md border bg-background px-4 text-sm font-medium hover:bg-muted" @click="step = 'upload'; reset()">Back</button>
            <div v-if="counts.error" class="text-xs text-destructive">{{ counts.error }} row(s) have errors. Fix or skip them before confirming.</div>
            <button type="button" :disabled="loading || counts.error > 0 || !rows.length || (mode === 'clear' && confirmText !== 'DELETE ALL STORES')" class="ms-auto inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground disabled:opacity-50" @click="commit">
              {{ loading ? 'Saving…' : 'Confirm & import' }}
            </button>
          </div>
        </div>

        <!-- Step 3 — Done -->
        <div v-if="step === 'done'" class="mx-auto max-w-2xl space-y-4 rounded-xl border border-border bg-card p-6 text-center">
          <h3 class="text-lg font-semibold">Import complete</h3>
          <div class="flex flex-wrap justify-center gap-2 text-sm">
            <span class="rounded bg-emerald-500/15 px-2 py-1 font-semibold text-emerald-700 dark:text-emerald-300">{{ result?.created || 0 }} created</span>
            <span class="rounded bg-amber-500/15 px-2 py-1 font-semibold text-amber-700 dark:text-amber-300">{{ result?.updated || 0 }} updated</span>
            <span v-if="result?.branches" class="rounded bg-violet-500/15 px-2 py-1 font-semibold text-violet-700 dark:text-violet-300">{{ result.branches }} branches</span>
            <span v-if="result?.managers" class="rounded bg-sky-500/15 px-2 py-1 font-semibold text-sky-700 dark:text-sky-300">{{ result.managers }} contacts</span>
            <span v-if="result?.cleared" class="rounded bg-destructive/15 px-2 py-1 font-semibold text-destructive">{{ result.cleared }} cleared</span>
          </div>
          <p v-if="result?.backup" class="text-xs text-muted-foreground">Safety backup saved as <code dir="ltr">{{ result.backup }}</code> — restore it from the <Link :href="route('admin.store.migration.page')" class="text-primary underline">Migration page</Link>.</p>
          <p v-if="result?.skipped_with_products?.length" class="text-xs text-amber-600">Kept because they still have products: {{ result.skipped_with_products.join(', ') }}.</p>
          <div class="flex justify-center gap-2">
            <Link :href="route('admin.store.list')" class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground">Go to stores</Link>
            <button type="button" class="inline-flex h-9 items-center justify-center rounded-md border bg-background px-4 text-sm font-medium hover:bg-muted" @click="reset(); step = 'upload'">Import another file</button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import axios from 'axios';
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useNotification } from '@/composables/useNotification';

const { can } = usePermissions();
const file = ref(null);
const step = ref('upload');
const rows = ref([]);
const loading = ref(false);
const error = ref('');
const result = ref(null);
const mode = ref('upsert');
const confirmText = ref('');
const canClear = computed(() => can('manage stores'));

const counts = computed(() => {
  const c = { new: 0, update: 0, unchanged: 0, error: 0 };
  for (const r of rows.value) c[r.status] = (c[r.status] || 0) + 1;
  return c;
});
const totalBranches = computed(() => rows.value.reduce((n, r) => n + (r.branches?.length || 0), 0));
const totalManagers = computed(() => rows.value.reduce((n, r) => n + (r.managers?.length || 0), 0));

const onFileChange = (e) => { file.value = e.target.files?.[0] || null; error.value = ''; };
const reset = () => { file.value = null; rows.value = []; error.value = ''; result.value = null; mode.value = 'upsert'; confirmText.value = ''; };

// Same channel as every other admin form: failures reach /admin/client-error-logs.
const report = (message, extra) => {
  axios.post('/api/v1/client-errors', { message, fatal: false, route: window.location.pathname, extra: { feature: 'store-import', ...extra } }).catch(() => {});
};

const uploadFile = async () => {
  if (!file.value) return;
  error.value = '';
  loading.value = true;
  try {
    const fd = new FormData();
    fd.append('file', file.value);
    const { data } = await axios.post(route('admin.store.import.preview'), fd);
    rows.value = (data.rows || []).map(r => ({ ...r, showDiff: false }));
    step.value = 'preview';
  } catch (e) {
    error.value = e.response?.data?.message || e.response?.data?.errors?.file?.[0] || 'Failed to parse the file.';
    report(`Store import preview failed: ${error.value}`, { step: 'preview' });
  } finally {
    loading.value = false;
  }
};

const num = (v) => (v !== '' && v !== null && v !== undefined ? Number(v) : null);

const commit = async () => {
  loading.value = true;
  try {
    const payload = {
      mode: mode.value,
      confirm: mode.value === 'clear' ? confirmText.value : undefined,
      rows: rows.value.map(r => ({
        title: r.parsed.title,
        title_ar: r.parsed.title_ar || null,
        slug: r.parsed.slug || null,
        contracted_at: r.parsed.contracted_at || null,
        online_only: r.parsed.online_only,
        sales_id: r.parsed.sales_id || null,
        category_ids: r.parsed.category_ids || [],
        branches: (r.branches || []).map(b => ({
          name: b.name || null, name_ar: b.name_ar || null, address: b.address || null, address_ar: b.address_ar || null,
          phone: b.phone || null, governorate_id: b.governorate_id || null, city_id: b.city_id || null,
          latitude: num(b.latitude), longitude: num(b.longitude),
        })),
        managers: (r.managers || []).filter(m => (m.name || '').trim() !== '').map(m => ({ name: m.name, position: m.position || null, phones: m.phones || null })),
      })),
    };
    const { data } = await axios.post(route('admin.store.import.commit'), payload);
    result.value = data;
    step.value = 'done';
  } catch (e) {
    const msg = e.response?.data?.message || 'Import failed.';
    const errs = e.response?.data?.errors;
    error.value = errs ? `${msg} ${JSON.stringify(errs)}` : msg;
    useNotification().error(error.value);
    report(`Store import commit failed: ${msg}`, { step: 'commit', errors: errs });
  } finally {
    loading.value = false;
  }
};

const hasChanges = (row) => (row.diff || []).some(d => d.changed);
const changedFields = (row) => (row.diff || []).filter(d => d.changed);
const rowBgClass = (status) => ({
  'bg-emerald-500/10': status === 'new',
  'bg-amber-500/10': status === 'update',
  'bg-muted/40': status === 'unchanged',
  'bg-red-500/15': status === 'error',
});
const statusBadgeClass = (status) => ({
  'bg-emerald-600 text-white': status === 'new',
  'bg-amber-500 text-white': status === 'update',
  'bg-slate-500 text-white': status === 'unchanged',
  'bg-red-600 text-white': status === 'error',
}[status]);
const inputCls = (err) => [
  'w-full rounded border bg-background px-1.5 py-1 text-xs text-foreground focus:outline-none focus:ring-1',
  err ? 'border-destructive focus:ring-destructive' : 'border-input focus:ring-primary',
];
const formatVal = (v) => (v === null || v === undefined || v === '' ? '—' : String(v));
</script>
