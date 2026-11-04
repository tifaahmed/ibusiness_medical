<template>
  <MemberLayout>
    <div class="max-w-3xl mx-auto mt-3 sm:mt-4 px-3 sm:px-4 pb-32 sm:pb-8 space-y-4" data-testid="whatsapp-page">
      <div class="flex items-center gap-2">
        <Link :href="route('admin.user.membership.quick-add.done', member.slug)" class="text-sm underline" data-testid="wa-back">← {{ t.back || 'Back' }}</Link>
        <h3 class="text-base font-semibold">{{ t.send_whatsapp || 'Send the details on WhatsApp' }}</h3>
      </div>

      <!-- Everything that will go to the customer, in one place -->
      <div class="rounded-xl border border-border bg-card p-3 sm:p-5 space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="space-y-2">
            <div class="text-xs text-muted-foreground">{{ t.card_picture || 'The card' }}</div>
            <div class="rounded-lg border border-border p-2" data-testid="wa-card">
              <CardPreview v-if="template" :membership="cardMembership" :template="template" :partner="null" display-width="100%" @rendered="onRendered" />
              <p v-else class="text-xs text-muted-foreground">{{ t.no_card_template || 'No card design is set up yet.' }}</p>
            </div>
            <button type="button" :disabled="!cardReady" class="h-11 w-full rounded-md border border-border px-3 text-sm disabled:opacity-50" data-testid="wa-download" @click="downloadCard">{{ t.download_card || 'Save the card picture (to attach in WhatsApp)' }}</button>
          </div>

          <dl class="space-y-2 text-sm" data-testid="wa-details">
            <div><dt class="text-xs text-muted-foreground">{{ t.customer_name || 'Customer name' }}</dt><dd class="font-medium">{{ member.name }}</dd></div>
            <div><dt class="text-xs text-muted-foreground">{{ t.customer_phone || 'Phone' }}</dt><dd class="tabular-nums" dir="ltr">{{ member.phone }}</dd></div>
            <div><dt class="text-xs text-muted-foreground">{{ t.membership_number || 'Membership number' }}</dt><dd class="font-medium tabular-nums" dir="ltr">{{ member.membership_number }}</dd></div>
            <div><dt class="text-xs text-muted-foreground">{{ t.trial_until || 'Trial until' }}</dt><dd class="tabular-nums" dir="ltr">{{ member.expiration_date || '—' }}</dd></div>
            <div>
              <dt class="text-xs text-muted-foreground">{{ t.card_page || 'Card page' }}</dt>
              <dd class="break-all" dir="ltr"><a :href="member.card_link" target="_blank" rel="noopener" class="underline">{{ member.card_link }}</a></dd>
            </div>
            <div data-testid="wa-links">
              <dt class="text-xs text-muted-foreground">{{ t.app_links || 'App links' }}</dt>
              <dd class="space-y-1" dir="ltr">
                <div v-if="member.links.android" class="break-all"><span class="chip chip-neutral">Android</span> <a :href="member.links.android" target="_blank" rel="noopener" class="underline">{{ member.links.android }}</a></div>
                <div v-if="member.links.iphone" class="break-all"><span class="chip chip-neutral">iPhone</span> <a :href="member.links.iphone" target="_blank" rel="noopener" class="underline">{{ member.links.iphone }}</a></div>
                <span v-if="!member.links.android && !member.links.iphone" class="text-xs text-muted-foreground">{{ t.no_link || 'No store link set' }}</span>
              </dd>
            </div>
          </dl>
        </div>

        <!-- The message: a text editor with WhatsApp formatting. Editable only with the permission. -->
        <div class="space-y-2" data-testid="wa-editor">
          <div class="flex flex-wrap items-center gap-2">
            <span class="text-sm font-semibold">{{ t.whatsapp_message || 'WhatsApp message' }}</span>
            <template v-if="can_edit_message">
              <button v-for="b in tools" :key="b.k" type="button" class="h-9 min-w-[2.5rem] rounded-md border border-border px-2 text-sm" :title="b.title" :data-testid="'wa-tool-' + b.k" @click="wrap(b)"><span :class="b.cls">{{ b.l }}</span></button>
              <button v-if="edited" type="button" class="text-xs underline" data-testid="wa-reset" @click="reset">{{ t.reset_message || 'Back to the standard message' }}</button>
              <span v-if="edited" class="chip chip-warning">{{ t.edited_message || 'Edited for this customer only' }}</span>
            </template>
            <span v-else class="chip chip-neutral">{{ t.read_only || 'Read only' }}</span>
            <a v-if="can_edit_templates" :href="route('admin.setting.list', { search: 'wa_msg' })" class="ms-auto text-xs underline" data-testid="wa-templates-link">{{ t.edit_templates || 'Edit the standard message texts (site settings)' }}</a>
          </div>
          <textarea ref="box" v-model="text" rows="12" dir="rtl" :readonly="!can_edit_message" class="w-full rounded-md border border-border bg-background p-3 text-base leading-relaxed" data-testid="wa-text" @input="edited = true"></textarea>
          <div class="text-xs text-muted-foreground tabular-nums" dir="ltr">{{ text.length }}</div>
        </div>
      </div>

      <!-- Which number gets it -->
      <div class="rounded-xl border border-border bg-card p-3 sm:p-5 space-y-3" data-testid="wa-numbers">
        <div class="text-sm font-semibold">{{ t.send_to || 'Send to' }}</div>
        <label class="flex items-center gap-3 min-h-[44px] text-sm"><input v-model="pick" type="radio" value="own" class="h-5 w-5" /> <span>{{ t.customer_phone || 'Phone' }}: <span class="tabular-nums" dir="ltr">{{ member.phone }}</span></span></label>
        <label v-for="p in extra_phones" :key="p.id" class="flex items-center gap-3 min-h-[44px] text-sm"><input v-model="pick" type="radio" :value="'x' + p.id" class="h-5 w-5" /> <span><span class="tabular-nums" dir="ltr">{{ p.phone }}</span> <span v-if="p.note" class="text-xs text-muted-foreground">· {{ p.note }}</span></span></label>
        <label class="flex items-center gap-3 min-h-[44px] text-sm"><input v-model="pick" type="radio" value="new" class="h-5 w-5" data-testid="wa-pick-new" /> <span>{{ t.another_number || 'Another number that has WhatsApp' }}</span></label>
        <div v-if="pick === 'new'" class="space-y-2 ps-8">
          <input v-model="newPhone" inputmode="tel" dir="ltr" :placeholder="t.phone || 'Phone'" class="h-12 w-full rounded-md border border-border bg-background px-3 text-base" data-testid="wa-new-phone" />
          <label class="flex items-center gap-2 text-sm"><input v-model="saveNew" type="checkbox" class="h-5 w-5" /> {{ t.save_on_member || 'Also keep this number on the member (as a note)' }}</label>
        </div>
        <p v-if="err" class="panel-danger p-2 text-sm">{{ err }}</p>
      </div>

      <ExtraPhones :slug="member.slug" :phones="extra_phones" />

      <div class="fixed inset-x-0 bottom-0 z-20 border-t border-border bg-card p-3 sm:static sm:z-auto sm:border-0 sm:bg-transparent sm:p-0">
        <button type="button" :disabled="!target" class="h-12 w-full rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground disabled:opacity-50" data-testid="wa-open" @click="open">{{ t.open_whatsapp || 'Open WhatsApp' }}</button>
      </div>
    </div>
  </MemberLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import MemberLayout from '../MemberLayout.vue';
