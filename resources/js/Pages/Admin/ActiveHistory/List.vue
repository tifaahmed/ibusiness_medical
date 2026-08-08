<template>
  <MemberLayout>
    <div class="w-full max-w-full">
      <div class="space-y-2 sm:space-y-3 md:space-y-4 p-2 sm:p-3 md:p-4 lg:p-6 w-full max-w-full">
        <!-- Header -->
        <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border py-2 sm:py-3 md:py-4 shadow-sm w-full">
          <div class="flex flex-row items-center justify-between py-2 px-3 sm:px-4 md:px-6 gap-2 sm:gap-4">
            <div class="leading-none font-semibold min-w-0 flex-1">
              <div class="title-golden flex items-center gap-2 min-w-0">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon flex-shrink-0">
                  <rect x="1" y="5" width="22" height="14" rx="7" ry="7"></rect>
                  <circle cx="8" cy="12" r="3"></circle>
                </svg>
                <span class="text-sm sm:text-base truncate min-w-0">
                  Active Status History
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Filters -->
        <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
          <div class="p-3 sm:p-4 md:p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-2 sm:gap-3">
              <!-- Search by name -- always visible -->
              <div ref="searchContainer" class="min-w-0 relative">
                <label data-slot="label" class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full ltr:justify-start rtl:justify-end ltr:text-left rtl:text-right mb-1" for="search">
                  Search Member
                </label>
                <div class="relative">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    class="lucide lucide-search absolute left-2 sm:left-2.5 md:left-3 top-1/2 -translate-y-1/2 h-3 w-3 sm:h-3.5 sm:w-3.5 md:h-4 md:w-4 text-muted-foreground pointer-events-none z-10">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.3-4.3"></path>
                  </svg>
                  <input id="search" type="text" autocomplete="off" v-model="filters.search"
                    @input="handleSearchInput"
                    @focus="showSuggestions = true"
                    @keydown.escape="showSuggestions = false"
                    @keydown.down.prevent="moveSuggestion(1)"
                    @keydown.up.prevent="moveSuggestion(-1)"
                    @keydown.enter.prevent="confirmHighlighted"
                    placeholder="Search by name..."
                    class="placeholder:text-white dark:bg-input/30 border border-border text-foreground flex h-7 sm:h-8 md:h-9 w-full min-w-0 max-w-full rounded-md bg-transparent px-2 sm:px-2.5 md:px-3 py-1 text-xs sm:text-sm md:text-base shadow-xs transition-all outline-none [color-scheme:dark] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:bg-secondary/10 pl-7 sm:pl-8 md:pl-9 box-border" />
                </div>
                <ul v-if="showSuggestions && userNames.length > 0"
                  class="absolute z-50 left-0 right-0 mt-1 bg-[#0a0d14] border border-border rounded-md shadow-xl max-h-60 overflow-y-auto">
                  <li v-if="suggestions.length === 0" class="px-2.5 py-1.5 text-xs text-muted-foreground italic">No matches</li>
                  <li v-for="(item, index) in suggestions" :key="item.id"
                    @mousedown.prevent="selectSuggestion(item)"
                    :class="[
                      'px-2.5 py-1.5 cursor-pointer text-xs flex items-center gap-2 transition-colors',
                      highlightedIndex === index ? 'bg-primary text-primary-foreground' : 'text-foreground hover:bg-white/5'
                    ]">
                    <svg class="w-3 h-3 shrink-0 opacity-50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <circle cx="11" cy="11" r="8"></circle>
                      <path d="m21 21-4.3-4.3"></path>
                    </svg>
                    <span class="flex-1 truncate">{{ item.name }}</span>
                  </li>
                </ul>
              </div>

              <!-- Membership Number Filter -->
              <div :class="['min-w-0', showAdvanced ? '' : 'hidden sm:block']">
                <label data-slot="label" class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full ltr:justify-start rtl:justify-end ltr:text-left rtl:text-right mb-1" for="membership_number">
                  Membership No.
                </label>
                <input id="membership_number" v-model="filters.membership_number" @input="handleMembershipNumberInput" type="text" placeholder="Search by number..."
                  class="placeholder:text-white dark:bg-input/30 border border-border text-foreground flex h-7 sm:h-8 md:h-9 w-full min-w-0 max-w-full rounded-md bg-transparent px-2 sm:px-2.5 md:px-3 py-1 text-xs sm:text-sm shadow-xs transition-all outline-none [color-scheme:dark] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] box-border" />
              </div>

              <!-- Phone Filter -->
              <div :class="['min-w-0', showAdvanced ? '' : 'hidden sm:block']">
                <label data-slot="label" class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full ltr:justify-start rtl:justify-end ltr:text-left rtl:text-right mb-1" for="phone">
                  Phone
                </label>
                <input id="phone" v-model="filters.phone" @input="handlePhoneInput" type="text" placeholder="Search by phone..."
                  class="placeholder:text-white dark:bg-input/30 border border-border text-foreground flex h-7 sm:h-8 md:h-9 w-full min-w-0 max-w-full rounded-md bg-transparent px-2 sm:px-2.5 md:px-3 py-1 text-xs sm:text-sm shadow-xs transition-all outline-none [color-scheme:dark] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] box-border" />
              </div>

              <!-- Mobile-only advanced toggle -->
              <button type="button" @click="showAdvanced = !showAdvanced"
                class="sm:hidden inline-flex items-center justify-center gap-1.5 w-full px-3 h-9 rounded-md text-xs font-medium border border-border bg-transparent text-foreground hover:bg-primary hover:text-primary-foreground transition-colors cursor-pointer">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <line x1="4" x2="20" y1="6" y2="6"></line>
                  <line x1="8" x2="20" y1="12" y2="12"></line>
                  <line x1="12" x2="20" y1="18" y2="18"></line>
                </svg>
                {{ showAdvanced ? 'Hide advanced search' : 'Advanced search' }}
                <span v-if="advancedActiveCount > 0" class="ml-1 inline-flex items-center justify-center rounded-full bg-primary text-primary-foreground text-[10px] font-semibold w-4 h-4">{{ advancedActiveCount }}</span>
              </button>

              <!-- Sales Filter -->
              <div :class="['min-w-0', showAdvanced ? '' : 'hidden sm:block']">
                <label data-slot="label" class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full ltr:justify-start rtl:justify-end ltr:text-left rtl:text-right mb-1" for="sale_id">
                  Sales
                </label>
                <Select v-model="filters.sale_id" :options="salesOptions" placeholder="All sales" id="sale_id" @change="applyFilters()" />
              </div>

              <!-- Partner Filter -->
              <div :class="['min-w-0', showAdvanced ? '' : 'hidden sm:block']">
                <label data-slot="label" class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full ltr:justify-start rtl:justify-end ltr:text-left rtl:text-right mb-1" for="partner_id">
                  Partner
                </label>
                <Select v-model="filters.partner_id" :options="partnerOptions" placeholder="All partners" id="partner_id" @change="applyFilters()" />
              </div>

              <!-- Changed By Filter -->
              <div :class="['min-w-0', showAdvanced ? '' : 'hidden sm:block']">
                <label data-slot="label" class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full ltr:justify-start rtl:justify-end ltr:text-left rtl:text-right mb-1" for="changed_by">
                  Changed By
                </label>
                <Select v-model="filters.changed_by" :options="changedByOptions" placeholder="All changers" id="changed_by" @change="applyFilters()" />
              </div>

              <!-- Created From -->
              <div :class="['min-w-0', showAdvanced ? '' : 'hidden sm:block']">
                <label data-slot="label" class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full ltr:justify-start rtl:justify-end ltr:text-left rtl:text-right mb-1" for="created_from">
                  Created From
                </label>
                <input id="created_from" v-model="filters.created_from" @change="applyFilters()" type="date"
                  class="border border-border bg-transparent text-foreground rounded-md flex h-7 sm:h-8 md:h-9 w-full min-w-0 max-w-full px-2 sm:px-2.5 md:px-3 py-1 text-xs sm:text-sm shadow-xs transition-all outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] [color-scheme:dark] cursor-pointer" />
              </div>

              <!-- Created To -->
              <div :class="['min-w-0', showAdvanced ? '' : 'hidden sm:block']">
                <label data-slot="label" class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full ltr:justify-start rtl:justify-end ltr:text-left rtl:text-right mb-1" for="created_to">
                  Created To
                </label>
                <input id="created_to" v-model="filters.created_to" @change="applyFilters()" type="date" :min="filters.created_from || null"
                  class="border border-border bg-transparent text-foreground rounded-md flex h-7 sm:h-8 md:h-9 w-full min-w-0 max-w-full px-2 sm:px-2.5 md:px-3 py-1 text-xs sm:text-sm shadow-xs transition-all outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] [color-scheme:dark] cursor-pointer" />
              </div>

              <!-- Clear -->
              <div :class="['min-w-0 flex items-end', showAdvanced ? '' : 'hidden sm:block']">
                <button v-if="hasActiveFilters" @click="resetFilters"
                  class="cursor-pointer justify-center whitespace-nowrap text-xs font-medium transition-all disabled:pointer-events-none disabled:opacity-50 outline-none bg-destructive text-white shadow-xs hover:bg-destructive/90 h-7 sm:h-8 rounded-md px-2 sm:px-3 inline-flex items-center gap-1.5 sm:gap-2">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x h-3 w-3 sm:h-3.5 sm:w-3.5">
                    <path d="M18 6 6 18"></path>
                    <path d="m6 6 12 12"></path>
                  </svg>
                  <span class="hidden sm:inline">Clear</span>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Histories Table -->
        <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
          <div v-if="histories?.data?.length > 0">
            <div class="overflow-x-auto">
              <table class="w-full caption-bottom text-xs sm:text-sm py-3 sm:py-4">
                <thead class="[&_tr]:border-b [&_tr]:border-border">
                  <tr class="border-b border-border transition-colors">
                    <th v-if="cols.created_at" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap w-44">When</th>
                    <th v-if="cols.member" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap">Member</th>
                    <th v-if="cols.sales" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap">Sales</th>
                    <th v-if="cols.partner" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap">Partner</th>
                    <th v-if="cols.old_status" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-center align-middle font-medium whitespace-nowrap">Previous Status</th>
                    <th v-if="cols.new_status" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-center align-middle font-medium whitespace-nowrap">New Status</th>
                    <th v-if="cols.changed_by" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap">Changed By</th>
                  </tr>
                </thead>
                <tbody class="[&_tr:last-child]:border-0">
                  <tr v-for="history in histories.data" :key="history.id" class="border-b border-border transition-colors hover:bg-muted/50">
                    <td v-if="cols.created_at" class="p-2 sm:p-3 align-middle whitespace-nowrap">
                      <div class="flex flex-col">
                        <span class="text-foreground">{{ formatDateTime(history.created_at) }}</span>
                        <span class="text-muted-foreground text-xs">{{ relativeTime(history.created_at) }}</span>
                      </div>
                    </td>
                    <td v-if="cols.member" class="p-2 sm:p-3 align-middle">
                      <Link v-if="history.member" :href="route('admin.user.membership.show', history.member.slug)" class="flex flex-col min-w-0 hover:underline">
                        <span class="font-medium text-foreground truncate">{{ history.member.name }}</span>
                        <span class="text-muted-foreground text-xs truncate">{{ history.member.membership_number }}</span>
                        <span v-if="history.member.phone" class="text-muted-foreground/70 text-[11px] truncate">{{ history.member.phone }}</span>
                        <span class="text-muted-foreground text-xs truncate">{{ history.member.email }}</span>
                      </Link>
                      <span v-else class="text-muted-foreground italic text-xs">deleted member</span>
                    </td>
                    <td v-if="cols.sales" class="p-2 sm:p-3 align-middle whitespace-nowrap">
                      <span class="text-foreground text-xs">{{ history.sales?.label || '-' }}</span>
                    </td>
                    <td v-if="cols.partner" class="p-2 sm:p-3 align-middle whitespace-nowrap">
                      <span class="text-foreground text-xs">{{ history.partner?.title || '-' }}</span>
                    </td>
                    <td v-if="cols.old_status" class="p-2 sm:p-3 align-middle text-center">
                      <span :class="statusBadgeClass(history.old_is_active)">
                        {{ history.old_is_active ? 'Active' : 'Inactive' }}
                      </span>
                    </td>
                    <td v-if="cols.new_status" class="p-2 sm:p-3 align-middle text-center">
                      <span :class="statusBadgeClass(history.new_is_active)">
                        {{ history.new_is_active ? 'Active' : 'Inactive' }}
                      </span>
                      <svg v-if="history.old_is_active !== history.new_is_active" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline-block ml-1 text-amber-400">
                        <path d="M5 12h14"/>
                        <path d="m12 5 7 7-7 7"/>
                      </svg>
                    </td>
                    <td v-if="cols.changed_by" class="p-2 sm:p-3 align-middle">
                      <div v-if="history.changer" class="flex flex-col min-w-0">
                        <span class="font-medium text-foreground truncate">{{ history.changer.name }}</span>
                        <span class="text-muted-foreground text-xs truncate">{{ history.changer.email }}</span>
                      </div>
                      <span v-else class="text-muted-foreground italic text-xs">system / unknown</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="border-t border-border px-3 sm:px-4 md:px-6 py-2.5 sm:py-3">
              <div class="flex flex-row items-center justify-between gap-2 flex-wrap">
                <div class="flex items-center gap-2">
                  <div class="text-xs sm:text-sm text-muted-foreground">
                    Showing {{ histories.meta?.from || 0 }} to {{ histories.meta?.to || 0 }} of {{ histories.meta?.total || 0 }}
                  </div>
                  <!-- Columns toggle -->
                  <div class="relative">
                    <button
                      ref="columnToggleRef"
                      type="button"
                      @click="showColumnMenu = !showColumnMenu"
                      class="inline-flex items-center cursor-pointer justify-center gap-1.5 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-7 sm:h-8 px-2 sm:px-3"
                      title="Toggle columns"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5">
                        <rect width="18" height="18" x="3" y="3" rx="2" ry="2"></rect>
                        <line x1="3" x2="21" y1="9" y2="9"></line>
                        <line x1="3" x2="21" y1="15" y2="15"></line>
                        <line x1="9" x2="9" y1="3" y2="21"></line>
                        <line x1="15" x2="15" y1="3" y2="21"></line>
                      </svg>
                      <span class="hidden sm:inline">Columns</span>
                    </button>
                    <Teleport to="body">
                      <div
                        v-if="showColumnMenu"
                        ref="columnMenuRef"
                        :style="columnMenuStyle"
                        class="fixed z-[1000] w-48 rounded-md border border-border bg-popover text-popover-foreground shadow-2xl p-2 space-y-0.5"
                      >
                        <label
                          v-for="col in columnOptions"
                          :key="col.key"
                          class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-muted/50 cursor-pointer"
                        >
                          <input
                            type="checkbox"
                            :checked="cols[col.key]"
                            @change="toggleColumn(col.key)"
                            class="h-3.5 w-3.5 rounded border-border accent-primary"
                          />
                          <span class="text-xs text-foreground">{{ col.label }}</span>
                        </label>
                      </div>
                    </Teleport>
                  </div>
                </div>
                <Pagination v-if="histories?.meta?.links?.length > 0" :links="histories.meta.links" />
              </div>
            </div>
          </div>

          <!-- Empty state -->
          <div v-else class="p-12 text-center">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-golden-yellow/10 border border-golden-yellow/20 mb-6">
              <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-golden-yellow">
                <rect x="1" y="5" width="22" height="14" rx="7" ry="7"></rect>
                <circle cx="8" cy="12" r="3"></circle>
              </svg>
            </div>
            <h3 class="text-xl font-bold mb-1 text-foreground">No history yet</h3>
            <p class="text-muted-foreground text-sm">
              The active status has never been changed for any member.
            </p>
          </div>
        </div>
      </div>
    </div>
  </MemberLayout>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { router, usePage, Link } from "@inertiajs/vue3";
