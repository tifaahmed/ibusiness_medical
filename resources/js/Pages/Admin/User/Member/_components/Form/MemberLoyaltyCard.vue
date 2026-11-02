<template>
  <div class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm" data-testid="member-loyalty">
    <div class="py-2 px-6 flex flex-wrap items-center gap-3">
      <div class="title-golden leading-none font-semibold">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
          <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
        </svg>
        {{ t.loyalty?.member_tab_title || 'Loyalty points' }}
      </div>
      <span class="ms-auto chip chip-accent text-sm px-3 py-1" data-testid="loyalty-balance">
        {{ t.loyalty?.balance || 'Balance' }}: <span dir="ltr" class="tabular-nums">{{ balance ?? '…' }}</span>
      </span>
    </div>

    <!-- Add or take points: every change is saved as ONE new line below, never edited later. -->
    <form class="px-6 space-y-3" @submit.prevent="submit">
      <div class="flex flex-wrap items-end gap-3">
        <div class="flex rounded-md border border-border overflow-hidden" role="group">
          <button type="button" class="h-9 px-4 text-sm font-semibold min-w-[6rem]" :class="form.direction === 'add' ? 'chip-positive' : 'bg-background'" data-testid="loyalty-add" @click="form.direction = 'add'">+ {{ t.loyalty?.add || 'Add' }}</button>
          <button type="button" class="h-9 px-4 text-sm font-semibold min-w-[6rem]" :class="form.direction === 'take' ? 'chip-negative' : 'bg-background'" data-testid="loyalty-take" @click="form.direction = 'take'">− {{ t.loyalty?.take || 'Take' }}</button>
        </div>
        <input v-model.number="form.amount" type="number" min="1" max="1000000" step="1" required dir="ltr" data-testid="loyalty-amount"
               :placeholder="t.loyalty?.amount || 'Points'" class="h-9 w-32 rounded-md border border-border bg-background px-2 text-sm" />
        <input v-model="form.note" type="text" maxlength="255" required data-testid="loyalty-note"
               :placeholder="t.loyalty?.reason || 'Why? (saved with the line)'" class="h-9 min-w-[14rem] flex-1 rounded-md border border-border bg-background px-2 text-sm" />
        <button type="submit" :disabled="saving" data-testid="loyalty-save" class="h-9 min-w-[7rem] rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground disabled:opacity-50">
          {{ saving ? '…' : (t.loyalty?.save_line || 'Save') }}
        </button>
      </div>
      <p v-if="error" class="text-xs value-negative" data-testid="loyalty-error">{{ error }}</p>
    </form>

    <!-- The ledger: one line per change — what, why, where it came from, who, when, and the balance it left. -->
    <div class="px-6">
      <div class="rounded-lg border border-border divide-y divide-border max-h-96 overflow-y-auto" data-testid="loyalty-ledger">
        <p v-if="loading" class="px-3 py-2 text-sm text-muted-foreground">…</p>
        <p v-else-if="!rows.length" class="px-3 py-2 text-sm text-muted-foreground">{{ t.loyalty?.no_history || 'Nothing yet.' }}</p>
        <div v-for="row in rows" :key="row.id" class="flex flex-wrap items-center gap-x-3 gap-y-0.5 px-3 py-2 text-sm" data-testid="loyalty-row">
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

const SOURCES = { quiz: 'Quiz game', badge: 'Badge reward', admin: 'Admin (by hand)', wallet: 'Converted to wallet', transfer: 'Transfer', order: 'Order' };
const sourceLabel = (s) => t.value.loyalty?.['source_' + s] || SOURCES[s] || s;

// Newest first; "after" is the balance each line left behind (balance now, minus the lines above it).
const rows = computed(() => {
  let running = balance.value ?? 0;
  return ledger.value.map((r) => {
    const after = running;
    running -= r.value;
    return { ...r, after };
  });
});
const truncated = computed(() => ledger.value.length >= 200);

async function load() {
  loading.value = true;
  try {
    const { data } = await axios.get(route('admin.loyalty.members.ledger', props.userId));
    balance.value = data.balance;
    ledger.value = (data.data || []).filter((r) => r.ledger === 'points');
  } catch (e) {
    error.value = t.value.loyalty?.load_failed || 'Could not load the points history.';
  } finally {
    loading.value = false;
  }
}

function submit() {
  error.value = '';
  const amount = Math.abs(Math.trunc(Number(form.amount)));
  if (!amount) { error.value = t.value.loyalty?.amount_required || 'Enter a number of points.'; return; }
  const points = form.direction === 'take' ? -amount : amount;
  saving.value = true;
  router.post(route('admin.loyalty.grants.store'), { user_id: props.userId, points, note: form.note }, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: async () => {
      if (page.props.flash?.error) { error.value = page.props.flash.error; return; }
      useNotification().success(points > 0 ? 'Points added' : 'Points taken');
      form.amount = null;
      form.note = '';
      await load();
    },
    onError: (errors) => {
      error.value = errors.points || errors.note || errors.user_id || 'The points could not be saved.';
    },
    onFinish: () => { saving.value = false; },
  });
}

onMounted(load);
</script>
