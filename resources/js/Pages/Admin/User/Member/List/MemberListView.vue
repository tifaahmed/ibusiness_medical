<template>
  <MemberLayout>
    <!-- Natural page scroll on all screen sizes -->
    <div class="w-full max-w-full">
      <div class="space-y-2 sm:space-y-3 md:space-y-4 p-2 sm:p-3 md:p-4 lg:p-6 pb-2 sm:pb-3 md:pb-4 lg:pb-4 w-full max-w-full">
        <!-- Header Card with Actions and Filters -->
        <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-2 sm:gap-3 md:gap-4 rounded-xl border border-border py-2 sm:py-3 md:py-4 shadow-sm overflow-visible w-full max-w-full">
          <div data-slot="card-header" class="flex flex-row items-center justify-between py-2 px-3 sm:px-4 md:px-6 w-full overflow-hidden gap-2 sm:gap-4">
            <div data-slot="card-title" class="leading-none font-semibold min-w-0 flex-1">
              <div class="title-golden min-w-0 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-users title-icon sm:w-6 sm:h-6 flex-shrink-0">
                  <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                  <circle cx="9" cy="7" r="4"></circle>
                  <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                  <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                <span class="text-sm sm:text-base truncate block min-w-0">{{ t.header?.title || 'User Management' }}</span>
              </div>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
              <div class="flex-shrink-0">
                <button
                  v-if="canWrite"
                  ref="exportTriggerRef"
                  type="button"
                  @click="toggleExportMenu"
                  class="inline-flex items-center cursor-pointer justify-center gap-1.5 sm:gap-2 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 sm:h-9 px-2 sm:px-3 md:px-4 py-2"
                  :title="exportButtonTitle"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 sm:h-4 sm:w-4">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" x2="12" y1="15" y2="3"></line>
                  </svg>
                  <span class="hidden sm:inline">{{ t.actions?.export_long || 'Export' }}</span>
                  <span class="sm:hidden">{{ t.actions?.export_short || 'Exp' }}</span>
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3 w-3 opacity-70">
                    <polyline points="6 9 12 15 18 9"></polyline>
                  </svg>
                </button>
              </div>
              <Teleport to="body">
                <div
                  v-if="exportMenuOpen"
                  ref="exportMenuRef"
                  :style="exportMenuStyle"
                  class="fixed z-[1000] w-96 rounded-md border border-border bg-popover text-popover-foreground shadow-2xl p-3 space-y-3"
                >
                  <div>
                    <div class="text-[11px] font-semibold uppercase text-muted-foreground mb-1.5">{{ t.export_menu?.include_label || 'Include' }}</div>
                    <div class="grid grid-cols-2 gap-1">
                      <button
                        type="button"
                        @click="includeFamily = false"
                        :class="includeFamily ? 'border-border bg-background text-foreground' : 'border-primary bg-primary text-primary-foreground'"
                        class="text-xs px-2 py-1.5 rounded border font-medium transition-colors"
                      >
                        {{ t.export_menu?.members_only || 'Members' }}
                      </button>
                      <button
                        type="button"
                        @click="includeFamily = true"
                        :class="includeFamily ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-background text-foreground'"
                        class="text-xs px-2 py-1.5 rounded border font-medium transition-colors"
                      >
                        {{ t.export_menu?.with_family || '+ Family' }}
                      </button>
                    </div>
                  </div>
                  <div>
                    <div class="text-[11px] font-semibold uppercase text-muted-foreground mb-1.5">{{ t.export_menu?.split_label || 'Split into files of' }}</div>
                    <div class="grid grid-cols-5 gap-1 mb-1.5">
                      <button
                        v-for="opt in [0, 100, 200, 300, 500]"
                        :key="opt"
                        type="button"
                        @click="chunkSize = opt"
                        :class="chunkSize === opt ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-background text-foreground'"
                        class="text-[11px] px-1 py-1.5 rounded border font-medium transition-colors"
                      >
                        {{ opt === 0 ? (t.export_menu?.no_split || 'None') : opt }}
                      </button>
                    </div>
                    <div class="flex items-center gap-2">
                      <span class="text-[11px] text-muted-foreground whitespace-nowrap">{{ t.export_menu?.custom_label || 'Custom:' }}</span>
                      <input
                        type="number"
                        min="1"
                        step="1"
                        v-model.number="chunkSize"
                        :placeholder="t.export_menu?.custom_placeholder || 'rows / file'"
                        class="flex-1 h-7 px-2 text-xs rounded border border-input bg-background text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-primary"
                      />
                    </div>
                  </div>
                  <div>
                    <div class="flex items-center justify-between mb-1.5">
                      <span class="text-[11px] font-semibold uppercase text-muted-foreground">{{ t.export_menu?.columns_label || 'Columns' }}</span>
                      <button
                        type="button"
                        @click="toggleAllColumns"
                        class="text-[10px] text-muted-foreground hover:text-foreground underline"
                      >
                        {{ allColumnsSelected ? (t.export_menu?.deselect_all || 'Deselect all') : (t.export_menu?.select_all || 'Select all') }}
                      </button>
                    </div>
                    <div class="max-h-56 overflow-y-auto grid grid-cols-2 gap-x-2 gap-y-0.5">
                      <label
                        v-for="col in exportColumnOptions"
                        :key="col.key"
                        class="flex items-center gap-1.5 py-0.5 cursor-pointer"
                      >
                        <input
                          type="checkbox"
                          :value="col.key"
                          v-model="selectedColumns"
                          class="h-3 w-3 rounded border-border accent-primary"
                        />
                        <span class="text-[11px] text-foreground truncate">{{ col.label }}</span>
                      </label>
                    </div>
                  </div>
                  <div class="pt-2 border-t border-border space-y-1.5">
                    <a
                      :href="exportComputedUrl"
                      @click="exportMenuOpen = false"
                      class="block w-full text-center bg-primary text-primary-foreground rounded-md px-3 py-2 text-xs font-semibold hover:bg-primary/90 btn-golden"
                    >
                      {{ chunkSize ? (t.export_menu?.download_zip || 'Download ZIP') : (t.export_menu?.download_excel || 'Download Excel') }}
                    </a>
                    <a
                      v-if="canManagePayments"
                      :href="exportToPayUrl"
                      @click="exportMenuOpen = false"
                      class="block w-full text-center border border-input bg-background text-foreground rounded-md px-3 py-2 text-xs font-semibold hover:bg-muted"
                    >
                      {{ t.export_menu?.export_to_pay || 'Export to Pay' }}
                    </a>
                  </div>
                </div>
              </Teleport>
              <Link
                v-if="canWrite"
                :href="route('admin.user.membership.import.page')"
                data-slot="button"
                class="inline-flex items-center cursor-pointer justify-center gap-1.5 sm:gap-2 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 sm:h-9 px-2 sm:px-3 md:px-4 py-2 flex-shrink-0"
                :title="t.actions?.import_tooltip || 'Import members from CSV/XLSX'"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 sm:h-4 sm:w-4">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                  <polyline points="17 8 12 3 7 8"></polyline>
                  <line x1="12" x2="12" y1="3" y2="15"></line>
                </svg>
                <span class="hidden sm:inline">{{ t.actions?.import_long || 'Import' }}</span>
                <span class="sm:hidden">{{ t.actions?.import_short || 'Imp' }}</span>
              </Link>
              <Link
                :href="route('admin.user.membership.trash')"
                data-slot="button"
                class="inline-flex items-center cursor-pointer justify-center gap-1.5 sm:gap-2 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 sm:h-9 px-2 sm:px-3 md:px-4 py-2 flex-shrink-0 btn-view-trash"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash2 h-3.5 w-3.5 sm:h-4 sm:w-4">
                  <path d="M3 6h18"></path>
                  <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                  <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                  <line x1="10" x2="10" y1="11" y2="17"></line>
                  <line x1="14" x2="14" y1="11" y2="17"></line>
                </svg>
                <span class="hidden sm:inline">{{ t.actions?.view_trash_long || 'View Trash' }}</span>
                <span class="sm:hidden">{{ t.actions?.view_trash_short || 'Trash' }}</span>
              </Link>
              <Link
                v-if="canWrite"
                :href="route('admin.user.membership.create')"
                data-slot="button"
                class="inline-flex items-center cursor-pointer justify-center gap-1.5 sm:gap-2 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-8 sm:h-9 px-2 sm:px-3 md:px-4 py-2 flex-shrink-0 btn-golden"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus h-3.5 w-3.5 sm:h-4 sm:w-4">
                  <path d="M5 12h14"></path>
                  <path d="M12 5v14"></path>
                </svg>
                <span class="hidden sm:inline">{{ t.actions?.add_new_long || 'Add New User' }}</span>
                <span class="sm:hidden">{{ t.actions?.add_short || 'Add' }}</span>
              </Link>
            </div>
          </div>
          
          <!-- Filter Content -->
          <div data-slot="card-content" class="px-2 sm:px-4 md:px-6 space-y-2 sm:space-y-3 md:space-y-4 w-full max-w-full overflow-visible min-w-0">
            <UserMembershipListFilterContent :initial-filters="filters" @filter-change="handleFilterChange" />
          </div>
        </div>

      </div>

      <!-- Members Chart Toggle -->
      <div class="w-full max-w-full px-2 sm:px-3 md:px-4 lg:px-6">
        <button
          type="button"
          @click="chartExpanded = !chartExpanded"
          class="w-full flex items-center justify-between gap-2 px-4 py-2.5 rounded-xl border border-border bg-card text-card-foreground shadow-sm hover:bg-muted/50 transition-colors cursor-pointer"
        >
          <span class="text-sm font-semibold flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" x2="18" y1="20" y2="10"/><line x1="12" x2="12" y1="20" y2="4"/><line x1="6" x2="6" y1="20" y2="14"/>
            </svg>
            {{ t.member_list?.chart_title || 'Members over time' }}
          </span>
          <svg
            xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
            :class="chartExpanded ? 'rotate-180' : ''"
            class="transition-transform duration-200 text-muted-foreground"
          >
            <polyline points="6 9 12 15 18 9"></polyline>
          </svg>
        </button>
      </div>

      <!-- Members Chart (collapsible) -->
      <Transition
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="opacity-0 -translate-y-2 max-h-0"
        enter-to-class="opacity-100 translate-y-0 max-h-[400px]"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="opacity-100 translate-y-0 max-h-[400px]"
        leave-to-class="opacity-0 -translate-y-2 max-h-0"
      >
        <div v-if="chartExpanded" class="w-full max-w-full px-2 sm:px-3 md:px-4 lg:px-6 pt-2">
          <MembersChart :chart-data="chartData" :chart-days="chartDays" />
        </div>
      </Transition>

      <!-- spacing -->
      <div class="w-full max-w-full px-2 sm:px-3 md:px-4 lg:px-6">
        <div class="h-3 sm:h-4"></div>
      </div>

      <!-- Table Card -->
      <div class="w-full max-w-full px-2 sm:px-3 md:px-4 lg:px-6 pb-2 sm:pb-3 md:pb-4 lg:pb-6">
        <MemberListTable :members="members" @delete="handleDelete" />
      </div>
    </div>
  </MemberLayout>
