<template>
  <AppLayout title="Visitor">
    <div class="w-full max-w-full space-y-3 p-2 sm:p-3 md:p-4 lg:p-6">
      <div class="bg-card text-card-foreground rounded-xl border border-border p-4 shadow-sm space-y-3">
        <div class="flex flex-wrap items-center gap-2 justify-between">
          <VisitorWho :visitor="visitor" />
          <Link :href="route('admin.visitors.index')" class="h-8 inline-flex items-center rounded-md border border-border px-3 text-xs">← Visitors</Link>
        </div>
        <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-2 text-sm">
          <div><dt class="text-xs text-muted-foreground">Came through</dt><dd><SourceBadge :source="visitor.source" /></dd></div>
          <div><dt class="text-xs text-muted-foreground">Device</dt><dd>{{ [visitor.device_model, visitor.os, visitor.browser, visitor.app_version].filter(Boolean).join(' · ') || '—' }}</dd></div>
          <div><dt class="text-xs text-muted-foreground">IP</dt><dd dir="ltr">{{ visitor.ip_address || '—' }}</dd></div>
          <div><dt class="text-xs text-muted-foreground">Place</dt><dd>{{ [visitor.city, visitor.governorate].filter(Boolean).join(', ') || '—' }}</dd></div>
          <div>
            <dt class="text-xs text-muted-foreground">GPS</dt>
            <dd>
              <a v-if="visitor.latitude" :href="`https://www.google.com/maps?q=${visitor.latitude},${visitor.longitude}`" target="_blank" rel="noopener" class="text-primary hover:underline" dir="ltr">{{ visitor.latitude }}, {{ visitor.longitude }}</a>
              <span v-else>—</span>
            </dd>
          </div>
          <div><dt class="text-xs text-muted-foreground">First seen / last seen</dt><dd>{{ fmt(visitor.first_seen_at) }} → {{ fmt(visitor.last_seen_at) }}</dd></div>
          <div><dt class="text-xs text-muted-foreground">Visits</dt><dd>{{ visitor.visits_count }}</dd></div>
          <div class="sm:col-span-2"><dt class="text-xs text-muted-foreground">First referrer</dt><dd class="break-all">{{ visitor.first_referrer || '—' }}</dd></div>
        </dl>
      </div>

      <div class="bg-card text-card-foreground rounded-xl border border-border shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="[&_tr]:border-b [&_tr]:border-border">
            <tr>
              <th class="h-9 px-3 text-left font-medium whitespace-nowrap">When</th>
              <th class="h-9 px-3 text-left font-medium">Page</th>
              <th class="h-9 px-3 text-left font-medium">Product / facility</th>
              <th class="h-9 px-3 text-left font-medium">Place</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in visits.data" :key="p.id" class="border-b border-border hover:bg-muted/50">
              <td class="p-3 align-middle text-xs whitespace-nowrap">{{ fmt(p.visited_at) }}</td>
              <td class="p-3 align-middle text-xs"><div class="font-medium">{{ p.page }}</div><div class="text-muted-foreground break-all max-w-md">{{ p.url }}</div></td>
              <td class="p-3 align-middle text-xs">
                <Link v-if="p.product" :href="route('admin.product.show', p.product.slug)" class="text-primary hover:underline">{{ p.product.name }}</Link>
                <span v-else-if="p.facility">{{ p.facility.name }}</span>
                <span v-else>—</span>
              </td>
              <td class="p-3 align-middle text-xs">{{ [p.city, p.governorate].filter(Boolean).join(', ') || '—' }}</td>
            </tr>
            <tr v-if="!visits.data.length"><td colspan="4" class="p-8 text-center text-muted-foreground">No page visits.</td></tr>
          </tbody>
        </table>
      </div>
      <Pagination v-if="visits.links?.length > 3" :links="visits.links" />
    </div>
  </AppLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Pages/_components/Pagination.vue';
import SourceBadge from './_components/SourceBadge.vue';
import VisitorWho from './_components/VisitorWho.vue';

defineProps({
  visitor: { type: Object, required: true },
  visits: { type: Object, required: true },
});

const fmt = (iso) => (iso ? new Date(iso).toLocaleString() : '—');
</script>