import { onClickOutside } from '@vueuse/core';
import Fuse from 'fuse.js';
import MemberLayout from "@/Pages/Admin/User/Member/MemberLayout.vue";
import Pagination from "@/Pages/_components/Pagination.vue";
import Select from "@/Components/ui/Select.vue";

const page = usePage();
const userNames = computed(() => page.props.userNames || []);

const props = defineProps({
  histories: { type: Object, required: true },
  filters: { type: Object, default: () => ({ search: '', membership_number: '', phone: '', partner_id: null, sale_id: null, changed_by: '', created_from: '', created_to: '' }) },
  salesOptions: { type: Array, default: () => [] },
  partnerOptions: { type: Array, default: () => [] },
  changedByOptions: { type: Array, default: () => [] },
});

const filters = ref({
  search: props.filters?.search || '',
  membership_number: props.filters?.membership_number || '',
  phone: props.filters?.phone || '',
  partner_id: props.filters?.partner_id || null,
  sale_id: props.filters?.sale_id || null,
  changed_by: props.filters?.changed_by || '',
  created_from: props.filters?.created_from || '',
  created_to: props.filters?.created_to || '',
});

const hasActiveFilters = computed(() =>
  filters.value.search
  || filters.value.membership_number
  || filters.value.phone
  || filters.value.partner_id
  || filters.value.sale_id
  || filters.value.changed_by
  || filters.value.created_from
  || filters.value.created_to
);

