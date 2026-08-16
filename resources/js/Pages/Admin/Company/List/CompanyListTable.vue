<template>
  <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
    <div v-if="companies?.data?.length > 0" data-slot="card-content" class="p-0">
      <div class="overflow-x-auto">
        <table class="w-full caption-bottom text-sm min-w-full">
          <thead class="[&_tr]:border-b [&_tr]:border-border">
            <tr class="hover:bg-muted/50 border-b border-border transition-colors">
              <th class="text-foreground h-10 px-2 text-left align-middle font-medium whitespace-nowrap min-w-[300px]">
                Company Details
              </th>
              <th class="text-foreground h-10 px-2 align-middle font-medium whitespace-nowrap w-28 text-center">
                Members
              </th>
              <th class="text-foreground h-10 px-2 align-middle font-medium whitespace-nowrap w-28 text-center">
                Actions
              </th>
            </tr>
          </thead>
          <tbody class="[&_tr:last-child]:border-0">
            <tr
              v-for="company in companies.data"
              :key="company.id"
              class="data-[state=selected]:bg-muted border-b border-border transition-colors hover:bg-muted/50"
            >
              <td class="p-2 align-middle">
                <div class="flex-1 min-w-0 space-y-1">
                  <Link
                    :href="route('admin.company.edit', company.slug)"
                    class="font-semibold text-base text-foreground hover:text-golden-yellow transition-colors cursor-pointer block"
                  >
                    {{ getTranslatedName(company.name) }}
                  </Link>
                  <div v-if="getOtherLocaleName(company.name)" class="text-xs text-muted-foreground" dir="auto">
                    {{ getOtherLocaleName(company.name) }}
                  </div>
                  <div class="text-xs text-muted-foreground font-mono">{{ company.slug }}</div>
                </div>
              </td>
              <td class="p-2 align-middle text-center">
                <button
                  type="button"
                  @click="openMembers(company)"
                  :disabled="!(company.memberships_count > 0)"
                  title="View members"
                  class="inline-flex items-center gap-1.5 text-sm justify-center rounded-md border border-border bg-background px-2.5 py-1 transition-colors hover:bg-primary hover:text-primary-foreground disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-background disabled:hover:text-foreground cursor-pointer"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-blue-500">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                  </svg>
                  <span>{{ company.memberships_count || 0 }}</span>
                </button>
              </td>
              <td class="p-2 align-middle text-center">
                <div class="flex items-center justify-center gap-2">
                  <Link
                    :href="route('admin.company.edit', company.slug)"
                    class="inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md px-3 text-emerald-bright hover:!bg-emerald-bright/10 hover:!text-emerald-bright"
                    title="Edit"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3">
                      <path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                      <path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"/>
                    </svg>
                  </Link>
                  <button
                    v-if="company.memberships_count === 0"
                    type="button"
                    @click="$emit('delete', company.slug)"
                    class="inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all border bg-background shadow-xs hover:bg-destructive hover:text-white dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md px-3 text-destructive hover:!bg-destructive/10 hover:!text-destructive"
                    title="Delete"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3">
                      <path d="M3 6h18"/>
                      <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/>
                      <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="border-t border-border/50 px-3 sm:px-6 py-3 sm:py-4 w-full">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between w-full">
          <div class="text-xs sm:text-sm text-muted-foreground">
            Showing {{ companies.meta?.from || 0 }} to {{ companies.meta?.to || 0 }} of {{ companies.meta?.total || 0 }} results
          </div>
          <div class="flex items-center gap-2">
            <p class="text-xs sm:text-sm font-medium whitespace-nowrap">Rows per page</p>
            <select
              :value="companies.meta?.per_page || 15"
              @change="handlePerPageChange"
              dir="ltr"
              class="border-input dark:bg-input/30 rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] h-8 w-[70px] cursor-pointer"
            >
              <option value="10">10</option>
              <option value="15">15</option>
              <option value="25">25</option>
              <option value="50">50</option>
            </select>
          </div>
          <Pagination v-if="companies?.meta?.links?.length > 0" :links="companies?.meta?.links" />
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else data-slot="card-content" class="p-12">
      <div class="text-center max-w-md mx-auto space-y-6">
        <div class="inline-flex items-center justify-center w-20 h-20 sm:w-24 sm:h-24 rounded-full bg-golden-yellow/10 border border-golden-yellow/20 mb-6 shadow-lg shadow-golden-yellow/10">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-10 h-10 sm:w-12 sm:h-12 text-golden-yellow subtle-float">
            <rect width="20" height="14" x="2" y="7" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
          </svg>
        </div>
        <h3 class="text-xl sm:text-2xl font-bold mb-1 text-foreground">No Companies Found</h3>
        <p class="text-muted-foreground text-sm sm:text-base leading-relaxed">No companies match your current filters.</p>
        <Link
          :href="route('admin.company.create')"
          class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2 btn-golden"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
            <path d="M5 12h14"/><path d="M12 5v14"/>
          </svg>
          Add Company
        </Link>
      </div>
    </div>
  </div>

  <!-- Members popup -->
  <Modal :show="showMembersModal" max-width="2xl" @close="closeMembers">
    <div class="bg-popover text-popover-foreground rounded-xl border border-border shadow-xl">
      <div class="flex items-center justify-between gap-4 px-5 py-4 border-b border-border">
        <div class="flex items-center gap-2 min-w-0">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-golden-yellow shrink-0">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
            <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
          </svg>
          <h3 class="font-semibold text-white truncate">
            Members
            <span v-if="getTranslatedName(selectedCompany?.name)" class="text-white/60 text-sm">— {{ getTranslatedName(selectedCompany?.name) }}</span>
          </h3>
        </div>
        <div class="flex items-center gap-1 shrink-0">
          <div ref="exportMenuContainerRef" class="relative">
            <button
              type="button"
              @click="toggleExportMenu"
              title="Export to Excel"
              class="inline-flex items-center gap-1.5 text-xs font-medium rounded-md border border-border bg-background/10 px-2.5 py-1.5 text-white/80 transition-colors hover:bg-accent hover:text-white"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <path d="M7 10l5 5 5-5"/>
                <path d="M12 15V3"/>
              </svg>
              <span class="hidden sm:inline">Export</span>
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3 w-3 opacity-70">
                <polyline points="6 9 12 15 18 9"/>
              </svg>
            </button>
            <!--
              Deliberately NOT teleported to <body>: Modal.vue opens its
              <dialog> with showModal(), which promotes it to the browser's
              top layer. Anything teleported outside that <dialog> (even with
              a high z-index) paints behind it. Staying in-place as an
              absolutely-positioned child keeps this inside the dialog's own
              top-layer subtree so it renders above the modal content.
            -->
            <div
              v-if="exportMenuOpen"
              class="absolute z-50 ltr:right-0 rtl:left-0 top-full mt-2 w-96 max-h-[80vh] overflow-y-auto rounded-md border border-border bg-popover text-popover-foreground shadow-2xl p-3 space-y-3"
            >
              <div>
                <div class="flex items-center justify-between mb-1.5">
                  <span class="text-[11px] font-semibold uppercase text-muted-foreground">Columns</span>
                  <button type="button" @click="toggleAllExportColumns" class="text-[10px] text-muted-foreground hover:text-foreground underline">
                    {{ allExportColumnsSelected ? 'Deselect all' : 'Select all' }}
                  </button>
                </div>
                <div class="max-h-56 overflow-y-auto grid grid-cols-2 gap-x-2 gap-y-0.5">
                  <label v-for="col in exportColumnOptions" :key="col.key" class="flex items-center gap-1.5 py-0.5 cursor-pointer">
                    <input type="checkbox" :value="col.key" v-model="selectedExportColumns" class="h-3 w-3 rounded border-border accent-primary" />
                    <span class="text-[11px] text-foreground truncate">{{ col.label }}</span>
                  </label>
                </div>
              </div>
              <div>
                <div class="text-[11px] font-semibold uppercase text-muted-foreground mb-1.5">Split into files of</div>
                <div class="grid grid-cols-4 gap-1 mb-1.5">
                  <button
                    v-for="opt in [0, 50, 100, 200]"
                    :key="opt"
                    type="button"
                    @click="exportChunkSize = opt"
                    :class="exportChunkSize === opt ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-background text-foreground'"
                    class="text-[11px] px-1 py-1.5 rounded border font-medium transition-colors"
                  >
                    {{ opt === 0 ? 'None' : opt }}
                  </button>
                </div>
                <div class="flex items-center gap-2">
                  <span class="text-[11px] text-muted-foreground whitespace-nowrap">Custom:</span>
                  <input
                    type="number"
                    min="1"
                    step="1"
                    v-model.number="exportChunkSize"
                    placeholder="rows / file"
                    class="flex-1 h-7 px-2 text-xs rounded border border-input bg-background text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-primary"
                  />
                </div>
              </div>
              <div class="pt-2 border-t border-border">
                <a
                  :href="exportUrl"
                  target="_blank"
                  rel="noopener"
                  @click="exportMenuOpen = false"
                  class="block w-full text-center bg-primary text-primary-foreground rounded-md px-3 py-2 text-xs font-semibold hover:bg-primary/90 btn-golden"
                >
                  {{ exportChunkSize ? 'Download ZIP' : 'Download Excel' }}
                </a>
              </div>
            </div>
          </div>
          <button type="button" @click="closeMembers" class="p-1.5 rounded-md text-white/70 hover:text-white hover:bg-accent transition-colors" title="Close">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 6 6 18"/>
              <path d="m6 6 12 12"/>
            </svg>
          </button>
        </div>
      </div>

      <!-- Filters -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 px-5 pt-4">
        <input
          v-model="memberFilters.name"
          @input="handleMemberFilterInput"
          type="text"
          placeholder="Filter by name…"
          class="h-8 rounded-md border border-border bg-background/10 px-2.5 text-sm text-white placeholder:text-white/40 outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
        />
        <input
          v-model="memberFilters.membership_number"
          @input="handleMemberFilterInput"
          type="text"
          placeholder="Filter by membership #…"
          class="h-8 rounded-md border border-border bg-background/10 px-2.5 text-sm text-white placeholder:text-white/40 outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
        />
        <input
          v-model="memberFilters.phone"
          @input="handleMemberFilterInput"
          type="text"
          placeholder="Filter by phone…"
          class="h-8 rounded-md border border-border bg-background/10 px-2.5 text-sm text-white placeholder:text-white/40 outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
        />
      </div>

      <div class="p-5 max-h-[60vh] overflow-y-auto">
        <div v-if="membersLoading" class="text-center py-6 text-white/70">Loading…</div>
        <div v-else-if="membersError" class="text-center py-6 text-destructive">{{ membersError }}</div>
        <div v-else-if="companyMembers.length" class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-border text-white/60 text-xs">
                <th class="text-left font-medium py-2 pr-3">Name</th>
                <th class="text-left font-medium py-2 pr-3">Membership #</th>
                <th class="text-left font-medium py-2">Phone</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="member in companyMembers" :key="member.id" class="border-b border-border/50 last:border-0">
                <td class="py-2 pr-3 text-white">{{ member.name || '—' }}</td>
                <td class="py-2 pr-3 text-white/80 font-mono">{{ member.membership_number || '—' }}</td>
                <td class="py-2 text-white/80">{{ member.phone || '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else class="text-center py-6 text-white/70">No members found.</div>
      </div>

      <!-- Pagination -->
      <div v-if="membersMeta.total > 0" class="flex flex-col sm:flex-row items-center justify-between gap-2 px-5 py-3 border-t border-border">
        <div class="text-xs text-white/60">
          Showing {{ membersMeta.from || 0 }} to {{ membersMeta.to || 0 }} of {{ membersMeta.total || 0 }} results
        </div>
        <div class="flex items-center gap-1">
          <button
            type="button"
            :disabled="membersMeta.current_page <= 1"
            @click="goToMemberPage(membersMeta.current_page - 1)"
            class="inline-flex items-center justify-center h-7 w-7 rounded-md border border-border text-white/80 hover:bg-accent hover:text-white disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-transparent"
          >
            ‹
          </button>
          <span class="text-xs text-white/80 px-2">
            Page {{ membersMeta.current_page || 1 }} of {{ membersMeta.last_page || 1 }}
          </span>
          <button
            type="button"
            :disabled="membersMeta.current_page >= membersMeta.last_page"
            @click="goToMemberPage(membersMeta.current_page + 1)"
            class="inline-flex items-center justify-center h-7 w-7 rounded-md border border-border text-white/80 hover:bg-accent hover:text-white disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-transparent"
          >
            ›
          </button>
        </div>
      </div>
    </div>
  </Modal>
</template>

<script setup>
import Pagination from "@/Pages/_components/Pagination.vue";
import Modal from "@/Components/Modal.vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import { computed, ref, onMounted, onUnmounted } from "vue";

const props = defineProps({
  companies: { type: Object, required: true }
});

defineEmits(['delete']);

const page = usePage();
const locale = page.props.locale || 'ar';

// Members popup
const showMembersModal = ref(false);
const selectedCompany = ref(null);
const companyMembers = ref([]);
const membersLoading = ref(false);
const membersError = ref('');
const membersMeta = ref({ current_page: 1, last_page: 1, per_page: 10, total: 0, from: 0, to: 0 });
const memberFilters = ref({ name: '', membership_number: '', phone: '' });
let memberFilterTimeout = null;

// Export popover — mirrors the export menu on the admin/user/membership page:
// same full column catalogue and split-into-files behavior (backed by the
// shared ExportsMemberColumns trait server-side), scoped to this company's
// members. Rendered in-place (not Teleported) — see template comment for why.
const exportMenuOpen = ref(false);
const exportMenuContainerRef = ref(null);
const exportChunkSize = ref(0);

const exportColumnOptions = [
  { key: 'index', label: '#' },
  { key: 'name', label: 'Name' },
  { key: 'email', label: 'Email' },
  { key: 'phone', label: 'Phone' },
  { key: 'membership_number', label: 'Membership No.' },
  { key: 'national_id', label: 'National ID' },
  { key: 'status', label: 'Status' },
  { key: 'payment', label: 'Payment' },
  { key: 'visibility', label: 'Visibility' },
  { key: 'job_title', label: 'Job Title' },
  { key: 'company', label: 'Company' },
  { key: 'partner', label: 'Partner' },
  { key: 'sales', label: 'Sales' },
  { key: 'governorate', label: 'Governorate' },
  { key: 'city', label: 'City' },
  { key: 'registration_date', label: 'Reg. Date' },
  { key: 'expiration_date', label: 'Exp. Date' },
  { key: 'created_at', label: 'Created At' },
  { key: 'updated_at', label: 'Updated At' },
  { key: 'family_members', label: 'Family Members' },
  { key: 'last_active_history', label: 'Last Activation' },
  { key: 'creator', label: 'Created By' },
  { key: 'payment_type', label: 'Payment Type' },
  { key: 'total_amount', label: 'Amount Paid' },
  { key: 'total_months_paid', label: 'Total Months Paid' },
  { key: 'covered_until', label: 'Covered Until' },
  { key: 'days_since_reg', label: 'Days Since Reg.' },
  { key: 'days_covered', label: 'Days Covered' },
  { key: 'outstanding_days', label: 'Outstanding Days' },
  { key: 'payment_status', label: 'Payment Status' },
];
const allExportColumnKeys = exportColumnOptions.map(c => c.key);
const selectedExportColumns = ref([...allExportColumnKeys]);
const allExportColumnsSelected = computed(() => selectedExportColumns.value.length === allExportColumnKeys.length);
const toggleAllExportColumns = () => {
  selectedExportColumns.value = allExportColumnsSelected.value ? [] : [...allExportColumnKeys];
};

const toggleExportMenu = () => {
  exportMenuOpen.value = !exportMenuOpen.value;
};

const handleExportClickOutside = (e) => {
  if (!exportMenuOpen.value) return;
  if (exportMenuContainerRef.value?.contains(e.target)) return;
  exportMenuOpen.value = false;
};
onMounted(() => {
  document.addEventListener('click', handleExportClickOutside);
});
onUnmounted(() => {
  document.removeEventListener('click', handleExportClickOutside);
});

const exportUrl = computed(() => {
  if (!selectedCompany.value) return '#';
  const params = new URLSearchParams();
  if (memberFilters.value.name) params.set('name', memberFilters.value.name);
  if (memberFilters.value.membership_number) params.set('membership_number', memberFilters.value.membership_number);
  if (memberFilters.value.phone) params.set('phone', memberFilters.value.phone);
  if (selectedExportColumns.value.length < allExportColumnKeys.length) {
    params.set('columns', selectedExportColumns.value.join(','));
  }
  if (exportChunkSize.value > 0) params.set('chunk_size', exportChunkSize.value);
  const qs = params.toString();
  const base = route('admin.company.members.export', selectedCompany.value.slug);
  return qs ? `${base}?${qs}` : base;
});

const fetchMembers = async (page = 1) => {
  if (!selectedCompany.value) return;
  membersError.value = '';
  membersLoading.value = true;
  try {
    const { data } = await axios.get(route('admin.company.members', selectedCompany.value.slug), {
      params: {
        page,
        per_page: 10,
        name: memberFilters.value.name || undefined,
        membership_number: memberFilters.value.membership_number || undefined,
        phone: memberFilters.value.phone || undefined,
      },
    });
    companyMembers.value = data?.data || [];
    membersMeta.value = data?.meta || membersMeta.value;
  } catch (e) {
    membersError.value = 'Failed to load members.';
  } finally {
    membersLoading.value = false;
  }
};

const openMembers = (company) => {
  selectedCompany.value = company;
  showMembersModal.value = true;
  companyMembers.value = [];
  memberFilters.value = { name: '', membership_number: '', phone: '' };
  membersMeta.value = { current_page: 1, last_page: 1, per_page: 10, total: 0, from: 0, to: 0 };
  exportMenuOpen.value = false;
  exportChunkSize.value = 0;
  selectedExportColumns.value = [...allExportColumnKeys];
  fetchMembers(1);
};

const closeMembers = () => {
  showMembersModal.value = false;
  exportMenuOpen.value = false;
};

const handleMemberFilterInput = () => {
  clearTimeout(memberFilterTimeout);
  memberFilterTimeout = setTimeout(() => fetchMembers(1), 300);
};

const goToMemberPage = (page) => {
  if (page < 1 || page > (membersMeta.value.last_page || 1)) return;
  fetchMembers(page);
};

const getTranslatedName = (name) => {
  if (typeof name === 'string') return name;
  if (typeof name === 'object' && name !== null) {
    return name[locale] || name['ar'] || name['en'] || Object.values(name)[0] || '';
  }
  return '';
};

const getOtherLocaleName = (name) => {
  if (typeof name !== 'object' || name === null) return '';
  const primary = name[locale] || name['ar'] || name['en'] || '';
  const others = Object.values(name).filter((v) => v && v !== primary);
  return others[0] || '';
};

const handlePerPageChange = (event) => {
  const perPage = event.target.value;
  const url = new URL(window.location.href);
  url.searchParams.set('per_page', perPage);
  url.searchParams.set('page', '1');
  router.visit(url.toString(), { preserveState: false, preserveScroll: false });
};
</script>
