<template>
  <AppLayout>
    <div class="p-3 sm:p-4 md:p-6 space-y-4">
      <div class="flex items-center justify-between gap-3">
        <h1 class="text-lg sm:text-xl font-semibold flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>
          {{ t.title || 'Membership Card Patches Batches' }}
        </h1>
        <Link
          v-if="canWrite && canAny('create membership card patches', 'create own membership card patches', 'create partner membership card patches')"
          :href="route('admin.membership-card-patches.create')"
          class="inline-flex items-center gap-2 rounded-md h-9 px-3 text-sm font-medium bg-primary text-primary-foreground hover:bg-primary/90 btn-golden"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
          {{ t.new_batch || 'New batch' }}
        </Link>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="rounded-xl border border-border bg-card p-3">
          <div class="text-xs text-muted-foreground">{{ t.stats?.batches || 'Batches' }}</div>
          <div class="text-2xl font-semibold">{{ stats.batches }}</div>
        </div>
        <div class="rounded-xl border border-border bg-card p-3">
          <div class="text-xs text-muted-foreground">{{ t.stats?.memberships_created || 'Memberships created' }}</div>
          <div class="text-2xl font-semibold">{{ stats.total_memberships }}</div>
        </div>
        <div class="rounded-xl border border-border bg-card p-3">
          <div class="text-xs text-muted-foreground">{{ t.stats?.completed || 'Completed' }}</div>
          <div class="flex items-baseline gap-2">
            <div class="text-2xl font-semibold text-emerald-600">{{ stats.completed_memberships }}</div>
            <div class="text-xs text-muted-foreground">{{ completedPct }}</div>
          </div>
        </div>
      </div>

      <!-- Filter -->
      <div class="rounded-xl border border-border bg-card p-3 flex flex-col sm:flex-row sm:items-end gap-2">
        <div class="flex-1 min-w-0">
          <label class="block text-xs font-medium mb-1" for="filter-membership-number">
            {{ t.filter?.label || 'Filter by membership number' }}
          </label>
          <input
            id="filter-membership-number"
            v-model="membershipNumber"
            type="text"
            :placeholder="t.filter?.placeholder || 'e.g. MEM-1010 (matches any batch containing this number)'"
            class="w-full rounded-md border border-border bg-background px-3 py-2 text-sm font-mono placeholder:text-white"
            @input="onMembershipNumberInput"
          />
        </div>
        <button
          v-if="membershipNumber"
          type="button"
          class="inline-flex items-center gap-1 rounded-md bg-red-600 text-white border border-red-700 hover:bg-red-700 px-2.5 py-1.5 text-xs font-medium shadow-sm transition-colors cursor-pointer"
          @click="clearFilter"
        >
          {{ t.filter?.clear || 'Clear' }}
        </button>
      </div>

      <!-- Table -->
      <div class="rounded-xl border border-border bg-card text-card-foreground overflow-hidden">
        <div v-if="cards.data.length === 0" class="p-10 text-center text-sm text-muted-foreground">
          {{ t.empty || 'No batches yet. Create your first one to generate cards.' }}
        </div>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-muted/50 border-b border-border">
              <tr>
                <th class="px-3 py-2 text-left font-medium">{{ t.table?.col_id || '#' }}</th>
                <th class="px-3 py-2 text-left font-medium">{{ t.table?.col_batch_name || 'Batch name' }}</th>
                <th class="px-3 py-2 text-left font-medium">{{ t.table?.col_prefix || 'Prefix' }}</th>
                <th class="px-3 py-2 text-left font-medium">{{ t.table?.col_display_prefix || 'Display prefix' }}</th>
                <th class="px-3 py-2 text-center font-medium">{{ t.table?.col_quantity || 'Quantity' }}</th>
                <th class="px-3 py-2 text-center font-medium">{{ t.table?.col_completed || 'Completed' }}</th>
                <th class="px-3 py-2 text-center font-medium">{{ t.table?.col_range || 'Range' }}</th>
                <th class="px-3 py-2 text-left font-medium">{{ t.table?.col_created_by || 'Created by' }}</th>
                <th class="px-3 py-2 text-left font-medium">{{ t.table?.col_created_at || 'Created at' }}</th>
                <th class="px-3 py-2 text-center font-medium">{{ t.table?.col_pdf || 'PDF' }}</th>
                <th class="px-3 py-2 text-center font-medium">{{ t.table?.col_actions || 'Actions' }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="card in cards.data" :key="card.id" class="border-b border-border hover:bg-muted/30">
                <td class="px-3 py-2 text-muted-foreground">{{ card.id }}</td>
                <td class="px-3 py-2 font-medium">
                  <Link :href="route('admin.membership-card-patches.show', card.id)" class="hover:text-golden-yellow hover:underline">
                    {{ batchTitle(card) }}
                  </Link>
                </td>
                <td class="px-3 py-2 font-mono text-xs">
                  <span v-if="card.prefix">{{ card.prefix }}</span>
                  <span v-else class="text-muted-foreground">—</span>
                </td>
                <td class="px-3 py-2 font-mono text-xs">
                  <span v-if="card.display_prefix">{{ card.display_prefix }}</span>
                  <span v-else class="text-muted-foreground">—</span>
                </td>
                <td class="px-3 py-2 text-center">{{ card.quantity }}</td>
                <td class="px-3 py-2 text-center">
                  <span v-if="card.completed_count > 0" class="text-emerald-600 font-medium">{{ card.completed_count }}</span>
                  <span v-else class="text-muted-foreground">0</span>
                  <span v-if="card.completed_percentage > 0" class="text-xs text-muted-foreground"> ({{ card.completed_percentage }}%)</span>
                </td>
                <td class="px-3 py-2 text-center font-mono text-xs">{{ card.start_number }} – {{ card.end_number }}</td>
                <td class="px-3 py-2">{{ card.creator?.name || '—' }}</td>
                <td class="px-3 py-2 text-muted-foreground">{{ card.created_at }}</td>
                <td class="px-3 py-2 text-center">
                  <a
                    v-if="card.has_pdf"
                    :href="card.pdf_url"
                    :download="pdfDownloadName(card)"
                    class="inline-flex items-center gap-1 rounded-md bg-blue-600 text-white border border-blue-700 hover:bg-blue-700 px-2.5 py-1 text-xs font-medium shadow-sm transition-colors"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
                    {{ t.table?.action_download || 'Download' }}
                  </a>
                  <span v-else class="text-xs text-muted-foreground">—</span>
                </td>
                <td class="px-3 py-2 text-center">
                  <div class="inline-flex items-center gap-2">
                    <Link
                      :href="route('admin.membership-card-patches.show', card.id)"
                      class="inline-flex items-center gap-1 rounded-md bg-emerald-600 text-white border border-emerald-700 hover:bg-emerald-700 px-2.5 py-1 text-xs font-medium shadow-sm transition-colors"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                      {{ t.table?.action_view || 'View' }}
                    </Link>
                    <button
                      type="button"
                      class="inline-flex items-center gap-1 rounded-md bg-red-600 text-white border border-red-700 hover:bg-red-700 px-2.5 py-1 text-xs font-medium shadow-sm transition-colors cursor-pointer"
                      @click="confirmDelete(card)"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                      {{ t.table?.action_delete || 'Delete' }}
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="cards.meta && cards.meta.last_page > 1" class="flex items-center justify-between p-3 border-t border-border">
          <div class="text-xs text-muted-foreground">
            {{ paginationShowing }}
          </div>
          <div class="flex gap-1">
            <Link
              v-for="link in cards.meta.links"
              :key="link.label"
              :href="link.url || '#'"
              v-html="link.label"
              :class="[
                'px-2 py-1 text-xs rounded border',
                link.active ? 'bg-primary text-primary-foreground border-primary' : 'border-border hover:bg-muted',
                !link.url ? 'opacity-50 pointer-events-none' : '',
              ]"
              preserve-scroll
            />
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { AppLayout } from '@/Pages/Admin/Layout/Layout.js';
import { usePermissions } from '@/composables/usePermissions';

