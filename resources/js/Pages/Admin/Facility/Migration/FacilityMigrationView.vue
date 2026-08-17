<template>
  <FacilityLayout>
    <div class="space-y-3 p-3 sm:p-4 lg:p-6 w-full max-w-full">
      <Breadcrumb
        title="Facility Migration"
        :breadcrumbs="[
          { label: 'Facilities', link: route('admin.facility.list'), active: false },
          { label: 'Migration', link: '#', active: true },
        ]"
      />

      <div class="bg-card border border-border rounded-xl p-4 text-sm text-muted-foreground">
        Move facilities between sites without losing anything: every column in every language,
        branches, tags, offers and the image files themselves travel in one package.
        Large sites are handled in <strong class="text-foreground">parts</strong> — export a few
        facilities at a time, and import them step by step so the browser never has to hold the
        whole job in one request.
        <div class="mt-2 flex flex-wrap gap-3 text-xs">
          <span class="px-2 py-1 rounded bg-muted">{{ summary.facilities }} facilities</span>
          <span class="px-2 py-1 rounded bg-muted">{{ summary.branches }} branches</span>
          <span class="px-2 py-1 rounded bg-muted">{{ summary.media }} images</span>
          <span class="px-2 py-1 rounded bg-muted">upload limit: {{ summary.upload_max }} / post: {{ summary.post_max }}</span>
        </div>
      </div>

      <div class="flex gap-2 border-b border-border">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          type="button"
          @click="activeTab = tab.key"
          :class="[
            'px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors',
            activeTab === tab.key
              ? 'border-primary text-foreground'
              : 'border-transparent text-muted-foreground hover:text-foreground',
          ]"
        >
          {{ tab.label }}
        </button>
      </div>

      <!-- ============================ EXPORT ============================ -->
      <div v-show="activeTab === 'export'" class="bg-card border border-border rounded-xl p-4 sm:p-6 space-y-5">
        <div>
          <h2 class="text-lg font-semibold">Build a migration package</h2>
          <p class="text-sm text-muted-foreground mt-1">
            Produces a .zip holding the data and the image files. Import it on the other site.
          </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <label class="space-y-1">
            <span class="text-xs font-medium text-muted-foreground">Search name or slug</span>
            <input v-model="exportFilters.search" type="text" placeholder="All facilities" :class="inputCls" />
          </label>
          <label class="space-y-1">
            <span class="text-xs font-medium text-muted-foreground">Facility type</span>
            <select v-model="exportFilters.facility_type_id" :class="inputCls">
              <option value="">All types</option>
              <option v-for="o in facilityTypes" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
          </label>
          <label class="space-y-1">
            <span class="text-xs font-medium text-muted-foreground">Governorate</span>
            <select v-model="exportFilters.governorate_id" :class="inputCls">
              <option value="">All governorates</option>
              <option v-for="o in governorates" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
          </label>
          <label class="space-y-1">
            <span class="text-xs font-medium text-muted-foreground">Sales rep</span>
            <select v-model="exportFilters.sales_id" :class="inputCls">
              <option value="">All</option>
              <option v-for="o in salesOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
          </label>
          <label class="space-y-1">
            <span class="text-xs font-medium text-muted-foreground">Created from</span>
            <input v-model="exportFilters.created_from" type="date" :class="inputCls" />
          </label>
          <label class="space-y-1">
            <span class="text-xs font-medium text-muted-foreground">Created to</span>
            <input v-model="exportFilters.created_to" type="date" :class="inputCls" />
          </label>
        </div>

        <div class="flex flex-wrap items-center gap-5 text-sm">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" v-model="includeMedia" />
            Include image files
            <span class="text-xs text-muted-foreground">(off = data only, much smaller)</span>
          </label>
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" v-model="includeOffers" />
            Include offers
          </label>
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" v-model="splitParts" />
            Split into parts
          </label>
          <label v-if="splitParts" class="flex items-center gap-2">
            <span class="text-xs text-muted-foreground">facilities per part</span>
            <input v-model.number="perPart" type="number" min="1" class="w-24 px-2 py-1 rounded border border-input bg-background text-sm" />
          </label>
        </div>

        <div class="flex flex-wrap gap-2">
          <button type="button" @click="loadPlan" :disabled="planLoading" :class="btnSecondary">
            {{ planLoading ? 'Counting…' : 'Check what matches' }}
          </button>
          <a v-if="!splitParts" :href="exportUrl()" :class="btnPrimary">Download package</a>
        </div>

        <div v-if="plan" class="rounded-lg border border-border p-4 space-y-3">
          <p class="text-sm">
            <strong>{{ plan.total }}</strong> facilit{{ plan.total === 1 ? 'y' : 'ies' }} match.
            <template v-if="splitParts">
              That is <strong>{{ plan.parts }}</strong> part{{ plan.parts === 1 ? '' : 's' }}
              of {{ plan.per_part }}.
            </template>
          </p>
          <div v-if="splitParts && plan.parts" class="flex flex-wrap gap-2">
            <a
              v-for="n in plan.parts"
              :key="n"
              :href="exportUrl(n)"
              :class="[btnSecondary, downloadedParts.includes(n) ? 'opacity-60' : '']"
              @click="downloadedParts.push(n)"
            >
              Part {{ n }} / {{ plan.parts }}
            </a>
          </div>
          <p v-if="splitParts && plan.parts" class="text-xs text-muted-foreground">
            Download the parts one at a time. Each is a complete package on its own — import them
            in any order on the other site using <strong>merge</strong> mode.
          </p>
        </div>

        <div v-if="plan === null && planError" class="text-sm text-destructive">{{ planError }}</div>
      </div>

      <!-- ============================ IMPORT ============================ -->
      <div v-show="activeTab === 'import'" class="bg-card border border-border rounded-xl p-4 sm:p-6 space-y-5">
        <div>
          <h2 class="text-lg font-semibold">Restore a migration package</h2>
          <p class="text-sm text-muted-foreground mt-1">
            Upload a .zip built by the other site — or, if it is too big to upload, drop it into
            <code class="px-1 rounded bg-muted">storage/app/facility-migration/</code> over FTP and
            give the filename below.
          </p>
        </div>

        <!-- Template Downloads -->
        <div class="flex flex-wrap gap-3">
          <a
            :href="route('admin.facility.migration.template.example')"
            class="inline-flex items-center gap-2 rounded-md border border-border bg-muted/50 px-4 py-2 text-sm font-medium text-foreground hover:bg-muted transition-colors"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>
            Download Example & Instructions
          </a>
          <a
            :href="route('admin.facility.migration.template.blank')"
            class="inline-flex items-center gap-2 rounded-md border border-border bg-muted/50 px-4 py-2 text-sm font-medium text-foreground hover:bg-muted transition-colors"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><line x1="12" x2="12" y1="18" y2="12"/><line x1="9" x2="15" y1="15" y2="15"/></svg>
            Download Empty Template
          </a>
        </div>

        <!-- Step 1: choose a package -->
        <div v-if="importStep === 'choose'" class="space-y-4">
          <div class="grid gap-4 sm:grid-cols-2">
            <label class="space-y-1">
              <span class="text-xs font-medium text-muted-foreground">Upload package (.zip)</span>
              <input type="file" accept=".zip,.json" @change="onPackageChange" :class="inputCls" />
            </label>
            <label class="space-y-1">
              <span class="text-xs font-medium text-muted-foreground">…or filename already on the server</span>
              <input v-model="serverPath" type="text" placeholder="facility-migration-full-2026-01-01_120000.zip" :class="inputCls" />
            </label>
          </div>

          <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <label class="space-y-1">
              <span class="text-xs font-medium text-muted-foreground">Mode</span>
              <select v-model="importMode" :class="inputCls">
                <option value="merge">Merge — update by slug, insert what is missing</option>
                <option value="fresh">Fresh — delete everything first</option>
              </select>
            </label>
            <label class="space-y-1">
              <span class="text-xs font-medium text-muted-foreground">Facilities per step</span>
              <input v-model.number="chunkSize" type="number" min="1" max="200" :class="inputCls" />
            </label>
            <label class="flex items-end gap-2 pb-2 cursor-pointer text-sm">
              <input type="checkbox" v-model="dryRun" /> Dry run (write nothing)
            </label>
            <label class="flex items-end gap-2 pb-2 cursor-pointer text-sm">
              <input type="checkbox" v-model="skipMedia" /> Skip image files
            </label>
          </div>

          <div v-if="importMode === 'fresh' && !dryRun" class="rounded-lg border border-destructive/40 bg-destructive/10 p-3 text-sm">
            <strong class="text-destructive">Fresh mode deletes every existing facility, branch and image file.</strong>
            Deleted images cannot be recovered by a rollback.
            <label class="mt-2 flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="confirmWipe" /> I understand — wipe and replace.
            </label>
          </div>

          <div v-if="importError" class="text-sm text-destructive">{{ importError }}</div>

          <div class="flex flex-wrap gap-2">
            <button type="button" @click="inspectPackage" :disabled="!hasPackage || busy" :class="btnSecondary">
              {{ busy ? 'Reading…' : 'Inspect package' }}
            </button>
            <button type="button" @click="startImport" :disabled="!canStart || busy" :class="btnPrimary">
              Start import
            </button>
          </div>

          <div v-if="inspection" class="rounded-lg border border-border p-4 text-sm space-y-2">
            <p class="font-medium">Package contents</p>
            <p class="text-muted-foreground text-xs">
              Built {{ inspection.generated_at }} by {{ inspection.source?.app_url || 'unknown site' }} ·
              images bundled: {{ inspection.options?.include_media_files ? 'yes' : 'no' }}
            </p>
            <div class="flex flex-wrap gap-2 text-xs">
              <span v-for="(v, k) in inspection.counts" :key="k" class="px-2 py-1 rounded bg-muted">
                {{ String(k).replace(/_/g, ' ') }}: {{ v }}
              </span>
            </div>
          </div>
        </div>

        <!-- Step 2: running -->
        <div v-if="importStep === 'running'" class="space-y-4">
          <div class="flex items-center justify-between text-sm">
            <span class="font-medium">
              {{ dryRun ? 'Dry run' : 'Importing' }} — {{ progress.processed }} of {{ progress.total }} facilities
            </span>
            <span class="text-muted-foreground">{{ progress.percent }}%</span>
          </div>
          <div class="h-3 rounded-full bg-muted overflow-hidden">
            <div class="h-full bg-primary transition-all duration-300" :style="{ width: progress.percent + '%' }"></div>
          </div>

          <div class="flex flex-wrap gap-2 text-xs">
            <span v-for="(v, k) in progress.stats" :key="k" class="px-2 py-1 rounded bg-muted">
              {{ String(k).replace(/_/g, ' ') }}: {{ v }}
            </span>
          </div>

          <div v-if="progress.errors?.length" class="rounded-lg border border-destructive/40 bg-destructive/10 p-3 text-xs space-y-1">
            <p class="font-semibold text-destructive">{{ progress.errors.length }} facility(ies) failed — the rest continue:</p>
            <p v-for="(e, i) in progress.errors.slice(-5)" :key="i">{{ e.facility }}: {{ e.message }}</p>
          </div>

          <div class="flex gap-2">
            <button v-if="paused" type="button" @click="runLoop" :class="btnPrimary">Resume</button>
            <button v-else type="button" @click="paused = true" :class="btnSecondary">Pause</button>
            <button type="button" @click="cancelImport" :class="btnSecondary">Cancel</button>
          </div>
          <p class="text-xs text-muted-foreground">
            Each step is its own request, so you can pause, or close and reopen this page —
            nothing already imported is lost.
          </p>
        </div>

        <!-- Step 3: done -->
        <div v-if="importStep === 'done'" class="space-y-4">
          <h3 class="text-lg font-semibold">
            {{ result?.dry_run ? 'Dry run complete — nothing was written' : 'Import complete' }}
          </h3>
          <div class="flex flex-wrap gap-2 text-sm">
            <span v-for="(v, k) in result?.stats || {}" :key="k" class="px-2 py-1 rounded bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 font-medium">
              {{ String(k).replace(/_/g, ' ') }}: {{ v }}
            </span>
          </div>

          <details v-if="result?.warnings?.length" class="rounded-lg border border-border p-3 text-xs">
            <summary class="cursor-pointer font-medium">{{ result.warnings.length }} warning(s)</summary>
            <p v-for="(w, i) in result.warnings" :key="i" class="mt-1 text-muted-foreground">{{ w }}</p>
          </details>

          <details v-if="result?.errors?.length" class="rounded-lg border border-destructive/40 p-3 text-xs">
            <summary class="cursor-pointer font-medium text-destructive">{{ result.errors.length }} failure(s)</summary>
            <p v-for="(e, i) in result.errors" :key="i" class="mt-1">{{ e.facility }}: {{ e.message }}</p>
          </details>

          <div class="flex gap-2">
            <Link :href="route('admin.facility.list')" :class="btnPrimary">Go to facilities</Link>
            <button type="button" @click="resetImport" :class="btnSecondary">Import another package</button>
          </div>
        </div>
      </div>
    </div>
  </FacilityLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import FacilityLayout from '../FacilityLayout.vue';