const advancedActiveCount = computed(() => {
  let n = 0;
  if (filters.value.membership_number) n++;
  if (filters.value.phone) n++;
  if (filters.value.partner_id) n++;
  if (filters.value.sale_id) n++;
  if (filters.value.changed_by) n++;
  if (filters.value.created_from) n++;
  if (filters.value.created_to) n++;
  return n;
});

const showAdvanced = ref(advancedActiveCount.value > 0);

// Column visibility toggle
const STORAGE_KEY = 'active_history_columns';
const columnOptions = [
  { key: 'created_at', label: 'When' },
  { key: 'member', label: 'Member' },
  { key: 'sales', label: 'Sales' },
  { key: 'partner', label: 'Partner' },
  { key: 'old_status', label: 'Previous Status' },
  { key: 'new_status', label: 'New Status' },
  { key: 'changed_by', label: 'Changed By' },
];
const defaultCols = {
  created_at: true,
  member: true,
  sales: true,
  partner: true,
  old_status: true,
  new_status: true,
  changed_by: true,
};

function loadCols() {
  try {
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved) return { ...defaultCols, ...JSON.parse(saved) };
  } catch {}
  return { ...defaultCols };
}

const cols = ref(loadCols());

watch(cols, (val) => {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(val));
  } catch {}
}, { deep: true });

