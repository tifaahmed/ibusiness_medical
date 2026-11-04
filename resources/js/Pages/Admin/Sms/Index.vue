<template>
  <AppLayout>
    <div class="space-y-3 p-3 sm:p-4 lg:p-6 w-full max-w-full">
      <Breadcrumb :title="t.sms?.title || 'Customer SMS'" :breadcrumbs="[{ label: t.sms?.title || 'Customer SMS', link: '#', active: true }]" />

      <div class="max-w-5xl mx-auto space-y-4">
        <!-- The master switch: while it is off, messages are only recorded as queued. -->
        <section class="bg-card rounded-xl border border-border shadow-sm p-4 space-y-3" data-testid="sms-switch">
          <div class="flex flex-wrap items-center gap-3">
            <span class="chip" :class="enabled ? 'chip-positive' : 'chip-warning'" data-testid="sms-state">{{ enabled ? (t.sms?.on || 'Sending is ON') : (t.sms?.off || 'Sending is OFF — messages only queue') }}</span>
            <span v-if="!configured" class="chip chip-negative">{{ t.sms?.not_configured || 'Gateway not configured in .env' }}</span>
            <button type="button" class="h-9 min-w-[9rem] rounded-md px-4 text-sm font-medium" :class="enabled ? 'border border-border' : 'bg-primary text-primary-foreground'" data-testid="sms-toggle" @click="toggle">{{ enabled ? (t.sms?.turn_off || 'Turn sending off') : (t.sms?.turn_on || 'Turn sending on') }}</button>
            <button v-if="enabled" type="button" class="h-9 min-w-[9rem] rounded-md border border-border px-4 text-sm" @click="sendQueued">{{ t.sms?.send_queued || 'Send queued now' }}</button>
          </div>
          <p v-if="enabled" class="panel-note text-xs">{{ t.sms?.warning_on || 'Real messages are leaving and each one costs money. Turn it off to stop.' }}</p>
          <div class="flex flex-wrap gap-2 text-xs">
            <span v-for="s in ['queued', 'sent', 'failed']" :key="s" class="chip" :class="{ 'chip-warning': s === 'queued', 'chip-positive': s === 'sent', 'chip-negative': s === 'failed' }">{{ s }}: {{ counts[s]?.n || 0 }} · {{ counts[s]?.segments || 0 }} {{ t.sms?.billed || 'billed parts' }}</span>
          </div>
        </section>

        <div class="flex flex-wrap gap-2">
          <button v-for="s in ['', 'queued', 'sent', 'failed']" :key="s" type="button" class="h-8 min-w-[5rem] rounded-md border px-3 text-xs" :class="status === s ? 'bg-primary text-primary-foreground' : 'border-border'" @click="filter(s)">{{ s || (t.sms?.all || 'All') }}</button>
        </div>

        <section class="bg-card rounded-xl border border-border shadow-sm overflow-hidden">
          <div v-if="!rows.length" class="p-10 text-center text-sm text-muted-foreground" data-testid="sms-empty">{{ t.sms?.empty || 'No messages yet.' }}</div>
          <div v-else class="divide-y divide-border">
            <div v-for="m in rows" :key="m.id" class="px-3 py-2.5 text-sm space-y-1" data-testid="sms-row">
              <div class="flex flex-wrap items-center gap-2">
                <span class="chip" :class="{ 'chip-warning': m.status === 'queued', 'chip-positive': m.status === 'sent', 'chip-negative': m.status === 'failed' }">{{ m.status }}</span>
                <span class="chip chip-neutral tabular-nums" dir="ltr">{{ m.characters }} / {{ m.segments }}</span>
                <span class="text-xs text-foreground/80" dir="ltr">{{ m.phone }}</span>
                <Link v-if="m.member" :href="route('admin.user.membership.show', m.member.slug)" class="font-medium hover:underline">{{ m.member.name }}</Link>
                <span v-if="m.by" class="chip chip-neutral">{{ m.by }}</span>
                <span class="text-xs text-foreground/80" dir="ltr">{{ m.created_at }}</span>
              </div>
              <p class="whitespace-pre-line break-words" dir="auto">{{ m.body }}</p>
              <p v-if="m.error" class="text-xs value-negative">{{ m.error }}</p>
            </div>
          </div>
        </section>

        <nav v-if="meta.last_page > 1" class="flex flex-wrap gap-1 justify-center">
          <template v-for="(l, i) in meta.links" :key="i">
            <Link v-if="l.url" :href="l.url" preserve-scroll class="min-w-[2.25rem] h-9 inline-flex items-center justify-center rounded-md border border-border px-2 text-sm" :class="l.active ? 'bg-primary text-primary-foreground' : 'bg-background'" v-html="l.label" />
          </template>
        </nav>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { AppLayout, Breadcrumb } from '@/Pages/Admin/Layout/Layout.js';

const props = defineProps({
  enabled: { type: Boolean, default: false },
  configured: { type: Boolean, default: false },
  rows: { type: Array, default: () => [] },
  meta: { type: Object, default: () => ({ last_page: 1, links: [] }) },
  counts: { type: Object, default: () => ({}) },
  status: { type: String, default: '' },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const toggle = () => {
  const turningOn = !props.enabled;
  if (turningOn && !window.confirm(t.value.sms?.confirm_on || 'Turn real sending ON? Each message costs money.')) return;
  router.put(route('admin.sms.toggle'), { enabled: turningOn }, { preserveScroll: true });
};
const sendQueued = () => { if (window.confirm(t.value.sms?.confirm_send || 'Send every queued message now?')) router.post(route('admin.sms.send-queued'), {}, { preserveScroll: true }); };
const filter = (s) => router.get(route('admin.sms.index'), s ? { status: s } : {}, { preserveState: true, replace: true });
</script>
