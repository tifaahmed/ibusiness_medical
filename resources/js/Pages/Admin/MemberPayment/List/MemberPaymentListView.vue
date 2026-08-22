<template>
  <MemberPaymentLayout>
    <div class="flex flex-col h-full lg:h-auto w-full max-w-full overflow-x-hidden">
      <div class="flex-shrink-0 space-y-2 sm:space-y-3 md:space-y-4 p-2 sm:p-3 md:p-4 lg:p-6 pb-2 sm:pb-3 md:pb-4 lg:pb-4 w-full max-w-full overflow-hidden">
        <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-2 sm:gap-3 md:gap-4 rounded-xl border border-border py-2 sm:py-3 md:py-4 shadow-sm overflow-hidden w-full max-w-full">
          <div data-slot="card-header" class="flex flex-row items-center justify-between py-2 px-3 sm:px-4 md:px-6 w-full overflow-hidden gap-2 sm:gap-4">
            <div data-slot="card-title" class="leading-none font-semibold min-w-0 flex-1">
              <div class="title-golden min-w-0 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon sm:w-6 sm:h-6 flex-shrink-0">
                  <path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2z"></path>
                  <path d="M12 6v6l4 2"></path>
                </svg>
                <span class="text-sm sm:text-base truncate block min-w-0">{{ t.title || 'Payments' }}</span>
              </div>
            </div>
            <Link
              v-if="canWrite"
              :href="route('admin.member-payment.import.page')"
              data-slot="button"
              class="inline-flex items-center cursor-pointer justify-center gap-1.5 sm:gap-2 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 border bg-background text-foreground shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 sm:h-9 px-2 sm:px-3 md:px-4 py-2 flex-shrink-0"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-upload h-3.5 w-3.5 sm:h-4 sm:w-4">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="17 8 12 3 7 8"></polyline>
                <line x1="12" x2="12" y1="3" y2="15"></line>
              </svg>
              <span class="hidden sm:inline">{{ t.import_payment_title || 'Import' }}</span>
              <span class="sm:hidden">{{ t.import_payment_title || 'Imp' }}</span>
            </Link>
            <Link
              v-if="canWrite"
              :href="route('admin.member-payment.create')"
              data-slot="button"
              class="inline-flex items-center cursor-pointer justify-center gap-1.5 sm:gap-2 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-8 sm:h-9 px-2 sm:px-3 md:px-4 py-2 flex-shrink-0 btn-golden"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus h-3.5 w-3.5 sm:h-4 sm:w-4">
                <path d="M5 12h14"></path>
                <path d="M12 5v14"></path>
              </svg>
              <span class="hidden sm:inline">{{ t.add_new || 'Add Payment' }}</span>
              <span class="sm:hidden">{{ t.add_new || 'Add' }}</span>
            </Link>
          </div>

          <div data-slot="card-content" class="px-2 sm:px-4 md:px-6 space-y-2 sm:space-y-3 md:space-y-4 w-full max-w-full overflow-hidden min-w-0">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-2 sm:gap-3 w-full">
              <!-- Name — always visible -->
              <div ref="searchContainer" class="min-w-0 relative">
                <label
                  data-slot="label"
                  class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full mb-1"
                >
                  {{ t.filter_name_label || 'Name' }}
                </label>
                <div class="relative">
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="24"
                    height="24"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    class="lucide lucide-search absolute left-2 sm:left-2.5 md:left-3 top-1/2 -translate-y-1/2 h-3 w-3 sm:h-3.5 sm:w-3.5 md:h-4 md:w-4 text-muted-foreground pointer-events-none z-10"
                  >
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.3-4.3"></path>
                  </svg>
                  <input
                    type="text"
                    autocomplete="off"
                    v-model="name"
                    @input="handleSearchInput"
                    @focus="showSuggestions = true"
                    @keydown.escape="showSuggestions = false"
                    @keydown.down.prevent="moveSuggestion(1)"
                    @keydown.up.prevent="moveSuggestion(-1)"
                    @keydown.enter.prevent="confirmHighlighted"
                    :placeholder="t.filter_name_placeholder || 'Search by name...'"
                    class="placeholder:text-white dark:bg-input/30 border border-border text-foreground flex h-7 sm:h-8 md:h-9 w-full min-w-0 max-w-full rounded-md bg-transparent px-2 sm:px-2.5 md:px-3 py-1 text-xs sm:text-sm shadow-xs transition-all outline-none [color-scheme:dark] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:bg-secondary/10 pl-7 sm:pl-8 md:pl-9 box-border"
                  />
                </div>
                <ul v-if="showSuggestions && userNames.length > 0"
                  class="absolute z-50 left-0 right-0 mt-1 bg-[#0a0d14] border border-border rounded-md shadow-xl max-h-60 overflow-y-auto">
                  <li v-if="suggestions.length === 0" class="px-2.5 py-1.5 text-xs text-muted-foreground italic">{{ t.no_matches || 'No matches' }}</li>
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

              <!-- Mobile advanced toggle -->
              <button
                type="button"
                @click="showAdvanced = !showAdvanced"
                class="sm:hidden inline-flex items-center justify-center gap-1.5 w-full px-3 h-9 rounded-md text-xs font-medium border border-border bg-transparent text-foreground hover:bg-primary hover:text-primary-foreground transition-colors"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <line x1="4" x2="20" y1="6" y2="6"></line>
                  <line x1="8" x2="20" y1="12" y2="12"></line>
                  <line x1="12" x2="20" y1="18" y2="18"></line>
                </svg>
                {{ showAdvanced ? (t.hide_advanced || 'Hide advanced') : (t.show_advanced || 'Advanced') }}
              </button>

              <!-- Advanced filters — hidden on mobile until toggled -->
              <div :class="['min-w-0', showAdvanced ? '' : 'hidden sm:block']">
                <label
                  data-slot="label"
                  class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full mb-1"
                >
                  {{ t.filter_membership_label || 'Membership No.' }}
                </label>
                <input
                  type="text"
                  v-model="membershipNumber"
                  :placeholder="t.filter_membership_placeholder || 'Search by number...'"
                  class="placeholder:text-white dark:bg-input/30 border border-border text-foreground flex h-7 sm:h-8 md:h-9 w-full min-w-0 max-w-full rounded-md bg-transparent px-2 sm:px-2.5 md:px-3 py-1 text-xs sm:text-sm shadow-xs transition-all outline-none [color-scheme:dark] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:bg-secondary/10 box-border"
                />
              </div>
              <div :class="['min-w-0', showAdvanced ? '' : 'hidden sm:block']">
                <label
                  data-slot="label"
                  class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full mb-1"
                >
                  {{ t.filter_phone_label || 'Phone' }}
                </label>
                <input
                  type="text"
                  v-model="phone"
                  :placeholder="t.filter_phone_placeholder || 'Search by phone...'"
                  class="placeholder:text-white dark:bg-input/30 border border-border text-foreground flex h-7 sm:h-8 md:h-9 w-full min-w-0 max-w-full rounded-md bg-transparent px-2 sm:px-2.5 md:px-3 py-1 text-xs sm:text-sm shadow-xs transition-all outline-none [color-scheme:dark] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:bg-secondary/10 box-border"
                />
              </div>
              <div :class="['min-w-0', showAdvanced ? '' : 'hidden sm:block']">
                <label
                  data-slot="label"
                  class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full mb-1"
                >
                  {{ t.filter_email_label || 'Email' }}
                </label>
                <input
                  type="text"
                  v-model="email"
                  :placeholder="t.filter_email_placeholder || 'Search by email...'"
                  class="placeholder:text-white dark:bg-input/30 border border-border text-foreground flex h-7 sm:h-8 md:h-9 w-full min-w-0 max-w-full rounded-md bg-transparent px-2 sm:px-2.5 md:px-3 py-1 text-xs sm:text-sm shadow-xs transition-all outline-none [color-scheme:dark] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:bg-secondary/10 box-border"
                />
              </div>
              <div :class="['min-w-0', showAdvanced ? '' : 'hidden sm:block']">
                <label
                  data-slot="label"
                  class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full mb-1"
                >
                  {{ t.filter_partner_label || 'Partner' }}
                </label>
                <Select
                  v-model="partnerId"
                  :options="partnerOptions"
                  :placeholder="t.filter_all_partners || 'All partners'"
                  id="partner_id"
                />
              </div>
              <div :class="['min-w-0', showAdvanced ? '' : 'hidden sm:block']">
                <label
                  data-slot="label"
                  class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full mb-1"
                >
                  {{ t.col_type || 'Type' }}
                </label>
                <Select
                  v-model="typeFilter"
                  :options="typeOptions"
                  :placeholder="t.filter_all_types || 'All types'"
                  id="type"
                />
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="flex-1 min-h-0 lg:min-h-fit w-full max-w-full px-2 sm:px-3 md:px-4 lg:px-6 pb-2 sm:pb-3 md:pb-4 lg:pb-6 overflow-hidden lg:overflow-visible">
        <MemberPaymentListTable :payments="payments" @delete="handleDelete" />
      </div>
    </div>
  </MemberPaymentLayout>
