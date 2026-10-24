<template>
  <AppLayout title="Store Migration">
    <div class="w-full max-w-full">
      <div class="space-y-3 p-2 sm:p-3 md:p-4 lg:p-6 w-full max-w-full">
        <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border py-3 shadow-sm w-full">
          <div class="flex flex-wrap items-center justify-between gap-2 px-4">
            <h2 class="flex items-center gap-2 text-base font-semibold">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-orange-400"><path d="M21 8v13H3V8" /><path d="M1 3h22v5H1z" /><path d="M10 12h4" /></svg>
              Store Migration
            </h2>
            <Link :href="route('admin.store.list')" class="text-sm text-muted-foreground hover:text-foreground">Back to stores</Link>
          </div>
          <p class="px-4 pt-2 text-xs text-muted-foreground">
            Move stores — data <em>and</em> images — between servers, or take a full copy. A package is a ZIP; restoring it only adds and fills in, it never removes anything.
          </p>
        </div>

        <div class="grid grid-cols-1 gap-3 xl:grid-cols-2">
          <!-- ===================== Export ===================== -->
          <section class="space-y-3 rounded-xl border border-border bg-card p-4">
            <h3 class="flex items-center gap-2 text-sm font-semibold">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline points="7 10 12 15 17 10" /><line x1="12" y1="15" x2="12" y2="3" /></svg>
              Export a package
            </h3>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
              <label class="space-y-1 text-[11px] font-medium sm:col-span-2">Search (title or slug)<input v-model="ex.search" type="text" :class="ctl" /></label>
              <label class="space-y-1 text-[11px] font-medium">Sales rep
                <select v-model="ex.sales_id" :class="ctl"><option value="">All</option><option v-for="s in salesOptions" :key="s.value" :value="s.value">{{ s.label }}</option></select>
              </label>
              <label class="space-y-1 text-[11px] font-medium">Stores per part
                <input v-model.number="ex.per_part" type="number" min="1" max="500" :class="ctl" />
              </label>
              <label class="space-y-1 text-[11px] font-medium">Created from<input v-model="ex.created_from" type="date" dir="ltr" :class="ctl" /></label>
              <label class="space-y-1 text-[11px] font-medium">Created to<input v-model="ex.created_to" type="date" dir="ltr" :class="ctl" /></label>
            </div>
            <div v-if="categories.length" class="space-y-1">
              <p class="text-[11px] font-medium">Categories</p>
              <div class="flex flex-wrap gap-1.5">
                <label v-for="c in categories" :key="c.id" class="inline-flex items-center gap-1 rounded-full border border-border px-2 py-0.5 text-[11px]" :class="ex.category_ids.includes(c.id) ? 'border-primary bg-primary/15' : ''">
                  <input v-model="ex.category_ids" type="checkbox" :value="c.id" class="size-3" /> {{ label(c.name) }}
                </label>
              </div>
            </div>
            <div class="space-y-1.5">
              <label class="flex items-center gap-2 text-xs"><input v-model="ex.include_media" type="checkbox" /> Include images and the contract file (bigger, but complete)</label>
              <label class="flex items-center gap-2 text-xs"><input v-model="ex.keep" type="checkbox" /> Keep a copy on the server (so it can be imported later without the download)</label>
            </div>

            <div class="flex flex-wrap items-center gap-2">
              <button type="button" :disabled="planning" class="inline-flex h-8 items-center rounded-md border bg-background px-3 text-xs font-medium hover:bg-muted disabled:opacity-50" @click="makePlan">{{ planning ? 'Counting…' : 'Plan the parts' }}</button>
              <span v-if="plan" class="text-xs text-muted-foreground">{{ plan.total }} store(s) → {{ plan.parts }} part(s) of up to {{ plan.per_part }}</span>
            </div>
            <ul v-if="plan?.filters_described?.length" class="list-disc space-y-0.5 ps-5 text-[11px] text-muted-foreground"><li v-for="(d, i) in plan.filters_described" :key="i">{{ d }}</li></ul>
            <p v-if="plan && !plan.total" class="text-xs text-amber-600">No stores match these filters.</p>
            <div v-if="plan?.parts" class="flex flex-wrap gap-2">
              <a v-for="p in plan.parts" :key="p" :href="exportUrl(p)" class="inline-flex h-8 min-w-[6rem] items-center justify-center rounded-md bg-primary px-3 text-xs font-semibold text-primary-foreground hover:bg-primary/90" @click="afterDownload">
                Part {{ p }} of {{ plan.parts }}
              </a>
            </div>
            <p class="text-[11px] text-muted-foreground">Each part is a complete package that imports on its own. Building one with images can take a minute.</p>
          </section>

          <!-- ===================== Kept packages ===================== -->
          <section class="space-y-3 rounded-xl border border-border bg-card p-4">
            <h3 class="flex items-center gap-2 text-sm font-semibold">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8v13H3V8" /><path d="M1 3h22v5H1z" /><path d="M10 12h4" /></svg>
              Packages on this server ({{ packages.length }})
            </h3>
            <div v-if="!packages.length" class="py-6 text-center text-sm text-muted-foreground">No kept packages yet. Tick "Keep a copy" when exporting, or a "clear stores" import saves one automatically.</div>
            <div v-for="p in packages" :key="p.name" class="flex flex-wrap items-center gap-2 rounded-lg border border-border bg-accent/30 p-2">
              <div class="min-w-0 flex-1">
                <p class="truncate text-xs font-medium" dir="ltr">{{ p.name }}</p>
                <p class="text-[11px] text-muted-foreground">{{ formatSize(p.size) }} · {{ p.modified }}</p>
              </div>
              <button type="button" :disabled="busy" class="h-7 rounded-md border border-border px-2 text-[11px] font-medium hover:bg-muted disabled:opacity-50" @click="beginImport({ server_name: p.name })">Import</button>
              <a :href="route('admin.store.migration.packages.download', { name: p.name })" class="inline-flex h-7 items-center rounded-md border border-border px-2 text-[11px] font-medium hover:bg-muted">Download</a>
              <button type="button" class="h-7 rounded-md border border-destructive/40 px-2 text-[11px] font-medium text-destructive hover:bg-destructive/10" @click="deletePackage(p)">Delete</button>
            </div>
          </section>
        </div>

        <!-- ===================== Import ===================== -->
        <section class="space-y-3 rounded-xl border border-border bg-card p-4">
          <h3 class="flex items-center gap-2 text-sm font-semibold">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline points="17 8 12 3 7 8" /><line x1="12" y1="3" x2="12" y2="15" /></svg>
            Import a package
          </h3>

          <div v-if="!session" class="flex flex-wrap items-end gap-2">
            <input type="file" accept=".zip" class="block min-w-0 flex-1 rounded-md border border-border p-2 text-sm file:mr-3 file:rounded file:border-0 file:bg-primary file:px-3 file:py-1.5 file:text-primary-foreground" @change="onFile" />
            <button type="button" :disabled="!file || busy" class="inline-flex h-9 items-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground disabled:opacity-50" @click="beginImport({ package: file })">{{ busy ? 'Unpacking…' : 'Open package' }}</button>
          </div>
          <p v-if="error" class="text-sm text-destructive">{{ error }}</p>

          <template v-if="session && !finished">
            <div class="flex flex-wrap items-center gap-2 text-xs">
              <span class="rounded bg-emerald-600 px-2 py-1 font-semibold text-white">{{ rows.filter(r => r.status === 'new').length }} new</span>
              <span class="rounded bg-amber-500 px-2 py-1 font-semibold text-white">{{ rows.filter(r => r.status === 'update').length }} update</span>
              <span class="text-muted-foreground">Package from {{ session.manifest.exported_at?.slice(0, 10) }} · {{ session.total }} store(s)<span v-if="session.manifest.part"> · part {{ session.manifest.part }}/{{ session.manifest.parts }}</span><span v-if="!session.manifest.with_media"> · no images</span></span>
            </div>

            <div class="grid grid-cols-1 gap-2 rounded-lg border border-border bg-accent/20 p-3 sm:grid-cols-2">
              <label class="space-y-1 text-[11px] font-medium">When a store already exists
                <select v-model="opts.mode" :class="ctl">
                  <option value="fill">Fill in blanks only — keep what it has (safest)</option>
                  <option value="overwrite">Overwrite with the package's non-empty values</option>
                </select>
              </label>
              <div class="space-y-1.5 pt-4 text-xs">
                <label class="flex items-center gap-2"><input v-model="opts.create_categories" type="checkbox" /> Create categories that don't exist yet</label>
                <label class="flex items-center gap-2"><input v-model="opts.skip_media" type="checkbox" /> Skip images and contract files</label>
                <label class="flex items-center gap-2" :class="opts.skip_media ? 'opacity-50' : ''"><input v-model="opts.overwrite_media" :disabled="opts.skip_media" type="checkbox" /> Replace images a store already has</label>
              </div>
            </div>

            <div class="overflow-x-auto rounded-lg border border-border">
              <table class="w-full min-w-[720px] text-xs">
                <thead class="bg-muted/50">
                  <tr>
                    <th class="w-10 px-2 py-2"><input type="checkbox" :checked="allSelected" @change="toggleAll($event.target.checked)" /></th>
                    <th class="px-2 py-2 text-left font-semibold">Store</th><th class="px-2 py-2 text-left font-semibold">Status</th>
                    <th class="px-2 py-2 text-center font-semibold">Branches</th><th class="px-2 py-2 text-center font-semibold">Contacts</th>
                    <th class="px-2 py-2 text-center font-semibold">Gallery</th><th class="px-2 py-2 text-left font-semibold">Result</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="r in rows" :key="r.index" class="border-t border-border" :class="r.result?.error ? 'bg-red-500/10' : r.result ? 'bg-emerald-500/10' : ''">
                    <td class="px-2 py-1.5 text-center"><input v-model="selected" type="checkbox" :value="r.index" :disabled="running" /></td>
                    <td class="px-2 py-1.5"><span class="font-medium">{{ r.title }}</span><span v-if="r.title_ar" class="ms-2 text-muted-foreground">{{ r.title_ar }}</span><div class="text-[10px] text-muted-foreground" dir="ltr">{{ r.slug }}</div></td>
                    <td class="px-2 py-1.5"><span class="rounded px-2 py-0.5 text-[10px] font-bold uppercase text-white" :class="r.status === 'new' ? 'bg-emerald-600' : 'bg-amber-500'">{{ r.status }}</span></td>
                    <td class="px-2 py-1.5 text-center">{{ r.branches }}</td><td class="px-2 py-1.5 text-center">{{ r.managers }}</td><td class="px-2 py-1.5 text-center">{{ r.gallery }}</td>
                    <td class="px-2 py-1.5">
                      <span v-if="r.result?.error" class="text-destructive">✕ {{ r.result.error }}</span>
                      <span v-else-if="r.result" class="text-emerald-700 dark:text-emerald-300">✓ {{ r.result.action }}<span v-if="r.result.media || r.result.gallery"> · {{ r.result.media || 0 }} img, {{ r.result.gallery || 0 }} gallery</span></span>
                      <ul v-if="r.result?.warnings?.length" class="list-disc ps-4 text-[10px] text-amber-600"><li v-for="(w, i) in r.result.warnings" :key="i">{{ w }}</li></ul>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div v-if="running || done" class="space-y-1">
              <div class="h-2 overflow-hidden rounded bg-muted"><div class="h-full bg-primary transition-all" :style="{ width: progress + '%' }"></div></div>
              <p class="text-[11px] text-muted-foreground">{{ done }} of {{ selected.length }} store(s) processed</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
              <button type="button" :disabled="running" class="inline-flex h-9 items-center rounded-md border bg-background px-4 text-sm font-medium hover:bg-muted disabled:opacity-50" @click="cancelImport">Cancel</button>
              <button type="button" :disabled="running || !selected.length" class="ms-auto inline-flex h-9 items-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground disabled:opacity-50" @click="runImport">
                {{ running ? 'Importing…' : `Import ${selected.length} store(s)` }}
              </button>
            </div>
          </template>

          <div v-if="finished" class="space-y-3 text-center">
            <h4 class="text-base font-semibold">Import finished</h4>
            <div class="flex flex-wrap justify-center gap-2 text-sm">
              <span class="rounded bg-emerald-500/15 px-2 py-1 font-semibold text-emerald-700 dark:text-emerald-300">{{ summary.created }} created</span>
              <span class="rounded bg-amber-500/15 px-2 py-1 font-semibold text-amber-700 dark:text-amber-300">{{ summary.updated }} updated</span>
              <span v-if="summary.failed" class="rounded bg-destructive/15 px-2 py-1 font-semibold text-destructive">{{ summary.failed }} failed</span>
            </div>
            <p v-if="summary.failed" class="text-xs text-muted-foreground">Failed stores changed nothing. Fix the cause and run the same package again — already-imported stores are only matched, never duplicated.</p>
            <div class="flex justify-center gap-2">
              <Link :href="route('admin.store.list')" class="inline-flex h-9 items-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground">Go to stores</Link>
              <button type="button" class="inline-flex h-9 items-center rounded-md border bg-background px-4 text-sm font-medium hover:bg-muted" @click="resetImport">Import another package</button>
            </div>
          </div>
        </section>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import axios from 'axios';
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useNotification } from '@/composables/useNotification';