import { Breadcrumb } from '@/Pages/Admin/Layout/Layout.js';
import { computed, ref } from 'vue';

const props = defineProps({
  summary: { type: Object, default: () => ({}) },
  facilityTypes: { type: Array, default: () => [] },
  governorates: { type: Array, default: () => [] },
  salesOptions: { type: Array, default: () => [] },
});

const tabs = [
  { key: 'export', label: 'Export' },
  { key: 'import', label: 'Import' },
];
const activeTab = ref('export');

const inputCls = 'w-full px-2 py-1.5 rounded-md border border-input bg-background text-foreground text-sm focus:outline-none focus:ring-1 focus:ring-primary';
const btnPrimary = 'inline-flex items-center justify-center rounded-md text-sm font-medium bg-primary text-primary-foreground h-9 px-4 disabled:opacity-50 btn-golden cursor-pointer';
const btnSecondary = 'inline-flex items-center justify-center rounded-md text-sm font-medium border border-border bg-background h-9 px-4 hover:bg-muted disabled:opacity-50 cursor-pointer';

/* ------------------------------- export ------------------------------- */

const exportFilters = ref({
  search: '',
  facility_type_id: '',
  governorate_id: '',
  sales_id: '',
  created_from: '',
  created_to: '',
});
const includeMedia = ref(true);
const includeOffers = ref(true);
const splitParts = ref(false);
const perPart = ref(25);
const plan = ref(null);
const planError = ref('');
const planLoading = ref(false);
const downloadedParts = ref([]);

