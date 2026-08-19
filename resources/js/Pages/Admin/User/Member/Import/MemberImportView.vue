<template>
  <MemberLayout>
    <div class="space-y-3 p-3 sm:p-4 lg:p-6 w-full max-w-full">
      <Breadcrumb
        title="Import Members"
        :breadcrumbs="[
          { label: 'Members', link: route('admin.user.membership.list'), active: false },
          { label: 'Import', link: '#', active: true },
        ]"
      />

      <!-- Step 1 — Upload -->
      <div v-if="step === 'upload'" class="space-y-4">
        <!-- Filter panel -->
        <div class="bg-card border border-border rounded-xl p-4">
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold">Export Members</h3>
            <a
              :href="exportUrl"
              class="inline-flex items-center gap-2 rounded-md text-xs sm:text-sm font-medium bg-primary text-primary-foreground h-9 px-4 btn-golden"
              title="Export filtered members in import-compatible format (can be re-imported later)"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
              Download Export
            </a>
          </div>
          <UserMembershipListFilterContent
            :initial-filters="{}"
            @filter-change="onFilterChange"
          />
        </div>

        <!-- Upload panel -->
        <div class="bg-card border border-border rounded-xl p-6 space-y-4 max-w-2xl mx-auto">
          <h2 class="text-lg font-semibold">Upload members file</h2>
          <p class="text-sm text-muted-foreground">
            Upload a CSV or XLSX file. The expected format matches the export — the same column headers
            (<code>Name, Email, Phone, Membership #, Status, Visibility, Job title, Company, Company id, Partner, Partner id, Registration date, Expiration date</code>).
            Company/Partner can be matched by name or numeric ID (ID takes priority if both are set).
            Title and filter banners at the top are skipped automatically.
          </p>

          <div class="flex flex-wrap gap-2">
            <a
              :href="route('admin.user.membership.import.template')"
              class="inline-flex items-center gap-2 rounded-md text-xs sm:text-sm font-medium border bg-background h-9 px-3 sm:px-4 hover:bg-muted"
              title="Blank file to fill in with your own member data"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
              Download Template
            </a>
            <a
              :href="route('admin.user.membership.import.template', { example: 1 })"
              class="inline-flex items-center gap-2 rounded-md text-xs sm:text-sm font-medium border bg-background h-9 px-3 sm:px-4 hover:bg-muted"
              title="Example file showing how to fill the data (includes available relationships & payment types)"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
              Download Example
            </a>
          </div>

          <input
            type="file"
            accept=".csv,.xlsx,.xls,.txt"
            @change="onFileChange"
            class="block w-full text-sm border border-border rounded-md p-2 file:mr-3 file:px-3 file:py-1.5 file:rounded file:border-0 file:bg-primary file:text-primary-foreground"
          />
          <div v-if="error" class="text-sm text-destructive">{{ error }}</div>
          <div class="flex gap-2 justify-end">
            <Link
              :href="route('admin.user.membership.list')"
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
      </div>

      <!-- Step 2 — Preview / Edit -->
      <div v-if="step === 'preview'" class="space-y-4">
        <!-- Summary -->
        <div class="bg-card border border-border rounded-xl p-4 flex flex-wrap items-center gap-4">
          <div class="flex items-center gap-2">
            <span class="px-2.5 py-1 rounded text-xs bg-emerald-600 text-white font-semibold">{{ counts.new }} NEW</span>
            <span class="px-2.5 py-1 rounded text-xs bg-amber-500 text-white font-semibold">{{ counts.update }} UPDATE</span>
            <span class="px-2.5 py-1 rounded text-xs bg-slate-500 text-white font-semibold">{{ counts.unchanged }} UNCHANGED</span>
            <span v-if="counts.error" class="px-2.5 py-1 rounded text-xs bg-red-600 text-white font-semibold">{{ counts.error }} ERROR</span>
            <span v-if="totalFamilies" class="px-2.5 py-1 rounded text-xs bg-violet-600 text-white font-semibold">{{ totalFamilies }} family</span>
            <span v-if="totalPayments" class="px-2.5 py-1 rounded text-xs bg-emerald-600 text-white font-semibold">{{ totalPayments }} payments</span>
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
          <strong>Warning:</strong> "Clear table" soft-deletes every existing member (sent to trash, can be restored). Imported rows are then added as brand-new members.
        </div>

        <!-- Editable rows -->
        <div class="bg-card text-card-foreground border border-border rounded-xl overflow-x-auto">
          <table class="text-xs text-foreground" style="min-width: 1700px; width: 100%;">
            <thead class="bg-muted/50">
              <tr class="border-b border-border">
                <th class="px-2 py-2 text-left font-semibold w-12 sticky left-0 bg-muted/80 backdrop-blur z-10">#</th>
                <th class="px-2 py-2 text-left font-semibold w-16">Avatar</th>
                <th class="px-2 py-2 text-left font-semibold w-24">Status</th>
                <th class="px-2 py-2 text-left font-semibold w-48">Name</th>
                <th class="px-2 py-2 text-left font-semibold w-56">Email</th>
                <th class="px-2 py-2 text-left font-semibold w-36">Phone</th>
                <th class="px-2 py-2 text-left font-semibold w-40">Membership #</th>
                <th class="px-2 py-2 text-left font-semibold w-20">Active</th>
                <th class="px-2 py-2 text-left font-semibold w-20">Visible</th>
                <th class="px-2 py-2 text-left font-semibold w-48">Job title</th>
                <th class="px-2 py-2 text-left font-semibold w-44">Company</th>
                <th class="px-2 py-2 text-left font-semibold w-44">Partner</th>
                <th class="px-2 py-2 text-left font-semibold w-36">Registration</th>
                <th class="px-2 py-2 text-left font-semibold w-36">Expiration</th>
                <th class="px-2 py-2 text-left font-semibold w-24 sticky right-0 bg-muted/80 backdrop-blur z-10"></th>
              </tr>
            </thead>
            <tbody>
              <template v-for="(row, i) in rows" :key="i">
                <tr class="border-b border-border" :class="rowBgClass(row.status)">
                  <td class="px-2 py-1.5 text-center font-mono sticky left-0 z-[5] bg-card" :class="rowBgClass(row.status)">
                    {{ i + 1 }}
                    <button
                      v-if="row.families && row.families.length"
                      type="button"
                      @click="row.showFamilies = !row.showFamilies"
                      class="block mx-auto mt-0.5 text-[10px] px-1.5 py-0.5 rounded bg-violet-600 text-white font-semibold"
                      :title="row.showFamilies ? 'Hide family' : 'Show family'"
                    >
                      {{ row.families.length }} fam
                    </button>
                    <button
                      v-if="row.payments && row.payments.length"
                      type="button"
                      @click="row.showPayments = !row.showPayments"
                      class="block mx-auto mt-0.5 text-[10px] px-1.5 py-0.5 rounded bg-emerald-600 text-white font-semibold"
                      :title="row.showPayments ? 'Hide payments' : 'Show payments'"
                    >
                      {{ row.payments.length }} pay
                    </button>
                  </td>
                  <td class="px-2 py-1 text-center">
                    <div class="relative w-10 h-10 mx-auto rounded-full bg-muted border border-border overflow-hidden flex items-center justify-center">
                      <span class="text-[10px] font-bold text-muted-foreground select-none">{{ initials(row.parsed.name) }}</span>
                      <img
                        v-if="row.parsed.avatar_url"
                        :src="row.parsed.avatar_url"
                        :alt="row.parsed.name"
                        class="absolute inset-0 w-full h-full object-cover"
                        loading="lazy"
                        @error="(e) => e.target.style.display='none'"
                      />
                    </div>
                  </td>
                  <td class="px-2 py-1.5">
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase" :class="statusBadgeClass(row.status)">
                      {{ row.status }}
                    </span>
                  </td>
                  <td class="px-2 py-1"><input v-model="row.parsed.name" :class="inputCls(row.errors?.name)" /></td>
                  <td class="px-2 py-1"><input v-model="row.parsed.email" :class="inputCls(row.errors?.email)" /></td>
                  <td class="px-2 py-1"><input v-model="row.parsed.phone" :class="inputCls()" /></td>
                  <td class="px-2 py-1"><input v-model="row.parsed.membership_number" :class="inputCls(row.errors?.membership_number)" /></td>
                  <td class="px-2 py-1 text-center"><input type="checkbox" v-model="row.parsed.is_active" /></td>
                  <td class="px-2 py-1 text-center"><input type="checkbox" v-model="row.parsed.is_visible" /></td>
                  <td class="px-2 py-1"><input v-model="row.parsed.job_title" :class="inputCls()" /></td>
                  <td class="px-2 py-1">
                    <select v-model="row.parsed.company_id" :class="inputCls(row.errors?.company)">
                      <option :value="null">— None —</option>
                      <option v-for="c in companyOptions" :key="c.value" :value="c.value">{{ c.label }}</option>
                    </select>
                  </td>
                  <td class="px-2 py-1">
                    <select v-model="row.parsed.partner_id" :class="inputCls(row.errors?.partner)">
                      <option :value="null">— None —</option>
                      <option v-for="p in partnerOptions" :key="p.value" :value="p.value">{{ p.label }}</option>
                    </select>
                  </td>
                  <td class="px-2 py-1"><input type="date" v-model="row.parsed.registration_date" :class="inputCls()" /></td>
                  <td class="px-2 py-1"><input type="date" v-model="row.parsed.expiration_date" :class="inputCls(row.errors?.expiration_date)" /></td>
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
                  <td colspan="15" class="px-2 pb-2 text-[11px] text-destructive">
                    <span v-for="(msg, key) in row.errors" :key="key" class="mr-3">⚠ <strong>{{ key }}:</strong> {{ msg }}</span>
                  </td>
                </tr>
                <!-- Family members sub-table -->
                <tr v-if="row.showFamilies && row.families && row.families.length" :class="rowBgClass(row.status)">
                  <td colspan="15" class="p-3">
                    <div class="bg-card border border-violet-500 rounded-md overflow-hidden">
                      <div class="bg-violet-600 text-white px-3 py-2 text-xs font-semibold">
                        Family members for {{ row.parsed.name }} ({{ row.families.length }})
                      </div>
                      <table class="w-full text-[11px]">
                        <thead class="bg-muted">
                          <tr>
                            <th class="px-3 py-1.5 text-left font-semibold w-16">Photo</th>
                            <th class="px-3 py-1.5 text-left font-semibold">Name</th>
                            <th class="px-3 py-1.5 text-left font-semibold">Relationship</th>
                            <th class="px-3 py-1.5 text-left font-semibold">DOB</th>
                            <th class="px-3 py-1.5 text-left font-semibold">Phone</th>
                            <th class="px-3 py-1.5 text-left font-semibold">Email</th>
                            <th class="px-3 py-1.5 text-center font-semibold">Active</th>
                            <th class="px-3 py-1.5 w-12"></th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="(fam, fi) in row.families" :key="fi" class="border-t border-border">
                            <td class="px-3 py-1 text-center">
                              <div class="relative w-8 h-8 mx-auto rounded-full bg-muted border border-border overflow-hidden flex items-center justify-center">
                                <span class="text-[9px] font-bold text-muted-foreground select-none">{{ initials(fam.name) }}</span>
                                <img
                                  v-if="fam.photo_url"
                                  :src="fam.photo_url"
                                  :alt="fam.name"
                                  class="absolute inset-0 w-full h-full object-cover"
                                  loading="lazy"
                                  @error="(e) => e.target.style.display='none'"
                                />
                              </div>
                            </td>
                            <td class="px-3 py-1"><input v-model="fam.name" :class="inputCls()" /></td>
                            <td class="px-3 py-1"><input v-model="fam.relationship" :class="inputCls()" placeholder="wife, son, …" /></td>
                            <td class="px-3 py-1"><input type="date" v-model="fam.date_of_birth" :class="inputCls()" /></td>
                            <td class="px-3 py-1"><input v-model="fam.phone" :class="inputCls()" /></td>
                            <td class="px-3 py-1"><input v-model="fam.email" :class="inputCls()" /></td>
                            <td class="px-3 py-1 text-center"><input type="checkbox" v-model="fam.is_active" /></td>
                            <td class="px-3 py-1 text-center">
                              <button type="button" @click="row.families.splice(fi, 1)" class="text-red-600 hover:underline text-[10px]">remove</button>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </td>
                </tr>

                <!-- Payments sub-table -->
                <tr v-if="row.showPayments && row.payments && row.payments.length" :class="rowBgClass(row.status)">
                  <td colspan="15" class="p-3">
                    <div class="bg-card border border-emerald-500 rounded-md overflow-hidden">
                      <div class="bg-emerald-600 text-white px-3 py-2 text-xs font-semibold flex items-center justify-between">
                        <span>Payments for {{ row.parsed.name }} ({{ row.payments.length }})</span>
                        <button type="button" @click="addPayment(row)" class="text-[10px] px-2 py-0.5 rounded bg-white/20 hover:bg-white/30 font-semibold">+ Add payment</button>
                      </div>
                      <table class="w-full text-[11px]">
                        <thead class="bg-muted">
                          <tr>
                            <th class="px-3 py-1.5 text-left font-semibold w-24">Amount</th>
                            <th class="px-3 py-1.5 text-left font-semibold w-24">Months paid</th>
                            <th class="px-3 py-1.5 text-left font-semibold w-28">From date</th>
                            <th class="px-3 py-1.5 text-left font-semibold w-28">To date</th>
                            <th class="px-3 py-1.5 text-left font-semibold">Notes</th>
                            <th class="px-3 py-1.5 text-left font-semibold w-28">Type</th>
                            <th class="px-3 py-1.5 w-12"></th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="(pay, pi) in row.payments" :key="pi" class="border-t border-border">
                            <td class="px-3 py-1"><input v-model="pay.amount" :class="inputCls()" placeholder="0.00" /></td>
                            <td class="px-3 py-1"><input v-model="pay.months_paid" :class="inputCls()" placeholder="12" /></td>
                            <td class="px-3 py-1"><input type="date" v-model="pay.from_date" :class="inputCls()" /></td>
                            <td class="px-3 py-1"><input type="date" v-model="pay.to_date" :class="inputCls()" /></td>
                            <td class="px-3 py-1"><input v-model="pay.notes" :class="inputCls()" /></td>
                            <td class="px-3 py-1"><input v-model="pay.type" :class="inputCls()" placeholder="commission" /></td>
                            <td class="px-3 py-1 text-center">
                              <button type="button" @click="row.payments.splice(pi, 1)" class="text-red-600 hover:underline text-[10px]">remove</button>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </td>
                </tr>

                <!-- Diff sub-table -->
                <tr v-if="row.showDiff && row.status === 'update' && mode === 'upsert'" :class="rowBgClass(row.status)">
                  <td colspan="15" class="p-3">
                    <div class="bg-card border border-border rounded-md overflow-hidden">
                      <div class="bg-muted/50 px-3 py-2 text-xs font-semibold">
                        Changes for <span class="text-foreground">{{ row.match?.name }}</span>
                        <span class="text-muted-foreground"> (#{{ row.match?.membership_number }})</span>
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
          <Link :href="route('admin.user.membership.list')" class="inline-flex items-center justify-center rounded-md text-sm font-medium bg-primary text-primary-foreground h-9 px-4 btn-golden">
            Go to members
          </Link>
          <button @click="reset(); step = 'upload'" class="inline-flex items-center justify-center rounded-md text-sm font-medium border bg-background h-9 px-4 hover:bg-muted">
            Import another file
          </button>
        </div>
      </div>
    </div>
  </MemberLayout>
</template>

<script setup>
import { Link, usePage } from "@inertiajs/vue3";
import MemberLayout from "../MemberLayout.vue";
import { Breadcrumb } from "@/Pages/Admin/Layout/Layout.js";
import UserMembershipListFilterContent from "../List/UserMembershipListFilterContent.vue";
import { computed, ref } from "vue";

const page = usePage();

const file = ref(null);
const step = ref('upload');
const rows = ref([]);
const companyOptions = ref(page.props.companyOptions || []);
const partnerOptions = ref(page.props.partnerOptions || []);
const mode = ref('upsert');
const loading = ref(false);
const error = ref('');
const result = ref(null);
const activeFilters = ref({});

const onFilterChange = (newFilters) => {
  activeFilters.value = newFilters;
};

const exportUrl = computed(() => {
  const params = new URLSearchParams();
  const f = activeFilters.value || {};
  if (f.search && String(f.search).trim()) params.set('search', f.search);
  if (f.email && String(f.email).trim()) params.set('email', String(f.email).trim());
  if (f.phone && String(f.phone).trim()) params.set('phone', String(f.phone).trim());
  if (f.membership_number && String(f.membership_number).trim()) params.set('membership_number', String(f.membership_number).trim());
  if (f.is_active !== null && f.is_active !== undefined && f.is_active !== '') params.set('is_active', f.is_active ? 1 : 0);
  if (f.is_paid !== null && f.is_paid !== undefined && f.is_paid !== '') params.set('is_paid', f.is_paid ? 1 : 0);
  if (f.is_from_card_patch !== null && f.is_from_card_patch !== undefined && f.is_from_card_patch !== '') params.set('is_from_card_patch', f.is_from_card_patch ? 1 : 0);
  if (f.has_custom_card !== null && f.has_custom_card !== undefined && f.has_custom_card !== '') params.set('has_custom_card', f.has_custom_card ? 1 : 0);
  if (f.partner_id !== null && f.partner_id !== undefined && f.partner_id !== '') params.set('partner_id', f.partner_id);
  if (f.creator_id !== null && f.creator_id !== undefined && f.creator_id !== '') params.set('creator_id', f.creator_id);
  if (f.sale_id !== null && f.sale_id !== undefined && f.sale_id !== '') params.set('sale_id', f.sale_id);
  if (f.company_id !== null && f.company_id !== undefined && f.company_id !== '') params.set('company_id', f.company_id);
  if (f.created_from) params.set('created_from', f.created_from);
  if (f.created_to) params.set('created_to', f.created_to);
  if (f.registration_date_from) params.set('registration_date_from', f.registration_date_from);
  if (f.registration_date_to) params.set('registration_date_to', f.registration_date_to);
  if (f.expiration_date_from) params.set('expiration_date_from', f.expiration_date_from);
  if (f.expiration_date_to) params.set('expiration_date_to', f.expiration_date_to);
  if (f.last_activation_changer && String(f.last_activation_changer).trim()) params.set('last_activation_changer', String(f.last_activation_changer).trim());
  if (f.last_activation_from) params.set('last_activation_from', f.last_activation_from);
  if (f.last_activation_to) params.set('last_activation_to', f.last_activation_to);
  if (f.outstanding_days_from !== null && f.outstanding_days_from !== undefined && f.outstanding_days_from !== '') params.set('outstanding_days_from', f.outstanding_days_from);
  if (f.outstanding_days_to !== null && f.outstanding_days_to !== undefined && f.outstanding_days_to !== '') params.set('outstanding_days_to', f.outstanding_days_to);
  params.set('include_family', 1);
  const qs = params.toString();
  const base = route('admin.user.membership.import.export');
  return qs ? `${base}?${qs}` : base;
});

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
    const { data } = await axios.post(route('admin.user.membership.import.preview'), fd);
    rows.value = (data.rows || []).map(r => ({
      ...r,
      showDiff: false,
      showFamilies: (r.families || []).length > 0,
      showPayments: false,
      families: (r.families || []).map(f => ({ ...f })),
      payments: (r.payments || []).map(p => ({ ...p })),
    }));
    companyOptions.value = data.companyOptions || [];
    partnerOptions.value = data.partnerOptions || [];
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
        name: r.parsed.name,
        email: r.parsed.email || null,
        phone: r.parsed.phone || null,
        membership_number: r.parsed.membership_number,
        is_active: !!r.parsed.is_active,
        is_visible: !!r.parsed.is_visible,
        job_title: r.parsed.job_title || null,
        company_id: r.parsed.company_id || null,
        partner_id: r.parsed.partner_id || null,
        registration_date: r.parsed.registration_date || null,
        expiration_date: r.parsed.expiration_date || null,
        families: (r.families || []).map(f => ({
          name: f.name,
          relationship: f.relationship || null,
          date_of_birth: f.date_of_birth || null,
          phone: f.phone || null,
          email: f.email || null,
          is_active: !!f.is_active,
        })),
        payments: (r.payments || []).map(p => ({
          amount: p.amount ? parseFloat(p.amount) : null,
          months_paid: p.months_paid ? parseInt(p.months_paid) : null,
          from_date: p.from_date || null,
          to_date: p.to_date || null,
          notes: p.notes || null,
          type: p.type || 'commission',
        })),
      })),
    };
    const { data } = await axios.post(route('admin.user.membership.import.commit'), payload);
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

const totalFamilies = computed(() => rows.value.reduce((sum, r) => sum + (r.families?.length || 0), 0));
const totalPayments = computed(() => rows.value.reduce((sum, r) => sum + (r.payments?.length || 0), 0));

const addPayment = (row) => {
  if (!row.payments) row.payments = [];
  row.payments.push({ amount: '', months_paid: '', from_date: '', to_date: '', notes: '', type: 'commission' });
  row.showPayments = true;
};

const hasChanges = (row) => (row.diff || []).some(d => d.changed);
const changedFields = (row) => (row.diff || []).filter(d => d.changed);

// Use semantic theme tokens (bg-background / text-foreground / border-input)
// rather than raw slate/white. Raw colors don't follow the app's dark/light
// CSS variables and were producing white-on-white text in this theme.
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

const initials = (name) => {
  if (!name) return '?';
  const parts = String(name).trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) return '?';
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
};
</script>
