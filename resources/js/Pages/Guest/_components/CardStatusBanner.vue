<template>
  <!--
    What a scan of the card says first. An expired card (a trial that ran out, or
    a paid year that lapsed) gets a big red sign and the three ways forward: buy
    it now with Paymob, call customer services, or message them. A trial still
    running gets a calm reminder of how long is left.
  -->
  <div
    v-if="status?.expired"
    role="alert"
    class="mb-3 sm:mb-6 rounded-2xl border-2 border-red-600 bg-red-600 p-4 sm:p-6 text-center text-white shadow-xl"
  >
    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mx-auto mb-2">
      <circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line>
    </svg>
    <p class="text-2xl sm:text-4xl font-black tracking-wide">{{ text.expired }}</p>
    <p class="mt-1 text-sm sm:text-base opacity-95">
      {{ status.is_trial ? text.trial_ended : text.lapsed }}<span v-if="status.expires_on" dir="ltr"> · {{ status.expires_on }}</span>
    </p>

    <div class="mt-4 flex flex-col sm:flex-row flex-wrap items-stretch justify-center gap-2">
      <button
        v-if="status.can_pay_online"
        type="button"
        :disabled="paying"
        @click="buy"
        class="inline-flex items-center justify-center gap-2 rounded-lg bg-white px-5 py-3 text-base font-bold text-red-700 shadow hover:bg-red-50 disabled:opacity-60 cursor-pointer"
      >
        {{ paying ? '…' : text.buy }}<span v-if="status.price > 0" dir="ltr"> — {{ status.price }} EGP</span>
      </button>
      <a v-if="phone" :href="`tel:${phone}`" class="inline-flex items-center justify-center gap-2 rounded-lg border-2 border-white px-5 py-3 text-base font-bold hover:bg-white/10">
        📞 {{ text.call }} <span dir="ltr">{{ status.contact.phone || phone }}</span>
      </a>
      <a v-if="whatsapp" :href="whatsappLink" target="_blank" rel="noopener" class="inline-flex items-center justify-center gap-2 rounded-lg border-2 border-white px-5 py-3 text-base font-bold hover:bg-white/10">
        💬 {{ text.message }}
      </a>
    </div>
    <p v-if="error" class="mt-3 rounded bg-white/15 px-3 py-2 text-sm">{{ error }}</p>
  </div>

  <div
    v-else-if="status?.state === 'trial'"
    class="mb-3 sm:mb-6 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-amber-500/50 bg-amber-500/15 px-4 py-3 text-amber-900"
  >
    <span class="text-sm font-semibold">
      {{ text.trial }} — {{ status.days_left }} {{ text.days_left }}<span v-if="status.expires_on" dir="ltr"> ({{ status.expires_on }})</span>
    </span>
    <a v-if="phone" :href="`tel:${phone}`" class="text-xs font-bold underline">{{ text.call }} {{ status.contact.phone || phone }}</a>
  </div>
</template>

<script setup>
import { computed, ref } from "vue";
import { router } from "@inertiajs/vue3";

const props = defineProps({
  status: { type: Object, default: null },
  slug: { type: String, required: true },
  locale: { type: String, default: "ar" },
});

const dictionary = {
  ar: {
    expired: "الكارت منتهي",
    trial_ended: "انتهت الفترة التجريبية",
    lapsed: "انتهى اشتراك هذا الكارت",
    buy: "اشترك الآن (دفع إلكتروني)",
    call: "اتصل بخدمة العملاء",
    message: "راسل خدمة العملاء",
    trial: "كارت تجريبي",
    days_left: "يوم متبقٍ",
    wa_text: "مرحباً، أريد تجديد كارت ديلار رقم",
    fail: "تعذر فتح صفحة الدفع. حاول مرة أخرى.",
  },
  en: {
    expired: "CARD EXPIRED",
    trial_ended: "The free trial has ended",
    lapsed: "This card's subscription has ended",
    buy: "Subscribe now (pay online)",
    call: "Call customer service",
    message: "Message customer service",
    trial: "Trial card",
    days_left: "days left",
    wa_text: "Hello, I'd like to renew Deilar card number",
    fail: "The payment page could not be opened. Please try again.",
  },
};

const text = computed(() => dictionary[props.locale] || dictionary.en);
const phone = computed(() => props.status?.contact?.phone_international || props.status?.contact?.phone || "");
const whatsapp = computed(() => (props.status?.contact?.whatsapp || "").replace(/\D/g, ""));
const whatsappLink = computed(() => `https://wa.me/${whatsapp.value}?text=${encodeURIComponent(`${text.value.wa_text} ${props.slug}`)}`);

const paying = ref(false);
const error = ref("");

const buy = () => {
  paying.value = true;
  error.value = "";
  router.post(route("guest.membership.subscribe", props.slug), {}, {
    onError: (errors) => {
      error.value = errors.subscribe || text.value.fail;
      reportClientError("Card subscribe failed", { errors });
    },
    onFinish: () => { paying.value = false; },
  });
};

/* AGENTS.md: front-end failures go to the client-error endpoint. */
function reportClientError(message, extra) {
  try {
    fetch("/api/v1/client-errors", {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({ message, route: window.location.pathname, fatal: false, extra: { slug: props.slug, ...extra } }),
    }).catch(() => {});
  } catch {
    // Reporting is never worth a second error.
  }
}
</script>
