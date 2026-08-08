<template>
  <MemberPaymentLayout>
    <div class="space-y-3 p-3 sm:p-4 lg:p-6 w-full max-w-full">
      <Breadcrumb
        title="Import Payments"
        :breadcrumbs="[
          { label: 'Member Payments', link: route('admin.member-payment.list'), active: false },
          { label: 'Import', link: '#', active: true },
        ]"
      />

      <!-- Step 1 — Upload -->
      <div v-if="step === 'upload'" class="bg-card border border-border rounded-xl p-6 space-y-4 max-w-2xl mx-auto">
        <h2 class="text-lg font-semibold">{{ t.import_payment_upload_title || 'Upload payment file' }}</h2>
        <p class="text-sm text-muted-foreground">
          {{ t.import_payment_upload_description || 'Upload an XLSX file exported from "Export to Pay". The file must contain Name, Email, Membership #, Amount Paid, and Type columns.' }}
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
            :href="route('admin.member-payment.list')"
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
            <span v-if="loading">{{ t.import_payment_processing || 'Processing\u2026' }}</span>
            <span v-else>{{ t.import_payment_preview_title || 'Preview' }}</span>
          </button>
        </div>
      </div>

      <!-- Step 2 — Preview / Edit -->
      <div v-if="step === 'preview'" class="space-y-4">
        <!-- Summary -->
        <div class="bg-card border border-border rounded-xl p-4 flex flex-wrap items-center gap-4">
          <div class="flex items-center gap-2">
            <span class="px-2.5 py-1 rounded text-xs bg-emerald-600 text-white font-semibold">{{ counts.matched }} {{ t.import_payment_member_matched || 'Matched' }}</span>
            <span v-if="counts.unmatched" class="px-2.5 py-1 rounded text-xs bg-amber-500 text-white font-semibold">{{ counts.unmatched }} {{ t.import_payment_member_unmatched || 'Unmatched' }}</span>
            <span v-if="counts.error" class="px-2.5 py-1 rounded text-xs bg-red-600 text-white font-semibold">{{ counts.error }} {{ t.import_payment_errors || 'errors' }}</span>
          </div>
        </div>

        <!-- Default values -->
        <div class="bg-card border border-border rounded-xl p-4 grid grid-cols-1 sm:grid-cols-4 gap-3">
          <div>
            <label class="text-xs font-semibold text-muted-foreground block mb-1">Months Paid</label>
            <input type="number" min="1" v-model.number="defaultMonths" class="w-full h-8 px-2 text-xs rounded border border-input bg-background text-foreground focus:outline-none focus:ring-1 focus:ring-primary" />
          </div>
          <div>
            <label class="text-xs font-semibold text-muted-foreground block mb-1">From Date</label>
            <input type="date" v-model="defaultFromDate" class="w-full h-8 px-2 text-xs rounded border border-input bg-background text-foreground focus:outline-none focus:ring-1 focus:ring-primary" />
          </div>
          <div>
            <label class="text-xs font-semibold text-muted-foreground block mb-1">To Date</label>
            <input type="date" v-model="defaultToDate" class="w-full h-8 px-2 text-xs rounded border border-input bg-background text-foreground focus:outline-none focus:ring-1 focus:ring-primary" />
          </div>
          <div class="flex items-end">
            <button type="button" @click="applyDefaults" class="w-full h-8 rounded-md text-xs font-medium bg-primary text-primary-foreground px-3 hover:bg-primary/90">
              Apply to all
            </button>
          </div>
        </div>

        <!-- Editable rows -->
        <div class="bg-card text-card-foreground border border-border rounded-xl overflow-x-auto">
          <table class="text-xs text-foreground" style="min-width: 900px; width: 100%;">
            <thead class="bg-muted/50">
              <tr class="border-b border-border">
                <th class="px-2 py-2 text-left font-semibold w-12">#</th>
                <th class="px-2 py-2 text-left font-semibold">Name</th>
                <th class="px-2 py-2 text-left font-semibold">Membership #</th>
                <th class="px-2 py-2 text-left font-semibold">Amount</th>
                <th class="px-2 py-2 text-left font-semibold">Type</th>
                <th class="px-2 py-2 text-left font-semibold w-28">Months Paid</th>
                <th class="px-2 py-2 text-left font-semibold w-36">From Date</th>
                <th class="px-2 py-2 text-left font-semibold w-36">To Date</th>
                <th class="px-2 py-2 text-left font-semibold w-20">Status</th>
                <th class="px-2 py-2 text-left font-semibold w-16"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, i) in rows" :key="i" class="border-b border-border" :class="rowBgClass(row.status)">
                <td class="px-2 py-1.5 text-center font-mono">{{ i + 1 }}</td>
                <td class="px-2 py-1">{{ row.parsed.name }}</td>
                <td class="px-2 py-1 font-mono">{{ row.parsed.membership_number || (row.match?.membership_number || '—') }}</td>
                <td class="px-2 py-1">
                  <input type="number" min="0" step="0.01" v-model="row.parsed.amount" :disabled="row.parsed.type === 'free'" :class="inputCls(row.errors?.amount)" />
                </td>
                <td class="px-2 py-1">
                  <select v-model="row.parsed.type" :class="inputCls(row.errors?.type)" class="w-28">
                    <option value="commission">Commission</option>
                    <option value="profit">Profit</option>
                    <option value="free">Free</option>
                  </select>
                </td>
                <td class="px-2 py-1">
                  <input type="number" min="1" v-model.number="row.months_paid" @input="onMonthsPaidChange(row)" :class="inputCls()" />
                </td>
                <td class="px-2 py-1">
                  <input type="date" v-model="row.from_date" @input="onFromDateChange(row)" :class="inputCls()" />
                </td>
                <td class="px-2 py-1">
                  <input type="date" v-model="row.to_date" :class="inputCls()" />
                </td>
                <td class="px-2 py-1 text-center">
                  <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase" :class="statusBadgeClass(row.status)">
                    {{ row.status }}
                  </span>
                </td>
                <td class="px-2 py-1 text-center">
                  <button type="button" @click="rows.splice(i, 1)" class="text-destructive hover:underline text-[11px]" title="Skip this row">
                    skip
                  </button>
                </td>
              </tr>
              <tr v-if="rowErrors.length" class="bg-red-500/10">
                <td colspan="10" class="px-2 py-1.5 text-[11px] text-destructive">
                  <div v-for="(msg, ki) in rowErrors" :key="ki" class="flex items-start gap-1">
                    <span>⚠</span>
                    <span><strong>Row {{ msg.index + 1 }}:</strong> {{ msg.message }}</span>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Action bar -->
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
          <div v-if="validRowCount === 0" class="text-xs text-muted-foreground">
            No valid rows to import. Set default dates/months and apply them.
          </div>
          <button
            type="button"
            @click="commit"
            :disabled="loading || validRowCount === 0"
            class="ml-auto inline-flex items-center justify-center rounded-md text-sm font-medium bg-primary text-primary-foreground h-9 px-4 disabled:opacity-50 btn-golden"
          >
            <span v-if="loading">{{ t.import_payment_processing || 'Processing\u2026' }}</span>
            <span v-else>{{ t.import_payment_confirm_button || 'Confirm & Import' }} ({{ validRowCount }})</span>
          </button>
        </div>
      </div>

      <!-- Step 3 — Done -->
      <div v-if="step === 'done'" class="bg-card border border-border rounded-xl p-6 max-w-2xl mx-auto text-center space-y-4">
        <h2 class="text-lg font-semibold">{{ t.import_payment_complete_title || 'Import Complete' }}</h2>
        <div class="text-sm">
          <span class="px-2 py-1 rounded bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 font-semibold">{{ result?.created || 0 }} {{ t.import_payment_created || 'created' }}</span>
          <span v-if="result?.errors?.length" class="px-2 py-1 rounded bg-destructive/15 text-destructive font-semibold">{{ result.errors.length }} {{ t.import_payment_errors || 'errors' }}</span>
        </div>
        <div class="flex justify-center gap-2">
          <Link :href="route('admin.member-payment.list')" class="inline-flex items-center justify-center rounded-md text-sm font-medium bg-primary text-primary-foreground h-9 px-4 btn-golden">
            {{ t.import_payment_go_to_payments || 'Go to payments' }}
          </Link>
          <button @click="reset(); step = 'upload'" class="inline-flex items-center justify-center rounded-md text-sm font-medium border bg-background h-9 px-4 hover:bg-muted">
            {{ t.import_payment_import_another || 'Import another file' }}
          </button>
        </div>
      </div>
    </div>
  </MemberPaymentLayout>