const showColumnMenu = ref(false);
const columnToggleRef = ref(null);
const columnMenuRef = ref(null);
const columnMenuStyle = ref({});

function positionColumnMenu() {
  if (!columnToggleRef.value) return;
  const r = columnToggleRef.value.getBoundingClientRect();
  const menuW = 192;
  const menuH = 260;
  const margin = 8;
  let left = r.left;
  if (left + menuW > window.innerWidth - margin) {
    left = window.innerWidth - menuW - margin;
  }
  columnMenuStyle.value = {
    top: `${r.top - menuH - 4}px`,
    left: `${Math.max(margin, left)}px`,
  };
}

function toggleColumn(key) {
  cols.value = { ...cols.value, [key]: !cols.value[key] };
}

watch(showColumnMenu, (val) => {
  if (val) {
    requestAnimationFrame(positionColumnMenu);
  }
});

function handleColumnClickOutside(e) {
  if (!showColumnMenu.value) return;
  if (columnToggleRef.value?.contains(e.target)) return;
  if (columnMenuRef.value?.contains(e.target)) return;
  showColumnMenu.value = false;
}

onMounted(() => {
  document.addEventListener('click', handleColumnClickOutside);
});
onUnmounted(() => {
  document.removeEventListener('click', handleColumnClickOutside);
});

