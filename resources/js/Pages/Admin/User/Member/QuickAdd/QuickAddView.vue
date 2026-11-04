<template>
  <MemberLayout>
    <div class="max-w-2xl mx-auto mt-3 sm:mt-4 px-3 sm:px-4 pb-32 sm:pb-8">
    <form class="rounded-xl border border-border bg-card p-3 sm:p-6 space-y-4" data-testid="quick-add-form" @submit.prevent="submit">
      <div class="space-y-1">
        <h3 class="flex items-center gap-2 text-base font-semibold">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg>
          {{ t.quick_add_title || 'Quick add' }}
        </h3>
        <p class="text-xs text-muted-foreground">{{ t.quick_add_help || 'Registered on the phone by sales. The customer gets a short message and signs in to the app with the phone number.' }}</p>
        <div class="chip chip-neutral" data-testid="quick-add-by">{{ t.quick_add_by || 'Added by' }}: {{ me }}</div>
      </div>

      <div data-testid="qa-sales">
        <div v-if="lockedSales" class="chip chip-accent" data-testid="qa-sales-locked">{{ t.sales || 'Sales' }}: {{ lockedSales.label }} <span class="opacity-70">({{ t.locked || 'fixed' }})</span></div>
        <SearchableSelect v-else-if="salesOptions.length" v-model="form.sales_id" :options="salesChoices" :placeholder="t.sales_none || 'Sales (optional) — type to search'" data-testid="qa-sales-select" />
      </div>

      <div class="space-y-3">
        <input v-model="form.name" required maxlength="255" autocomplete="off" :placeholder="t.customer_name || 'Customer name'" class="h-12 w-full rounded-md border border-border bg-background px-3 text-base" data-testid="qa-name" />
        <div class="space-y-1">
          <input v-model="form.phone" required inputmode="numeric" maxlength="11" autocomplete="off" :placeholder="t.customer_phone || 'Phone (01xxxxxxxxx)'" class="h-12 w-full rounded-md border bg-background px-3 text-base tabular-nums" :class="phone.state === 'taken' ? 'border-red-500' : 'border-border'" dir="ltr" data-testid="qa-phone" />
          <!-- Asked the moment the 11th digit is typed -->
          <p v-if="phone.state === 'checking'" class="text-xs text-muted-foreground" data-testid="qa-phone-checking">{{ t.checking_phone || 'Checking the number…' }}</p>
          <p v-else-if="phone.state === 'free'" class="chip chip-positive" data-testid="qa-phone-free">{{ t.phone_free || 'This number is not registered — OK to add' }}</p>
          <p v-else-if="phone.state === 'taken'" class="panel-danger p-2 text-sm" data-testid="qa-phone-taken">{{ phone.message }}</p>
          <p v-else-if="form.phone.length > 0" class="text-xs text-muted-foreground tabular-nums" dir="ltr">{{ form.phone.length }} / 11</p>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <fieldset class="space-y-1">
          <legend class="text-xs text-muted-foreground">{{ t.phone_type || 'Phone type' }}</legend>
          <div class="grid grid-cols-2 gap-2">
            <button v-for="d in devices" :key="d.v" type="button" class="h-12 rounded-md border px-3 text-sm font-medium" :class="form.device === d.v ? 'chip-positive border-transparent' : 'border-border'" :data-testid="'qa-device-' + d.v" @click="form.device = d.v">{{ d.l }}</button>
          </div>
        </fieldset>
        <fieldset class="space-y-1">
          <legend class="text-xs text-muted-foreground">{{ t.customer_kind || 'Individual or company' }}</legend>
          <div class="grid grid-cols-2 gap-2">
            <button v-for="k in kinds" :key="k.v" type="button" class="h-12 rounded-md border px-3 text-sm font-medium" :class="form.member_kind === k.v ? 'chip-positive border-transparent' : 'border-border'" :data-testid="'qa-kind-' + k.v" @click="form.member_kind = k.v">{{ k.l }}</button>
          </div>
        </fieldset>
      </div>

      <label class="flex items-center gap-3 text-sm min-h-[44px]"><input v-model="form.wants_card" type="checkbox" class="h-5 w-5" data-testid="qa-card" /> {{ t.wants_card_label || 'Wants to receive a card' }}</label>

      <!-- The message exactly as the customer will receive it, from the same builder that sends it. -->
      <section v-if="preview" class="rounded-md border border-border p-3 space-y-2" data-testid="qa-preview">
        <div class="flex flex-wrap items-center gap-2 text-xs">
          <span class="font-semibold">{{ t.message_preview || 'Message the customer will receive' }}</span>
          <span v-if="preview.days" class="chip chip-accent">{{ t.trial_note || 'Trial' }}: {{ preview.days }} {{ t.days || 'days' }}</span>
          <span class="chip" :class="preview.sending ? 'chip-positive' : 'chip-warning'">{{ preview.sending ? (t.sending_on || 'Will be sent now') : (t.sending_off || 'Sending is off — will only be queued') }}</span>
          <span v-if="preview.link === false" class="chip chip-warning">{{ t.no_link || 'No store link set for this phone' }}</span>
        </div>
        <!-- With the permission the text for THIS customer can be rewritten; it starts as the template text and follows the choices until edited. -->
        <div v-if="can_edit_message" class="space-y-1" data-testid="qa-edit-box">
          <textarea v-model="custom" rows="4" dir="rtl" maxlength="500" class="w-full rounded-md border border-border bg-background p-2 text-base" data-testid="qa-edit-text" @input="edited = true"></textarea>
          <button v-if="edited" type="button" class="text-xs underline" data-testid="qa-edit-reset" @click="resetText">{{ t.reset_message || 'Back to the standard message' }}</button>
          <span v-if="edited" class="chip chip-warning ms-2">{{ t.edited_message || 'Edited for this customer only' }}</span>
        </div>
        <p v-else class="whitespace-pre-line break-words text-sm" dir="rtl" data-testid="qa-preview-text">{{ preview.body }}</p>
        <a v-if="can_edit_templates" :href="route('admin.setting.list', { search: 'quick_msg' })" class="inline-block text-xs underline" data-testid="qa-templates-link">{{ t.edit_templates || 'Edit the standard message texts (site settings)' }}</a>

        <!-- Letter counter: Arabic SMS is 70 characters for ONE message (67 per part once it is longer). -->
        <div class="space-y-1" data-testid="qa-counter">
          <div class="flex items-center justify-between gap-2 text-xs">
            <span class="chip tabular-nums" :class="over ? 'chip-warning' : 'chip-positive'" dir="ltr" data-testid="qa-counter-chars">{{ shownLength }} / {{ SINGLE }}</span>
            <span :class="over ? 'value-warning' : 'value-positive'" data-testid="qa-counter-text">
              {{ over ? (t.over_one || 'Over one message') + ' — ' + segments + ' ' + (t.messages || 'messages') + ' (' + (t.billed || 'billed as') + ' ' + segments + ')' : (t.within_one || 'Fits in one message') + ' — ' + (SINGLE - shownLength) + ' ' + (t.left || 'left') }}
            </span>
          </div>
          <div class="h-1.5 w-full overflow-hidden rounded bg-muted"><div class="h-full rounded" :class="over ? 'bg-amber-500' : 'bg-emerald-500'" :style="{ width: Math.min(100, (shownLength / SINGLE) * 100) + '%' }"></div></div>
        </div>
      </section>
      <p v-else class="text-xs text-muted-foreground" data-testid="qa-preview-loading">…</p>
      <p v-if="error" class="panel-danger p-2 text-sm" data-testid="qa-error">{{ error }}</p>

      <!-- On a phone the buttons stay on screen while the form scrolls -->
      <div class="fixed inset-x-0 bottom-0 z-20 flex gap-2 border-t border-border bg-card p-3 sm:static sm:z-auto sm:border-0 sm:bg-transparent sm:p-0 sm:justify-end">
        <button type="button" class="h-12 flex-1 sm:flex-none sm:min-w-[7rem] rounded-md border border-border px-4 text-sm" @click="close">{{ t.cancel || 'Cancel' }}</button>
        <button type="submit" :disabled="busy || !ready" class="h-12 flex-[2] sm:flex-none sm:min-w-[9rem] rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground disabled:opacity-50" data-testid="qa-submit">{{ t.quick_add_submit || 'Add now' }}</button>
      </div>
    </form>
    </div>
  </MemberLayout>