</template>

<script setup>
import MemberLayout from "../MemberLayout.vue";
import UserMembershipListFilterContent from "./UserMembershipListFilterContent.vue";
import MemberListTable from "./MemberListTable.vue";
import MembersChart from "./MembersChart.vue";
import { useMemberStore } from "../Stores/MemberStore";
import { Link, usePage } from "@inertiajs/vue3";
import { storeToRefs } from "pinia";
import { ref, computed, onMounted, onUnmounted } from "vue";
import { usePermissions } from '@/composables/usePermissions';

const { canManage } = usePermissions();
// Export, import and create are writes; a read-only account keeps the
// list, the trash view and the detail pages.
const canWrite = computed(() => canManage('manage memberships', 'manage own memberships', 'manage partner memberships'));


// `page.props.translations.admin.member_list.*` — shared via HandleInertiaRequests
// every label below uses an English fallback so the page still renders if a
// key is missing or the locale fails to load.
const page = usePage();
const t = computed(() => page.props.translations?.admin?.member_list || {});
const canManagePayments = computed(() => {
  const perms = page.props.auth?.user?.permissions || [];
  return perms.some(p => ['manage member payments', 'manage own member payments', 'manage partner member payments'].includes(p));
});

const props = defineProps({
  members: {
    type: Object,
    required: true
  },
  filters: {
    type: Object,
    default: () => ({
      search: '',
      role: '',
      status: ''
    })
  },
  chartData: {
    type: Object,
    default: () => ({ daily: {}, monthly: {} })
  },
  chartDays: {
    type: [String, Number],
    default: null
  }
});

