<template>
  <AppLayout :title="t.title || 'Contact Messages'">
    <div class="w-full max-w-full">
      <div class="space-y-2 sm:space-y-3 md:space-y-4 p-2 sm:p-3 md:p-4 lg:p-6 w-full max-w-full">
        <!-- Header -->
        <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border py-2 sm:py-3 md:py-4 shadow-sm w-full">
          <div class="flex flex-row items-center justify-between py-2 px-3 sm:px-4 md:px-6 gap-2 sm:gap-4">
            <div class="leading-none font-semibold min-w-0 flex-1">
              <div class="title-golden flex items-center gap-2 min-w-0">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon flex-shrink-0 text-blue-400">
                  <rect width="20" height="16" x="2" y="4" rx="2"></rect>
                  <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
                </svg>
                <span class="text-sm sm:text-base truncate min-w-0">{{ t.title || 'Contact Messages' }}</span>
              </div>
            </div>
            <div class="flex items-center gap-1.5 sm:gap-2 flex-shrink-0">
              <button
                v-if="canManage"
                ref="exportTriggerRef"
                type="button"
                @click="toggleExportMenu"
                class="inline-flex items-center cursor-pointer justify-center gap-1.5 sm:gap-2 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium transition-all border bg-background shadow-xs hover:bg-accent h-8 sm:h-9 px-2 sm:px-3 md:px-4 py-2 text-foreground"
                :title="t.export_tooltip || 'Export the filtered messages to Excel'"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 sm:h-4 sm:w-4 text-emerald-600">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                  <path d="m7 10 5 5 5-5"/>
                  <path d="M12 15V3"/>
                </svg>
                <span class="hidden sm:inline">{{ t.export || 'Export' }}</span>
                <span class="sm:hidden">{{ t.export_short || 'Export' }}</span>
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3 w-3 opacity-70">
                  <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
              </button>
              <Teleport to="body">
                <div
                  v-if="exportMenuOpen"
                  ref="exportMenuRef"
                  :style="exportMenuStyle"
                  class="fixed z-[1000] w-96 rounded-md border border-border bg-popover text-popover-foreground shadow-2xl p-3 space-y-3"
                >
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
                  <div class="pt-2 border-t border-border">
                    <a
                      :href="exportComputedUrl"
                      @click="exportMenuOpen = false"
                      class="block w-full text-center bg-primary text-primary-foreground rounded-md px-3 py-2 text-xs font-semibold hover:bg-primary/90"
                    >
                      {{ chunkSize ? (t.export_menu?.download_zip || 'Download ZIP') : (t.export_menu?.download_excel || 'Download Excel') }}
                    </a>
                  </div>
                </div>
              </Teleport>
            </div>
          </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2 sm:gap-3">
          <div v-for="stat in statCards" :key="stat.key"
            class="bg-card text-card-foreground rounded-xl border border-border p-3 sm:p-4 shadow-sm"
          >
            <div class="text-xs text-muted-foreground mb-1">{{ stat.label }}</div>
            <div class="text-xl sm:text-2xl font-bold" :class="stat.color">{{ stat.count }}</div>
          </div>
        </div>

        <!-- Filters -->
        <FilterAccordion>
          <div class="p-3 sm:p-4 md:p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 sm:gap-3">
              <div class="min-w-0">
                <label class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full mb-1">{{ t.search || 'Search' }}</label>
                <div class="relative">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    class="absolute left-2 sm:left-2.5 md:left-3 top-1/2 -translate-y-1/2 h-3 w-3 sm:h-3.5 sm:w-3.5 md:h-4 md:w-4 text-muted-foreground pointer-events-none z-10">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.3-4.3"></path>
                  </svg>
                  <input v-model="filters.search" @input="handleSearch" type="text" :placeholder="t.search_placeholder || 'Search by name, phone, register…'"
                    class="placeholder:text-white dark:bg-input/30 border border-border text-foreground flex h-7 sm:h-8 md:h-9 w-full min-w-0 max-w-full rounded-md bg-transparent px-2 sm:px-2.5 md:px-3 py-1 text-xs sm:text-sm md:text-base shadow-xs transition-all outline-none [color-scheme:dark] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] box-border pl-7 sm:pl-8 md:pl-9" />
                </div>
              </div>
              <div class="min-w-0">
                <label class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full mb-1">{{ t.status || 'Status' }}</label>
                <Select
                  :modelValue="filters.status"
                  :options="statusOptions"
                  :placeholder="t.all_statuses || 'All Statuses'"
                  @update:modelValue="val => { filters.status = val; applyFilters(); }"
                  @change="applyFilters"
                />
              </div>
              <div class="min-w-0">
                <label class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full mb-1">{{ t.source || 'Came through' }}</label>
                <Select
                  :modelValue="filters.source"
                  :options="sourceOptions"
                  :placeholder="t.all_sources || 'All Sources'"
                  @update:modelValue="val => { filters.source = val; applyFilters(); }"
                  @change="applyFilters"
                />
              </div>
              <div class="min-w-0">
                <label class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full mb-1">{{ t.channel || 'From' }}</label>
                <Select
                  :modelValue="filters.channel"
                  :options="channelOptions"
                  :placeholder="t.all_channels || 'Website & App'"
                  @update:modelValue="val => { filters.channel = val; applyFilters(); }"
                  @change="applyFilters"
                />
              </div>
              <div class="min-w-0">
                <label class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full mb-1">{{ t.membership || 'Membership' }}</label>
                <Select
                  :modelValue="filters.membership"
                  :options="membershipOptions"
                  @update:modelValue="val => { filters.membership = val; applyFilters(); }"
                  @change="applyFilters"
                />
              </div>
              <div class="min-w-0 flex items-end">
                <label class="flex items-center gap-2 text-xs sm:text-sm font-medium select-none cursor-pointer h-7 sm:h-8 md:h-9">
                  <input type="checkbox" v-model="filters.unique_phone" @change="applyFilters" class="h-4 w-4 cursor-pointer" />
                  {{ t.unique_numbers || 'Unique numbers' }}
                </label>
              </div>
              <div class="min-w-0 flex items-end">
                <button v-if="hasActiveFilters" @click="resetFilters"
                  class="cursor-pointer justify-center whitespace-nowrap text-xs font-medium transition-all outline-none bg-destructive text-white shadow-xs hover:bg-destructive/90 h-7 sm:h-8 rounded-md px-2 sm:px-3 inline-flex items-center gap-1.5 sm:gap-2"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3 w-3">
                    <path d="M18 6 6 18"></path>
                    <path d="m6 6 12 12"></path>
                  </svg>
                  {{ t.clear || 'Clear' }}
                </button>
              </div>
            </div>
          </div>
        </FilterAccordion>

        <!-- Bulk action bar -->
        <div v-if="canManage && selectedIds.length" class="mb-3 rounded-xl border border-primary/40 bg-primary/5 p-3 flex flex-col lg:flex-row gap-2 lg:items-end">
          <div class="text-sm font-medium whitespace-nowrap">{{ selectedIds.length }} {{ t.selected || 'selected' }}</div>
          <div class="min-w-[180px]">
            <label class="block text-xs font-medium mb-1">{{ t.change_status_to || 'Change status to' }}</label>
            <Select :modelValue="bulk.status" :options="bulkStatusOptions" @update:modelValue="val => bulk.status = val" />
          </div>
          <div class="flex-1 min-w-0">
            <label class="block text-xs font-medium mb-1">{{ t.add_note || 'Add a note to all selected' }}</label>
            <textarea v-model="bulk.note" rows="1" maxlength="5000" :placeholder="t.note_placeholder || 'Optional note…'"
              class="w-full rounded-md border border-border bg-transparent px-2 py-1.5 text-sm outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"></textarea>
            <div v-if="bulkSuggestions.length" class="mt-1.5 flex flex-wrap gap-1">
              <button v-for="text in bulkSuggestions" :key="text" type="button" @click="bulk.note = text"
                class="rounded-full border border-border px-2 py-0.5 text-[11px] hover:bg-muted cursor-pointer max-w-full truncate"
                :class="bulk.note === text ? 'bg-primary/10 border-primary/40' : ''">{{ text }}</button>
            </div>
          </div>
          <button @click="openConfirm" :disabled="!bulk.status && !bulk.note.trim()"
            class="h-8 rounded-md px-3 text-xs font-medium bg-primary text-primary-foreground disabled:opacity-50 cursor-pointer">{{ t.apply || 'Apply' }}</button>
          <button @click="selectedIds = []" class="h-8 rounded-md px-3 text-xs font-medium border border-border cursor-pointer">{{ t.clear_selection || 'Clear selection' }}</button>
        </div>

        <!-- Confirmation -->
        <div v-if="confirming" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="confirming = false">
          <div class="w-full max-w-md rounded-xl border border-border bg-card text-card-foreground p-5 shadow-lg">
            <h3 class="text-lg font-bold mb-2">{{ t.confirm_bulk_title || 'Confirm bulk update' }}</h3>
            <p class="text-sm mb-2">
              {{ (t.confirm_bulk_body || 'This will update :count message(s):').replace(':count', selectedIds.length) }}
            </p>
            <ul class="text-sm list-disc ps-5 mb-3 space-y-1">
              <li v-if="bulk.status">{{ t.status || 'Status' }} → <strong>{{ bulkStatusLabel }}</strong></li>
              <li v-if="bulk.note.trim()">{{ t.note || 'Note' }}: <em class="whitespace-pre-wrap">{{ bulk.note.trim() }}</em></li>
            </ul>
            <p v-if="bulkError" class="text-sm text-destructive mb-2">{{ bulkError }}</p>
            <div class="flex justify-end gap-2">
              <button @click="confirming = false" :disabled="bulkBusy" class="h-8 rounded-md px-3 text-xs font-medium border border-border cursor-pointer">{{ t.cancel || 'Cancel' }}</button>
              <button @click="submitBulk" :disabled="bulkBusy" class="h-8 rounded-md px-3 text-xs font-medium bg-primary text-primary-foreground disabled:opacity-50 cursor-pointer">{{ bulkBusy ? (t.saving || 'Saving…') : (t.confirm || 'Confirm') }}</button>
            </div>
          </div>
        </div>

        <!-- Messages Table -->
        <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
          <div v-if="messages?.data?.length > 0">
            <div class="overflow-x-auto">
              <table class="w-full caption-bottom text-xs sm:text-sm">
                <thead class="[&_tr]:border-b [&_tr]:border-border">
                  <tr class="border-b border-border transition-colors">
                    <th v-if="canManage" class="h-9 sm:h-10 px-2 sm:px-3 w-8">
                      <input type="checkbox" :checked="allOnPageSelected" @change="togglePage" class="h-4 w-4 cursor-pointer" />
                    </th>
                    <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap">{{ t.name || 'Name' }}</th>
                    <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap">{{ t.phone || 'Phone' }}</th>
                    <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap min-w-[14rem]">{{ t.message || 'Message' }}</th>
                    <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-center align-middle font-medium whitespace-nowrap">{{ t.source || 'Came through' }}</th>
                    <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-center align-middle font-medium whitespace-nowrap">{{ t.status || 'Status' }}</th>
                    <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap">{{ t.date || 'Date' }}</th>
                    <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-center align-middle font-medium whitespace-nowrap">{{ t.actions || 'Actions' }}</th>
                  </tr>
                </thead>
                <tbody class="[&_tr:last-child]:border-0">
                  <tr v-for="msg in messages.data" :key="msg.id" class="border-b border-border transition-colors hover:bg-muted/50" :class="selectedIds.includes(msg.id) ? 'bg-primary/5' : ''">
                    <td v-if="canManage" class="p-2 sm:p-3 align-middle">
                      <input type="checkbox" :value="msg.id" v-model="selectedIds" class="h-4 w-4 cursor-pointer" />
                    </td>
                    <td class="p-2 sm:p-3 align-middle">
                      <span class="font-medium text-foreground">{{ msg.name || '—' }}</span>
                      <span v-if="msg.commercial_register" class="block text-[11px] text-muted-foreground">
                        {{ t.commercial_register || 'CR' }}: {{ msg.commercial_register }}
                      </span>
                    </td>
                    <td class="p-2 sm:p-3 align-middle">
                      <span class="text-foreground text-xs" dir="ltr">{{ msg.phone }}</span>
                      <span v-if="msg.is_member === true" class="ms-1 inline-flex items-center rounded-full border border-emerald-500/30 bg-emerald-500/10 px-1.5 py-0.5 text-[10px] font-medium text-emerald-500">✓ {{ t.member || 'Member' }}</span>
                      <span v-else-if="msg.is_member === false" class="ms-1 inline-flex items-center rounded-full border border-amber-500/30 bg-amber-500/10 px-1.5 py-0.5 text-[10px] font-medium text-amber-500">{{ t.not_member_badge || 'Not a member' }}</span>
                    </td>
                    <td class="p-2 sm:p-3 align-middle max-w-xs">
                      <span v-if="msg.subject" class="block text-xs font-medium text-foreground truncate" :title="msg.subject">{{ msg.subject }}</span>
                      <span v-if="msg.message" class="block text-xs text-muted-foreground line-clamp-2 break-words" :title="msg.message">{{ msg.message }}</span>
                      <span v-if="!msg.subject && !msg.message" class="text-muted-foreground">—</span>
                    </td>
                    <td class="p-2 sm:p-3 align-middle text-center">
                      <span :class="sourceBadgeClass(msg.source)">{{ msg.source_label }}</span>
                      <span v-if="msg.channel_label" class="ms-1 text-[10px] text-muted-foreground">{{ msg.channel === 'mobile' ? '📱' : '🌐' }} {{ msg.channel_label }}</span>
                    </td>
                    <td class="p-2 sm:p-3 align-middle text-center">
                      <span :class="statusBadgeClass(msg.status)">{{ msg.status_label }}</span>
                    </td>
                    <td class="p-2 sm:p-3 align-middle whitespace-nowrap">
                      <div class="flex flex-col">
                        <span class="text-foreground text-xs">{{ formatDate(msg.created_at) }}</span>
                        <span class="text-muted-foreground text-[11px]">{{ msg.created_at_human }}</span>
                      </div>
                    </td>
                    <td class="p-2 sm:p-3 align-middle text-center">
                      <Link :href="route('admin.contact-messages.show', msg.id)"
                        class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-md text-xs font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground h-7 px-2"
                        :title="t.view_details || 'View message'"
                      >
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
                          <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
                          <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                        {{ t.view || 'View' }}
                      </Link>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="border-t border-border px-3 sm:px-4 md:px-6 py-2.5 sm:py-3">
              <div class="flex flex-row items-center justify-between gap-2 flex-wrap">
                <div class="text-xs sm:text-sm text-muted-foreground">
                  {{ formatShowingResults(messages.meta) }}
                </div>
                <Pagination v-if="messages?.meta?.links?.length > 0" :links="messages.meta.links" />
              </div>
            </div>
          </div>
          <div v-else class="p-12 text-center">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-blue-500/10 border border-blue-500/20 mb-6">
              <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-blue-400">
                <rect width="20" height="16" x="2" y="4" rx="2"></rect>
                <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
              </svg>
            </div>
            <h3 class="text-xl font-bold mb-1 text-foreground">{{ t.not_found || 'No messages yet' }}</h3>
            <p class="text-muted-foreground text-sm">{{ t.not_found_message || 'No contact messages match your criteria.' }}</p>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import FilterAccordion from "@/Components/ui/FilterAccordion.vue";
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router, usePage, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Pages/_components/Pagination.vue';
import Select from '@/Components/ui/Select.vue';
import axios from 'axios';

