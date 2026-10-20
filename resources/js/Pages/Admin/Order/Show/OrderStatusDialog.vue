<template>
  <Teleport to="body">
    <div
      v-if="kind"
      class="fixed inset-0 z-[100] flex items-start justify-center overflow-y-auto bg-black/60 p-4 backdrop-blur-sm sm:items-center"
      role="dialog"
      aria-modal="true"
      @click.self="$emit('close')"
      @keydown.esc="$emit('close')"
    >
      <div class="w-full max-w-xl overflow-hidden rounded-xl border border-border bg-card text-card-foreground shadow-xl">
        <div class="flex items-start gap-3 border-b border-border p-4">
          <div class="min-w-0 space-y-1.5">
            <p class="text-xs font-medium text-muted-foreground">{{ info.group }}</p>
            <span :class="['inline-flex items-center rounded-md border px-2 py-0.5 text-sm font-medium', info.badgeClass]">{{ info.label }}</span>
          </div>
          <button type="button" class="ms-auto rounded-md p-1 text-muted-foreground hover:bg-muted hover:text-foreground cursor-pointer" @click="$emit('close')">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
          </button>
        </div>

        <div class="space-y-4 p-4">
          <p class="text-sm leading-relaxed text-muted-foreground">{{ info.description }}</p>

          <!-- The journey: every step the order passes through, the one it is on, and when it got there. -->
          <ol v-if="steps.length" class="space-y-0">
            <li v-for="(step, index) in steps" :key="step.value" class="relative flex gap-3 pb-4 last:pb-0">
              <span
                v-if="index < steps.length - 1"
                class="absolute start-[11px] top-6 h-[calc(100%-1.5rem)] w-0.5"
                :class="step.state === 'done' ? 'bg-emerald-500' : 'bg-border'"
              ></span>
              <span
                class="z-10 flex h-6 w-6 shrink-0 items-center justify-center rounded-full border-2 text-[11px] font-bold"
                :class="dotClass(step)"
              >
                <svg v-if="step.state === 'done'" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                <template v-else>{{ index + 1 }}</template>
              </span>
              <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-baseline gap-x-2">
                  <span class="text-sm font-medium" :class="step.state === 'upcoming' ? 'text-muted-foreground' : ''">{{ step.label }}</span>
                  <span v-if="step.state === 'current'" class="rounded bg-primary/10 px-1.5 py-0.5 text-[11px] font-medium text-primary">{{ txt.now }}</span>
                </div>
                <p v-if="step.at" class="text-xs tabular-nums text-muted-foreground" dir="ltr">
                  {{ formatDateTime(step.at) }} · {{ timeAgo(step.at, locale) }}
                </p>
                <p v-if="step.by" class="text-xs text-muted-foreground">{{ txt.by }} {{ step.by }}</p>
                <p v-if="step.state === 'upcoming'" class="text-xs text-muted-foreground">{{ txt.not_yet }}</p>
              </div>
            </li>
          </ol>

          <!-- Everything recorded against this part of the order, newest last. -->
          <div v-if="history.length">
            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ txt.history }}</h3>
            <ul class="space-y-2">
              <li v-for="log in history" :key="log.id" class="rounded-lg border border-border bg-background/40 p-2.5 text-xs">
                <div class="flex flex-wrap items-center gap-2">
                  <span :class="['inline-flex items-center rounded-md border px-1.5 py-0.5 font-medium', logActionClass[log.action] || 'border-border bg-muted/50 text-muted-foreground']">{{ logActionLabel(t, log.action) }}</span>
                  <span class="text-muted-foreground">{{ log.admin?.name || (t.order?.log_system || 'System / storefront') }}</span>
                  <span class="ms-auto tabular-nums text-muted-foreground" dir="ltr">{{ formatDateTime(log.created_at) }}</span>
                </div>
                <p v-if="log.change" class="mt-1.5 flex flex-wrap items-center gap-1.5">
                  <span v-if="log.change.from" class="rounded bg-red-500/10 px-1 py-0.5 text-red-400 line-through">{{ log.change.from }}</span>
                  <span v-if="log.change.from" class="text-muted-foreground">→</span>
                  <span class="rounded bg-emerald-500/10 px-1 py-0.5 text-emerald-400">{{ log.change.to }}</span>
                </p>
              </li>
            </ul>
          </div>
          <p v-else-if="!steps.length" class="text-xs text-muted-foreground">{{ txt.no_history }}</p>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { usePage } from "@inertiajs/vue3";
import { computed } from "vue";
import {
  deliveryStatusLabel,
  formatDateTime,
  logActionClass,
  logActionLabel,
  orderStatusLabel,
  paymentStatusLabel,
  paymentTypeClass,
  paymentTypeLabel,
  statusClass,
  timeAgo,
} from "../orderDisplay.js";

