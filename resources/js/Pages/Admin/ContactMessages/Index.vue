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
                  <input v-model="filters.search" @input="handleSearch" type="text" :placeholder="t.search_placeholder || 'Search by name, email, subject...'"
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

        <!-- Messages Table -->
        <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
          <div v-if="messages?.data?.length > 0">
            <div class="overflow-x-auto">
              <table class="w-full caption-bottom text-xs sm:text-sm">
                <thead class="[&_tr]:border-b [&_tr]:border-border">
                  <tr class="border-b border-border transition-colors">
                    <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap">{{ t.name || 'Name' }}</th>
                    <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap">{{ t.email || 'Email' }}</th>
                    <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap">{{ t.subject || 'Subject' }}</th>
                    <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-center align-middle font-medium whitespace-nowrap">{{ t.status || 'Status' }}</th>
                    <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap">{{ t.date || 'Date' }}</th>
                    <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-center align-middle font-medium whitespace-nowrap">{{ t.actions || 'Actions' }}</th>
                  </tr>
                </thead>
                <tbody class="[&_tr:last-child]:border-0">
                  <tr v-for="msg in messages.data" :key="msg.id" class="border-b border-border transition-colors hover:bg-muted/50">
                    <td class="p-2 sm:p-3 align-middle">
                      <span class="font-medium text-foreground">{{ msg.name }}</span>
                    </td>
                    <td class="p-2 sm:p-3 align-middle">
                      <span class="text-foreground text-xs">{{ msg.email }}</span>
                    </td>
                    <td class="p-2 sm:p-3 align-middle max-w-[200px]">
                      <span class="text-foreground text-xs truncate block">{{ msg.subject }}</span>
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
import { ref, computed } from 'vue';
import { router, usePage, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Pages/_components/Pagination.vue';
import Select from '@/Components/ui/Select.vue';

const page = usePage();
const t = computed(() => page.props.translations?.admin?.contact_messages || {});

const props = defineProps({
  messages: { type: Object, required: true },
  stats: { type: Object, required: true },
  filters: { type: Object, default: () => ({ status: 'all', search: '' }) },
});

const filters = ref({
  search: props.filters?.search || '',
  status: props.filters?.status || 'all',
});

const hasActiveFilters = computed(() =>
  filters.value.search || filters.value.status !== 'all'
);

const statusOptions = [
  { value: 'all', label: t.value.all_statuses || 'All Statuses' },
  { value: 'new', label: t.value.new || 'New' },
  { value: 'read', label: t.value.read || 'Read' },
  { value: 'replied', label: t.value.replied || 'Replied' },
  { value: 'archived', label: t.value.archived || 'Archived' },
];

const statCards = computed(() => [
  { key: 'total', label: t.value.total || 'Total', count: props.stats.total, color: 'text-foreground' },
  { key: 'new', label: t.value.new || 'New', count: props.stats.new, color: 'text-blue-400' },
  { key: 'read', label: t.value.read || 'Read', count: props.stats.read, color: 'text-emerald-400' },
  { key: 'replied', label: t.value.replied || 'Replied', count: props.stats.replied, color: 'text-purple-400' },
  { key: 'archived', label: t.value.archived || 'Archived', count: props.stats.archived, color: 'text-amber-400' },
]);

const statusBadgeClass = (status) => {
  const map = {
    new: 'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium bg-blue-500/20 text-blue-300 ring-1 ring-blue-500/30',
    read: 'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium bg-emerald-500/20 text-emerald-300 ring-1 ring-emerald-500/30',
    replied: 'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium bg-purple-500/20 text-purple-300 ring-1 ring-purple-500/30',
    archived: 'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium bg-amber-500/20 text-amber-300 ring-1 ring-amber-500/30',
  };
  return map[status] || map.new;
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
  router.get(route('admin.contact-messages.index'), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const resetFilters = () => {
  filters.value = { search: '', status: 'all' };
  router.get(route('admin.contact-messages.index'), {}, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};
</script>