</template>

<script setup>
import MemberPaymentLayout from "../MemberPaymentLayout.vue";
import MemberPaymentListTable from "./MemberPaymentListTable.vue";
import { useMemberPaymentStore } from "../Stores/MemberPaymentStore";
import { Link, router, usePage } from "@inertiajs/vue3";
import { ref, computed, watch } from "vue";
import { onClickOutside } from '@vueuse/core';
import Fuse from 'fuse.js';
import Select from "@/Components/ui/Select.vue";
import { usePermissions } from '@/composables/usePermissions';

const { canManage } = usePermissions();
// Create/export/import are writes: hidden from read-only accounts,
// and refused by the routes behind them either way.
const canWrite = computed(() => canManage('manage member payments', 'manage own member payments', 'manage partner member payments'));


const props = defineProps({
  payments: { type: Object, required: true },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin?.member_payment || {});
const partnerOptions = computed(() => page.props.partnerOptions || []);
const userNames = computed(() => page.props.userNames || []);

const getParam = (key) => {
  if (typeof window === 'undefined') return '';
  return new URLSearchParams(window.location.search).get(key) || '';
};

const showAdvanced = ref(false);

const name = ref(getParam('name'));
const membershipNumber = ref(getParam('membership_number'));
const phone = ref(getParam('phone'));
const email = ref(getParam('email'));
const partnerId = ref(getParam('partner_id'));
const typeFilter = ref(getParam('type'));

const typeOptions = computed(() => [
  { value: 'commission', label: t.value.type_commission || 'Commission' },
  { value: 'profit', label: t.value.type_profit || 'Profit' },
  { value: 'free', label: t.value.type_free || 'Free' },
]);

const paymentStore = useMemberPaymentStore();
paymentStore.setPayments(props.payments);

const handleDelete = (id) => {
  paymentStore.confirmDelete(id);
};

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
  const q = (name.value || '').trim();
  if (!q) return userNames.value.slice(0, 8);
  return fuse.value.search(q).slice(0, 8).map(r => r.item);
});

watch(suggestions, () => { highlightedIndex.value = -1; });

const selectSuggestion = (item) => {
  name.value = item.name;
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
    applyFilters();
  }, 300);
};

const applyFilters = () => {
  const params = { page: '1' };
  if (name.value?.trim()) params.name = name.value.trim();
  if (membershipNumber.value) params.membership_number = membershipNumber.value;
  if (phone.value) params.phone = phone.value;
  if (email.value) params.email = email.value;
  if (partnerId.value) params.partner_id = partnerId.value;
  if (typeFilter.value) params.type = typeFilter.value;
  router.visit(window.location.pathname + '?' + new URLSearchParams(params).toString(), {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

let debounceTimer = null;
const debounceApply = () => {
  if (debounceTimer) clearTimeout(debounceTimer);
  debounceTimer = setTimeout(applyFilters, 300);
};

watch(name, debounceApply);
watch(membershipNumber, debounceApply);
watch(phone, debounceApply);
watch(email, debounceApply);
watch(partnerId, debounceApply);
watch(typeFilter, debounceApply);
</script>
