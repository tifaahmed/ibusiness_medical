<template>
  <AppLayout>
    <div class="space-y-3 p-3 sm:p-4 lg:p-6 w-full max-w-full">
      <Breadcrumb
        :title="t.subscriptions?.title || 'Membership Payments'"
        :breadcrumbs="[{ label: t.subscriptions?.title || 'Membership Payments', link: '#', active: true }]"
      />

      <div class="max-w-7xl mx-auto space-y-3">
        <!-- Title, status tabs, filters -->
        <div class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
          <div class="p-3 border-b border-border flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-golden-yellow"><rect width="20" height="14" x="2" y="5" rx="2"></rect><line x1="2" x2="22" y1="10" y2="10"></line></svg>
              <h1 class="text-lg font-semibold">{{ t.subscriptions?.title || 'Membership Payments' }}</h1>
              <span class="text-xs text-muted-foreground">{{ subscriptions.total }}</span>
            </div>
            <div class="flex flex-wrap items-center gap-1 rounded-md border border-border p-0.5">
              <button
                v-for="status in statuses"
                :key="status"
                type="button"
                @click="apply({ status })"
                :class="['px-2 py-1 text-xs rounded transition-colors cursor-pointer', filters.status === status ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted']"
              >
                {{ statusText(status) }}<span v-if="status !== 'all' && counts[status]" class="ms-1 tabular-nums">{{ counts[status] }}</span>
              </button>
            </div>
          </div>
          <div class="p-3 flex flex-wrap items-center gap-2">
            <input v-model="search" @keyup.enter="apply({ search })" type="search" :placeholder="t.subscriptions?.search || 'Card number, name or phone'" class="h-8 w-60 rounded-md border border-border bg-background px-2 text-sm" />
            <select :value="filters.method || ''" @change="apply({ method: $event.target.value || null })" class="h-8 rounded-md border border-border bg-background px-2 text-sm">
              <option value="">{{ t.subscriptions?.all_methods || 'All methods' }}</option>
              <option v-for="method in methods" :key="method" :value="method">{{ methodText(method) }}</option>
            </select>
          </div>
        </div>

        <!-- Empty state -->
        <div v-if="!subscriptions.data.length" class="bg-card rounded-xl border border-border p-10 text-center text-sm text-muted-foreground flex flex-col items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="opacity-50"><rect width="20" height="14" x="2" y="5" rx="2"></rect><line x1="2" x2="22" y1="10" y2="10"></line></svg>
          {{ t.subscriptions?.empty || 'No payments here.' }}
        </div>

        <!-- Rows -->
        <div v-for="row in subscriptions.data" :key="row.id" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm p-3 space-y-2">
          <div class="flex flex-wrap items-center gap-2">
            <span class="font-mono text-sm font-semibold">#{{ row.id }}</span>
            <Link v-if="row.membership_slug" :href="route('guest.membership.show', row.membership_slug)" class="text-sm text-primary hover:underline font-mono" target="_blank">{{ row.membership_number }}</Link>
            <span class="text-sm font-medium">{{ row.member_name || '—' }}</span>
            <span class="text-xs text-muted-foreground" dir="ltr">{{ row.member_phone }}</span>
            <span :class="['inline-flex items-center rounded-md border px-1.5 py-0.5 text-xs font-medium', methodClass[row.method]]">{{ methodText(row.method) }}</span>
            <span :class="['inline-flex items-center rounded-md border px-1.5 py-0.5 text-xs font-medium', statusClass[row.status]]">{{ statusText(row.status) }}</span>
            <span class="ms-auto text-sm font-semibold tabular-nums">{{ row.amount.toFixed(2) }} EGP · {{ row.months }} {{ t.subscriptions?.months || 'mo' }}</span>
          </div>

          <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
            <span dir="ltr">{{ row.created_at }}</span>
            <span v-if="row.reference">{{ t.subscriptions?.reference || 'Reference' }}: <b class="text-foreground">{{ row.reference }}</b></span>
            <span v-if="row.paymob_transaction_id" dir="ltr">Paymob #{{ row.paymob_transaction_id }}<template v-if="row.paymob_response_code"> · {{ row.paymob_response_code }}</template></span>
            <span v-if="row.confirmed_by">{{ t.subscriptions?.by || 'By' }} {{ row.confirmed_by }} · {{ row.confirmed_at }}</span>
            <span v-if="row.rejection_reason" class="text-red-500">{{ row.rejection_reason }}</span>
          </div>

          <!-- Receipts: a claim to check against the wallet, never a confirmation. -->
          <div v-if="row.receipts.length" class="flex flex-wrap gap-2">
            <a v-for="receipt in row.receipts" :key="receipt.id" :href="receipt.url" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-md border border-border bg-muted/40 px-2 py-1 text-xs hover:bg-muted">
              <img v-if="isImage(receipt.name)" :src="receipt.url" :alt="receipt.name" class="h-10 w-10 rounded object-cover" />
              <svg v-else xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
              {{ receipt.name }}
            </a>
          </div>

          <div v-if="row.status === 'pending' || row.status === 'rejected'" class="flex flex-wrap items-center gap-2 pt-1">
            <button type="button" :disabled="busy === row.id" @click="confirm(row)" class="inline-flex items-center gap-1.5 rounded-md bg-emerald-600 px-3 h-8 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50 cursor-pointer">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
              {{ t.subscriptions?.confirm || 'Confirm payment' }}
            </button>
            <template v-if="row.status === 'pending'">
              <input v-model="reasons[row.id]" type="text" maxlength="255" :placeholder="t.subscriptions?.reason || 'Reason (optional)'" class="h-8 w-52 rounded-md border border-border bg-background px-2 text-sm" />
              <button type="button" :disabled="busy === row.id" @click="reject(row)" class="inline-flex items-center gap-1.5 rounded-md border border-red-500/40 bg-red-500/10 px-3 h-8 text-sm font-medium text-red-500 hover:bg-red-500 hover:text-white disabled:opacity-50 cursor-pointer">
                {{ t.subscriptions?.reject || 'Reject' }}
              </button>
            </template>
          </div>
        </div>

        <!-- Pagination -->
        <div v-if="subscriptions.links.length > 3" class="flex flex-wrap justify-center gap-1">
          <template v-for="link in subscriptions.links" :key="link.label">
            <Link v-if="link.url" :href="link.url" preserve-scroll :class="['rounded px-2.5 py-1 text-sm border border-border', link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted']" v-html="link.label" />
          </template>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Link, router, usePage } from "@inertiajs/vue3";