const page = usePage();
const t = computed(() => page.props.translations?.admin?.contact_messages || {});

const props = defineProps({
  messages: { type: Object, required: true },
  stats: { type: Object, required: true },
  // The pipeline and the forms come from the server so this page cannot drift
  // out of step with the enums behind them.
  statuses: { type: Array, default: () => [] },
  sources: { type: Array, default: () => [] },
  channels: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({ status: 'all', source: 'all', search: '' }) },
  canManage: { type: Boolean, default: false },
});

const filters = ref({
  search: props.filters?.search || '',
  status: props.filters?.status || 'all',
  source: props.filters?.source || 'all',
  channel: props.filters?.channel || 'all',
  membership: props.filters?.membership || 'all',
  unique_phone: !!props.filters?.unique_phone,
});

const selectedIds = ref([]);
const bulk = ref({ status: '', note: '' });
const confirming = ref(false);
const bulkBusy = ref(false);
const bulkError = ref('');

const pageIds = computed(() => (props.messages?.data || []).map(m => m.id));
const allOnPageSelected = computed(() => pageIds.value.length > 0 && pageIds.value.every(id => selectedIds.value.includes(id)));
const togglePage = () => {
  selectedIds.value = allOnPageSelected.value
    ? selectedIds.value.filter(id => !pageIds.value.includes(id))
    : [...new Set([...selectedIds.value, ...pageIds.value])];
};
const bulkStatusOptions = computed(() => [
  { value: '', label: t.value.keep_status || '— keep current —' },
  ...props.statuses.map(s => ({ value: s.value, label: t.value[s.value] || s.label })),
]);
const bulkSuggestions = computed(() => props.statuses.find(s => s.value === bulk.value.status)?.suggestions || []);
const bulkStatusLabel = computed(() => bulkStatusOptions.value.find(o => o.value === bulk.value.status)?.label || '');
const openConfirm = () => { bulkError.value = ''; confirming.value = true; };

