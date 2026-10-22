<template>
  <div class="space-y-3">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
      <div class="rounded-xl border border-border bg-card p-3">
        <div class="text-xs text-muted-foreground">Views</div>
        <div class="text-xl font-bold">{{ stats.views }}</div>
      </div>
      <div class="rounded-xl border border-border bg-card p-3">
        <div class="text-xs text-muted-foreground">Unique visitors</div>
        <div class="text-xl font-bold">{{ stats.unique_visitors }}</div>
      </div>
      <div class="rounded-xl border border-border bg-card p-3">
        <div class="text-xs text-muted-foreground">Members</div>
        <div class="text-xl font-bold text-emerald-500">{{ stats.members }}</div>
      </div>
      <div class="rounded-xl border border-border bg-card p-3 flex flex-wrap gap-1 items-center">
        <span v-for="(n, s) in stats.by_source" :key="s" class="inline-flex items-center gap-1 text-xs"><SourceBadge :source="s" /> {{ n }}</span>
        <span v-if="!Object.keys(stats.by_source).length" class="text-xs text-muted-foreground">No visits yet</span>
      </div>
    </div>

    <div class="flex flex-wrap gap-2 items-center">
      <select v-model="filters.source" class="h-8 rounded-md border border-border bg-card px-2 text-xs" @change="load(1)">
        <option value="all">All sources</option>
        <option value="mobile">Mobile app (Android + iOS)</option>
        <option v-for="s in sources" :key="s.value" :value="s.value">{{ s.label }}</option>
      </select>
      <select v-model="filters.identified" class="h-8 rounded-md border border-border bg-card px-2 text-xs" @change="load(1)">
        <option value="">Everyone</option>
        <option value="yes">Identified (phone / email / member)</option>
        <option value="member">Signed-in members</option>
      </select>
    </div>

    <div class="bg-card text-card-foreground rounded-xl border border-border shadow-sm overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="[&_tr]:border-b [&_tr]:border-border">
          <tr>
            <th class="h-9 px-3 text-left font-medium whitespace-nowrap">When</th>
            <th class="h-9 px-3 text-left font-medium">Visitor</th>
            <th class="h-9 px-3 text-center font-medium">Came through</th>
            <th class="h-9 px-3 text-left font-medium">Place</th>
            <th class="h-9 px-3 text-left font-medium">IP</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="v in rows" :key="v.id" class="border-b border-border hover:bg-muted/50">
            <td class="p-3 align-middle whitespace-nowrap text-xs">{{ fmt(v.visited_at) }}</td>
            <td class="p-3 align-middle"><VisitorWho v-if="v.visitor" :visitor="v.visitor" /></td>
            <td class="p-3 align-middle text-center"><SourceBadge :source="v.source" /></td>
            <td class="p-3 align-middle text-xs">{{ [v.visitor?.city, v.visitor?.governorate].filter(Boolean).join(', ') || '—' }}</td>
            <td class="p-3 align-middle text-xs" dir="ltr">{{ v.ip_address || '—' }}</td>
          </tr>
          <tr v-if="!rows.length && !loading">
            <td colspan="5" class="p-8 text-center text-muted-foreground">
              <svg class="mx-auto mb-2 h-8 w-8 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
              No visits recorded for this product yet.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="meta.last_page > 1" class="flex items-center justify-between text-xs">
      <span>{{ meta.total }} visits</span>
      <div class="flex gap-1">
        <button :disabled="meta.current_page <= 1" class="h-8 rounded-md border border-border px-3 disabled:opacity-50 cursor-pointer" @click="load(meta.current_page - 1)">Prev</button>
        <span class="px-2 self-center">{{ meta.current_page }} / {{ meta.last_page }}</span>
        <button :disabled="meta.current_page >= meta.last_page" class="h-8 rounded-md border border-border px-3 disabled:opacity-50 cursor-pointer" @click="load(meta.current_page + 1)">Next</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';
import SourceBadge from './SourceBadge.vue';
import VisitorWho from './VisitorWho.vue';

const props = defineProps({ slug: { type: String, required: true } });

const rows = ref([]);
const stats = ref({ views: 0, unique_visitors: 0, members: 0, by_source: {} });
const sources = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const filters = ref({ source: 'all', identified: '' });
const loading = ref(false);

const fmt = (iso) => (iso ? new Date(iso).toLocaleString() : '—');

async function load(pageNo = 1) {
  loading.value = true;
  try {
    const { data } = await axios.get(route('admin.product.visitors', props.slug), { params: { ...filters.value, page: pageNo } });
    rows.value = data.visits.data;
    meta.value = data.visits.meta;
    stats.value = data.stats;
    sources.value = data.sources;
  } catch (e) {
    try {
      axios.post('/api/v1/client-errors', { message: String(e?.message || e), stack: e?.stack, route: 'admin.product.visitors', extra: { slug: props.slug } }).catch(() => {});
    } catch (_) { /* reporting must never break the page */ }
  } finally {
    loading.value = false;
  }
}

onMounted(() => load(1));
</script>
