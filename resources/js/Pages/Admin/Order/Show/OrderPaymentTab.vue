<template>
  <div class="space-y-3">
    <!-- Where the money stands, and the buttons that act on it -->
    <div class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
      <div class="p-3 border-b border-border flex flex-wrap items-center justify-between gap-2">
        <div class="flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-golden-yellow">
            <rect width="20" height="14" x="2" y="5" rx="2"></rect><line x1="2" x2="22" y1="10" y2="10"></line>
          </svg>
          <h2 class="text-lg font-semibold">{{ t.order?.payment_tab || 'Payment' }}</h2>
        </div>

        <div v-if="canManage && !order.deleted_at" class="flex flex-wrap gap-2">
          <button
            v-if="paymob.is_online"
            type="button"
            :disabled="busy !== null || !paymob.inquiry_ready"
            :title="paymob.inquiry_ready ? (t.order?.payment_check_hint || 'Ask Paymob what happened to this payment and apply the answer.') : 'PAYMOB_API_KEY is not set'"
            @click="post('check')"
            :class="btn"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-3-6.7L21 8"></path><path d="M21 3v5h-5"></path></svg>
            {{ busy === 'check' ? '…' : (t.order?.payment_check || 'Check with Paymob') }}
          </button>
          <span v-if="paymob.refund_requested" class="inline-flex items-center rounded-md bg-amber-500/15 text-amber-600 text-xs font-semibold h-8 px-3">
            {{ t.order?.refund_requested || 'Buyer requested a refund' }}
          </span>
          <button
            v-if="paymob.can_refund && paymob.refundable > 0"
            type="button"
            :disabled="busy !== null"
            @click="openRefund"
            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all border border-red-500/40 bg-red-500/10 text-red-500 hover:bg-red-500 hover:text-white h-8 px-3 py-1.5 disabled:opacity-50 cursor-pointer"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7v6h6"></path><path d="M21 17a9 9 0 0 0-15-6.7L3 13"></path></svg>
            {{ t.order?.payment_refund || 'Refund' }}
          </button>
          <button
            type="button"
            :disabled="busy !== null || !order.receipt_email"
            :title="order.receipt_email ? order.receipt_email : (t.order?.receipt_no_email || 'No email address on this order')"
            @click="post('resend-receipt')"
            :class="btn"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path></svg>
            {{ busy === 'resend-receipt' ? '…' : (t.order?.payment_resend_receipt || 'Email receipt') }}
          </button>
        </div>
      </div>

      <div class="p-3 grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
        <div>
          <label class="text-xs font-medium text-muted-foreground">{{ t.order?.payment_gateway || 'Gateway' }}</label>
          <p class="mt-0.5">{{ paymob.is_online ? 'Paymob' : paymentTypeLabel(t, order.payment_type) }}</p>
        </div>
        <div>
          <label class="text-xs font-medium text-muted-foreground">{{ t.order?.payment_status || 'Payment status' }}</label>
          <p class="mt-0.5">
            <span :class="['inline-flex rounded-md border px-1.5 py-0.5 text-xs font-medium', statusClass.payment[order.payment_status?.value]]">{{ paymentStatusLabel(t, order.payment_status) }}</span>
          </p>
        </div>
        <div>
          <label class="text-xs font-medium text-muted-foreground">{{ t.order?.payment_response_code || 'Last gateway code' }}</label>
          <p class="mt-0.5 font-mono" dir="ltr">{{ paymob.response_code || '—' }}</p>
        </div>
        <div>
          <label class="text-xs font-medium text-muted-foreground">{{ t.order?.payment_attempts || 'Attempts' }}</label>
          <p class="mt-0.5 tabular-nums">{{ paymob.attempts || 0 }}</p>
        </div>
        <div>
          <label class="text-xs font-medium text-muted-foreground">{{ t.order?.payment_transaction || 'Transaction ID' }}</label>
          <p class="mt-0.5 font-mono break-all" dir="ltr">{{ paymob.transaction_id || '—' }}</p>
        </div>
        <div>
          <label class="text-xs font-medium text-muted-foreground">{{ t.order?.payment_reference || 'Reference sent' }}</label>
          <p class="mt-0.5 font-mono break-all" dir="ltr">{{ paymob.reference || '—' }}</p>
        </div>
        <div>
          <label class="text-xs font-medium text-muted-foreground">{{ t.order?.payment_paid_at || 'Paid at' }}</label>
          <p class="mt-0.5 tabular-nums">{{ paymob.paid_at || '—' }}</p>
        </div>
        <div>
          <label class="text-xs font-medium text-muted-foreground">{{ t.order?.payment_refunded || 'Refunded' }}</label>
          <p class="mt-0.5 tabular-nums" :class="paymob.refunded_amount > 0 ? 'text-red-500' : ''">
            {{ formatPrice(paymob.refunded_amount) }}<span v-if="paymob.refunded_at" class="text-xs text-muted-foreground"> · {{ formatDateTime(paymob.refunded_at) }}</span>
          </p>
        </div>
        <div class="col-span-2">
          <label class="text-xs font-medium text-muted-foreground">{{ t.order?.payment_receipt_email || 'Receipt email' }}</label>
          <p class="mt-0.5 break-all" dir="ltr">
            {{ order.receipt_email || '—' }}
            <span v-if="paymob.receipt_emailed_at" class="text-xs text-emerald-500"> · {{ t.order?.payment_receipt_sent || 'sent' }} {{ paymob.receipt_emailed_at }}</span>
          </p>
        </div>
      </div>

      <p v-if="paymob.is_online && !paymob.configured" class="mx-3 mb-3 rounded-md border border-amber-500/30 bg-amber-500/10 p-2 text-xs text-amber-500">
        {{ t.order?.payment_not_configured || 'Paymob is not configured in .env — buyers cannot start an online payment.' }}
      </p>
    </div>

    <!-- Refund dialog -->
    <div v-if="refunding" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="refunding = false">
      <div class="w-full max-w-sm rounded-xl border border-border bg-card p-4 space-y-3 shadow-xl">
        <h3 class="text-base font-semibold">{{ t.order?.payment_refund || 'Refund' }} — {{ order.order_code }}</h3>
        <div>
          <label class="text-xs font-medium text-muted-foreground">{{ t.order?.payment_refund_amount || 'Amount' }} (max {{ formatPrice(paymob.refundable) }})</label>
          <input v-model="refundAmount" type="number" step="0.01" min="0.01" :max="paymob.refundable" class="mt-1 w-full rounded-md border border-border bg-background px-2 py-1.5 text-sm" dir="ltr" />
        </div>
        <div>
          <label class="text-xs font-medium text-muted-foreground">{{ t.order?.payment_refund_reason || 'Reason' }}</label>
          <textarea v-model="refundReason" rows="2" maxlength="500" class="mt-1 w-full rounded-md border border-border bg-background px-2 py-1.5 text-sm"></textarea>
        </div>
        <p class="text-xs text-muted-foreground">{{ t.order?.payment_refund_warn || 'This sends money back to the buyer through Paymob and cannot be undone.' }}</p>
        <div class="flex justify-end gap-2">
          <button type="button" @click="refunding = false" :class="btn">{{ t.common?.cancel || 'Cancel' }}</button>
          <button type="button" :disabled="busy !== null" @click="submitRefund" class="inline-flex items-center rounded-md bg-red-600 px-3 h-8 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50 cursor-pointer">
            {{ busy === 'refund' ? '…' : (t.order?.payment_refund_confirm || 'Refund now') }}
          </button>
        </div>
      </div>
    </div>

    <!-- The wire log: every call out and every call in, verbatim -->
    <div class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
      <div class="p-3 border-b border-border flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-golden-yellow"><path d="M4 17l6-6-6-6"></path><path d="M12 19h8"></path></svg>
        <h2 class="text-lg font-semibold">{{ t.order?.payment_log || 'Gateway log' }}</h2>
        <span class="text-xs text-muted-foreground">{{ logs.length }}</span>
      </div>

      <div v-if="!logs.length" class="p-6 text-center text-sm text-muted-foreground flex flex-col items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="opacity-50"><rect width="20" height="14" x="2" y="5" rx="2"></rect><line x1="2" x2="22" y1="10" y2="10"></line></svg>
        {{ t.order?.payment_log_empty || 'No gateway activity yet. Cash-on-delivery and wallet orders never reach Paymob.' }}
      </div>

      <ol v-else class="p-3 space-y-2">
        <li v-for="log in logs" :key="log.id" class="rounded-lg border border-border bg-background/40">
          <button type="button" class="w-full flex flex-wrap items-center gap-2 p-2.5 text-start cursor-pointer" @click="toggle(log.id)">
            <span :class="['inline-flex items-center gap-1 rounded-md border px-1.5 py-0.5 text-xs font-medium', directionClass[log.direction]]">
              {{ log.direction === 'out' ? '→ OUT' : '← IN' }}
            </span>
            <span class="text-sm font-medium">{{ eventLabel(log.event) }}</span>
            <span v-if="log.method" class="font-mono text-xs text-muted-foreground">{{ log.method }}</span>
            <span v-if="log.http_status" :class="['font-mono text-xs rounded px-1 py-0.5', httpClass(log.http_status)]" :title="t.order?.payment_http_code || 'HTTP status'">{{ log.http_status }}</span>
            <span v-if="log.gateway_code" class="font-mono text-xs rounded px-1 py-0.5 bg-sky-500/10 text-sky-500" :title="t.order?.payment_response_code || 'Gateway code'">{{ log.gateway_code }}</span>
            <span v-if="log.success === true" class="text-xs text-emerald-500">✓</span>
            <span v-else-if="log.success === false" class="text-xs text-red-500">✗</span>
            <span class="ms-auto flex flex-wrap items-center gap-2 text-xs text-muted-foreground tabular-nums">
              <span v-if="log.duration_ms !== null" class="rounded bg-muted px-1.5 py-0.5" :title="t.order?.payment_duration || 'Time the exchange took'">{{ log.duration_ms }} ms</span>
              <span v-if="log.gap_ms" :title="t.order?.payment_gap || 'Time since the previous exchange'">+{{ gap(log.gap_ms) }}</span>
              <span class="text-end">
                {{ formatDateTime(log.started_at) }}
                <span class="block text-[11px] opacity-70">{{ timeAgo(log.started_at, page.props.locale || 'en') }}</span>
              </span>
            </span>
          </button>

          <div v-if="open.has(log.id)" class="border-t border-border p-2.5 space-y-2 text-xs">
            <p v-if="log.error_message" class="text-red-500">{{ log.error_message }}</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1">
              <div class="break-all" dir="ltr"><span class="text-muted-foreground">URL:</span> {{ log.url || '—' }}</div>
              <div dir="ltr"><span class="text-muted-foreground">Started:</span> {{ formatDateTime(log.started_at) }} <span class="opacity-70">({{ timeAgo(log.started_at, page.props.locale || 'en') }})</span></div>
              <div dir="ltr"><span class="text-muted-foreground">Finished:</span> {{ formatDateTime(log.finished_at) }}</div>
              <div dir="ltr"><span class="text-muted-foreground">Flow:</span> <span class="font-mono">{{ log.correlation_id }}</span></div>
              <div v-if="log.admin"><span class="text-muted-foreground">{{ t.order?.payment_by || 'By' }}:</span> {{ log.admin }}</div>
              <div v-if="log.ip_address" dir="ltr"><span class="text-muted-foreground">IP:</span> {{ log.ip_address }}</div>
            </div>
            <div>
              <div class="mb-0.5 font-medium text-muted-foreground">{{ log.direction === 'out' ? (t.order?.payment_sent || 'Sent') : (t.order?.payment_received || 'Received') }}</div>
              <pre class="max-h-72 overflow-auto rounded-md bg-muted/50 p-2 font-mono whitespace-pre-wrap break-all" dir="ltr">{{ pretty(log.request_headers ? { headers: log.request_headers } : null) }}{{ pretty(log.request_body) }}</pre>
            </div>
            <div v-if="log.response_body !== null || log.http_status">
              <div class="mb-0.5 font-medium text-muted-foreground">{{ log.direction === 'out' ? (t.order?.payment_received || 'Received') : (t.order?.payment_answered || 'We answered') }}</div>
              <pre class="max-h-72 overflow-auto rounded-md bg-muted/50 p-2 font-mono whitespace-pre-wrap break-all" dir="ltr">{{ pretty(log.response_body) }}</pre>
            </div>
          </div>
        </li>
      </ol>
    </div>
  </div>
