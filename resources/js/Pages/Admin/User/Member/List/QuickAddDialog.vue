<template>
  <Modal :show="show" max-width="lg" @close="close">
    <form class="p-4 sm:p-6 space-y-3" data-testid="quick-add-form" @submit.prevent="submit">
      <h3 class="flex items-center gap-2 text-base font-semibold">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg>
        {{ t.quick_add_title || 'Quick add' }}
      </h3>
      <p class="text-xs text-muted-foreground">{{ t.quick_add_help || 'Registered on the phone by sales. The customer gets a short message and signs in to the app with the phone number.' }}</p>

      <div class="chip chip-neutral" data-testid="quick-add-by">{{ t.quick_add_by || 'Added by' }}: {{ me }}</div>

      <input v-model="form.name" required maxlength="255" :placeholder="t.customer_name || 'Customer name'" class="h-10 w-full rounded-md border border-border bg-background px-3 text-sm" data-testid="qa-name" />
      <input v-model="form.phone" required inputmode="numeric" maxlength="11" :placeholder="t.customer_phone || 'Phone (01xxxxxxxxx)'" class="h-10 w-full rounded-md border border-border bg-background px-3 text-sm" dir="ltr" data-testid="qa-phone" />

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <fieldset class="space-y-1">
          <legend class="text-xs text-muted-foreground">{{ t.phone_type || 'Phone type' }}</legend>
          <div class="flex flex-wrap gap-2">
            <button v-for="d in devices" :key="d.v" type="button" class="h-9 min-w-[7rem] rounded-md border px-3 text-sm" :class="form.device === d.v ? 'chip-positive border-transparent' : 'border-border'" :data-testid="'qa-device-' + d.v" @click="form.device = d.v">{{ d.l }}</button>
          </div>
        </fieldset>
        <fieldset class="space-y-1">
          <legend class="text-xs text-muted-foreground">{{ t.customer_kind || 'Individual or company' }}</legend>
          <div class="flex flex-wrap gap-2">
            <button v-for="k in kinds" :key="k.v" type="button" class="h-9 min-w-[7rem] rounded-md border px-3 text-sm" :class="form.member_kind === k.v ? 'chip-positive border-transparent' : 'border-border'" :data-testid="'qa-kind-' + k.v" @click="form.member_kind = k.v">{{ k.l }}</button>
          </div>
        </fieldset>
      </div>

      <label class="flex items-center gap-2 text-sm"><input v-model="form.wants_card" type="checkbox" data-testid="qa-card" /> {{ t.wants_card_label || 'Wants to receive a card' }}</label>

      <!-- The message exactly as the customer will receive it, from the same builder that sends it. -->
      <section v-if="preview" class="rounded-md border border-border p-3 space-y-2" data-testid="qa-preview">
        <div class="flex flex-wrap items-center gap-2 text-xs">
          <span class="font-semibold">{{ t.message_preview || 'Message the customer will receive' }}</span>
          <span class="chip chip-neutral tabular-nums" dir="ltr">{{ preview.characters }} / {{ preview.segments }}</span>
          <span class="chip chip-accent">{{ t.trial_note || 'Trial' }}: {{ preview.days }} {{ t.days || 'days' }}</span>
          <span class="chip" :class="preview.sending ? 'chip-positive' : 'chip-warning'">{{ preview.sending ? (t.sending_on || 'Will be sent now') : (t.sending_off || 'Sending is off — will only be queued') }}</span>
          <span v-if="!preview.link" class="chip chip-warning">{{ t.no_link || 'No store link set for this phone' }}</span>
        </div>
        <p class="whitespace-pre-line break-words text-sm" dir="rtl" data-testid="qa-preview-text">{{ preview.body }}</p>
      </section>
      <p v-else class="text-xs text-muted-foreground">{{ t.preview_hint || 'Choose the phone type and Individual / Company to see the message.' }}</p>
      <p v-if="error" class="text-xs text-red-400" data-testid="qa-error">{{ error }}</p>

      <div class="flex flex-wrap gap-2 justify-end">
        <button type="button" class="h-9 min-w-[7rem] rounded-md border border-border px-4 text-sm" @click="close">{{ t.cancel || 'Cancel' }}</button>
        <button type="submit" :disabled="busy || !ready" class="h-9 min-w-[8rem] rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground disabled:opacity-50" data-testid="qa-submit">{{ t.quick_add_submit || 'Add now' }}</button>
      </div>
    </form>
  </Modal>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';

const props = defineProps({ show: { type: Boolean, default: false } });
const emit = defineEmits(['close']);

const page = usePage();
const t = computed(() => ({ ...(page.props.translations?.admin?.member_list || {}), ...(page.props.translations?.admin?.quick_add || {}) }));
const me = computed(() => page.props.auth?.user?.name || '—');

const devices = computed(() => [{ v: 'android', l: t.value.android || 'Android' }, { v: 'iphone', l: t.value.iphone || 'iPhone' }]);
const kinds = computed(() => [{ v: 'individual', l: t.value.individual || 'Individual' }, { v: 'company', l: t.value.company || 'Company' }]);

const form = reactive({ name: '', phone: '', device: '', member_kind: '', wants_card: false });
const error = ref('');
const busy = ref(false);
const ready = computed(() => form.name.trim() && /^01\d{9}$/.test(form.phone) && form.device && form.member_kind);

const preview = ref(null);
let seq = 0;
watch(() => [props.show, form.device, form.member_kind, form.wants_card], async () => {
  if (!props.show || !form.device || !form.member_kind) { preview.value = null; return; }
  const mine = ++seq;
  try {
    const url = route('admin.user.membership.quick-add.preview', { device: form.device, member_kind: form.member_kind, wants_card: form.wants_card ? 1 : 0 });
    const r = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    if (mine === seq) preview.value = r.ok ? await r.json() : null;
  } catch (e) { if (mine === seq) preview.value = null; }
});

const close = () => emit('close');
const submit = () => {
  error.value = '';
  busy.value = true;
  router.post(route('admin.user.membership.quick-add'), { ...form }, {
    preserveScroll: true,
    onSuccess: () => { Object.assign(form, { name: '', phone: '', device: '', member_kind: '', wants_card: false }); emit('close'); },
    onError: (e) => { error.value = Object.values(e)[0] || 'Could not add.'; },
    onFinish: () => { busy.value = false; },
  });
};
</script>
