<template>
  <div class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm" data-testid="member-wallet">
    <div class="py-2 px-6 flex flex-wrap items-center gap-3">
      <div class="title-golden leading-none font-semibold">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
          <path d="M20 12V8H6a2 2 0 0 1 0-4h12v4"></path><path d="M4 6v12a2 2 0 0 0 2 2h14v-4"></path><path d="M18 12a2 2 0 0 0 0 4h4v-4z"></path>
        </svg>
        {{ t.loyalty?.wallet_tab_title || 'Wallet' }}
      </div>
      <span class="ms-auto chip chip-info text-sm px-3 py-1" data-testid="wallet-balance">
        {{ t.loyalty?.balance || 'Balance' }}: <span dir="ltr" class="tabular-nums">{{ balance ?? '…' }}</span> EGP
      </span>
    </div>

    <!-- Add or take points: every change is saved as ONE new line below, never edited later. -->
    <p v-if="!canAny" class="px-6 text-sm value-warning" data-testid="no-ledger-permission">{{ t.loyalty?.no_permission_text || 'You do not have permission to add or take here. Ask the super admin.' }}</p>
    <form v-else class="px-6 space-y-3" @submit.prevent="submit">
      <div class="flex flex-wrap items-end gap-3">
        <div class="flex rounded-md border border-border overflow-hidden" role="group">
          <button v-if="allowance.add.allowed" type="button" class="h-9 px-4 text-sm font-semibold min-w-[6rem]" :class="form.direction === 'add' ? 'chip-positive' : 'bg-background'" data-testid="wallet-add" @click="form.direction = 'add'">+ {{ t.loyalty?.add || 'Add' }}</button>
          <button v-if="allowance.take.allowed" type="button" class="h-9 px-4 text-sm font-semibold min-w-[6rem]" :class="form.direction === 'take' ? 'chip-negative' : 'bg-background'" data-testid="wallet-take" @click="form.direction = 'take'">− {{ t.loyalty?.take || 'Take' }}</button>
        </div>
        <input v-model.number="form.amount" type="number" min="0.01" max="100000" step="0.01" required dir="ltr" data-testid="wallet-amount"
               :placeholder="t.loyalty?.amount_egp || 'Amount (EGP)'" class="h-9 w-32 rounded-md border border-border bg-background px-2 text-sm" />
        <input v-model="form.note" type="text" maxlength="255" required data-testid="wallet-note"
               :placeholder="t.loyalty?.reason || 'Why? (saved with the line)'" class="h-9 min-w-[14rem] flex-1 rounded-md border border-border bg-background px-2 text-sm" />
        <button type="submit" :disabled="saving" data-testid="wallet-save" class="h-9 min-w-[7rem] rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground disabled:opacity-50">
          {{ saving ? '…' : (t.loyalty?.save_line || 'Save') }}
        </button>
      </div>
      <p class="text-xs text-foreground/80" data-testid="ledger-allowance">
        {{ t.loyalty?.monthly_left || 'Your monthly allowance (left / limit)' }}:
        <span class="chip chip-positive">+ {{ allowanceText(allowance.add) }}</span>
        <span class="chip chip-negative">− {{ allowanceText(allowance.take) }}</span>
      </p>
      <p v-if="error" class="text-xs value-negative" data-testid="wallet-error">{{ error }}</p>
    </form>

    <!-- The ledger: one line per change — what, why, where it came from, who, when, and the balance it left. -->
    <div class="px-6">
      <div class="rounded-lg border border-border divide-y divide-border max-h-96 overflow-y-auto" data-testid="wallet-ledger">
        <p v-if="loading" class="px-3 py-2 text-sm text-muted-foreground">…</p>
        <p v-else-if="!rows.length" class="px-3 py-2 text-sm text-muted-foreground">{{ t.loyalty?.no_history || 'Nothing yet.' }}</p>
        <div v-for="row in rows" :key="row.id" class="flex flex-wrap items-center gap-x-3 gap-y-0.5 px-3 py-2 text-sm" data-testid="wallet-row">
          <span class="chip justify-center text-sm tabular-nums min-w-[4rem]" :class="row.value < 0 ? 'chip-negative' : 'chip-positive'" dir="ltr">{{ row.value > 0 ? '+' : '' }}{{ row.value }}</span>
          <span class="chip chip-neutral">{{ sourceLabel(row.source) }}</span>
          <span v-if="row.note" class="text-xs">{{ row.note }}</span>
          <span class="text-xs text-muted-foreground" dir="ltr">→ {{ row.after }}</span>
          <span class="chip chip-neutral" data-testid="ledger-by">{{ t.loyalty?.by || 'By' }}: {{ row.by || (t.loyalty?.by_member || 'the member / system') }}</span>
          <span class="ms-auto text-xs text-muted-foreground" dir="ltr">{{ row.at }}</span>
        </div>
      </div>
      <p v-if="truncated" class="mt-1 text-xs text-muted-foreground">{{ t.loyalty?.latest_200 || 'Showing the latest 200 lines.' }}</p>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import axios from 'axios';