</template>

<script setup>
import { router, usePage } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import { useNotification } from "@/composables/useNotification";
import { formatDateTime, formatPrice, paymentStatusLabel, timeAgo, paymentTypeLabel, statusClass } from "../orderDisplay.js";

const props = defineProps({
  order: { type: Object, required: true },
  canManage: { type: Boolean, default: false },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});
const paymob = computed(() => props.order.paymob || {});
const logs = computed(() => props.order.payment_logs || []);

const btn = 'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input h-8 px-3 py-1.5 disabled:opacity-50 disabled:pointer-events-none cursor-pointer';

const directionClass = {
  out: 'border-blue-500/30 bg-blue-500/10 text-blue-500',
  in: 'border-violet-500/30 bg-violet-500/10 text-violet-500',
};

const eventNames = {
  intention_create: ['payment_ev_intention', 'Open payment'],
  auth_token: ['payment_ev_auth', 'Gateway sign-in'],
  callback_received: ['payment_ev_callback', 'Callback from Paymob'],
  return_redirect: ['payment_ev_return', 'Buyer returned'],
  transaction_inquiry: ['payment_ev_inquiry', 'Payment check'],
  refund: ['payment_ev_refund', 'Refund'],
  result_applied: ['payment_ev_applied', 'Result applied to order'],
};
const eventLabel = (event) => {
  const [key, fallback] = eventNames[event] || [null, event];
  return (key && t.value.order?.[key]) || fallback;
};