const props = defineProps({
  categories: { type: Array, default: () => [] },
  salesOptions: { type: Array, default: () => [] },
  packages: { type: Array, default: () => [] },
  totalStores: { type: Number, default: 0 },
});

const ctl = 'flex h-8 w-full rounded-md border border-input bg-transparent px-2 text-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50';
const locale = computed(() => (usePage().props.locale === 'en' ? 'en' : 'ar'));
const label = (name) => (name && typeof name === 'object' ? name[locale.value] || name.en || name.ar || '' : String(name || ''));
const formatSize = (b) => (b > 1048576 ? `${(b / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(b / 1024))} KB`);

// Same channel as every other admin screen: failures reach /admin/client-error-logs.
const report = (message, extra) => {
  axios.post('/api/v1/client-errors', { message, fatal: false, route: window.location.pathname, extra: { feature: 'store-migration', ...extra } }).catch(() => {});
};

/* ---------------------------- export ---------------------------- */
const ex = ref({ search: '', sales_id: '', per_part: 25, created_from: '', created_to: '', category_ids: [], include_media: true, keep: true });
const plan = ref(null);
const planning = ref(false);
const packages = ref([...props.packages]);

const exportQuery = (extra = {}) => ({
  search: ex.value.search || undefined,
  sales_id: ex.value.sales_id || undefined,
  per_part: ex.value.per_part || 25,
  created_from: ex.value.created_from || undefined,
  created_to: ex.value.created_to || undefined,
  category_ids: ex.value.category_ids.length ? ex.value.category_ids.join(',') : undefined,
  include_media: ex.value.include_media ? 1 : 0,
  keep: ex.value.keep ? 1 : 0,
  ...extra,
});
const exportUrl = (part) => route('admin.store.migration.export', exportQuery({ part }));