import ExtraPhones from './ExtraPhones.vue';
import CardPreview from '@/Pages/Admin/MembershipCard/_components/CardPreview.vue';

const props = defineProps({
  member: { type: Object, required: true },
  message: { type: String, default: '' },
  extra_phones: { type: Array, default: () => [] },
  wa_own: { type: String, default: '' },
  card_templates: { type: Object, default: () => ({}) },
  can_edit_message: { type: Boolean, default: false },
  can_edit_templates: { type: Boolean, default: false },
});

const page = usePage();
const t = computed(() => ({ ...(page.props.translations?.admin?.member_list || {}), ...(page.props.translations?.admin?.quick_add || {}) }));

// The card picture: the same renderer the member page uses, with the plain (no partner) design.
const template = computed(() => {
  const f = props.card_templates?.no_partner || props.card_templates?.with_partner || null;
  return f ? { ...f, layout: f.layout, card_empty_url: f.card_empty_url, hidden_fields: f.hidden_fields } : null;
});
// One stable object: a literal written in the template is a NEW object on every render, which made the card repaint on every render.
const cardMembership = computed(() => ({ membership_number: props.member.membership_number, slug: props.member.slug }));
// The canvas itself is NOT reactive (a pixel buffer must not be tracked, and storing it reactively re-rendered this page after
// every repaint). Only a plain "ready" flag is, and it is set once.
let canvasEl = null;
const cardReady = ref(false);
const onRendered = (e) => { canvasEl = e.canvas; if (!cardReady.value) cardReady.value = true; };
const downloadCard = () => {
  if (!canvasEl) return;
  const a = document.createElement('a');
  a.download = `card-${props.member.membership_number}.png`;
  a.href = canvasEl.toDataURL('image/png');
  a.click();
};