const props = defineProps({
  order: { type: Object, required: true },
  /** Which tag was pressed: payment | delivery | order | type | source — or null when closed. */
  kind: { type: String, default: null },
});
defineEmits(["close"]);

const page = usePage();
const locale = computed(() => page.props.locale || "ar");
const t = computed(() => page.props.translations?.admin || {});
const ar = computed(() => locale.value === "ar");

/** Short UI words, in the admin's language. */
const txt = computed(() => (ar.value
  ? { now: "الآن", by: "بواسطة", not_yet: "لم تتم بعد", history: "السجل", no_history: "لا يوجد سجل مسجّل لهذا الجزء.", unknown: "غير معروف" }
  : { now: "Now", by: "by", not_yet: "Not reached yet", history: "History", no_history: "Nothing has been recorded for this yet.", unknown: "Unknown" }));

/* What each value MEANS, for whoever is reading the order and is not sure. */
const DESCRIPTIONS = {
  payment: {
    pending: ["The money has not been confirmed yet. For a wallet transfer an admin must check the receipt against the wallet; for Paymob it waits for the gateway's signed callback; cash on delivery is collected by the courier.", "لم يتم تأكيد المبلغ بعد. في التحويل بالمحفظة يجب أن يراجع المشرف الإيصال مع المحفظة، وفي Paymob ننتظر ردّ البوابة الموقّع، أما الدفع عند الاستلام فيحصّله المندوب."],
    accepted: ["The payment is confirmed — by the gateway's signed callback, or by an admin after checking the wallet.", "تم تأكيد الدفع — من البوابة بردّ موقّع، أو من المشرف بعد مراجعة المحفظة."],
    rejected: ["The payment was refused (declined card, or a receipt an admin did not believe). The order is not paid.", "تم رفض الدفع (بطاقة مرفوضة أو إيصال لم يصدّقه المشرف). الطلب غير مدفوع."],
    canceled: ["The payment was canceled before it completed.", "أُلغي الدفع قبل اكتماله."],
    refunded: ["The money was returned to the buyer. Paymob orders only become refunded once the gateway confirms it.", "أُعيد المبلغ للمشتري. طلبات Paymob لا تصبح مستردة إلا بعد تأكيد البوابة."],
  },
  delivery: {
    pending: ["The order has been placed and nothing has been handed to a courier yet.", "تم تسجيل الطلب ولم يُسلَّم لأي مندوب بعد."],
    processing: ["The order is being prepared or has been booked with the courier (ABS) and awaits collection.", "الطلب قيد التجهيز أو تم حجزه لدى شركة الشحن وينتظر الاستلام."],
    "on-delivery": ["The courier has the parcel and is on the way to the buyer. This status comes from the courier's callback.", "المندوب استلم الشحنة وهو في الطريق للمشتري. هذه الحالة تأتي من شركة الشحن."],
    completed: ["The parcel reached the buyer.", "وصلت الشحنة إلى المشتري."],
  },
  order: {
    pending: ["The order is still open: payment and/or delivery are not finished.", "الطلب ما زال مفتوحًا: الدفع أو التوصيل لم يكتمل."],
    success: ["The order completed successfully.", "اكتمل الطلب بنجاح."],
    failed: ["The order did not go through (for example a payment that failed).", "لم يكتمل الطلب (مثلًا فشل الدفع)."],
  },
  type: {
    cod: ["Cash on delivery: the courier collects the total (and the COD fee) from the buyer at the door.", "الدفع عند الاستلام: يحصّل المندوب الإجمالي (ورسوم الدفع عند الاستلام) من المشتري."],
    "transfer-wallet": ["Wallet transfer (Vodafone Cash / Instapay): the buyer sends receipts, and an admin confirms them against the wallet. A receipt is a claim, never proof by itself.", "تحويل بالمحفظة (فودافون كاش / إنستاباي): يرسل المشتري الإيصالات ويؤكدها المشرف مع المحفظة. الإيصال ادّعاء وليس إثباتًا."],
    paymob: ["Online card payment through Paymob. Card numbers never touch our servers; the gateway's signed callback confirms the payment.", "دفع إلكتروني بالبطاقة عبر Paymob. أرقام البطاقات لا تمر بخوادمنا، ويؤكد الدفع ردّ البوابة الموقّع."],
  },
};
const SOURCE_DESCRIPTION = ["Where the order came from. “storefront” is the Deilar website; other values name the app or channel that placed it.", "مصدر الطلب. “storefront” هو موقع ديلار؛ وغيره يدل على التطبيق أو القناة التي أنشأت الطلب."];

const pick = (pair) => (ar.value ? pair[1] : pair[0]);

const FIELD = { payment: "payment_status", delivery: "delivery_status", order: "order_status" };
const LABEL = { payment: paymentStatusLabel, delivery: deliveryStatusLabel, order: orderStatusLabel };
const CLASSES = { payment: statusClass.payment, delivery: statusClass.delivery, order: statusClass.order };
const CURRENT = computed(() => ({
  payment: props.order.payment_status?.value,
  delivery: props.order.delivery_status?.value,
  order: props.order.order_status?.value,
}));

