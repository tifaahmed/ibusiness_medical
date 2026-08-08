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
                  <circle cx="12" cy="12" r="9"></circle>
                  <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
                <span class="text-sm sm:text-base truncate min-w-0">
                  Activity Logs &mdash; {{ member.name }}
                </span>
              </div>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
              <Link
                v-if="member.slug"
                :href="route('admin.user.membership.edit', member.slug)"
                class="inline-flex items-center cursor-pointer justify-center gap-1.5 sm:gap-2 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium transition-all bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-8 sm:h-9 px-2 sm:px-3 md:px-4 py-2 btn-golden"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 sm:h-4 sm:w-4">
                  <path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                  <path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"></path>
                </svg>
                <span class="hidden sm:inline">Edit Member</span>
                <span class="sm:hidden">Edit</span>
              </Link>
              <Link
                :href="route('admin.user.membership.list')"
                class="inline-flex items-center cursor-pointer justify-center gap-1.5 sm:gap-2 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 sm:h-9 px-2 sm:px-3 md:px-4 py-2"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 sm:h-4 sm:w-4">
                  <path d="m12 19-7-7 7-7"></path>
                  <path d="M19 12H5"></path>
                </svg>
                <span class="hidden sm:inline">Back to Members</span>
                <span class="sm:hidden">Back</span>
              </Link>
            </div>
          </div>
        </div>

        <!-- Filters -->
        <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border py-2 sm:py-3 shadow-sm">
          <div class="flex flex-col sm:flex-row sm:items-end gap-2 sm:gap-3 px-3 sm:px-4 md:px-6">
            <div class="w-full sm:w-72 min-w-0">
              <label
                for="admin_id"
                class="flex items-center gap-1.5 text-xs leading-none font-medium select-none mb-1"
              >
                Filter by admin
              </label>
              <div class="relative">
                <select
                  id="admin_id"
                  v-model="adminFilter"
                  @change="applyFilters"
                  class="appearance-none border border-border dark:bg-input/30 bg-transparent text-foreground rounded-md px-3 pr-8 py-1 text-xs sm:text-sm h-7 sm:h-8 md:h-9 w-full shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] cursor-pointer transition-colors hover:border-ring/60 [color-scheme:dark]"
                >
                  <option :value="null" class="bg-card text-foreground">All admins</option>
                  <option v-for="opt in adminOptions" :key="opt.value" :value="opt.value" class="bg-card text-foreground">{{ opt.label }}</option>
                </select>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground">
                  <path d="m6 9 6 6 6-6"></path>
                </svg>
              </div>
            </div>
            <button
              v-if="adminFilter !== null"
              type="button"
              @click="adminFilter = null; applyFilters()"
              class="cursor-pointer justify-center whitespace-nowrap text-xs font-medium transition-all bg-destructive text-white shadow-xs hover:bg-destructive/90 h-8 sm:h-9 rounded-md px-3 inline-flex items-center gap-1.5"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 6 6 18"></path>
                <path d="m6 6 12 12"></path>
              </svg>
              Clear
            </button>
          </div>
        </div>

        <!-- Logs Table -->
        <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
          <div v-if="logs?.data?.length > 0">
            <div class="overflow-x-auto">
              <table class="w-full caption-bottom text-xs sm:text-sm py-3 sm:py-4">
                <thead class="[&_tr]:border-b [&_tr]:border-border">
                  <tr class="border-b border-border transition-colors">
                    <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap w-44">When</th>
                    <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap w-28">Action</th>
                    <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap">Admin</th>
                    <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap">Changes</th>
                    <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-center align-middle font-medium whitespace-nowrap w-20">Details</th>
                  </tr>
                </thead>
                <tbody class="[&_tr:last-child]:border-0">
                  <template v-for="log in logs.data" :key="log.id">
                    <tr class="border-b border-border transition-colors hover:bg-muted/50">
                      <td class="p-2 sm:p-3 align-middle whitespace-nowrap">
                        <div class="flex flex-col">
                          <span class="text-foreground">{{ formatDateTime(log.created_at) }}</span>
                          <span class="text-muted-foreground text-xs">{{ relativeTime(log.created_at) }}</span>
                        </div>
                      </td>
                      <td class="p-2 sm:p-3 align-middle whitespace-nowrap">
                        <span :class="['inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-semibold tracking-wide shadow-sm ring-1', actionClass(log.action)]">
                          <span class="w-1.5 h-1.5 rounded-full bg-white/80"></span>
                          {{ formatAction(log.action) }}
                        </span>
                      </td>
                      <td class="p-2 sm:p-3 align-middle">
                        <div v-if="log.admin" class="flex flex-col min-w-0">
                          <span class="font-medium text-foreground truncate">{{ log.admin.name }}</span>
                          <span class="text-muted-foreground text-xs truncate">{{ log.admin.email }}</span>
                        </div>
                        <span v-else class="text-muted-foreground italic text-xs">system / unknown</span>
                      </td>
                      <td class="p-2 sm:p-3 align-middle">
                        <div v-if="log.changed_fields?.length" class="flex flex-wrap gap-1">
                          <span
                            v-for="field in log.changed_fields"
                            :key="field"
                            class="inline-flex items-center rounded bg-muted px-1.5 py-0.5 text-xs font-mono text-muted-foreground"
                          >
                            {{ formatField(field) }}
                          </span>
                        </div>
                        <span v-else class="text-muted-foreground text-xs italic">
                          {{ log.action === 'created' ? 'Initial values' : '—' }}
                        </span>
                      </td>
                      <td class="p-2 sm:p-3 align-middle text-center">
                        <button
                          @click="toggle(log.id)"
                          class="inline-flex items-center cursor-pointer justify-center text-xs font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground h-7 rounded-md px-2"
                        >
                          {{ expanded[log.id] ? 'Hide' : 'View' }}
                        </button>
                      </td>
                    </tr>
                    <tr v-if="expanded[log.id]" class="bg-muted/30 border-b border-border">
                      <td colspan="5" class="p-3 sm:p-4 space-y-4">
                        <!-- Changed-fields highlight (top, distinct background) -->
                        <div
                          v-if="(log.action === 'updated' || log.action === 'family_updated') && (log.changed_fields?.length || 0) > 0"
                          class="rounded-lg border border-amber-500/40 bg-amber-500/10 p-3"
                        >
                          <div class="flex items-center gap-2 text-xs font-semibold text-amber-300 uppercase tracking-wide mb-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                              <path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"></path>
                            </svg>
                            Changed ({{ log.changed_fields.length }})
                          </div>
                          <div class="overflow-x-auto">
                            <table class="w-full text-xs">
                              <thead>
                                <tr class="text-amber-300/80">
                                  <th class="text-left font-medium pb-1.5 pr-3">Field</th>
                                  <th class="text-left font-medium pb-1.5 pr-3">Before</th>
                                  <th class="text-left font-medium pb-1.5">After</th>
                                </tr>
                              </thead>
                              <tbody class="font-mono">
                                <tr v-for="field in log.changed_fields" :key="field" class="border-t border-amber-500/20">
                                  <td class="py-1 pr-3 text-amber-200/90 whitespace-nowrap">{{ formatField(field) }}</td>
                                  <td class="py-1 pr-3 text-rose-300/90 break-all">{{ formatValue(log.old_values?.[field]) }}</td>
                                  <td class="py-1 text-emerald-300/90 break-all">{{ formatValue(log.new_values?.[field]) }}</td>
                                </tr>
                              </tbody>
                            </table>
                          </div>
                        </div>

                        <!-- Full Before / After snapshots (changed rows tinted) -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                          <div>
                            <div class="text-xs font-semibold text-muted-foreground uppercase tracking-wide mb-2">Before (full)</div>
                            <div v-if="log.old_values" class="bg-background border border-border rounded overflow-hidden">
                              <table class="w-full text-xs">
                                <tbody>
                                  <tr
                                    v-for="key in allKeys(log.old_values, log.new_values)"
                                    :key="key"
                                    :class="['border-b border-border last:border-0', isChanged(log, key) ? 'bg-amber-500/10' : '']"
                                  >
                                    <td class="py-1 px-2 text-muted-foreground whitespace-nowrap font-mono w-1/3 align-top">{{ formatField(key) }}</td>
                                    <td class="py-1 px-2 text-foreground break-all font-mono">{{ formatValue(log.old_values?.[key]) }}</td>
                                  </tr>
                                </tbody>
                              </table>
                            </div>
                            <p v-else class="text-xs text-muted-foreground italic">&mdash; nothing &mdash;</p>
                          </div>
                          <div>
                            <div class="text-xs font-semibold text-muted-foreground uppercase tracking-wide mb-2">After (full)</div>
                            <div v-if="log.new_values" class="bg-background border border-border rounded overflow-hidden">
                              <table class="w-full text-xs">
                                <tbody>
                                  <tr
                                    v-for="key in allKeys(log.old_values, log.new_values)"
                                    :key="key"
                                    :class="['border-b border-border last:border-0', isChanged(log, key) ? 'bg-amber-500/10' : '']"
                                  >
                                    <td class="py-1 px-2 text-muted-foreground whitespace-nowrap font-mono w-1/3 align-top">{{ formatField(key) }}</td>
                                    <td class="py-1 px-2 text-foreground break-all font-mono">{{ formatValue(log.new_values?.[key]) }}</td>
                                  </tr>
                                </tbody>
                              </table>
                            </div>
                            <p v-else class="text-xs text-muted-foreground italic">&mdash; nothing &mdash;</p>
                          </div>
                        </div>

                        <div v-if="log.ip_address || log.user_agent" class="pt-3 border-t border-border text-xs text-muted-foreground space-y-0.5">
                          <div v-if="log.ip_address"><span class="font-semibold">IP:</span> {{ log.ip_address }}</div>
                          <div v-if="log.user_agent" class="truncate"><span class="font-semibold">UA:</span> {{ log.user_agent }}</div>
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
                  Showing {{ logs.meta?.from || 0 }} to {{ logs.meta?.to || 0 }} of {{ logs.meta?.total || 0 }}
                </div>
                <Pagination v-if="logs?.meta?.links?.length > 0" :links="logs.meta.links" />
              </div>
            </div>
          </div>

          <!-- Empty state -->
          <div v-else class="p-12 text-center">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-golden-yellow/10 border border-golden-yellow/20 mb-6">
              <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-golden-yellow">
                <circle cx="12" cy="12" r="9"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
              </svg>
            </div>
            <h3 class="text-xl font-bold mb-1 text-foreground">No activity yet</h3>
            <p class="text-muted-foreground text-sm">
              No audit log entries exist for this member. New entries will appear here as changes are made.
            </p>
          </div>
        </div>
      </div>
    </div>
  </MemberLayout>