const makePlan = async () => {
  planning.value = true;
  try {
    const { data } = await axios.get(route('admin.store.migration.plan', exportQuery()));
    plan.value = data;
  } catch (e) {
    useNotification().error(e.response?.data?.message || 'Could not count the stores.');
    report(`Store migration plan failed: ${e.message}`, { step: 'plan' });
  } finally {
    planning.value = false;
  }
};

// The download streams; refresh the kept list a moment later so the new file appears.
const afterDownload = () => { setTimeout(refreshPackages, 4000); };
const refreshPackages = async () => {
  try { packages.value = (await axios.get(route('admin.store.migration.packages.index'))).data.packages; } catch { /* the list is a convenience */ }
};
const deletePackage = async (p) => {
  if (!window.confirm(`Delete the package "${p.name}" from the server? Stores are not affected.`)) return;
  try {
    packages.value = (await axios.delete(route('admin.store.migration.packages.destroy'), { data: { name: p.name } })).data.packages;
  } catch (e) {
    useNotification().error(e.response?.data?.message || 'Could not delete the package.');
  }
};

/* ---------------------------- import ---------------------------- */
const file = ref(null);
const busy = ref(false);
const error = ref('');
const session = ref(null);
const rows = ref([]);
const selected = ref([]);
const running = ref(false);
const done = ref(0);
const finished = ref(false);
const opts = ref({ mode: 'fill', create_categories: true, overwrite_media: false, skip_media: false });
const summary = ref({ created: 0, updated: 0, failed: 0 });

