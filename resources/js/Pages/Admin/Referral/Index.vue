<template>
  <AppLayout>
    <div class="space-y-3 p-3 sm:p-4 lg:p-6 w-full max-w-full">
      <Breadcrumb
        :title="t.referrals?.title || 'Referrals'"
        :breadcrumbs="[{ label: t.referrals?.title || 'Referrals', link: '#', active: true }]"
      />

      <div class="max-w-7xl mx-auto space-y-4">
        <!-- Totals over every referral -->
        <section class="grid grid-cols-2 lg:grid-cols-4 gap-3">
          <div v-for="card in cards" :key="card.key" class="bg-card rounded-xl border border-border shadow-sm p-3" :data-testid="`total-${card.key}`">
            <p class="text-xs text-muted-foreground">{{ card.label }}</p>
            <p class="mt-1 text-xl font-semibold tabular-nums" dir="ltr">{{ card.value }}</p>
          </div>
        </section>

        <form class="bg-card rounded-xl border border-border shadow-sm p-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3" @submit.prevent="apply">
          <input v-model="form.search" type="search" :placeholder="t.referrals?.search || 'Name, phone or card number'" class="h-9 rounded-md border border-border bg-background px-2 text-sm sm:col-span-2" data-testid="referral-search" />
          <select v-model="form.status" class="h-9 rounded-md border border-border bg-background px-2 text-sm">
            <option value="">{{ t.referrals?.all_status || 'Subscribed + waiting' }}</option>
            <option value="subscribed">{{ t.referrals?.subscribed || 'Subscribed' }}</option>
            <option value="waiting">{{ t.referrals?.waiting || 'Not subscribed yet' }}</option>
          </select>
          <select v-model="form.source" class="h-9 rounded-md border border-border bg-background px-2 text-sm">
            <option value="">{{ t.referrals?.all_sources || 'Link + typed number' }}</option>
            <option value="link">{{ t.referrals?.link || 'Link / QR' }}</option>
            <option value="card">{{ t.referrals?.card || 'Typed card number' }}</option>
          </select>
          <div class="flex flex-wrap gap-2">
            <button type="submit" class="h-9 min-w-[7rem] rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground">{{ t.referrals?.filter || 'Filter' }}</button>
            <button type="button" class="h-9 min-w-[7rem] rounded-md border border-border px-4 text-sm" @click="reset">{{ t.referrals?.reset || 'Reset' }}</button>
          </div>
        </form>

        <section class="bg-card rounded-xl border border-border shadow-sm overflow-hidden">
          <p v-if="!rows.length" class="p-10 text-center text-sm text-muted-foreground" data-testid="referral-empty">{{ t.referrals?.empty || 'No referrals yet.' }}</p>
          <div v-else class="divide-y divide-border">
            <div v-for="r in rows" :key="r.id" class="flex flex-wrap items-center gap-x-4 gap-y-1 px-3 py-2.5 text-sm" data-testid="referral-row">
              <div class="min-w-[10rem]">
                <p class="text-xs text-muted-foreground">{{ t.referrals?.referrer || 'Brought by' }}</p>
                <p class="font-medium">{{ r.referrer.name || '—' }}</p>
                <p class="text-xs" dir="ltr"><span class="chip chip-neutral font-mono"># {{ r.referrer.number }}</span> {{ r.referrer.phone }}</p>
              </div>
              <div class="min-w-[10rem]">
                <p class="text-xs text-muted-foreground">{{ t.referrals?.referred || 'New member' }}</p>
                <p class="font-medium">{{ r.referred.name || '—' }}</p>
                <p class="text-xs" dir="ltr">{{ r.referred.phone }}</p>
              </div>
              <span class="chip" :class="r.source === 'card' ? 'chip-info' : 'chip-accent'">{{ r.source === 'card' ? (t.referrals?.card || 'Typed card number') : (t.referrals?.link || 'Link / QR') }}</span>
              <span class="chip" :class="r.subscribed ? 'chip-positive' : 'chip-neutral'" data-testid="referral-status">{{ r.subscribed ? (t.referrals?.subscribed || 'Subscribed') : (t.referrals?.waiting || 'Not subscribed yet') }}</span>
              <div class="flex-1 min-w-[12rem] text-xs">
                <p>{{ t.referrals?.paid_trial || 'Trial reward' }}: <b class="tabular-nums" dir="ltr">{{ r.trial_bonus }}</b></p>
                <p>{{ t.referrals?.paid_commission || 'Commission' }}: <b class="tabular-nums" dir="ltr">{{ r.commission }}</b> <span class="text-muted-foreground">({{ r.payments }})</span></p>
                <p>{{ t.referrals?.discount || 'Discount' }}: <b class="tabular-nums" dir="ltr">{{ r.discount }}</b> <span class="text-muted-foreground">({{ r.discount_used ? (t.referrals?.used || 'used') : (t.referrals?.pending || 'waiting') }})</span></p>
              </div>
              <div class="text-xs font-medium text-foreground/80" dir="ltr">
                <p>{{ r.joined_at }}</p>
                <p v-if="r.subscribed_at" class="text-emerald-600">{{ r.subscribed_at }}</p>
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
import { computed, reactive } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { AppLayout, Breadcrumb } from '@/Pages/Admin/Layout/Layout.js';

const props = defineProps({
  rows: { type: Array, default: () => [] },
  meta: { type: Object, default: () => ({ last_page: 1, links: [] }) },
  totals: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const cards = computed(() => [
  { key: 'count', label: t.value.referrals?.t_count || 'Members brought', value: props.totals.count },
  { key: 'trials', label: t.value.referrals?.t_trials || 'Of them started a trial', value: props.totals.trials },
  { key: 'subscribed', label: t.value.referrals?.t_subscribed || 'Of them subscribed', value: props.totals.subscribed },
  { key: 'paid', label: t.value.referrals?.t_paid || 'Paid to referrers (EGP)', value: props.totals.paid_to_referrers },
  { key: 'reversed', label: t.value.referrals?.t_reversed || 'Commission reversed (refunds, EGP)', value: props.totals.reversed },
  { key: 'discounts', label: t.value.referrals?.t_discounts || 'Discounts used (EGP)', value: props.totals.discounts_given },
]);

const form = reactive({ search: props.filters.search || '', status: props.filters.status || '', source: props.filters.source || '' });
const apply = () => {
  const params = Object.fromEntries(Object.entries(form).filter(([, v]) => v !== '' && v !== null));
  router.get(route('admin.referrals.index'), params, { preserveState: true, preserveScroll: true, replace: true });
};
const reset = () => {
  Object.assign(form, { search: '', status: '', source: '' });
  apply();
};
</script>