</template>

<script setup>
import { Link, usePage } from "@inertiajs/vue3";
import MemberPaymentLayout from "../MemberPaymentLayout.vue";
import { Breadcrumb } from "@/Pages/Admin/Layout/Layout.js";
import axios from "axios";
import { computed, ref } from "vue";

const page = usePage();
const t = computed(() => page.props.translations?.admin?.member_payment || {});

const file = ref(null);
const step = ref('upload');
const rows = ref([]);
const loading = ref(false);
const error = ref('');
const result = ref(null);
const defaultMonths = ref(1);
const defaultFromDate = ref('');
const defaultToDate = ref('');

const counts = computed(() => {
  const c = { matched: 0, unmatched: 0, error: 0 };
  for (const r of rows.value) c[r.status] = (c[r.status] || 0) + 1;
  return c;
});

const validRowCount = computed(() => rows.value.filter(r => r.status !== 'error' && r.months_paid > 0 && r.from_date && r.to_date).length);

const rowErrors = computed(() => {
  const errs = [];
  for (const r of rows.value) {
    if (r.errors && Object.keys(r.errors).length) {
      errs.push({ index: r.index, message: Object.values(r.errors).join('; ') });
    }
  }
  return errs;
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

const addMonths = (dateStr, months) => {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  d.setMonth(d.getMonth() + months);
  return d.toISOString().split('T')[0];
};

const autoCalcDates = (row) => {
  const months = parseInt(row.months_paid) || 0;
  if (months <= 0) return;
  const match = row.match;
  if (!match) return;
  let fromDate;
  if (match.latest_payment_to_date) {
    const last = new Date(match.latest_payment_to_date);
    last.setDate(last.getDate() + 1);
    fromDate = last.toISOString().split('T')[0];
  } else if (match.registration_date) {
    fromDate = match.registration_date;
  } else {
    return;
  }
  row.from_date = fromDate;
  row.to_date = addMonths(fromDate, months);
};

const onMonthsPaidChange = (row) => {
  autoCalcDates(row);
};

const onFromDateChange = (row) => {
  const months = parseInt(row.months_paid) || 0;
  if (months > 0 && row.from_date) {
    row.to_date = addMonths(row.from_date, months);
  }
};

const applyDefaults = () => {
  for (const row of rows.value) {
    if (defaultMonths.value > 0) row.months_paid = defaultMonths.value;
    if (defaultFromDate.value) row.from_date = defaultFromDate.value;
    if (defaultToDate.value) row.to_date = defaultToDate.value;
    if (defaultMonths.value > 0 && !defaultFromDate.value && !defaultToDate.value) {
      autoCalcDates(row);
    } else if (defaultMonths.value > 0 && defaultFromDate.value && !defaultToDate.value) {
      row.to_date = addMonths(defaultFromDate.value, defaultMonths.value);
    }
  }
};

const uploadFile = async () => {
  if (!file.value) return;
  error.value = '';
  loading.value = true;
  try {
    const fd = new FormData();
    fd.append('file', file.value);
    const { data } = await axios.post(route('admin.member-payment.import.preview'), fd);
    rows.value = (data.rows || []).map(r => {
      const row = {
        ...r,
        months_paid: defaultMonths.value > 0 ? defaultMonths.value : 1,
        from_date: '',
        to_date: '',
      };
      autoCalcDates(row);
      return row;
    });
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
      rows: rows.value
        .filter(r => r.status !== 'error' && r.match && r.months_paid > 0 && r.from_date && r.to_date)
        .map(r => ({
          membership_id: r.match.membership_id,
          amount: r.parsed.type === 'free' ? 0 : (parseFloat(r.parsed.amount) || 0),
          type: r.parsed.type || 'commission',
          months_paid: r.months_paid,
          from_date: r.from_date,
          to_date: r.to_date,
        })),
    };
    const { data } = await axios.post(route('admin.member-payment.import.commit'), payload);
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

const rowBgClass = (status) => ({
  'bg-emerald-500/15': status === 'matched',
  'bg-amber-500/15': status === 'unmatched',
  'bg-red-500/20': status === 'error',
});

const statusBadgeClass = (status) => ({
  'bg-emerald-600 text-white': status === 'matched',
  'bg-amber-500 text-white': status === 'unmatched',
  'bg-red-600 text-white': status === 'error',
}[status]);

const inputCls = (err) => [
  'w-full px-1.5 py-1 rounded border bg-background text-foreground placeholder:text-muted-foreground text-xs focus:outline-none focus:ring-1',
  err ? 'border-destructive focus:ring-destructive' : 'border-input focus:ring-primary',
];
</script>
