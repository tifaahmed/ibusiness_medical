<template>
  <div class="bg-card text-card-foreground flex flex-col gap-3 rounded-xl border border-border py-4 shadow-sm" data-testid="ledger-limits">
    <div class="px-6">
      <div class="title-golden leading-none font-semibold">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
          <circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>
        </svg>
        {{ t.ledger_limits_title || 'Monthly limits — loyalty points & wallet' }}
      </div>
      <p class="mt-1 text-xs text-muted-foreground">{{ t.ledger_limits_hint || 'Counts what this admin adds or takes by hand each calendar month. Each row either uses the default set on the role of the admin, is Unlimited on purpose, or has its own limit. With nothing set anywhere the admin is unlimited. A limit only matters when the admin holds that permission.' }}</p>
    </div>

    <div class="px-6 grid grid-cols-1 lg:grid-cols-2 gap-3">
      <div v-for="row in rows" :key="row.permission" class="rounded-lg border border-border p-3 space-y-2" data-testid="ledger-limit-row">
        <div class="flex flex-wrap items-center gap-2">
          <span class="chip" :class="row.permission.includes('wallet') ? 'chip-info' : 'chip-accent'">{{ row.permission.includes('wallet') ? (t.wallet || 'Wallet') : (t.points || 'Points') }}</span>
          <span class="chip" :class="row.permission.startsWith('add') ? 'chip-positive' : 'chip-negative'">{{ row.permission.startsWith('add') ? (t.add || 'Add') : (t.take || 'Take') }}</span>
          <span v-if="!row.granted" class="chip chip-neutral">{{ t.not_granted || 'permission not held' }}</span>
          <span class="text-xs text-muted-foreground" dir="ltr">{{ row.permission }}</span>
        </div>
        <div class="flex flex-wrap items-center gap-3">
          <select v-model="row.mode" :disabled="!can_edit_limits" class="h-9 rounded-md border border-border bg-background px-2 text-sm" data-testid="ledger-limit-mode">
            <option value="inherit">{{ t.limit_inherit || 'Use the role default' }}</option>
            <option value="unlimited">{{ t.unlimited || 'Unlimited' }}</option>
            <option value="limit">{{ t.limit_custom || 'Own monthly limit' }}</option>
          </select>
          <input v-if="row.mode === 'limit'" v-model.number="row.value" type="number" min="0" step="0.01" dir="ltr" :disabled="!can_edit_limits"
                 class="h-9 w-36 rounded-md border border-border bg-background px-2 text-sm" :placeholder="t.monthly_limit || 'Per month'" data-testid="ledger-limit-input" />
        </div>
        <p class="text-xs text-foreground/80">
          <template v-if="row.mode === 'inherit'">{{ t.role_default_is || 'Role default' }}:
            <b dir="ltr">{{ row.source === 'role' ? row.effective_limit : (t.unlimited_not_set || 'unlimited (no role limit set)') }}</b> ·
          </template>
          {{ t.used_this_month || 'Used this month' }}: <b dir="ltr">{{ row.used }}</b>
        </p>
        <p v-if="row.mode === 'unlimited' || (row.mode === 'inherit' && row.source === 'none')" class="text-xs value-warning" data-testid="ledger-limit-warning">{{ t.no_cap_warning || 'No monthly cap: this admin can move any amount.' }}</p>
      </div>
    </div>

    <div v-if="can_edit_limits" class="px-6 flex justify-end">
      <button type="button" :disabled="saving" class="h-9 min-w-[9rem] rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground disabled:opacity-50" data-testid="ledger-limits-save" @click="save">
        {{ saving ? '…' : (t.save_limits || 'Save limits') }}
      </button>
    </div>
    <p v-else class="px-6 text-xs text-muted-foreground">{{ t.limits_super_only || 'Only the super admin can change these limits.' }}</p>
  </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps({
  admin_id: { type: Number, required: true },
  ledger_limits: { type: Array, default: () => [] },
  can_edit_limits: { type: Boolean, default: false },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin?.admin_user || {});

const rows = reactive(props.ledger_limits.map((l) => ({
  permission: l.permission,
  mode: l.mode,
  value: l.monthly_limit === null || l.monthly_limit === undefined ? null : Number(l.monthly_limit),
  effective_limit: l.effective_limit,
  source: l.source,
  granted: l.granted,
  used: Number(l.used),
})));

const saving = ref(false);
function save() {
  const limits = {};
  rows.forEach((r) => { limits[r.permission] = { mode: r.mode, value: r.mode === 'limit' ? r.value : null }; });
  saving.value = true;
  router.put(route('admin.admin-users.ledger-limits', props.admin_id), { limits }, { preserveScroll: true, onFinish: () => { saving.value = false; } });
}
</script>
