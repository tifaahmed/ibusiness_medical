<template>
  <AppLayout>
    <div class="space-y-3 p-3 sm:p-4 lg:p-6 w-full max-w-full">
      <Breadcrumb
        :title="t.loyalty?.log_title || 'Points & wallet log'"
        :breadcrumbs="[{ label: t.loyalty?.log_title || 'Points & wallet log', link: '#', active: true }]"
      />

      <div class="max-w-7xl mx-auto space-y-4">
        <!-- Totals of what the filters below match -->
        <section class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div v-for="kind in ['points', 'wallet']" :key="kind" class="bg-card rounded-xl border border-border shadow-sm p-3">
            <div class="flex items-center gap-2 text-sm font-semibold">
              <span class="chip" :class="kind === 'wallet' ? 'chip-info' : 'chip-accent'">{{ kind === 'wallet' ? (t.loyalty?.wallet || 'Wallet') : (t.loyalty?.points_word || 'Points') }}</span>
              <span class="text-muted-foreground text-xs">{{ totals[kind].n }} {{ t.loyalty?.lines || 'lines' }}</span>
            </div>
            <div class="mt-1 flex flex-wrap gap-x-6 gap-y-1 text-sm">
              <span class="chip chip-positive tabular-nums text-sm" dir="ltr">+{{ totals[kind].given }}</span>
              <span class="chip chip-negative tabular-nums text-sm" dir="ltr">{{ totals[kind].taken }}</span>
            </div>
          </div>
        </section>

        <!-- Filters: wrap on every width, wide ones take their own row -->
        <form class="bg-card rounded-xl border border-border shadow-sm p-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3" @submit.prevent="apply">
          <input v-model="form.search" type="search" :placeholder="t.loyalty?.search_member || 'Member name, phone or card number'" class="h-9 rounded-md border border-border bg-background px-2 text-sm sm:col-span-2" data-testid="log-search" />
          <select v-model="form.ledger" class="h-9 rounded-md border border-border bg-background px-2 text-sm">
            <option value="">{{ t.loyalty?.all_types || 'Points + wallet' }}</option>
            <option value="points">{{ t.loyalty?.points_word || 'Points' }}</option>
            <option value="wallet">{{ t.loyalty?.wallet || 'Wallet' }}</option>
          </select>
          <select v-model="form.direction" class="h-9 rounded-md border border-border bg-background px-2 text-sm">
            <option value="">{{ t.loyalty?.given_and_taken || 'Given + taken' }}</option>
            <option value="given">{{ t.loyalty?.given || 'Given' }}</option>
            <option value="taken">{{ t.loyalty?.taken || 'Taken' }}</option>
          </select>
          <select v-model="form.admin_id" class="h-9 rounded-md border border-border bg-background px-2 text-sm">
            <option value="">{{ t.loyalty?.all_admins || 'Every admin' }}</option>
            <option v-for="a in adminOptions" :key="a.id" :value="a.id">{{ a.name }}</option>
          </select>
          <input v-model="form.from" type="date" class="h-9 rounded-md border border-border bg-background px-2 text-sm" dir="ltr" />
          <input v-model="form.to" type="date" class="h-9 rounded-md border border-border bg-background px-2 text-sm" dir="ltr" />
          <div class="flex flex-wrap gap-2">
            <button type="submit" class="h-9 min-w-[7rem] rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground">{{ t.loyalty?.filter || 'Filter' }}</button>
            <button type="button" class="h-9 min-w-[7rem] rounded-md border border-border px-4 text-sm" @click="reset">{{ t.loyalty?.reset || 'Reset' }}</button>
          </div>
        </form>

        <!-- One line per change -->
        <section class="bg-card rounded-xl border border-border shadow-sm overflow-hidden">
          <div v-if="!rows.length" class="flex flex-col items-center gap-2 p-10 text-muted-foreground" data-testid="log-empty">
            <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/></svg>
            <p class="text-sm">{{ t.loyalty?.log_empty || 'Nothing has been given or taken yet.' }}</p>
          </div>
          <div v-else class="divide-y divide-border">
            <div v-for="r in rows" :key="r.key" class="flex flex-wrap items-center gap-x-4 gap-y-1 px-3 py-2.5 text-sm" data-testid="log-row">
              <span class="chip" :class="r.ledger === 'wallet' ? 'chip-info' : 'chip-accent'">{{ r.ledger === 'wallet' ? (t.loyalty?.wallet || 'Wallet') : (t.loyalty?.points_word || 'Points') }}</span>
              <span class="chip min-w-[5rem] justify-center text-sm tabular-nums" :class="r.value < 0 ? 'chip-negative' : 'chip-positive'" dir="ltr">{{ r.value > 0 ? '+' : '' }}{{ r.value }}</span>
              <div class="min-w-[10rem]">
                <component :is="r.member ? Link : 'span'" v-bind="r.member ? { href: route('admin.user.membership.edit', r.member.slug) } : {}" class="font-medium hover:underline">{{ r.member?.name || '—' }}</component>
                <div class="mt-0.5 flex flex-wrap items-center gap-1.5 text-xs" dir="ltr">
                  <span v-if="r.member?.membership_number" class="chip chip-neutral font-mono"># {{ r.member.membership_number }}</span>
                  <span v-if="r.member?.phone" class="text-foreground/80">{{ r.member.phone }}</span>
                </div>
              </div>
              <span class="flex-1 min-w-[12rem] text-foreground">{{ r.note || '—' }}</span>
              <span class="chip chip-neutral">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                {{ r.admin }}
              </span>
              <span class="text-xs font-medium text-foreground/80" dir="ltr">{{ r.at }}</span>
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
  adminOptions: { type: Array, default: () => [] },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const form = reactive({
  search: props.filters.search || '',
  ledger: props.filters.ledger || '',
  direction: props.filters.direction || '',
  admin_id: props.filters.admin_id || '',
  from: props.filters.from || '',
  to: props.filters.to || '',
});

const apply = () => {
  const params = Object.fromEntries(Object.entries(form).filter(([, v]) => v !== '' && v !== null));
  router.get(route('admin.points-wallet-log'), params, { preserveState: true, preserveScroll: true, replace: true });
};
const reset = () => {
  Object.assign(form, { search: '', ledger: '', direction: '', admin_id: '', from: '', to: '' });
  apply();
};
</script>