const exportParams = () => {
  const params = new URLSearchParams();
  Object.entries(exportFilters.value).forEach(([k, v]) => {
    if (v !== '' && v !== null && v !== undefined) params.set(k, v);
  });
  params.set('include_media', includeMedia.value ? '1' : '0');
  params.set('include_offers', includeOffers.value ? '1' : '0');
  return params;
};

const exportUrl = (part = null) => {
  const params = exportParams();
  if (part !== null) {
    params.set('part', part);
    params.set('per_part', perPart.value);
  }
  return route('admin.facility.migration.export') + '?' + params.toString();
};

const loadPlan = async () => {
  planLoading.value = true;
  planError.value = '';
  try {
    const params = exportParams();
    params.set('per_part', perPart.value);
    const { data } = await axios.get(route('admin.facility.migration.export.plan') + '?' + params.toString());
    plan.value = data;
    downloadedParts.value = [];
  } catch (e) {
    plan.value = null;
    planError.value = e.response?.data?.message || 'Could not count the facilities.';
  } finally {
    planLoading.value = false;
  }
};

/* ------------------------------- import ------------------------------- */

const packageFile = ref(null);
const serverPath = ref('');
const importMode = ref('merge');
const chunkSize = ref(5);
const dryRun = ref(false);
const skipMedia = ref(false);
const confirmWipe = ref(false);
const busy = ref(false);
const importError = ref('');
const inspection = ref(null);