// Autocomplete
const searchContainer = ref(null);
const showSuggestions = ref(false);
const highlightedIndex = ref(-1);

const fuse = computed(() => new Fuse(userNames.value, {
  keys: ['name'],
  threshold: 0.45,
  ignoreLocation: true,
  minMatchCharLength: 1,
  includeScore: true,
}));

const suggestions = computed(() => {
  const q = (filters.value.search || '').trim();
  if (!q) return userNames.value.slice(0, 8);
  return fuse.value.search(q).slice(0, 8).map(r => r.item);
});

watch(suggestions, () => { highlightedIndex.value = -1; });

const selectSuggestion = (item) => {
  filters.value.search = item.name;
  showSuggestions.value = false;
  highlightedIndex.value = -1;
  if (searchTimeout) clearTimeout(searchTimeout);
  applyFilters();
};

const moveSuggestion = (dir) => {
  if (!suggestions.value.length) return;
  highlightedIndex.value = Math.max(-1, Math.min(suggestions.value.length - 1, highlightedIndex.value + dir));
};

const confirmHighlighted = () => {
  const item = suggestions.value[highlightedIndex.value];
  if (item) {
    selectSuggestion(item);
  } else {
    showSuggestions.value = false;
    if (searchTimeout) clearTimeout(searchTimeout);
    applyFilters();
  }
};