const { canManage } = usePermissions();
// Create/export/import are writes: hidden from read-only accounts,
// and refused by the routes behind them either way.
const canWrite = computed(() => canManage('create membership card patches', 'create own membership card patches', 'create partner membership card patches'));


const props = defineProps({
  cards: { type: Object, required: true },
  stats: {
    type: Object,
    default: () => ({ batches: 0, total_memberships: 0, completed_memberships: 0 }),
  },
  filters: {
    type: Object,
    default: () => ({ membership_number: '' }),
  },
});

// Translations — keyed under admin.card_list
const page = usePage();
const t = computed(() => page.props.translations?.admin?.card_list || {});

const userPermissions = computed(() => page.props.auth?.user?.permissions || []);
const userRoles = computed(() => page.props.auth?.user?.roles || []);
const isSuperAdmin = computed(() => userRoles.value.includes('super_admin'));
const can = (permission) => isSuperAdmin.value || userPermissions.value.includes(permission);
const canAny = (...permissions) => permissions.some((p) => can(p));

const paginationShowing = computed(() => {
  const tpl = t.value.pagination_showing || 'Showing :from–:to of :total';
  return tpl
    .replace(':from', props.cards.meta?.from ?? 0)
    .replace(':to', props.cards.meta?.to ?? 0)
    .replace(':total', props.cards.meta?.total ?? 0);
});

function batchTitle(card) {
  if (card.batch_name) return card.batch_name;
  const tpl = t.value.table?.batch_fallback || 'Batch #:id';
  return tpl.replace(':id', card.id);
}

const membershipNumber = ref(props.filters?.membership_number || '');

let mnTimeout = null;
function onMembershipNumberInput() {
  if (mnTimeout) clearTimeout(mnTimeout);
  mnTimeout = setTimeout(applyFilter, 300);
}

function applyFilter() {
  router.get(
    route('admin.membership-card-patches.list'),
    { membership_number: membershipNumber.value.trim() || undefined },
    { preserveState: true, preserveScroll: true, replace: true },
  );
}

function clearFilter() {
  if (mnTimeout) clearTimeout(mnTimeout);
  membershipNumber.value = '';
  applyFilter();
}

const completedPct = computed(() => {
  const t = Number(props.stats.total_memberships) || 0;
  if (!t) return '';
  const c = Number(props.stats.completed_memberships) || 0;
  return `(${Math.round((c / t) * 100)}%)`;
});

function pdfDownloadName(card) {
  const raw = card.batch_name?.trim() || `batch-${card.id}`;
  const safe = raw.replace(/[^A-Za-z0-9_-]+/g, '_').replace(/^_+|_+$/g, '') || `batch-${card.id}`;
  return `${safe}.pdf`;
}

function confirmDelete(card) {
  const tpl = t.value.table?.confirm_delete
    || 'Delete batch ":name"? The generated memberships will stay; only the batch row + PDF will be removed.';
  if (!confirm(tpl.replace(':name', batchTitle(card)))) return;
  router.delete(route('admin.membership-card-patches.destroy', card.id), {
    preserveScroll: true,
  });
}
</script>