const memberStore = useMemberStore();
const { members: storeMembers } = storeToRefs(memberStore);

// Initialize store with props data immediately
memberStore.setMembers(props.members);

// Use props.members directly instead of store members to ensure we have the latest data
const members = computed(() => props.members);

const chartExpanded = ref(false);

const filters = ref(props.filters || {
  search: '',
  is_active: null
});

const chunkSize = ref(0);
const includeFamily = ref(false);
const exportMenuOpen = ref(false);
const exportTriggerRef = ref(null);
const exportMenuRef = ref(null);
const exportMenuStyle = ref({});

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
const allColumnKeys = exportColumnOptions.map(c => c.key);
const selectedColumns = ref([...allColumnKeys]);
const allColumnsSelected = computed(() => selectedColumns.value.length === allColumnKeys.length);
const toggleAllColumns = () => {
  selectedColumns.value = allColumnsSelected.value ? [] : [...allColumnKeys];
};

const MARGIN = 8;
const POPOVER_W = 384; // w-96

const positionExportMenu = () => {
  const r = exportTriggerRef.value?.getBoundingClientRect();
  if (!r) return;
  const right = Math.max(
    MARGIN,
    Math.min(window.innerWidth - r.right, window.innerWidth - POPOVER_W - MARGIN)
  );
  exportMenuStyle.value = {
    top: `${r.bottom + 6}px`,
    right: `${right}px`,
  };
};

