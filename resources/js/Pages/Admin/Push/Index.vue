<template>
  <AppLayout>
    <div class="space-y-3 p-3 sm:p-4 lg:p-6 w-full max-w-full">
      <Breadcrumb :title="t.push?.title || 'Push notifications'" :breadcrumbs="[{ label: t.push?.title || 'Push notifications', link: '#', active: true }]" />

      <div class="max-w-5xl mx-auto space-y-4">
        <p class="text-sm text-muted-foreground">{{ t.push?.subtitle }}</p>

        <section class="bg-card rounded-xl border border-border shadow-sm p-4 space-y-3" data-testid="push-status">
          <div class="flex flex-wrap items-center gap-2">
            <span class="chip" :class="configured ? 'chip-positive' : 'chip-negative'">
              <svg class="w-3.5 h-3.5 inline-block me-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0a3 3 0 11-6 0" /></svg>
              {{ configured ? (t.push?.configured || 'Firebase is connected') : (t.push?.not_configured) }}
            </span>
            <span class="chip chip-info">{{ t.push?.devices || 'Registered phones' }}: {{ devices.total }}</span>
            <span class="chip chip-neutral">{{ t.push?.android || 'Android' }}: {{ devices.android }}</span>
            <span class="chip chip-neutral">{{ t.push?.ios || 'iPhone' }}: {{ devices.ios }}</span>
            <span class="chip chip-neutral">{{ t.push?.members || 'Signed-in members' }}: {{ devices.members }}</span>
          </div>
          <div v-if="!configured" class="panel-note text-xs space-y-1">
            <p class="font-semibold">{{ t.push?.setup_title }}</p>
            <p v-for="n in 5" :key="n">{{ n }}. {{ t.push?.['setup_' + n] }}</p>
          </div>
        </section>

        <form class="bg-card rounded-xl border border-border shadow-sm p-4 space-y-3" data-testid="push-form" @submit.prevent="send">
          <h2 class="text-sm font-semibold">{{ t.push?.form_title || 'New notification' }}</h2>

          <label class="block space-y-1">
            <span class="text-xs font-medium">{{ t.push?.field_title || 'Title' }}</span>
            <input v-model="form.title" type="text" maxlength="150" required class="w-full h-10 rounded-md border border-border bg-background px-3 text-sm" dir="auto" />
            <span v-if="errors.title" class="text-xs value-negative">{{ errors.title }}</span>
          </label>

          <label class="block space-y-1">
            <span class="text-xs font-medium">{{ t.push?.field_body || 'Message' }}</span>
            <textarea v-model="form.body" rows="4" maxlength="1000" required class="w-full rounded-md border border-border bg-background px-3 py-2 text-sm" dir="auto"></textarea>
            <span v-if="errors.body" class="text-xs value-negative">{{ errors.body }}</span>
          </label>

          <label class="block space-y-1">
            <span class="text-xs font-medium">{{ t.push?.field_url || 'Opens when tapped (optional)' }}</span>
            <select v-model="pagePick" class="w-full h-10 rounded-md border border-border bg-background px-3 text-sm" @change="pickPage">
              <option value="">{{ t.push?.pick_page || 'Pick an app page… (or type a link below)' }}</option>
              <optgroup v-for="g in APP_PAGES" :key="g.group" :label="g.group">
                <option v-for="p in g.pages" :key="p.path" :value="p.path">{{ p.label }}</option>
              </optgroup>
            </select>
            <input v-model="form.url" type="text" maxlength="500" class="w-full h-10 rounded-md border border-border bg-background px-3 text-sm" dir="ltr" placeholder="https://… or /products" />
            <span class="text-xs text-muted-foreground">{{ t.push?.field_url_hint }}</span>
            <span v-if="errors.url" class="block text-xs value-negative">{{ errors.url }}</span>
          </label>

          <fieldset class="space-y-2">
            <legend class="text-xs font-medium">{{ t.push?.audience || 'Send to' }}</legend>
            <div class="flex flex-wrap gap-2">
              <button v-for="a in ['all', 'member']" :key="a" type="button" class="h-9 min-w-[10rem] rounded-md border px-3 text-sm" :class="form.audience === a ? 'chip-positive border-transparent' : 'border-border'" @click="form.audience = a">
                {{ a === 'all' ? (t.push?.audience_all || 'Everyone who has the app') : (t.push?.audience_member || 'Specific members') }}
              </button>
            </div>
            <div v-if="form.audience === 'member'" class="space-y-1">
              <textarea v-model="form.audience_phone" rows="4" class="w-full sm:w-96 rounded-md border border-border bg-background px-3 py-2 text-sm" dir="ltr" :placeholder="t.push?.member_phone || 'Member phones — one per line or separated by commas (up to 500)'" />
              <span v-if="errors.audience_phone" class="block text-xs value-negative">{{ errors.audience_phone }}</span>
            </div>
          </fieldset>

          <div class="flex flex-wrap items-center gap-3">
            <button type="submit" :disabled="busy || !configured" class="h-10 min-w-[10rem] rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground disabled:opacity-50" data-testid="push-send">
              {{ busy ? (t.push?.sending || 'Sending…') : (t.push?.send || 'Send notification') }}
            </button>
            <span v-if="!configured" class="text-xs value-warning">{{ t.push?.not_configured }}</span>
          </div>
        </form>

        <section class="bg-card rounded-xl border border-border shadow-sm overflow-hidden">
          <h2 class="px-4 pt-4 text-sm font-semibold">{{ t.push?.history || 'Sent notifications' }}</h2>
          <div v-if="!rows.length" class="p-10 text-center text-sm text-muted-foreground" data-testid="push-empty">{{ t.push?.empty || 'Nothing sent yet.' }}</div>
          <div v-else class="divide-y divide-border">
            <div v-for="r in rows" :key="r.id" class="px-4 py-3 text-sm space-y-1" data-testid="push-row">
              <div class="flex flex-wrap items-center gap-2">
                <span class="chip" :class="{ 'chip-warning': r.status === 'sending', 'chip-positive': r.status === 'sent', 'chip-negative': r.status === 'failed' }">{{ t.push?.['status_' + r.status] || r.status }}</span>
                <span class="chip chip-neutral">{{ r.audience === 'all' ? (t.push?.to_all || 'Everyone') : `${t.push?.to_member || 'To'} ${(r.audience_phone || '').split(',').length} ${t.push?.members_word || 'member(s)'}` }}</span>
                <span class="chip chip-positive tabular-nums">{{ r.delivered }} {{ t.push?.delivered || 'delivered' }}</span>
                <span v-if="r.failed" class="chip chip-negative tabular-nums">{{ r.failed }} {{ t.push?.failed || 'failed' }}</span>
                <span v-if="r.removed" class="chip chip-neutral tabular-nums">{{ r.removed }} {{ t.push?.removed || 'removed' }}</span>
                <span v-if="r.by" class="chip chip-neutral">{{ r.by }}</span>
                <span class="text-xs text-foreground/80" dir="ltr">{{ r.created_at }}</span>
              </div>
              <p class="font-medium break-words" dir="auto">{{ r.title }}</p>
              <p class="whitespace-pre-line break-words text-foreground/80" dir="auto">{{ r.body }}</p>
              <p v-if="r.url" class="text-xs text-muted-foreground" dir="ltr">{{ r.url }}</p>
              <p v-if="r.error" class="text-xs value-negative">{{ r.error }}</p>
            </div>
          </div>
        </section>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { AppLayout, Breadcrumb } from '@/Pages/Admin/Layout/Layout.js';