// WhatsApp formatting, not HTML: the app does not render markup.
const tools = [
  { k: 'bold', l: 'B', cls: 'font-bold', mark: '*', title: '*bold*' },
  { k: 'italic', l: 'I', cls: 'italic', mark: '_', title: '_italic_' },
  { k: 'strike', l: 'S', cls: 'line-through', mark: '~', title: '~strike~' },
  { k: 'mono', l: '</>', cls: 'font-mono', mark: '```', title: '```mono```' },
];
const box = ref(null);
const text = ref(props.message);
const edited = ref(false);
const wrap = (b) => {
  const el = box.value; if (!el) return;
  const { selectionStart: s, selectionEnd: e } = el;
  const sel = text.value.slice(s, e) || '';
  text.value = text.value.slice(0, s) + b.mark + sel + b.mark + text.value.slice(e);
  edited.value = true;
  requestAnimationFrame(() => { el.focus(); el.setSelectionRange(s + b.mark.length, s + b.mark.length + sel.length); });
};
const reset = () => { text.value = props.message; edited.value = false; };

const pick = ref('own');
const newPhone = ref('');
const saveNew = ref(true);
const err = ref('');

const digits = (raw) => {
  let d = String(raw || '').replace(/[٠-٩]/g, (c) => String(c.charCodeAt(0) - 0x0660)).replace(/\D+/g, '').replace(/^00+/, '');
  if (d.startsWith('0')) d = '20' + d.slice(1);
  return d;
};
const target = computed(() => {
  if (pick.value === 'own') return props.wa_own || digits(props.member.phone);
  if (pick.value === 'new') { const d = digits(newPhone.value); return d.length >= 10 && d.length <= 15 ? d : ''; }
  const x = props.extra_phones.find((p) => 'x' + p.id === pick.value);
  return x ? x.wa : '';
});

const open = () => {
  err.value = '';
  if (!target.value) { err.value = t.value.bad_number || 'Enter a valid number.'; return; }
  if (pick.value === 'new' && saveNew.value) {
    router.post(route('admin.user.membership.extra-phones.store', props.member.slug), { phone: newPhone.value, note: t.value.whatsapp_note || 'WhatsApp' }, { preserveScroll: true, preserveState: true });
  }
  window.open(`https://wa.me/${target.value}?text=${encodeURIComponent(text.value)}`, '_blank', 'noopener');
};
</script>
