<template>
  <div class="bg-card text-card-foreground flex flex-col gap-3 rounded-xl border border-border py-4 shadow-sm" data-testid="role-ledger-limits">
    <div class="px-6">
      <div class="title-golden leading-none font-semibold">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
          <circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>
        </svg>
        Monthly limits — loyalty points &amp; wallet
      </div>
      <p class="mt-1 text-xs text-muted-foreground">The default for every admin who holds this role: the most they may add or take by hand per calendar month. A single admin can be given their own limit on their edit page. Super admin is never capped.</p>
    </div>

    <div class="px-6 grid grid-cols-1 lg:grid-cols-2 gap-3">
      <div v-for="row in rows" :key="row.permission" class="rounded-lg border border-border p-3 space-y-2" data-testid="role-limit-row">
        <div class="flex flex-wrap items-center gap-2">
          <span class="chip" :class="row.permission.includes('wallet') ? 'chip-info' : 'chip-accent'">{{ row.permission.includes('wallet') ? 'Wallet' : 'Points' }}</span>
          <span class="chip" :class="row.permission.startsWith('add') ? 'chip-positive' : 'chip-negative'">{{ row.permission.startsWith('add') ? 'Add' : 'Take' }}</span>
          <span v-if="!row.granted" class="chip chip-neutral">not in this role</span>
          <span class="text-xs text-muted-foreground" dir="ltr">{{ row.permission }}</span>
        </div>
        <div class="flex flex-wrap items-center gap-3">
          <label class="inline-flex items-center gap-2 text-sm">
            <input v-model="row.unlimited" type="checkbox" :disabled="!can_edit_limits" class="h-4 w-4" data-testid="role-limit-unlimited" />
            Unlimited
          </label>
          <input v-model.number="row.value" type="number" min="0" step="0.01" dir="ltr" :disabled="!can_edit_limits || row.unlimited"
                 class="h-9 w-36 rounded-md border border-border bg-background px-2 text-sm disabled:opacity-50" placeholder="Per month" data-testid="role-limit-input" />
        </div>
        <p v-if="row.granted && row.unlimited" class="text-xs value-warning" data-testid="role-limit-warning">
          {{ row.configured ? 'Unlimited on purpose.' : 'Not set yet — holders of this role are UNLIMITED until you enter a number.' }}
        </p>
      </div>
    </div>

    <div v-if="can_edit_limits" class="px-6 flex justify-end">
      <button type="button" :disabled="saving" class="h-9 min-w-[9rem] rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground disabled:opacity-50" data-testid="role-limits-save" @click="save">
        {{ saving ? '…' : 'Save limits' }}
      </button>
    </div>
    <p v-else class="px-6 text-xs text-muted-foreground">Only the super admin can change these limits.</p>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  role_id: { type: Number, required: true },
  ledger_limits: { type: Array, default: () => [] },
  can_edit_limits: { type: Boolean, default: false },
});

const rows = reactive(props.ledger_limits.map((l) => ({
  permission: l.permission,
  granted: l.granted,
  configured: l.configured,
  unlimited: l.monthly_limit === null || l.monthly_limit === undefined,
  value: l.monthly_limit === null || l.monthly_limit === undefined ? null : Number(l.monthly_limit),
})));

const saving = ref(false);
function save() {
  const limits = {};
  rows.forEach((r) => { limits[r.permission] = r.unlimited || r.value === null || r.value === '' ? null : r.value; });
  saving.value = true;
  router.put(route('admin.roles.ledger-limits', props.role_id), { limits }, { preserveScroll: true, onFinish: () => { saving.value = false; } });
}
</script>
