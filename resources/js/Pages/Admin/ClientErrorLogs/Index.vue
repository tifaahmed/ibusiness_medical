<template>
  <AppLayout :title="t.title || 'App Error Logs'">
    <div class="w-full max-w-full">
      <div class="space-y-2 sm:space-y-3 md:space-y-4 p-2 sm:p-3 md:p-4 lg:p-6 w-full max-w-full">
        <!-- Header -->
        <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border py-2 sm:py-3 md:py-4 shadow-sm w-full">
          <div class="flex flex-row items-center justify-between py-2 px-3 sm:px-4 md:px-6 gap-2 sm:gap-4">
            <div class="leading-none font-semibold min-w-0 flex-1">
              <div class="title-golden flex items-center gap-2 min-w-0">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon flex-shrink-0 text-red-400">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                </svg>
                <span class="text-sm sm:text-base truncate min-w-0">{{ t.title || 'App Error Logs' }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-3 gap-2 sm:gap-3">
          <div class="bg-card text-card-foreground rounded-xl border border-border p-3 sm:p-4 shadow-sm">
            <div class="text-xs text-muted-foreground mb-1">{{ t.total || 'Total' }}</div>
            <div class="text-xl sm:text-2xl font-bold text-foreground">{{ stats.total }}</div>
          </div>
          <div class="bg-card text-card-foreground rounded-xl border border-border p-3 sm:p-4 shadow-sm">
            <div class="text-xs text-muted-foreground mb-1">{{ t.fatal || 'Fatal' }}</div>
            <div class="text-xl sm:text-2xl font-bold text-red-400">{{ stats.fatal }}</div>
          </div>
          <div class="bg-card text-card-foreground rounded-xl border border-border p-3 sm:p-4 shadow-sm">
            <div class="text-xs text-muted-foreground mb-1">{{ t.today || 'Today' }}</div>
            <div class="text-xl sm:text-2xl font-bold text-amber-400">{{ stats.today }}</div>
          </div>
        </div>

        <!-- Filters -->
        <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
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
                  <input v-model="filters.search" @input="handleSearch" type="text" :placeholder="t.search_placeholder || 'Search by message or route...'"
                    class="placeholder:text-white dark:bg-input/30 border border-border text-foreground flex h-7 sm:h-8 md:h-9 w-full min-w-0 max-w-full rounded-md bg-transparent px-2 sm:px-2.5 md:px-3 py-1 text-xs sm:text-sm md:text-base shadow-xs transition-all outline-none [color-scheme:dark] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] box-border pl-7 sm:pl-8 md:pl-9" />
                </div>
              </div>
              <div class="min-w-0">
                <label class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full mb-1">{{ t.platform || 'Platform' }}</label>
                <Select
                  :modelValue="filters.platform"
                  :options="platformOptions"
                  :placeholder="t.all_platforms || 'All Platforms'"
                  @update:modelValue="val => { filters.platform = val; applyFilters(); }"
                  @change="applyFilters"
                />
              </div>
              <div class="min-w-0">
                <label class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full mb-1">{{ t.severity || 'Severity' }}</label>
                <Select
                  :modelValue="filters.fatal"
                  :options="fatalOptions"
                  :placeholder="t.all_severities || 'All Severities'"
                  @update:modelValue="val => { filters.fatal = val; applyFilters(); }"
                  @change="applyFilters"
                />
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
        </div>

        <!-- Logs Table -->
        <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
          <div v-if="logs?.data?.length > 0">
            <div class="overflow-x-auto">
              <table class="w-full caption-bottom text-xs sm:text-sm">
                <thead class="[&_tr]:border-b [&_tr]:border-border">
                  <tr class="border-b border-border transition-colors">
                    <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap"></th>
                    <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap">{{ t.message || 'Message' }}</th>
                    <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap">{{ t.route || 'Route' }}</th>
                    <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-center align-middle font-medium whitespace-nowrap">{{ t.platform || 'Platform' }}</th>
                    <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-center align-middle font-medium whitespace-nowrap">{{ t.version || 'Version' }}</th>
                    <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-center align-middle font-medium whitespace-nowrap">{{ t.severity || 'Severity' }}</th>
                    <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap">{{ t.date || 'Date' }}</th>
                    <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-center align-middle font-medium whitespace-nowrap">{{ t.actions || 'Actions' }}</th>
                  </tr>
                </thead>
                <tbody class="[&_tr:last-child]:border-0">
                  <template v-for="log in logs.data" :key="log.id">
                    <tr class="border-b border-border transition-colors hover:bg-muted/50 cursor-pointer" @click="toggleExpand(log.id)">
                      <td class="p-2 sm:p-3 align-middle">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                          class="w-3.5 h-3.5 text-muted-foreground transition-transform" :class="{ 'rotate-90': expanded === log.id }">
                          <path d="m9 18 6-6-6-6"></path>
                        </svg>
                      </td>
                      <td class="p-2 sm:p-3 align-middle max-w-[280px]">
                        <span class="text-foreground text-xs truncate block">{{ log.message }}</span>
                      </td>
                      <td class="p-2 sm:p-3 align-middle">
                        <span class="text-foreground text-xs">{{ log.route || '-' }}</span>
                      </td>
                      <td class="p-2 sm:p-3 align-middle text-center">
                        <span class="text-foreground text-xs">{{ log.platform || '-' }}</span>
                      </td>
                      <td class="p-2 sm:p-3 align-middle text-center">
                        <span class="text-foreground text-xs">{{ log.app_version || '-' }}</span>
                      </td>
                      <td class="p-2 sm:p-3 align-middle text-center">
                        <span :class="severityBadgeClass(log.fatal)">{{ log.fatal ? (t.fatal || 'Fatal') : (t.non_fatal || 'Non-fatal') }}</span>
                      </td>
                      <td class="p-2 sm:p-3 align-middle whitespace-nowrap">
                        <div class="flex flex-col">
                          <span class="text-foreground text-xs">{{ log.created_at }}</span>
                          <span class="text-muted-foreground text-[11px]">{{ log.created_at_human }}</span>
                        </div>
                      </td>
                      <td class="p-2 sm:p-3 align-middle text-center" @click.stop>
                        <button @click="destroyLog(log.id)"
                          class="cursor-pointer inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-md text-xs font-medium transition-all border bg-background shadow-xs hover:bg-destructive hover:text-white h-7 px-2"
                          :title="t.delete || 'Delete'"
                        >
                          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
                            <path d="M3 6h18"></path>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                          </svg>
                        </button>
                      </td>
                    </tr>
                    <tr v-if="expanded === log.id" class="border-b border-border bg-muted/30">
                      <td colspan="8" class="p-3 sm:p-4">
                        <div class="space-y-3">
                          <div v-if="log.stack">
                            <label class="text-xs font-medium text-muted-foreground">{{ t.stack_trace || 'Stack Trace' }}</label>
                            <pre class="mt-1 text-[11px] leading-relaxed text-foreground bg-background border border-border rounded-md p-2 sm:p-3 overflow-x-auto whitespace-pre-wrap break-words max-h-96 overflow-y-auto">{{ log.stack }}</pre>
                          </div>
                          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                            <div v-if="log.ip_address">
                              <label class="font-medium text-muted-foreground">{{ t.ip_address || 'IP Address' }}</label>
                              <p class="text-foreground">{{ log.ip_address }}</p>
                            </div>
                            <div v-if="log.user_agent">
                              <label class="font-medium text-muted-foreground">{{ t.user_agent || 'User Agent' }}</label>
                              <p class="text-foreground break-all">{{ log.user_agent }}</p>
                            </div>
                          </div>
                        </div>
                      </td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>
            <div class="border-t border-border px-3 sm:px-4 md:px-6 py-2.5 sm:py-3">
              <div class="flex flex-row items-center justify-between gap-2 flex-wrap">
                <div class="text-xs sm:text-sm text-muted-foreground">
                  {{ formatShowingResults(logs.meta) }}
                </div>
                <Pagination v-if="logs?.meta?.links?.length > 0" :links="logs.meta.links" />
              </div>
            </div>
          </div>
          <div v-else class="p-12 text-center">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-emerald-500/10 border border-emerald-500/20 mb-6">
              <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-emerald-400">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <h3 class="text-xl font-bold mb-1 text-foreground">{{ t.not_found || 'No errors reported' }}</h3>
            <p class="text-muted-foreground text-sm">{{ t.not_found_message || 'No client errors match your criteria.' }}</p>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Pages/_components/Pagination.vue';
import Select from '@/Components/ui/Select.vue';

const page = usePage();
const t = computed(() => page.props.translations?.admin?.client_error_logs || {});

const props = defineProps({
  logs: { type: Object, required: true },
  stats: { type: Object, required: true },
  filters: { type: Object, default: () => ({ platform: 'all', fatal: 'all', search: '' }) },
});

const filters = ref({
  search: props.filters?.search || '',
  platform: props.filters?.platform || 'all',
  fatal: props.filters?.fatal || 'all',
});

const expanded = ref(null);
const toggleExpand = (id) => {
  expanded.value = expanded.value === id ? null : id;
};

const hasActiveFilters = computed(() =>
  filters.value.search || filters.value.platform !== 'all' || filters.value.fatal !== 'all'
);

const platformOptions = [
  { value: 'all', label: t.value.all_platforms || 'All Platforms' },
  { value: 'ios', label: 'iOS' },
  { value: 'android', label: 'Android' },
];

const fatalOptions = [
  { value: 'all', label: t.value.all_severities || 'All Severities' },
  { value: '1', label: t.value.fatal || 'Fatal' },
  { value: '0', label: t.value.non_fatal || 'Non-fatal' },
];

const severityBadgeClass = (fatal) => fatal
  ? 'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium bg-red-500/20 text-red-300 ring-1 ring-red-500/30'
  : 'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium bg-amber-500/20 text-amber-300 ring-1 ring-amber-500/30';

const formatShowingResults = (meta) => {
  const pattern = t.value.showing_results || 'Showing :from to :to of :total';
  return pattern
    .replace(':from', meta?.from || '0')
    .replace(':to', meta?.to || '0')
    .replace(':total', meta?.total || '0');
};

let searchTimeout = null;
const handleSearch = () => {
  if (searchTimeout) clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => { applyFilters(); }, 300);
};

const applyFilters = () => {
  const params = {};
  if (filters.value.search?.trim()) params.search = filters.value.search.trim();
  if (filters.value.platform && filters.value.platform !== 'all') params.platform = filters.value.platform;
  if (filters.value.fatal && filters.value.fatal !== 'all') params.fatal = filters.value.fatal;
  router.get(route('admin.client-error-logs.index'), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const resetFilters = () => {
  filters.value = { search: '', platform: 'all', fatal: 'all' };
  router.get(route('admin.client-error-logs.index'), {}, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const destroyLog = (id) => {
  router.delete(route('admin.client-error-logs.destroy', id), { preserveScroll: true });
};
</script>