const toggleExportMenu = () => {
  exportMenuOpen.value = !exportMenuOpen.value;
  if (exportMenuOpen.value) {
    // Position after the menu is in the DOM.
    requestAnimationFrame(positionExportMenu);
  }
};

const handleClickOutside = (e) => {
  if (!exportMenuOpen.value) return;
  if (exportTriggerRef.value?.contains(e.target)) return;
  if (exportMenuRef.value?.contains(e.target)) return;
  exportMenuOpen.value = false;
};
const closeOnScrollOrResize = () => {
  if (exportMenuOpen.value) positionExportMenu();
};
onMounted(() => {
  document.addEventListener('click', handleClickOutside);
  window.addEventListener('resize', closeOnScrollOrResize);
  window.addEventListener('scroll', closeOnScrollOrResize, true);
});
onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
  window.removeEventListener('resize', closeOnScrollOrResize);
  window.removeEventListener('scroll', closeOnScrollOrResize, true);
});

const handleDelete = (memberSlug) => {
  memberStore.confirmDelete(memberSlug);
};

const handleFilterChange = (newFilters) => {
  filters.value = newFilters;
};

const buildExportUrl = (extraParams = {}) => {
  const params = new URLSearchParams();
  const f = filters.value || {};
  if (f.search && String(f.search).trim()) params.set('search', f.search);
  if (f.email && String(f.email).trim()) params.set('email', String(f.email).trim());
  if (f.phone && String(f.phone).trim()) params.set('phone', String(f.phone).trim());
  if (f.membership_number && String(f.membership_number).trim()) params.set('membership_number', String(f.membership_number).trim());
  if (f.is_active !== null && f.is_active !== undefined && f.is_active !== '') {
    params.set('is_active', f.is_active ? 1 : 0);
  }
  if (f.is_paid !== null && f.is_paid !== undefined && f.is_paid !== '') {
    params.set('is_paid', f.is_paid ? 1 : 0);
  }
  if (f.is_from_card_patch !== null && f.is_from_card_patch !== undefined && f.is_from_card_patch !== '') {
    params.set('is_from_card_patch', f.is_from_card_patch ? 1 : 0);
  }
  if (f.has_custom_card !== null && f.has_custom_card !== undefined && f.has_custom_card !== '') {
    params.set('has_custom_card', f.has_custom_card ? 1 : 0);
  }
  if (f.partner_id !== null && f.partner_id !== undefined && f.partner_id !== '') {
    params.set('partner_id', f.partner_id);
  }
  if (f.creator_id !== null && f.creator_id !== undefined && f.creator_id !== '') {
    params.set('creator_id', f.creator_id);
  }
  if (f.sale_id !== null && f.sale_id !== undefined && f.sale_id !== '') {
    params.set('sale_id', f.sale_id);
  }
  if (f.company_id !== null && f.company_id !== undefined && f.company_id !== '') {
    params.set('company_id', f.company_id);
  }
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
  if (selectedColumns.value.length < allColumnKeys.length) {
    params.set('columns', selectedColumns.value.join(','));
  }
  for (const [k, v] of Object.entries(extraParams)) params.set(k, v);
  if (chunkSize.value > 0) params.set('chunk_size', chunkSize.value);
  const qs = params.toString();
  const base = route('admin.user.membership.export');
  return qs ? `${base}?${qs}` : base;
};