const submitBulk = async () => {
  bulkBusy.value = true;
  bulkError.value = '';
  try {
    await axios.post(route('admin.contact-messages.bulk-update'), {
      ids: selectedIds.value,
      action: bulk.value.status || null,
      note: bulk.value.note.trim() || null,
    });
    confirming.value = false;
    selectedIds.value = [];
    bulk.value = { status: '', note: '' };
    router.reload({ preserveScroll: true });
  } catch (e) {
    bulkError.value = e?.response?.data?.message || e?.message || 'Update failed';
    try {
      axios.post('/api/v1/client-errors', { message: String(e?.message || e), stack: e?.stack, route: 'admin.contact-messages.bulk-update', extra: { count: selectedIds.value.length } }).catch(() => {});
    } catch (_) { /* reporting must never break the page */ }
  } finally {
    bulkBusy.value = false;
  }
};

const hasActiveFilters = computed(() =>
  filters.value.search || filters.value.status !== 'all' || filters.value.source !== 'all' || filters.value.channel !== 'all' || filters.value.membership !== 'all' || filters.value.unique_phone
);

const statusOptions = computed(() => [
  { value: 'all', label: t.value.all_statuses || 'All Statuses' },
  ...props.statuses.map(s => ({ value: s.value, label: t.value[s.value] || s.label })),
]);