const httpClass = (status) => (status >= 200 && status < 300
  ? 'bg-emerald-500/10 text-emerald-500'
  : status >= 400 ? 'bg-red-500/10 text-red-500' : 'bg-amber-500/10 text-amber-500');

const gap = (ms) => (ms < 1000 ? `${ms} ms` : ms < 60000 ? `${(ms / 1000).toFixed(1)} s` : `${Math.round(ms / 60000)} min`);

/* Bodies are shown exactly as stored; JSON is only re-indented to be readable. */
const pretty = (value) => {
  if (value === null || value === undefined || value === '') return '';
  const text = typeof value === 'string' ? value : JSON.stringify(value, null, 2);
  try {
    const parsed = JSON.parse(text);
    return typeof parsed === 'object' && parsed !== null ? JSON.stringify(parsed, null, 2) : text;
  } catch {
    return text;
  }
};

const open = ref(new Set());
const toggle = (id) => {
  const next = new Set(open.value);
  next.has(id) ? next.delete(id) : next.add(id);
  open.value = next;
};

const busy = ref(null);
const refunding = ref(false);
const refundAmount = ref('');
const refundReason = ref('');

const openRefund = () => {
  refundAmount.value = paymob.value.refundable;
  refundReason.value = '';
  refunding.value = true;
};

const send = (action, data = {}) => {
  busy.value = action;
  router.post(route(`admin.order.payment.${action}`, props.order.order_code), data, {
    preserveScroll: true,
    onSuccess: (response) => {
      const flash = response.props.flash || {};
      if (flash.error) useNotification().error(flash.error);
      else if (flash.success) useNotification().success(flash.success);
      refunding.value = false;
    },
    onError: (errors) => {
      useNotification().error(Object.values(errors)[0] || t.value.order?.payment_action_failed || 'The action failed.');
      reportClientError(`Order payment ${action} failed`, { errors });
    },
    onFinish: () => { busy.value = null; },
  });
};

const post = (action) => send(action);
const submitRefund = () => send('refund', { amount: refundAmount.value, reason: refundReason.value });

/* AGENTS.md: front-end failures go to the client-error endpoint. */
function reportClientError(message, extra) {
  try {
    fetch('/api/v1/client-errors', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ message, route: window.location.pathname, fatal: false, extra: { order_code: props.order.order_code, ...extra } }),
    }).catch(() => {});
  } catch {
    // Reporting is never worth a second error.
  }
}
</script>