defineProps({
  configured: { type: Boolean, default: false },
  devices: { type: Object, default: () => ({ total: 0, android: 0, ios: 0, members: 0 }) },
  rows: { type: Array, default: () => [] },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});
const errors = computed(() => page.props.errors || {});
const busy = ref(false);
const form = reactive({ title: '', body: '', url: '', audience: 'all', audience_phone: '' });

/* The app's fixed pages (src/router.tsx in deilar-mobile-new). A static list on purpose: pages that depend on a record
   (one product, one facility) are copied from their own list with the "copy app link" button instead. */
const APP_PAGES = [
  { group: 'Main tabs · التابات', pages: [
    { path: '/', label: 'Home · الرئيسية' },
    { path: '/store', label: 'Stores · المتاجر' },
    { path: '/store/map', label: 'Stores map · خريطة المتاجر' },
    { path: '/products', label: 'Products · منتجاتنا' },
    { path: '/map', label: 'Map · الخريطة' },
    { path: '/map?view=list', label: 'Facilities list · قائمة المنشآت' },
    { path: '/map?view=list&sort=discount', label: 'Biggest discounts first · الأعلى خصم' },
  ] },
  { group: 'My card · كارتي', pages: [
    { path: '/card', label: 'My card · كارتي' },
    { path: '/card/members', label: 'Relatives · الأقارب' },
    { path: '/card/pay', label: 'Pay / renew the card · الاشتراك والدفع' },
    { path: '/card/physical', label: 'Order a physical card · كارت فعلي' },
  ] },
  { group: 'Account · حسابي', pages: [
    { path: '/account', label: 'Account · حسابي' },
    { path: '/account/personal', label: 'Personal data · البيانات الشخصية' },
    { path: '/account/addresses', label: 'Addresses · العناوين' },
    { path: '/account/wallet', label: 'Wallet · محفظتي' },
    { path: '/account/invite', label: 'Invite friends · ادعُ أصحابك' },
    { path: '/account/support', label: 'Support · الدعم' },
    { path: '/account/theme', label: 'Theme · المظهر' },
  ] },
  { group: 'Shopping · الشراء', pages: [
    { path: '/cart', label: 'Cart · السلة' },
    { path: '/checkout', label: 'Checkout · إتمام الطلب' },
    { path: '/orders', label: 'My orders · طلباتي' },
  ] },
  { group: 'Quiz · الكويز', pages: [
    { path: '/quiz', label: 'Quiz home · الكويز' },
    { path: '/quiz/mcq/football', label: 'Football · كرة القدم' },
    { path: '/quiz/mcq/fawazeer', label: 'Puzzles · فوازير' },
    { path: '/quiz/mcq/general', label: 'General knowledge · ثقافة عامة' },
    { path: '/quiz/mcq/history', label: 'History · تاريخ' },
    { path: '/quiz/mcq/cinema', label: 'Cinema & series · سينما ومسلسلات' },
    { path: '/quiz/badges', label: 'Badges · الأوسمة' },
    { path: '/quiz/history', label: 'Points log · سجل النقاط' },
    { path: '/quiz/moods', label: 'Points screens · شاشة النقاط' },
  ] },
];
const pagePick = ref('');
const pickPage = () => { if (pagePick.value) form.url = pagePick.value; };

const send = () => {
  const ask = form.audience === 'all'
    ? (t.value.push?.confirm_all || 'Send to EVERY phone that has the app?')
    : (t.value.push?.confirm_member || 'Send this to those members only?');
  if (!window.confirm(ask)) return;
  busy.value = true;
  router.post(route('admin.push.store'), { ...form }, {
    preserveScroll: true,
    onSuccess: () => { form.title = ''; form.body = ''; form.url = ''; },
    onFinish: () => { busy.value = false; },
  });
};
</script>