import { computed, reactive, ref } from "vue";
import { AppLayout, Breadcrumb } from "@/Pages/Admin/Layout/Layout.js";
import { useNotification } from "@/composables/useNotification";

const props = defineProps({
  subscriptions: { type: Object, required: true },
  filters: { type: Object, required: true },
  counts: { type: Object, default: () => ({}) },
  statuses: { type: Array, default: () => [] },
  methods: { type: Array, default: () => [] },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});
const search = ref(props.filters.search || "");
const reasons = reactive({});
const busy = ref(null);

const statusClass = {
  pending: "border-amber-500/30 bg-amber-500/10 text-amber-500",
  accepted: "border-emerald-500/30 bg-emerald-500/10 text-emerald-500",
  rejected: "border-red-500/30 bg-red-500/10 text-red-500",
  refunded: "border-fuchsia-500/30 bg-fuchsia-500/10 text-fuchsia-500",
  canceled: "border-zinc-500/30 bg-zinc-500/10 text-zinc-400",
};
const methodClass = {
  paymob: "border-indigo-500/30 bg-indigo-500/10 text-indigo-500",
  cash_on_delivery: "border-sky-500/30 bg-sky-500/10 text-sky-500",
  vodafone_cash: "border-red-500/30 bg-red-500/10 text-red-500",
  instapay: "border-violet-500/30 bg-violet-500/10 text-violet-500",
};
const statusLabels = { pending: "Pending", accepted: "Accepted", rejected: "Rejected", refunded: "Refunded", canceled: "Canceled", all: "All" };
const methodLabels = { paymob: "Paymob (online)", cash_on_delivery: "Cash on delivery", vodafone_cash: "Vodafone Cash", instapay: "Instapay" };
const statusText = (key) => t.value.subscriptions?.[`status_${key}`] || statusLabels[key] || key;
const methodText = (key) => t.value.subscriptions?.[`method_${key}`] || methodLabels[key] || key;
const isImage = (name) => /\.(jpe?g|png|webp|gif)$/i.test(name || "");

const apply = (changes) => {
  router.get(route("admin.subscription.index"), { ...props.filters, ...changes }, { preserveState: true, replace: true });
};

const send = (row, action, data = {}) => {
  busy.value = row.id;
  router.post(route(`admin.subscription.${action}`, row.id), data, {
    preserveScroll: true,
    onSuccess: (response) => {
      const flash = response.props.flash || {};
      if (flash.error) useNotification().error(flash.error);
      else if (flash.success) useNotification().success(flash.success);
    },
    onError: (errors) => {
      useNotification().error(Object.values(errors)[0] || "The action failed.");
      reportClientError(`Subscription ${action} failed`, { subscription_id: row.id, errors });
    },
    onFinish: () => { busy.value = null; },
  });
};
const confirm = (row) => {
  if (window.confirm(t.value.subscriptions?.confirm_ask || "Confirm this payment and extend the card?")) send(row, "confirm");
};
const reject = (row) => send(row, "reject", { reason: reasons[row.id] || "" });

/* AGENTS.md: front-end failures go to the client-error endpoint. */
function reportClientError(message, extra) {
  try {
    fetch("/api/v1/client-errors", {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({ message, route: window.location.pathname, fatal: false, extra }),
    }).catch(() => {});
  } catch {
    // Reporting is never worth a second error.
  }
}
</script>