</template>


<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import MemberLayout from '../MemberLayout.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

const props = defineProps({
  locked_sales: { type: Object, default: null },
  sales_options: { type: Array, default: () => [] },
  can_edit_message: { type: Boolean, default: false },
  can_edit_templates: { type: Boolean, default: false },
});
const lockedSales = computed(() => props.locked_sales);
const salesOptions = computed(() => props.sales_options);
// A first row that clears the choice, so a picked rep can be un-picked.
const salesChoices = computed(() => [{ value: null, label: t.value.sales_none_option || '— No sales —' }, ...props.sales_options]);

const page = usePage();
const t = computed(() => ({ ...(page.props.translations?.admin?.member_list || {}), ...(page.props.translations?.admin?.quick_add || {}) }));
const me = computed(() => page.props.auth?.user?.name || '—');

const devices = computed(() => [{ v: 'android', l: t.value.android || 'Android' }, { v: 'iphone', l: t.value.iphone || 'iPhone' }]);
const kinds = computed(() => [{ v: 'individual', l: t.value.individual || 'Individual' }, { v: 'company', l: t.value.company || 'Company' }]);

// Android + company are what sales usually register, so they start selected.
const form = reactive({ name: '', phone: '', device: 'android', member_kind: 'company', wants_card: false, sales_id: null });
const error = ref('');
const busy = ref(false);
const phone = reactive({ state: 'idle', message: '' }); // idle | checking | free | taken
const ready = computed(() => form.name.trim() && /^01\d{9}$/.test(form.phone) && form.device && form.member_kind && phone.state !== 'taken' && phone.state !== 'checking');