const importStep = ref('choose');
const token = ref(null);
const paused = ref(false);
const progress = ref({ processed: 0, total: 0, percent: 0, stats: {}, errors: [] });
const result = ref(null);

const hasPackage = computed(() => !!packageFile.value || serverPath.value.trim() !== '');
const canStart = computed(
  () => hasPackage.value && (importMode.value !== 'fresh' || dryRun.value || confirmWipe.value)
);

const onPackageChange = (e) => {
  packageFile.value = e.target.files?.[0] || null;
  inspection.value = null;
  importError.value = '';
};

// The package travels as multipart when uploaded, or by name when it was put on
// the server out of band — both endpoints accept either.
const packageForm = (extra = {}) => {
  const fd = new FormData();
  if (packageFile.value) fd.append('package', packageFile.value);
  else fd.append('server_path', serverPath.value.trim());
  Object.entries(extra).forEach(([k, v]) => fd.append(k, v));
  return fd;
};

const inspectPackage = async () => {
  busy.value = true;
  importError.value = '';
  try {
    const { data } = await axios.post(route('admin.facility.migration.inspect'), packageForm());
    inspection.value = data;
  } catch (e) {
    inspection.value = null;
    importError.value = e.response?.data?.message || 'Could not read that package.';
  } finally {
    busy.value = false;
  }
};