/* The happy path of each status, and the side-exits that replace its last step. */
const PATHS = {
  payment: { main: ["pending", "accepted"], exits: ["rejected", "canceled", "refunded"] },
  delivery: { main: ["pending", "processing", "on-delivery", "completed"], exits: [] },
  order: { main: ["pending", "success"], exits: ["failed"] },
};

const info = computed(() => {
  const kind = props.kind;
  const groups = {
    payment: t.value.order?.payment_status || (ar.value ? "حالة الدفع" : "Payment status"),
    delivery: t.value.order?.delivery_status || (ar.value ? "حالة التوصيل" : "Delivery status"),
    order: t.value.order?.order_status || (ar.value ? "حالة الطلب" : "Order status"),
    type: t.value.order?.payment_type || (ar.value ? "طريقة الدفع" : "Payment method"),
    source: t.value.order?.source || (ar.value ? "المصدر" : "Source"),
  };

  if (kind === "type") {
    const value = props.order.payment_type?.value;
    return { group: groups.type, label: paymentTypeLabel(t.value, props.order.payment_type), badgeClass: paymentTypeClass[value], description: pick(DESCRIPTIONS.type[value] || ["", ""]) };
  }
  if (kind === "source") {
    return { group: groups.source, label: props.order.source || "—", badgeClass: "border-border bg-muted/50 text-muted-foreground", description: pick(SOURCE_DESCRIPTION) };
  }

  const value = CURRENT.value[kind];
  return {
    group: groups[kind],
    label: LABEL[kind]?.(t.value, props.order[FIELD[kind]]),
    badgeClass: CLASSES[kind]?.[value],
    description: pick(DESCRIPTIONS[kind]?.[value] || ["", ""]),
  };
});

/** The audit trail oldest-first, so a step's time is the moment the status was written. */
const logsAsc = computed(() => [...(props.order.logs || [])].sort((a, b) => a.id - b.id));

/** Which audit actions belong to which tag. */
const RELATED = {
  payment: ["created", "payment_status_changed", "payment_initiated", "payment_callback", "payment_checked", "payment_accepted", "payment_rejected", "payment_refunded", "refund_failed", "receipt_emailed", "payment_type_changed", "canceled"],
  delivery: ["created", "delivery_status_changed", "shipped"],
  order: ["created", "order_status_changed", "canceled"],
  type: ["created", "payment_type_changed", "payment_initiated", "payment_accepted", "payment_rejected", "payment_refunded"],
  source: ["created"],
};

const changeOf = (log, field) => {
  if (!field) return null;
  const to = log.new_values?.[field];
  if (to === undefined || to === null) return null;
  const from = log.old_values?.[field];
  const pretty = (value) => (value === undefined || value === null ? null : (LABEL[props.kind]?.(t.value, String(value)) || String(value)));
  return { from: pretty(from), to: pretty(to) };
};

const history = computed(() => logsAsc.value
  .filter((log) => (RELATED[props.kind] || []).includes(log.action))
  .map((log) => ({ ...log, change: changeOf(log, FIELD[props.kind]) })));

const steps = computed(() => {
  const kind = props.kind;
  const path = PATHS[kind];
  if (!path) return [];

  const current = CURRENT.value[kind];
  const field = FIELD[kind];
  const exit = path.exits.includes(current) ? current : null;
  const values = exit
    ? [...(current === "refunded" ? ["pending", "accepted"] : ["pending"]), exit]
    : path.main;
  const currentIndex = values.indexOf(current);

  /* When, and by whom, each status was written — the last time it was set. */
  const reached = {};
  for (const log of logsAsc.value) {
    const value = log.new_values?.[field];
    if (typeof value === "string") {
      reached[value] = { at: log.created_at, by: log.admin?.name || null };
    }
  }

  return values.map((value, index) => {
    const state = index < currentIndex ? "done" : index === currentIndex ? "current" : "upcoming";
    const stamp = reached[value] || (index === 0 ? { at: props.order.created_at, by: null } : null);
    return {
      value,
      label: LABEL[kind](t.value, value),
      state: exit && index === currentIndex ? "exit" : state,
      at: state === "upcoming" ? null : stamp?.at || null,
      by: state === "upcoming" ? null : stamp?.by || null,
    };
  });
});

const dotClass = (step) => ({
  done: "border-emerald-500 bg-emerald-500 text-white",
  current: "border-primary bg-primary/10 text-primary ring-4 ring-primary/20",
  exit: "border-red-500 bg-red-500/10 text-red-500 ring-4 ring-red-500/20",
  upcoming: "border-border bg-card text-muted-foreground",
}[step.state]);
</script>