</template>

<script setup>
import MemberLayout from "../MemberLayout.vue";
import Pagination from "@/Pages/_components/Pagination.vue";
import { Link, router } from "@inertiajs/vue3";
import { reactive, ref } from "vue";

const props = defineProps({
  member: { type: Object, required: true },
  logs: { type: Object, required: true },
  filters: { type: Object, default: () => ({ admin_id: null }) },
  adminOptions: { type: Array, default: () => [] },
});

const expanded = reactive({});
const toggle = (id) => { expanded[id] = !expanded[id]; };

const adminFilter = ref(props.filters?.admin_id ?? null);
const applyFilters = () => {
  const params = {};
  if (adminFilter.value !== null && adminFilter.value !== undefined && adminFilter.value !== '') {
    params.admin_id = adminFilter.value;
  }
  router.get(route('admin.user.membership.logs', props.member.slug), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const ACTION_LABELS = {
  created: 'Created',
  updated: 'Updated',
  deleted: 'Deleted',
  restored: 'Restored',
  force_deleted: 'Force Deleted',
  family_created: 'Family Added',
  family_updated: 'Family Updated',
  family_deleted: 'Family Removed',
};

const formatAction = (action) => ACTION_LABELS[action] || action;

// Solid badges with white text for high-contrast scan-ability across the
// log table. Each action gets a distinct hue + matching ring so they read
// as semantically related (create/update/delete cluster around the same
// palette region while still being instantly distinguishable).
const actionClass = (action) => ({
  created: 'bg-emerald-600 text-white ring-emerald-400/40',
  updated: 'bg-blue-600 text-white ring-blue-400/40',
  deleted: 'bg-amber-600 text-white ring-amber-400/40',
  restored: 'bg-violet-600 text-white ring-violet-400/40',
  force_deleted: 'bg-red-600 text-white ring-red-400/40',
  family_created: 'bg-teal-600 text-white ring-teal-400/40',
  family_updated: 'bg-sky-600 text-white ring-sky-400/40',
  family_deleted: 'bg-rose-600 text-white ring-rose-400/40',
}[action] || 'bg-gray-600 text-white ring-gray-400/40');

const formatField = (field) => field.replace(/_/g, ' ');

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

const stringify = (obj) => {
  try { return JSON.stringify(obj, null, 2); }
  catch (e) { return String(obj); }
};

const formatValue = (v) => {
  if (v === null || v === undefined || v === '') return '—';
  if (typeof v === 'boolean') return v ? 'Yes' : 'No';
  if (typeof v === 'object') {
    try { return JSON.stringify(v); } catch (e) { return String(v); }
  }
  return String(v);
};

const allKeys = (oldVals, newVals) => {
  const set = new Set();
  if (oldVals && typeof oldVals === 'object') Object.keys(oldVals).forEach(k => set.add(k));
  if (newVals && typeof newVals === 'object') Object.keys(newVals).forEach(k => set.add(k));
  return Array.from(set);
};

const isChanged = (log, key) => {
  if (!Array.isArray(log.changed_fields)) return false;
  return log.changed_fields.includes(key);
};
</script>
