<template>
  <AppLayout>
    <div class="space-y-3 p-3 sm:p-4 lg:p-6 w-full max-w-full">
      <Breadcrumb
        :title="t.withdrawals?.title || 'Wallet cash-outs'"
        :breadcrumbs="[{ label: t.withdrawals?.title || 'Wallet cash-outs', link: '#', active: true }]"
      />

      <div class="max-w-7xl mx-auto space-y-4">
        <p v-if="!rules.configured" class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900" data-testid="wd-not-configured">
          {{ t.withdrawals?.not_configured || 'Paymob Payouts is not set up (PAYMOB_PAYOUTS_* in .env). Members cannot cash out.' }}
        </p>
        <p v-else-if="!rules.switch" class="rounded-lg border border-border bg-muted/40 p-3 text-sm">
          {{ t.withdrawals?.switch_off || 'Cash-out is switched off in /admin/setting (wallet_withdrawals_enabled).' }}
        </p>
        <p v-if="rules.auto" class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm font-semibold text-red-800">
          {{ t.withdrawals?.auto_on || 'AUTO is on: requests are paid at once with no approval.' }}
        </p>

        <section class="grid grid-cols-2 lg:grid-cols-6 gap-3">
          <button v-for="s in statuses" :key="s" type="button" class="bg-card rounded-xl border border-border p-3 text-start" :class="filters.status === s ? 'ring-2 ring-primary' : ''" @click="filter(s)">
            <p class="text-xs text-muted-foreground">{{ label(s) }}</p>
            <p class="mt-1 text-xl font-semibold tabular-nums" dir="ltr">{{ counts[s] || 0 }}</p>
          </button>
        </section>

        <section class="bg-card rounded-xl border border-border shadow-sm overflow-hidden">
          <p v-if="!rows.length" class="p-10 text-center text-sm text-muted-foreground">{{ t.withdrawals?.empty || 'No cash-out requests.' }}</p>
          <div v-else class="divide-y divide-border">
            <div v-for="r in rows" :key="r.id" class="p-3 flex flex-wrap items-center gap-3" :data-testid="`wd-row-${r.id}`">
              <div class="min-w-[10rem]">
                <p class="font-medium">{{ r.member.name || '—' }}</p>
                <p class="text-xs text-muted-foreground" dir="ltr">{{ r.member.phone }}</p>
              </div>
              <div class="min-w-[8rem] text-sm" dir="ltr">
                <p class="font-semibold tabular-nums">{{ r.amount }} EGP</p>
                <p class="text-xs">→ {{ r.msisdn }}</p>
              </div>
              <span class="chip" :class="chip(r.status)">{{ label(r.status) }}</span>
              <div class="flex-1 min-w-[12rem] text-xs text-muted-foreground">
                <p v-if="r.provider_code">Paymob {{ r.provider_code }}<span v-if="r.provider_message"> — {{ r.provider_message }}</span></p>
                <p v-if="r.refunded">{{ t.withdrawals?.refunded || 'Money returned to the wallet' }}</p>
                <p dir="ltr">{{ r.created_at }}<span v-if="r.paid_at"> · paid {{ r.paid_at }}</span></p>
              </div>
              <div class="flex flex-wrap gap-2">
                <button v-if="r.status === 'pending'" type="button" class="h-9 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground" :data-testid="`wd-approve-${r.id}`" @click="approve(r)">{{ t.withdrawals?.approve || 'Approve & send' }}</button>
                <button v-if="r.status === 'pending'" type="button" class="h-9 rounded-md border border-border px-3 text-sm" @click="reject(r)">{{ t.withdrawals?.reject || 'Reject' }}</button>
                <button v-if="r.status === 'processing'" type="button" class="h-9 rounded-md border border-border px-3 text-sm" @click="refresh(r)">{{ t.withdrawals?.check || 'Ask Paymob' }}</button>
              </div>
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
  rows: { type: Array, default: () => [] },
  meta: { type: Object, default: () => ({ last_page: 1, links: [] }) },
  rules: { type: Object, required: true },
  counts: { type: Object, default: () => ({}) },
  filters: { type: Object, default: () => ({}) },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});
const statuses = ['pending', 'processing', 'paid', 'failed', 'rejected', 'canceled'];
const names = {
  pending: ['Waiting for approval', 'في انتظار الموافقة'], processing: ['Sent to Paymob', 'أُرسل إلى Paymob'], paid: ['Paid', 'تم الصرف'],
  failed: ['Failed (money returned)', 'فشل (رجعت الفلوس)'], rejected: ['Rejected', 'مرفوض'], canceled: ['Canceled by member', 'ألغاه العضو'],
};
const label = (s) => t.value.withdrawals?.['s_' + s] || names[s][0];
const chip = (s) => ({ paid: 'chip-positive', pending: 'chip-accent', processing: 'chip-info', failed: 'chip-neutral', rejected: 'chip-neutral', canceled: 'chip-neutral' }[s]);

const filter = (s) => router.get(route('admin.withdrawals.index'), props.filters.status === s ? {} : { status: s }, { preserveState: true, replace: true });
const approve = (r) => {
  if (confirm(`${r.amount} EGP → ${r.msisdn}?`)) router.post(route('admin.withdrawals.approve', r.id), {}, { preserveScroll: true });
};
const reject = (r) => {
  const reason = prompt(t.value.withdrawals?.reason || 'Reason (optional)') ?? null;
  if (reason !== null) router.post(route('admin.withdrawals.reject', r.id), { reason }, { preserveScroll: true });
};
const refresh = (r) => router.post(route('admin.withdrawals.refresh', r.id), {}, { preserveScroll: true });
</script>
