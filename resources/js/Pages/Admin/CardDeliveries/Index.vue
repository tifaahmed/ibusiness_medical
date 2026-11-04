<template>
  <AppLayout>
    <div class="space-y-3 p-3 sm:p-4 lg:p-6 w-full max-w-full">
      <Breadcrumb
        :title="t.card_deliveries?.title || 'Card deliveries'"
        :breadcrumbs="[{ label: t.card_deliveries?.title || 'Card deliveries', link: '#', active: true }]"
      />

      <div class="max-w-5xl mx-auto space-y-4">
        <div class="flex flex-wrap gap-2 justify-end">
          <Link :href="route('admin.delivery-couriers.index')" class="inline-flex h-9 min-w-[8rem] items-center justify-center rounded-md border border-border px-4 text-sm">{{ t.card_deliveries?.couriers || 'Delivery couriers' }}</Link>
        </div>

        <section v-if="canManage" class="bg-card rounded-xl border border-border shadow-sm p-3 space-y-2" data-testid="delivery-add">
          <h3 class="text-sm font-semibold">{{ t.card_deliveries?.add_title || 'Record a card delivery' }}</h3>
          <div class="relative">
            <input v-if="!picked" v-model="memberQuery" type="search" :placeholder="t.member_notes?.find_member || 'Find the member: name, phone or card number'" class="h-9 w-full rounded-md border border-border bg-background px-2 text-sm" @input="searchMembers" />
            <div v-else class="flex flex-wrap items-center gap-2 text-sm">
              <span class="chip chip-accent">{{ picked.name }}</span>
              <span v-if="picked.membership_number" class="chip chip-neutral font-mono" dir="ltr"># {{ picked.membership_number }}</span>
              <button type="button" class="text-xs text-blue-400 hover:underline" @click="picked = null">{{ t.member_notes?.change_member || 'Change' }}</button>
            </div>
            <ul v-if="!picked && options.length" class="absolute z-20 mt-1 w-full max-h-60 overflow-auto rounded-md border border-border bg-card shadow-lg">
              <li v-for="o in options" :key="o.id">
                <button type="button" class="flex w-full flex-wrap items-center gap-2 px-3 py-2 text-start text-sm hover:bg-muted" @click="pick(o)">
                  <span class="font-medium">{{ o.name }}</span>
                  <span v-if="o.membership_number" class="chip chip-neutral font-mono" dir="ltr"># {{ o.membership_number }}</span>
                  <span v-if="o.phone" class="text-xs text-foreground/80" dir="ltr">{{ o.phone }}</span>
                </button>
              </li>
            </ul>
          </div>
          <DeliveryForm v-if="picked" :key="picked.id" :user-id="picked.id" :couriers="couriers" @done="picked = null" />
        </section>

        <form class="bg-card rounded-xl border border-border shadow-sm p-3 grid grid-cols-1 sm:grid-cols-3 gap-3" @submit.prevent="apply">
          <input v-model="form.search" type="search" :placeholder="t.card_deliveries?.search || 'Search member, phone, card or note'" class="h-9 rounded-md border border-border bg-background px-2 text-sm sm:col-span-3" />
          <select v-model="form.courier_id" class="h-9 rounded-md border border-border bg-background px-2 text-sm">
            <option value="">{{ t.card_deliveries?.all_couriers || 'Every courier' }}</option>
            <option v-for="c in couriers" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
          <select v-model="form.status" class="h-9 rounded-md border border-border bg-background px-2 text-sm">
            <option value="">{{ t.card_deliveries?.all_statuses || 'Every status' }}</option>
            <option value="scheduled">{{ t.card_deliveries?.status_scheduled || 'Sent with courier' }}</option>
            <option value="delivered">{{ t.card_deliveries?.status_delivered || 'Delivered' }}</option>
            <option value="failed">{{ t.card_deliveries?.status_failed || 'Not delivered' }}</option>
          </select>
          <div class="flex flex-wrap gap-2">
            <button type="submit" class="h-9 min-w-[7rem] rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground">{{ t.card_deliveries?.filter || 'Filter' }}</button>
            <button type="button" class="h-9 min-w-[7rem] rounded-md border border-border px-4 text-sm" @click="reset">{{ t.card_deliveries?.reset || 'Reset' }}</button>
          </div>
        </form>

        <section class="bg-card rounded-xl border border-border shadow-sm overflow-hidden">
          <div v-if="!rows.length" class="flex flex-col items-center gap-2 p-10 text-muted-foreground" data-testid="deliveries-empty">
            <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><path d="M16 8h4l3 3v5h-7z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
            <p class="text-sm">{{ t.card_deliveries?.empty || 'No card deliveries yet.' }}</p>
          </div>
          <div v-else class="divide-y divide-border">
            <DeliveryItem v-for="d in rows" :key="d.id" :d="d" :couriers="couriers" :can-manage="canManage" show-member />
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
import { computed, reactive, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { AppLayout, Breadcrumb } from '@/Pages/Admin/Layout/Layout.js';
import DeliveryForm from './DeliveryForm.vue';
import DeliveryItem from './DeliveryItem.vue';

const props = defineProps({
  rows: { type: Array, default: () => [] },
  meta: { type: Object, default: () => ({ last_page: 1, links: [] }) },
  filters: { type: Object, default: () => ({}) },
  couriers: { type: Array, default: () => [] },
  canManage: { type: Boolean, default: false },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const form = reactive({ search: props.filters.search || '', courier_id: props.filters.courier_id || '', status: props.filters.status || '' });
const apply = () => {
  const params = Object.fromEntries(Object.entries(form).filter(([, v]) => v !== '' && v !== null));
  router.get(route('admin.card-deliveries.index'), params, { preserveState: true, preserveScroll: true, replace: true });
};
const reset = () => { Object.assign(form, { search: '', courier_id: '', status: '' }); apply(); };

const memberQuery = ref('');
const options = ref([]);
const picked = ref(null);
let timer = null;
const searchMembers = () => {
  clearTimeout(timer);
  timer = setTimeout(async () => {
    if (memberQuery.value.trim().length < 2) { options.value = []; return; }
    try {
      const r = await fetch(route('admin.member-notes.members', { q: memberQuery.value }), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      options.value = r.ok ? await r.json() : [];
    } catch (e) { options.value = []; }
  }, 250);
};
const pick = (o) => { picked.value = o; options.value = []; memberQuery.value = ''; };
</script>
