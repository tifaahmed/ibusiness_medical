<template>
  <AppLayout title="Visitors">
    <div class="w-full max-w-full">
      <div class="space-y-3 p-2 sm:p-3 md:p-4 lg:p-6 w-full max-w-full">
        <div class="bg-card text-card-foreground rounded-xl border border-border py-3 px-4 shadow-sm">
          <div class="title-golden flex items-center gap-2 font-semibold">
            <svg class="title-icon flex-shrink-0 text-blue-400" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
            Visitors
          </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
          <div class="rounded-xl border border-border bg-card p-3"><div class="text-xs text-muted-foreground">Visitors</div><div class="text-xl font-bold">{{ stats.total }}</div></div>
          <div class="rounded-xl border border-border bg-card p-3"><div class="text-xs text-muted-foreground">Identified</div><div class="text-xl font-bold text-amber-500">{{ stats.identified }}</div></div>
          <div class="rounded-xl border border-border bg-card p-3"><div class="text-xs text-muted-foreground">Members</div><div class="text-xl font-bold text-emerald-500">{{ stats.members }}</div></div>
          <div class="rounded-xl border border-border bg-card p-3 flex flex-wrap items-center gap-2">
            <span v-for="(n, s) in stats.by_source" :key="s" class="inline-flex items-center gap-1 text-xs"><SourceBadge :source="s" /> {{ n }}</span>
          </div>
        </div>

        <div class="rounded-xl border border-border bg-card p-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2">
          <input v-model="f.search" type="search" placeholder="Search phone, email, name, IP…" class="h-8 rounded-md border border-border bg-transparent px-2 text-sm min-w-0" @keyup.enter="apply" />
          <select v-model="f.source" class="h-8 rounded-md border border-border bg-card px-2 text-sm min-w-0" @change="apply">
            <option value="all">All sources</option>
            <option v-for="s in sources" :key="s.value" :value="s.value">{{ s.label }}</option>
          </select>
          <select v-model="f.identified" class="h-8 rounded-md border border-border bg-card px-2 text-sm min-w-0" @change="apply">
            <option value="">Everyone</option>
            <option value="yes">Identified</option>
            <option value="member">Signed-in members</option>
            <option value="no">Anonymous</option>
          </select>
          <select v-model="f.governorate_id" class="h-8 rounded-md border border-border bg-card px-2 text-sm min-w-0" @change="apply">
            <option value="">All governorates</option>
            <option v-for="g in governorates" :key="g.id" :value="g.id">{{ g.name }}</option>
          </select>
        </div>

        <div class="bg-card text-card-foreground rounded-xl border border-border shadow-sm overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="[&_tr]:border-b [&_tr]:border-border">
              <tr>
                <th class="h-9 px-3 text-left font-medium">Visitor</th>
                <th class="h-9 px-3 text-center font-medium">Came through</th>
                <th class="h-9 px-3 text-left font-medium">Device</th>
                <th class="h-9 px-3 text-left font-medium">Place</th>
                <th class="h-9 px-3 text-center font-medium">Visits</th>
                <th class="h-9 px-3 text-left font-medium whitespace-nowrap">Last seen</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="v in visitors.data" :key="v.id" class="border-b border-border hover:bg-muted/50">
                <td class="p-3 align-middle"><VisitorWho :visitor="v" /></td>
                <td class="p-3 align-middle text-center"><SourceBadge :source="v.source" /></td>
                <td class="p-3 align-middle text-xs">{{ [v.device_model, v.os, v.browser].filter(Boolean).join(' · ') || '—' }}<div class="text-muted-foreground" dir="ltr">{{ v.ip_address }}</div></td>
                <td class="p-3 align-middle text-xs">{{ [v.city, v.governorate].filter(Boolean).join(', ') || '—' }}</td>
                <td class="p-3 align-middle text-center">{{ v.visits_count }}</td>
                <td class="p-3 align-middle text-xs whitespace-nowrap">{{ fmt(v.last_seen_at) }}</td>
              </tr>
              <tr v-if="!visitors.data.length">
                <td colspan="6" class="p-8 text-center text-muted-foreground">No visitors match.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <Pagination v-if="visitors.links?.length > 3" :links="visitors.links" />
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Pages/_components/Pagination.vue';
import SourceBadge from './_components/SourceBadge.vue';
import VisitorWho from './_components/VisitorWho.vue';

const props = defineProps({
  visitors: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
  sources: { type: Array, default: () => [] },
  governorates: { type: Array, default: () => [] },
  stats: { type: Object, required: true },
});

const f = ref({
  search: props.filters.search || '',
  source: props.filters.source || 'all',
  identified: props.filters.identified || '',
  governorate_id: props.filters.governorate_id || '',
});

const fmt = (iso) => (iso ? new Date(iso).toLocaleString() : '—');
const apply = () => router.get(route('admin.visitors.index'), Object.fromEntries(Object.entries(f.value).filter(([, v]) => v !== '' && v !== 'all')), { preserveState: true, replace: true });
</script>