import { router, usePage } from '@inertiajs/vue3';
import { useNotification } from '@/composables/useNotification';

const props = defineProps({ userId: { type: Number, required: true } });

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const balance = ref(null);
const loading = ref(true);
const saving = ref(false);
const error = ref('');
const ledger = ref([]);
const form = reactive({ direction: 'add', amount: null, note: '' });
// What THIS admin may do by hand (permission + monthly cap), as the backend last said it.
const allowance = ref({ add: { allowed: true }, take: { allowed: true } });
const canAny = computed(() => allowance.value.add.allowed || allowance.value.take.allowed);
const allowanceText = (a) => (!a.allowed ? (t.value.loyalty?.no_permission || 'not allowed') : a.limit === null ? (t.value.loyalty?.unlimited || 'unlimited') : `${a.remaining} / ${a.limit}`);

const SOURCES = { admin: 'Admin (by hand)', points: 'Converted from points', topup: 'Top-up', order: 'Paid an order', refund: 'Order refund', transfer: 'Transfer', membership: 'Membership' };
const sourceLabel = (s) => t.value.loyalty?.['wallet_source_' + s] || SOURCES[s] || s;

// Newest first; "after" is the balance each line left behind (balance now, minus the lines above it).
const rows = computed(() => {
  let running = balance.value ?? 0;
  return ledger.value.map((r) => {
    const after = running;
    running = Math.round((running - r.value) * 100) / 100;
    return { ...r, after: Math.round(after * 100) / 100 };
  });
});
const truncated = computed(() => ledger.value.length >= 200);

async function load() {
  loading.value = true;
  try {
    const { data } = await axios.get(route('admin.loyalty.members.ledger', props.userId));
    balance.value = data.wallet_balance;
    ledger.value = (data.data || []).filter((r) => r.ledger === 'wallet');
    allowance.value = data.allowance?.wallet ?? allowance.value;
    if (!allowance.value[form.direction].allowed) form.direction = form.direction === 'add' ? 'take' : 'add';
  } catch (e) {
    error.value = t.value.loyalty?.load_failed || 'Could not load the wallet history.';
  } finally {
    loading.value = false;
  }
}

function submit() {
  error.value = '';
  const amount = Math.round(Math.abs(Number(form.amount)) * 100) / 100;
  if (!amount) { error.value = t.value.loyalty?.amount_required || 'Enter an amount.'; return; }
  const signed = form.direction === 'take' ? -amount : amount;
  saving.value = true;
  router.post(route('admin.loyalty.wallet.adjust'), { user_id: props.userId, amount: signed, note: form.note }, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: async () => {
      if (page.props.flash?.error) { error.value = page.props.flash.error; return; }
      useNotification().success(signed > 0 ? 'Money added to the wallet' : 'Money taken from the wallet');
      form.amount = null;
      form.note = '';
      await load();
    },
    onError: (errors) => {
      error.value = errors.amount || errors.note || errors.user_id || 'The wallet could not be changed.';
    },
    onFinish: () => { saving.value = false; },
  });
}

onMounted(load);
</script>