// ---- Export menu (columns + optional split into multiple files) ----
const chunkSize = ref(0);
const exportMenuOpen = ref(false);
const exportTriggerRef = ref(null);
const exportMenuRef = ref(null);
const exportMenuStyle = ref({});

const exportColumnOptions = [
  { key: 'id', label: 'ID' },
  { key: 'name', label: 'Name' },
  { key: 'email', label: 'Email' },
  { key: 'phone', label: 'Phone' },
  { key: 'commercial_register', label: 'Commercial Register' },
  { key: 'source', label: 'Source' },
  { key: 'status', label: 'Status' },
  { key: 'sales_name', label: 'Assigned To' },
  { key: 'subject', label: 'Subject' },
  { key: 'message', label: 'Message' },
  { key: 'admin_notes', label: 'Admin Notes' },
  { key: 'created_at', label: 'Submitted At' },
  { key: 'read_at', label: 'Read At' },
  { key: 'replied_at', label: 'Replied At' },
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

const exportComputedUrl = computed(() => {
  const params = new URLSearchParams();
  if (filters.value.search?.trim()) params.set('search', filters.value.search.trim());
  if (filters.value.status && filters.value.status !== 'all') params.set('status', filters.value.status);
  if (filters.value.source && filters.value.source !== 'all') params.set('source', filters.value.source);
  if (filters.value.channel && filters.value.channel !== 'all') params.set('channel', filters.value.channel);
  if (filters.value.membership && filters.value.membership !== 'all') params.set('membership', filters.value.membership);
  if (filters.value.unique_phone) params.set('unique_phone', '1');
  if (selectedColumns.value.length < allColumnKeys.length) {
    params.set('columns', selectedColumns.value.join(','));
  }
  if (chunkSize.value > 0) params.set('chunk_size', chunkSize.value);
  const qs = params.toString();
  const base = route('admin.contact-messages.export');
  return qs ? `${base}?${qs}` : base;
});

const membershipOptions = computed(() => [
  { value: 'all', label: t.value.all_people || 'Members & non-members' },
  { value: 'member', label: t.value.already_member || 'Already a member' },
  { value: 'not_member', label: t.value.not_member || 'Not a member' },
]);

const channelOptions = computed(() => [
  { value: 'all', label: t.value.all_channels || 'Website & App' },
  ...props.channels,
]);

const sourceOptions = computed(() => [
  { value: 'all', label: t.value.all_sources || 'All Sources' },
  ...props.sources.map(s => ({ value: s.value, label: t.value[s.value] || s.label })),
]);

const STATUS_COLORS = {
  new: 'text-blue-400',
  in_progress: 'text-amber-400',
  resolved: 'text-emerald-400',
  closed: 'text-muted-foreground',
  rejected: 'text-red-400',
};

const statCards = computed(() => [
  { key: 'total', label: t.value.total || 'Total', count: props.stats.total, color: 'text-foreground' },
  ...props.statuses.map(s => ({
    key: s.value,
    label: t.value[s.value] || s.label,
    count: props.stats[s.value] ?? 0,
    color: STATUS_COLORS[s.value] || 'text-foreground',
  })),
]);

const statusBadgeClass = (status) => {
  const base = 'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1';
  const map = {
    new: `${base} bg-blue-500/20 text-blue-300 ring-blue-500/30`,
    in_progress: `${base} bg-amber-500/20 text-amber-300 ring-amber-500/30`,
    resolved: `${base} bg-emerald-500/20 text-emerald-300 ring-emerald-500/30`,
    closed: `${base} bg-muted text-muted-foreground ring-border`,
    rejected: `${base} bg-red-500/20 text-red-300 ring-red-500/30`,
  };
  return map[status] || map.new;
};

/* A join request is the one that needs verifying, so it is the one that has to
   be picked out of a list at a glance. */
const sourceBadgeClass = (source) => {
  const base = 'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1';
  const map = {
    contact_form: `${base} bg-slate-500/20 text-slate-300 ring-slate-500/30`,
    card_popup: `${base} bg-indigo-500/20 text-indigo-300 ring-indigo-500/30`,
    join_request: `${base} bg-purple-500/20 text-purple-300 ring-purple-500/30`,
  };
  return map[source] || map.contact_form;
};

const formatShowingResults = (meta) => {
  const pattern = t.value.showing_results || 'Showing :from to :to of :total';
  return pattern
    .replace(':from', meta?.from || '0')
    .replace(':to', meta?.to || '0')
    .replace(':total', meta?.total || '0');
};

const formatDate = (s) => {
  if (!s) return '-';
  return new Date(s).toLocaleString('en-US', {
    year: 'numeric', month: 'short', day: 'numeric',
    hour: '2-digit', minute: '2-digit',
  });
};

let searchTimeout = null;
const handleSearch = () => {
  if (searchTimeout) clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => { applyFilters(); }, 300);
};

const applyFilters = () => {
  const params = {};
  if (filters.value.search?.trim()) params.search = filters.value.search.trim();
  if (filters.value.status && filters.value.status !== 'all') params.status = filters.value.status;
  if (filters.value.source && filters.value.source !== 'all') params.source = filters.value.source;
  if (filters.value.channel && filters.value.channel !== 'all') params.channel = filters.value.channel;
  if (filters.value.membership && filters.value.membership !== 'all') params.membership = filters.value.membership;
  if (filters.value.unique_phone) params.unique_phone = 1;
  router.get(route('admin.contact-messages.index'), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const resetFilters = () => {
  filters.value = { search: '', status: 'all', source: 'all', channel: 'all', membership: 'all', unique_phone: false };
  router.get(route('admin.contact-messages.index'), {}, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};
</script>