onClickOutside(searchContainer, () => { showSuggestions.value = false; });

let searchTimeout = null;
const handleSearchInput = () => {
  showSuggestions.value = true;
  if (searchTimeout) clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    if (!filters.value.search?.trim()) {
      applyFilters();
    }
  }, 300);
};

let membershipNumberTimeout = null;
let phoneTimeout = null;

const handleMembershipNumberInput = () => {
  if (membershipNumberTimeout) clearTimeout(membershipNumberTimeout);
  membershipNumberTimeout = setTimeout(() => {
    applyFilters();
  }, 300);
};

const handlePhoneInput = () => {
  if (phoneTimeout) clearTimeout(phoneTimeout);
  phoneTimeout = setTimeout(() => {
    applyFilters();
  }, 300);
};

const applyFilters = () => {
  const params = {};
  if (filters.value.search?.trim()) params.search = filters.value.search.trim();
  if (filters.value.membership_number?.trim()) params.membership_number = filters.value.membership_number.trim();
  if (filters.value.phone?.trim()) params.phone = filters.value.phone.trim();
  if (filters.value.partner_id) params.partner_id = filters.value.partner_id;
  if (filters.value.sale_id) params.sale_id = filters.value.sale_id;
  if (filters.value.changed_by) params.changed_by = filters.value.changed_by;
  if (filters.value.created_from) params.created_from = filters.value.created_from;
  if (filters.value.created_to) params.created_to = filters.value.created_to;

  router.get(route('admin.active-history.list'), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const resetFilters = () => {
  filters.value = { search: '', membership_number: '', phone: '', partner_id: null, sale_id: null, changed_by: '', created_from: '', created_to: '' };
  router.get(route('admin.active-history.list'), {}, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const formatDateTime = (s) => {
  if (!s) return '-';
  return new Date(s).toLocaleString('en-US', {
    year: 'numeric', month: 'short', day: 'numeric',
    hour: '2-digit', minute: '2-digit',
  });
};

const relativeTime = (s) => {
  if (!s) return '';
  const diffMs = Date.now() - new Date(s).getTime();
  const sec = Math.floor(diffMs / 1000);
  if (sec < 60) return `${sec}s ago`;
  const min = Math.floor(sec / 60);
  if (min < 60) return `${min}m ago`;
  const hr = Math.floor(min / 60);
  if (hr < 24) return `${hr}h ago`;
  const days = Math.floor(hr / 24);
  if (days < 30) return `${days}d ago`;
  const months = Math.floor(days / 30);
  if (months < 12) return `${months}mo ago`;
  return `${Math.floor(months / 12)}y ago`;
};

const statusBadgeClass = (active) => active
  ? 'inline-flex items-center rounded-md px-2 py-1 text-xs font-semibold tracking-wide shadow-sm ring-1 bg-emerald-600 text-white ring-emerald-400/40'
  : 'inline-flex items-center rounded-md px-2 py-1 text-xs font-semibold tracking-wide shadow-sm ring-1 bg-rose-600 text-white ring-rose-400/40';
</script>