const exportComputedUrl = computed(() => buildExportUrl(includeFamily.value ? { include_family: 1 } : {}));

const exportToPayUrl = computed(() => {
  const params = new URLSearchParams();
  const f = filters.value || {};
  if (f.search && String(f.search).trim()) params.set('search', f.search);
  if (f.email && String(f.email).trim()) params.set('email', String(f.email).trim());
  if (f.phone && String(f.phone).trim()) params.set('phone', String(f.phone).trim());
  if (f.membership_number && String(f.membership_number).trim()) params.set('membership_number', String(f.membership_number).trim());
  if (f.is_active !== null && f.is_active !== undefined && f.is_active !== '') {
    params.set('is_active', f.is_active ? 1 : 0);
  }
  if (f.is_paid !== null && f.is_paid !== undefined && f.is_paid !== '') {
    params.set('is_paid', f.is_paid ? 1 : 0);
  }
  if (f.partner_id !== null && f.partner_id !== undefined && f.partner_id !== '') {
    params.set('partner_id', f.partner_id);
  }
  if (f.creator_id !== null && f.creator_id !== undefined && f.creator_id !== '') {
    params.set('creator_id', f.creator_id);
  }
  if (f.sale_id !== null && f.sale_id !== undefined && f.sale_id !== '') {
    params.set('sale_id', f.sale_id);
  }
  if (f.created_from) params.set('created_from', f.created_from);
  if (f.created_to) params.set('created_to', f.created_to);
  if (f.registration_date_from) params.set('registration_date_from', f.registration_date_from);
  if (f.registration_date_to) params.set('registration_date_to', f.registration_date_to);
  if (f.expiration_date_from) params.set('expiration_date_from', f.expiration_date_from);
  if (f.expiration_date_to) params.set('expiration_date_to', f.expiration_date_to);
  if (f.is_from_card_patch !== null && f.is_from_card_patch !== undefined && f.is_from_card_patch !== '') {
    params.set('is_from_card_patch', f.is_from_card_patch ? 1 : 0);
  }
  if (f.has_custom_card !== null && f.has_custom_card !== undefined && f.has_custom_card !== '') {
    params.set('has_custom_card', f.has_custom_card ? 1 : 0);
  }
  const qs = params.toString();
  const base = route('admin.member-payment.export-to-pay');
  return qs ? `${base}?${qs}` : base;
});

const exportButtonTitle = computed(() => {
  const m = t.value.export_menu || {};
  const parts = [includeFamily.value
    ? (m.tooltip_with_family || 'with family')
    : (m.tooltip_members_only || 'members only')];
  if (chunkSize.value) {
    const split = m.tooltip_split || 'split / :size';
    parts.push(split.replace(':size', chunkSize.value));
  }
  return `${m.tooltip_prefix || 'Export'} — ${parts.join(', ')}`;
});
</script>