const startImport = async () => {
  busy.value = true;
  importError.value = '';
  try {
    const { data } = await axios.post(
      route('admin.facility.migration.begin'),
      packageForm({
        mode: importMode.value,
        dry_run: dryRun.value ? 1 : 0,
        skip_media: skipMedia.value ? 1 : 0,
        confirm_wipe: confirmWipe.value ? 1 : 0,
      })
    );
    token.value = data.token;
    progress.value = { processed: 0, total: data.total, percent: 0, stats: {}, errors: [] };
    importStep.value = 'running';
    paused.value = false;
    runLoop();
  } catch (e) {
    importError.value = e.response?.data?.message || 'Could not open the import session.';
  } finally {
    busy.value = false;
  }
};

// One request per chunk, chained — never a single long-running call.
const runLoop = async () => {
  paused.value = false;
  while (!paused.value && token.value) {
    let data;
    try {
      ({ data } = await axios.post(route('admin.facility.migration.step'), {
        token: token.value,
        limit: chunkSize.value,
      }));
    } catch (e) {
      importError.value = e.response?.data?.message || 'A step failed.';
      paused.value = true;
      return;
    }
    progress.value = data;
    if (data.done) {
      await finishImport();
      return;
    }
  }
};

const finishImport = async () => {
  try {
    const { data } = await axios.post(route('admin.facility.migration.finish'), { token: token.value });
    result.value = data;
  } catch (e) {
    importError.value = e.response?.data?.message || 'Could not close the session.';
  } finally {
    token.value = null;
    importStep.value = 'done';
  }
};

const cancelImport = async () => {
  paused.value = true;
  if (token.value) {
    try {
      await axios.post(route('admin.facility.migration.cancel'), { token: token.value });
    } catch (e) {
      // The session is disposable — a failed cleanup is not worth blocking on.
    }
  }
  token.value = null;
  resetImport();
};

const resetImport = () => {
  importStep.value = 'choose';
  packageFile.value = null;
  serverPath.value = '';
  inspection.value = null;
  result.value = null;
  importError.value = '';
  confirmWipe.value = false;
  progress.value = { processed: 0, total: 0, percent: 0, stats: {}, errors: [] };
};
</script>
