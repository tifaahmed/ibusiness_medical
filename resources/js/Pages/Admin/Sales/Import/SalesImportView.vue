<template>
  <SalesLayout>
    <div class="space-y-3 p-3 sm:p-4 lg:p-6 w-full max-w-full">
      <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-2 sm:gap-3 md:gap-4 rounded-xl border border-border py-2 sm:py-3 md:py-4 shadow-sm overflow-hidden w-full max-w-full">
        <div data-slot="card-header" class="flex flex-row items-center justify-between py-2 px-3 sm:px-4 md:px-6 w-full overflow-hidden gap-2 sm:gap-4">
          <div data-slot="card-title" class="leading-none font-semibold min-w-0 flex-1">
            <div class="title-golden min-w-0 flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon sm:w-6 sm:h-6 flex-shrink-0">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="17 8 12 3 7 8"></polyline>
                <line x1="12" y1="3" x2="12" y2="15"></line>
              </svg>
              <span class="text-sm sm:text-base truncate block min-w-0">{{ t.sales?.import_page_title || 'Import Sales' }}</span>
            </div>
          </div>
          <Link
            :href="route('admin.sales.list')"
            data-slot="button"
            class="inline-flex items-center cursor-pointer justify-center gap-1.5 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium border bg-background hover:bg-muted h-8 sm:h-9 px-2 sm:px-3 md:px-4 py-2"
          >
            <span class="hidden sm:inline">{{ t.sales?.go_to_sales || 'Go to sales' }}</span>
            <span class="sm:hidden">{{ t.common?.back || 'Back' }}</span>
          </Link>
        </div>
      </div>

      <!-- Step 1 — Upload -->
      <div v-if="step === 'upload'" class="bg-card border border-border rounded-xl p-6 space-y-5 max-w-3xl mx-auto">
        <div>
          <h2 class="text-lg font-semibold mb-1">{{ t.sales?.choose_file || 'Choose a file to import' }}</h2>
          <p class="text-sm text-muted-foreground">
            {{ t.sales?.file_hint || 'Accepted formats: .xlsx, .xls, .csv (max 5MB). Use the export button on the Sales page or the template above as a starting point.' }}
          </p>
        </div>

        <input
          type="file"
          accept=".csv,.xlsx,.xls,.txt"
          @change="onFileChange"
          class="block w-full text-sm border border-border rounded-md p-2 file:mr-3 file:px-3 file:py-1.5 file:rounded file:border-0 file:bg-primary file:text-primary-foreground"
        />
        <div v-if="error" class="text-sm text-destructive">{{ error }}</div>

        <!-- Template downloads -->
        <div class="flex flex-wrap gap-2">
          <a
            :href="route('admin.sales.import.template')"
            class="inline-flex items-center gap-2 rounded-md text-xs sm:text-sm font-medium border bg-background h-9 px-3 sm:px-4 hover:bg-muted"
            :title="t.sales?.download_template_title || 'Blank file to fill in with your own sales data'"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
            {{ t.sales?.download_template || 'Download Template' }}
          </a>
          <a
            :href="route('admin.sales.import.template', { example: 1 })"
            class="inline-flex items-center gap-2 rounded-md text-xs sm:text-sm font-medium border bg-background h-9 px-3 sm:px-4 hover:bg-muted"
            :title="t.sales?.download_example_title || 'Example file showing how to fill the data'"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
            {{ t.sales?.download_example || 'Download Example' }}
          </a>
        </div>

        <!-- Strategy selection -->
        <div>
          <label class="text-sm font-semibold block mb-2">{{ t.sales?.strategy_label || 'Import strategy' }}</label>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <label
              v-for="s in strategies"
              :key="s.value"
              class="flex gap-2 p-3 rounded-md border cursor-pointer transition-colors"
              :class="strategy === s.value ? 'border-primary bg-primary/5 ring-1 ring-primary/40' : 'border-border hover:bg-muted'"
            >
              <input type="radio" :value="s.value" v-model="strategy" class="mt-0.5" />
              <span class="min-w-0">
                <span class="block text-sm font-semibold">{{ s.label }}</span>
                <span class="block text-xs text-muted-foreground">{{ s.desc }}</span>
              </span>
            </label>
          </div>
        </div>

        <div v-if="strategy === 'delete_all_then_add'" class="bg-destructive/10 text-destructive border border-destructive/30 rounded-md p-3 text-sm">
          <strong>{{ t.common?.warning || 'Warning:' }}</strong> {{ t.sales?.delete_all_warning || 'Warning: this deletes ALL existing sales rows before importing. This cannot be undone.' }}
        </div>

        <div class="flex gap-2 justify-end">
          <button
            type="button"
            @click="uploadFile"
            :disabled="!file || loading"
            class="inline-flex items-center justify-center rounded-md text-sm font-medium bg-primary text-primary-foreground h-9 px-4 disabled:opacity-50 btn-golden"
          >
            <span v-if="loading">{{ t.sales?.parsing || 'Parsing…' }}</span>
            <span v-else>{{ t.sales?.preview_btn || 'Preview' }}</span>
          </button>
        </div>
      </div>

      <!-- Step 2 — Preview / Edit -->
      <div v-if="step === 'preview'" class="space-y-4">
        <div class="bg-card border border-border rounded-xl p-4 flex flex-wrap items-center gap-4">
          <div class="flex items-center gap-2">
            <span class="px-2.5 py-1 rounded text-xs bg-emerald-600 text-white font-semibold">{{ counts.new }} {{ t.sales?.row_status_new || 'new' }}</span>
            <span class="px-2.5 py-1 rounded text-xs bg-slate-500 text-white font-semibold">{{ counts.exists }} {{ t.sales?.row_status_exists || 'exists' }}</span>
            <span v-if="counts.error" class="px-2.5 py-1 rounded text-xs bg-red-600 text-white font-semibold">{{ counts.error }} {{ t.common?.error || 'error' }}</span>
          </div>
          <div class="ml-auto text-sm text-muted-foreground">
            {{ strategyLabel }}
          </div>
        </div>

        <div v-if="strategy === 'delete_all_then_add'" class="bg-destructive/10 text-destructive border border-destructive/30 rounded-md p-3 text-sm">
          <strong>{{ t.common?.warning || 'Warning:' }}</strong> {{ t.sales?.delete_all_warning || 'Warning: this deletes ALL existing sales rows before importing. This cannot be undone.' }}
        </div>

        <div class="bg-card border border-border rounded-xl p-4 text-sm text-muted-foreground">
          {{ t.sales?.preview_hint || 'Edit the names below or remove rows before confirming. Nothing is saved until you click "Confirm & import".' }}
        </div>

        <div class="bg-card text-card-foreground border border-border rounded-xl overflow-x-auto">
          <table class="text-xs text-foreground" style="min-width: 900px; width: 100%;">
            <thead class="bg-muted/50">
              <tr class="border-b border-border">
                <th class="px-2 py-2 text-left font-semibold w-12">{{ t.sales?.col_index || '#' }}</th>
                <th class="px-2 py-2 text-left font-semibold w-16">{{ t.sales?.col_id || 'ID' }}</th>
                <th class="px-2 py-2 text-left font-semibold w-28">{{ t.sales?.col_status || 'Status' }}</th>
                <th class="px-2 py-2 text-left font-semibold w-56">{{ t.sales?.col_name_en || 'Name' }}</th>
                <th class="px-2 py-2 text-left font-semibold w-56">{{ t.sales?.col_name_ar || 'Name (AR)' }}</th>
                <th class="px-2 py-2 text-left font-semibold">{{ t.sales?.col_image || 'Image URL' }}</th>
                <th class="px-2 py-2 text-center font-semibold w-16">{{ t.sales?.col_created_by || 'Created by' }}</th>
                <th class="px-2 py-2 text-center font-semibold w-16"></th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(row, i) in rows"
                :key="i"
                class="border-b border-border"
                :class="row.status === 'new' ? 'bg-emerald-500/5' : 'bg-muted/20'"
              >
                <td class="px-2 py-1.5 text-center font-mono">{{ i + 1 }}</td>
                <td class="px-2 py-1.5 font-mono text-center">{{ row.id !== null && row.id !== '' ? row.id : '—' }}</td>
                <td class="px-2 py-1.5">
                  <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase" :class="statusBadgeClass(row.status)">
                    {{ row.status === 'new' ? (t.sales?.row_status_new || 'new') : (t.sales?.row_status_exists || 'exists') }}
                  </span>
                </td>
                <td class="px-2 py-1"><input v-model="row.name_en" :class="inputCls(row.errors?.name_en || row.errors?.name)" :placeholder="t.sales?.col_name_en || 'Name'" /></td>
                <td class="px-2 py-1"><input v-model="row.name_ar" :class="inputCls(row.errors?.name_ar || row.errors?.name)" :placeholder="t.sales?.col_name_ar || 'Name (AR)'" /></td>
                <td class="px-2 py-1">
                  <input v-model="row.image_url" :class="inputCls()" dir="ltr" :placeholder="'https://…'" />
                </td>
                <td class="px-2 py-1 text-center font-mono">{{ row.created_by !== null && row.created_by !== '' ? row.created_by : '—' }}</td>
                <td class="px-2 py-1 text-center">
                  <button type="button" @click="rows.splice(i, 1)" class="text-destructive hover:underline text-[11px]" :title="t.sales?.skip || 'skip'">
                    {{ t.sales?.skip || 'skip' }}
                  </button>
                </td>
              </tr>
              <tr v-if="rowsWithErrors.length">
                <td colspan="8" class="px-3 py-2 text-[11px] text-destructive">
                  {{ rowsWithErrors.length }} {{ t.sales?.rows_with_errors || 'row(s) have errors. Fix or skip them before confirming.' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="sticky bottom-0 z-10 bg-card border border-border rounded-lg p-3 flex flex-wrap items-center gap-3">
          <button
            type="button"
            @click="reset()"
            class="inline-flex items-center justify-center rounded-md text-sm font-medium border bg-background h-9 px-4 hover:bg-muted"
          >
            {{ t.sales?.back || 'Back' }}
          </button>
          <button
            type="button"
            @click="commit"
            :disabled="loading || rows.length === 0"
            class="ml-auto inline-flex items-center justify-center rounded-md text-sm font-medium bg-primary text-primary-foreground h-9 px-4 disabled:opacity-50 btn-golden"
          >
            <span v-if="loading">{{ t.sales?.saving || 'Saving…' }}</span>
            <span v-else>{{ t.sales?.confirm_import || 'Confirm & import' }}</span>
          </button>
        </div>
      </div>

      <!-- Step 3 — Done -->
      <div v-if="step === 'done'" class="bg-card border border-border rounded-xl p-6 max-w-2xl mx-auto text-center space-y-4">
        <h2 class="text-lg font-semibold">{{ t.sales?.import_complete || 'Import complete' }}</h2>
        <div class="text-sm">
          <span class="px-2 py-1 rounded bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 font-semibold mr-2">{{ result?.created || 0 }} {{ t.sales?.created || 'created' }}</span>
          <span class="px-2 py-1 rounded bg-amber-500/15 text-amber-700 dark:text-amber-300 font-semibold mr-2">{{ result?.updated || 0 }} {{ t.sales?.updated || 'updated' }}</span>
          <span class="px-2 py-1 rounded bg-slate-500/15 text-slate-700 dark:text-slate-300 font-semibold mr-2">{{ result?.skipped || 0 }} {{ t.sales?.skipped || 'skipped' }}</span>
          <span v-if="result?.cleared" class="px-2 py-1 rounded bg-destructive/15 text-destructive font-semibold">{{ result.cleared }} {{ t.sales?.cleared || 'cleared' }}</span>
        </div>
        <div v-if="result?.errors && result.errors.length" class="text-left max-h-48 overflow-y-auto bg-destructive/5 border border-destructive/20 rounded-md p-3 text-xs text-destructive">
          <div v-for="(e, i) in result.errors" :key="i" class="mb-1">
            <strong>Row {{ e.index ?? i + 1 }}</strong> ({{ e.name || '—' }}): {{ e.message }}
          </div>
        </div>
        <div class="flex justify-center gap-2">
          <Link :href="route('admin.sales.list')" class="inline-flex items-center justify-center rounded-md text-sm font-medium bg-primary text-primary-foreground h-9 px-4 btn-golden">
            {{ t.sales?.go_to_sales || 'Go to sales' }}
          </Link>
          <button @click="reset()" class="inline-flex items-center justify-center rounded-md text-sm font-medium border bg-background h-9 px-4 hover:bg-muted">
            {{ t.sales?.import_another || 'Import another file' }}
          </button>
        </div>
      </div>
    </div>
  </SalesLayout>
</template>

<script setup>
import { Link, usePage } from "@inertiajs/vue3";
import SalesLayout from "../SalesLayout.vue";
import { computed, ref } from "vue";

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const strategies = computed(() => {
  const s = t.value.sales || {};
  return [
    { value: 'update', label: s.strategy_update || 'Update', desc: s.strategy_update_desc || 'Rows whose id already exists are updated. Unknown ids are added as new.' },
    { value: 'create', label: s.strategy_create || 'Create', desc: s.strategy_create_desc || 'Every row is added as a brand-new sales record (new auto ids).' },
    { value: 'delete_all_then_add', label: s.strategy_delete_all || 'Delete all, then add', desc: s.strategy_delete_all_desc || 'All existing sales are deleted first, then every row is added with its exact id (full restore).' },
    { value: 'add_only', label: s.strategy_add_only || 'Add only', desc: s.strategy_add_only_desc || 'Only rows whose id does not exist yet are added. Existing rows are skipped.' },
  ];
});

const strategyLabel = computed(() => strategies.value.find(s => s.value === strategy.value)?.label || '');

const file = ref(null);
const step = ref('upload');
const rows = ref([]);
const strategy = ref('update');
const loading = ref(false);
const error = ref('');
const result = ref(null);

const counts = computed(() => {
  const c = { new: 0, exists: 0, error: 0 };
  for (const r of rows.value) {
    c[r.status] = (c[r.status] || 0) + 1;
    if (r.errors && Object.keys(r.errors).length) c.error++;
  }
  return c;
});

const rowsWithErrors = computed(() => rows.value.filter(r => r.errors && Object.keys(r.errors).length));

const onFileChange = (e) => {
  file.value = e.target.files?.[0] || null;
  error.value = '';
};

const reset = () => {
  file.value = null;
  rows.value = [];
  error.value = '';
  result.value = null;
  step.value = 'upload';
};

const uploadFile = async () => {
  if (!file.value) return;
  error.value = '';
  loading.value = true;
  try {
    const fd = new FormData();
    fd.append('file', file.value);
    const { data } = await axios.post(route('admin.sales.import.preview'), fd);
    rows.value = (data.rows || []).map(r => ({
      ...r,
      name_ar: r.name_ar ?? '',
      name_en: r.name_en ?? '',
      image_url: r.image_url ?? '',
    }));
    step.value = 'preview';
  } catch (e) {
    error.value = e.response?.data?.message || (t.value.sales?.failed_to_parse || 'Failed to parse the file. Make sure it is a valid Excel/CSV file with the "#" and "Name" columns.');
  } finally {
    loading.value = false;
  }
};

const commit = async () => {
  loading.value = true;
  try {
    const payload = {
      strategy: strategy.value,
      rows: rows.value.map((r, i) => ({
        index: r.index ?? i,
        id: r.id !== null && r.id !== '' ? Number(r.id) : null,
        name_ar: r.name_ar || null,
        name_en: r.name_en || null,
        image_url: r.image_url || null,
        created_by: r.created_by !== null && r.created_by !== '' ? Number(r.created_by) : null,
      })),
    };
    const { data } = await axios.post(route('admin.sales.import.commit'), payload);
    result.value = data;
    step.value = 'done';
  } catch (e) {
    const msg = e.response?.data?.message || (t.value.sales?.import_failed || 'Import failed.');
    const errs = e.response?.data?.errors;
    error.value = errs ? `${msg} ${JSON.stringify(errs)}` : msg;
    alert(error.value);
  } finally {
    loading.value = false;
  }
};

const statusBadgeClass = (status) => ({
  'bg-emerald-600 text-white': status === 'new',
  'bg-slate-500 text-white': status === 'exists',
}[status] || 'bg-slate-500 text-white');

const inputCls = (err) => [
  'w-full px-1.5 py-1 rounded border bg-background text-foreground placeholder:text-muted-foreground text-xs focus:outline-none focus:ring-1',
  err ? 'border-destructive focus:ring-destructive' : 'border-input focus:ring-primary',
];
</script>