const allSelected = computed(() => rows.value.length > 0 && selected.value.length === rows.value.length);
const progress = computed(() => (selected.value.length ? Math.round((done.value / selected.value.length) * 100) : 0));
const toggleAll = (on) => { selected.value = on ? rows.value.map(r => r.index) : []; };
const onFile = (e) => { file.value = e.target.files?.[0] || null; error.value = ''; };

const beginImport = async (source) => {
  busy.value = true;
  error.value = '';
  try {
    const fd = new FormData();
    if (source.package) fd.append('package', source.package);
    if (source.server_name) fd.append('server_name', source.server_name);
    const { data } = await axios.post(route('admin.store.migration.begin'), fd);
    session.value = data;
    rows.value = data.rows.map(r => ({ ...r, result: null }));
    selected.value = rows.value.map(r => r.index);
    done.value = 0;
    finished.value = false;
    summary.value = { created: 0, updated: 0, failed: 0 };
    window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
  } catch (e) {
    error.value = e.response?.data?.message || 'The package could not be opened.';
    report(`Store migration begin failed: ${error.value}`, { step: 'begin' });
  } finally {
    busy.value = false;
  }
};

const runImport = async () => {
  running.value = true;
  done.value = 0;
  summary.value = { created: 0, updated: 0, failed: 0 };
  const size = session.value.step_size || 5;
  const queue = rows.value.filter(r => selected.value.includes(r.index)).map(r => r.index);
  try {
    for (let i = 0; i < queue.length; i += size) {
      const chunk = queue.slice(i, i + size);
      const { data } = await axios.post(route('admin.store.migration.step'), { token: session.value.token, indexes: chunk, ...opts.value });
      for (const res of data.results) {
        const row = rows.value.find(r => r.index === res.index);
        if (row) row.result = res;
        if (res.error) {
          summary.value.failed++;
          report(`Store migration: "${res.title}" failed: ${res.error}`, { step: 'apply', index: res.index });
        } else if (res.action === 'created') summary.value.created++;
        else summary.value.updated++;
      }
      done.value += chunk.length;
    }
    await axios.post(route('admin.store.migration.finish'), { token: session.value.token, summary: summary.value });
    finished.value = true;
  } catch (e) {
    error.value = e.response?.data?.message || 'The import stopped. Run the same package again to continue — nothing is duplicated.';
    report(`Store migration step failed: ${error.value}`, { step: 'step' });
  } finally {
    running.value = false;
  }
};

const cancelImport = async () => {
  if (session.value) await axios.post(route('admin.store.migration.cancel'), { token: session.value.token }).catch(() => {});
  resetImport();
};
const resetImport = () => { session.value = null; rows.value = []; selected.value = []; file.value = null; finished.value = false; error.value = ''; refreshPackages(); };
</script>