// The moment the 11th digit is in, ask whether the number is already registered (and by whom).
let phoneSeq = 0;
watch(() => form.phone, async (v) => {
  const mine = ++phoneSeq;
  phone.message = '';
  if (!/^01\d{9}$/.test(v)) { phone.state = 'idle'; return; }
  phone.state = 'checking';
  try {
    const r = await fetch(route('admin.user.membership.quick-add.phone-check', { phone: v }), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    if (mine !== phoneSeq) return;
    const d = r.ok ? await r.json() : null;
    // If the check itself fails, do not block: the save checks again on the server.
    phone.state = d?.taken ? 'taken' : 'free';
    phone.message = d?.message || '';
  } catch (e) { if (mine === phoneSeq) phone.state = 'idle'; }
});

const SINGLE = 70; // one Arabic (UCS-2) SMS
// The text in the counter is the one that will be sent: the typed one when edited, else the standard one.
const custom = ref('');
const edited = ref(false);
const shownLength = computed(() => (props.can_edit_message && edited.value ? custom.value.length : (preview.value?.characters || 0)));
const segments = computed(() => (shownLength.value <= SINGLE ? 1 : Math.ceil(shownLength.value / 67)));
const over = computed(() => shownLength.value > SINGLE);
const resetText = () => { edited.value = false; custom.value = preview.value?.body || ''; };
watch(() => preview.value?.body, (b) => { if (!edited.value) custom.value = b || ''; });

const preview = ref(null);
let seq = 0;
watch(() => [form.device, form.member_kind, form.wants_card], async () => {
    const mine = ++seq;
  try {
    const url = route('admin.user.membership.quick-add.preview', { device: form.device || '', member_kind: form.member_kind || '', wants_card: form.wants_card ? 1 : 0 });
    const r = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    if (mine === seq) preview.value = r.ok ? await r.json() : null;
  } catch (e) { if (mine === seq) preview.value = null; }
}, { immediate: true });

const close = () => router.visit(route('admin.user.membership.list'));
const submit = () => {
  error.value = '';
  busy.value = true;
  router.post(route('admin.user.membership.quick-add'), { ...form, sales_id: lockedSales.value ? lockedSales.value.value : form.sales_id, message_override: props.can_edit_message && edited.value ? custom.value : '' }, {
    onError: (e) => { error.value = Object.values(e)[0] || 'Could not add.'; },
    onFinish: () => { busy.value = false; },
  });
};
</script>
