<!--
  The order as ABS will receive it, shown before a courier is booked.

  This is not a confirmation step dressed up as one. Two things about our data
  genuinely cannot be sent unchecked:

    * `customer_governorate` / `customer_city` are free Arabic text the buyer
      typed; ABS routes on numeric ids. The server guesses, this dialog shows
      the guess beside what the buyer actually wrote, and the submit is refused
      until both ids are set.
    * the COD figure is money a courier will collect at the customer's door.

  So the destination and the cash are the two things given the most room, the
  raw payload is one click away rather than hidden, and the dialog stays open
  on a failure so a wrong city can be fixed without losing everything else.
-->
<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-[100] flex items-start justify-center overflow-y-auto bg-black/60 p-4 backdrop-blur-sm sm:items-center"
      role="dialog"
      aria-modal="true"
      :aria-label="t.order?.ship_title || 'Ship with ABS'"
      @click.self="close"
    >
      <div class="my-auto w-full max-w-3xl rounded-xl border border-border bg-card text-card-foreground shadow-lg">
        <!-- Header -->
        <div class="flex items-center gap-2 border-b border-border p-3 sm:p-4">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 text-golden-yellow">
            <path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"></path>
            <path d="M15 18H9"></path>
            <path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.62l-3.48-4.35A1 1 0 0 0 17.52 8H14"></path>
            <circle cx="17" cy="18" r="2"></circle>
            <circle cx="7" cy="18" r="2"></circle>
          </svg>
          <div class="min-w-0">
            <h2 class="truncate text-sm font-semibold sm:text-base">
              {{ t.order?.ship_title || 'Ship with ABS' }}
            </h2>
            <p class="truncate font-mono text-xs text-muted-foreground">{{ orderCode }}</p>
          </div>
          <button
            type="button"
            @click="close"
            class="ms-auto inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground cursor-pointer"
            :title="t.common?.close || 'Close (Esc)'"
            :aria-label="t.common?.close || 'Close'"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 6 6 18"></path><path d="m6 6 12 12"></path>
            </svg>
          </button>
        </div>

        <!-- Loading the preview -->
        <div v-if="loading" class="flex items-center justify-center gap-2 p-10 text-sm text-muted-foreground">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="animate-spin">
            <path d="M21 12a9 9 0 1 1-6.22-8.56"></path>
          </svg>
          {{ t.order?.ship_loading || 'Reading the ABS address lists…' }}
        </div>

        <!-- The preview could not even be built -->
        <div v-else-if="loadError" class="p-4 sm:p-6">
          <div class="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive">
            {{ loadError }}
          </div>
        </div>

        <div v-else-if="preview" class="max-h-[70vh] space-y-4 overflow-y-auto p-3 sm:p-4">
          <!-- What the admin should look at twice. Never blocking on its own:
               the submit is gated by the two ids, not by these. -->
          <ul v-if="warnings.length" class="space-y-1.5 rounded-md border border-amber-500/40 bg-amber-500/10 p-3 text-xs text-amber-700 dark:text-amber-400">
            <li v-for="warning in warnings" :key="warning" class="flex items-start gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 shrink-0">
                <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path>
                <path d="M12 9v4"></path><path d="M12 17h.01"></path>
              </svg>
              <span>{{ warningText(warning) }}</span>
            </li>
          </ul>

          <!-- 1. Destination — the reason this dialog exists -->
          <section class="rounded-lg border border-border">
            <header class="border-b border-border px-3 py-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
              {{ t.order?.ship_destination || 'Destination (ABS)' }}
            </header>
            <div class="grid grid-cols-1 gap-3 p-3 sm:grid-cols-2">
              <div>
                <label class="mb-1 block text-xs font-medium" :for="'ship-governorate'">
                  {{ t.order?.governorate || 'Governorate' }}
                  <span class="text-destructive">*</span>
                </label>
                <SearchableSelect
                  id="ship-governorate"
                  v-model="form.governorate_id"
                  :options="governorateOptions"
                  :placeholder="t.order?.ship_pick_governorate || 'Choose a governorate…'"
                  @change="onGovernorateChange"
                />
                <p class="mt-1 text-xs" :class="preview.destination.governorate.matched ? 'text-muted-foreground' : 'text-amber-600 dark:text-amber-400'">
                  {{ t.order?.ship_order_says || 'Order says' }}:
                  <span class="font-medium">{{ preview.destination.governorate.stored || '—' }}</span>
                </p>
              </div>

              <div>
                <label class="mb-1 block text-xs font-medium" :for="'ship-city'">
                  {{ t.order?.city || 'City' }}
                  <span class="text-destructive">*</span>
                </label>
                <SearchableSelect
                  id="ship-city"
                  v-model="form.city_id"
                  :options="cityOptions"
                  :disabled="!form.governorate_id || citiesLoading"
                  :placeholder="citiesLoading
                    ? (t.order?.ship_loading_cities || 'Loading cities…')
                    : (t.order?.ship_pick_city || 'Choose a city…')"
                />
                <p class="mt-1 text-xs" :class="preview.destination.city.matched ? 'text-muted-foreground' : 'text-amber-600 dark:text-amber-400'">
                  {{ t.order?.ship_order_says || 'Order says' }}:
                  <span class="font-medium">{{ preview.destination.city.stored || '—' }}</span>
                </p>
              </div>
            </div>
          </section>

          <!-- 2. Recipient — read-only: this is the order, edited on the edit page -->
          <section class="rounded-lg border border-border">
            <header class="border-b border-border px-3 py-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
              {{ t.order?.ship_recipient || 'Recipient' }}
            </header>
            <dl class="grid grid-cols-1 gap-x-4 gap-y-2 p-3 text-sm sm:grid-cols-2">
              <div>
                <dt class="text-xs text-muted-foreground">{{ t.order?.customer || 'Customer' }}</dt>
                <dd class="font-medium">{{ preview.order.customer_full_name || '—' }}</dd>
              </div>
              <div>
                <dt class="text-xs text-muted-foreground">{{ t.order?.phone || 'Phone' }}</dt>
                <dd class="font-medium" dir="ltr">
                  {{ preview.order.normalized_phone || '—' }}
                  <!-- Both spellings, so a normalisation that mangled the
                       number is visible rather than silent. -->
                  <span
                    v-if="preview.order.normalized_phone && preview.order.customer_phone !== preview.order.normalized_phone"
                    class="ms-1 text-xs text-muted-foreground"
                  >({{ preview.order.customer_phone }})</span>
                </dd>
              </div>
              <div class="sm:col-span-2">
                <dt class="text-xs text-muted-foreground">{{ t.order?.address || 'Address' }}</dt>
                <dd class="font-medium">{{ addressLine || '—' }}</dd>
              </div>
            </dl>
          </section>

          <!-- 3. Parcel and money — the COD is the number that costs real money -->
          <section class="rounded-lg border border-border">
            <header class="border-b border-border px-3 py-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
              {{ t.order?.ship_parcel || 'Parcel & collection' }}
            </header>
            <div class="space-y-3 p-3">
              <div
                class="flex flex-wrap items-center justify-between gap-2 rounded-md p-3"
                :class="Number(form.cash) > 0 ? 'bg-golden-yellow/10 border border-golden-yellow/40' : 'bg-muted/50 border border-border'"
              >
                <div>
                  <label class="block text-xs font-medium" for="ship-cash">
                    {{ t.order?.ship_cod || 'Cash to collect on delivery' }}
                  </label>
                  <p class="text-xs text-muted-foreground">
                    {{ preview.order.payment_type_label }} —
                    {{ t.order?.total_amount || 'Total' }} {{ preview.order.total_amount }},
                    {{ t.order?.paid || 'Paid' }} {{ preview.order.total_paid }}
                  </p>
                </div>
                <input
                  id="ship-cash"
                  v-model="form.cash"
                  type="number"
                  step="0.01"
                  min="0"
                  dir="ltr"
                  class="h-9 w-36 rounded-md border border-border bg-background px-2 text-end text-base font-semibold tabular-nums"
                />
              </div>

              <div>
                <label class="mb-1 block text-xs font-medium" for="ship-contents">
                  {{ t.order?.ship_contents || 'Contents (on the manifest)' }}
                </label>
                <input
                  id="ship-contents"
                  v-model="form.contents"
                  type="text"
                  maxlength="500"
                  class="h-9 w-full rounded-md border border-border bg-background px-2 text-sm"
                />
              </div>

              <div>
                <label class="mb-1 block text-xs font-medium" for="ship-instructions">
                  {{ t.order?.ship_instructions || 'Instructions for the courier' }}
                </label>
                <input
                  id="ship-instructions"
                  v-model="form.special_instructions"
                  type="text"
                  maxlength="500"
                  class="h-9 w-full rounded-md border border-border bg-background px-2 text-sm"
                />
              </div>
            </div>
          </section>

          <!-- 4. The literal body. Collapsed, but present: nothing reaches the
               courier that the admin could not have read on the way past. -->
          <details class="rounded-lg border border-border">
            <summary class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
              {{ t.order?.ship_raw_payload || 'Exact request sent to ABS' }}
            </summary>
            <pre dir="ltr" class="max-h-64 overflow-auto border-t border-border bg-muted/40 p-3 text-xs leading-relaxed">{{ payloadJson }}</pre>
          </details>

          <!-- What ABS refused, if anything. The dialog stays open on it. -->
          <div v-if="submitError" class="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive">
            {{ submitError }}
          </div>
        </div>

        <!-- Footer -->
        <div class="flex flex-wrap items-center gap-2 border-t border-border p-3 sm:p-4">
          <p v-if="preview && !canSubmit && !submitting" class="text-xs text-amber-600 dark:text-amber-400">
            {{ t.order?.ship_pick_both || 'Choose a governorate and a city before shipping.' }}
          </p>
          <div class="ms-auto flex items-center gap-2">
            <button
              type="button"
              @click="close"
              :disabled="submitting"
              class="inline-flex h-9 items-center justify-center rounded-md border border-border bg-background px-3 text-sm font-medium transition-colors hover:bg-muted disabled:pointer-events-none disabled:opacity-50 cursor-pointer"
            >
              {{ t.common?.cancel || 'Cancel' }}
            </button>
            <button
              type="button"
              @click="submit"
              :disabled="!canSubmit || submitting"
              class="btn-golden inline-flex h-9 items-center justify-center gap-2 rounded-md bg-primary px-4 text-sm font-semibold text-primary-foreground shadow-xs transition-all hover:bg-primary/90 disabled:pointer-events-none disabled:opacity-50 cursor-pointer"
            >
              <svg v-if="submitting" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="animate-spin">
                <path d="M21 12a9 9 0 1 1-6.22-8.56"></path>
              </svg>
              <svg v-else xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 6 9 17l-5-5"></path>
              </svg>
              {{ submitting
                ? (t.order?.ship_submitting || 'Booking…')
                : (t.order?.ship_confirm || 'Confirm & ship') }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { router, usePage } from "@inertiajs/vue3";
import { computed, onBeforeUnmount, ref, watch } from "vue";
import SearchableSelect from "@/Components/ui/SearchableSelect.vue";

const props = defineProps({
  open: { type: Boolean, default: false },
  orderCode: { type: String, required: true },
});

const emit = defineEmits(["close"]);

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const loading = ref(false);
const loadError = ref(null);
const submitting = ref(false);
const submitError = ref(null);
const citiesLoading = ref(false);

/** The server's answer: order, destination lists, payload and warnings. */
const preview = ref(null);

/* The city list on screen. It starts as whatever the preview matched, and is
   replaced when the admin picks a different governorate — ABS scopes cities by
   governorate, so it cannot be filtered client-side. */
const cities = ref([]);

const form = ref({
  governorate_id: null,
  city_id: null,
  cash: null,
  contents: "",
  special_instructions: "",
});

const governorateOptions = computed(() =>
  (preview.value?.destination?.governorate?.options || []).map((row) => ({
    value: row.id,
    label: row.label,
  })),
);

const cityOptions = computed(() =>
  cities.value.map((row) => ({ value: row.id, label: row.label })),
);

const warnings = computed(() => preview.value?.warnings || []);

const addressLine = computed(() => {
  const order = preview.value?.order;
  if (!order) return "";

  return [
    order.customer_street,
    order.customer_building_number && `${t.value.order?.building || 'Building'} ${order.customer_building_number}`,
    order.customer_floor_number && `${t.value.order?.floor || 'Floor'} ${order.customer_floor_number}`,
    order.customer_apartment_number && `${t.value.order?.apartment || 'Apt'} ${order.customer_apartment_number}`,
    order.customer_special_mark,
  ].filter(Boolean).join("، ") || order.customer_address;
});

/* The body as it now stands — the server's payload with whatever the admin has
   since changed folded in, so the JSON on screen keeps matching the form above
   it rather than showing the state it was opened in. */
const payloadJson = computed(() => {
  if (!preview.value) return "";

  const body = JSON.parse(JSON.stringify(preview.value.payload));
  body.shipment = {
    ...body.shipment,
    governorateId: form.value.governorate_id ?? undefined,
    cityId: form.value.city_id ?? undefined,
    cash: Number(form.value.cash ?? 0),
    contents: form.value.contents || undefined,
    specialInstructions: form.value.special_instructions || undefined,
  };

  return JSON.stringify(body, null, 2);
});

/* Both ids, or nothing ships. This is the one hard gate — the server enforces
   the same rule in ShipOrderRequest, because a dialog is not a permission. */
const canSubmit = computed(() =>
  Boolean(form.value.governorate_id && form.value.city_id) && !loading.value && !loadError.value,
);

function warningText(key) {
  const messages = {
    no_governorate_match: t.value.order?.ship_warn_governorate
      || "The order's governorate did not match any ABS governorate — choose it yourself.",
    no_city_match: t.value.order?.ship_warn_city
      || "The order's city did not match any ABS city — choose it yourself.",
    no_phone: t.value.order?.ship_warn_phone
      || 'This order has no usable phone number. ABS needs one to deliver.',
    no_address: t.value.order?.ship_warn_address
      || 'This order has no street or address text.',
    no_cod: t.value.order?.ship_warn_cod
      || 'Nothing has been paid and nothing will be collected on delivery.',
    no_pickup_location: t.value.order?.ship_warn_pickup
      || 'No ABS pickup location is configured, so no collection will be booked with this shipment.',
  };

  return messages[key] || key;
}

/**
 * Ship the failure to the client-error endpoint so a courier booking nobody
 * can make shows up in /admin/client-error-logs rather than only in the
 * admin's face. Mirrors `reportClientError` in OrderShowView.
 */
function reportClientError(message, step) {
  try {
    fetch('/api/v1/client-errors', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        message,
        route: window.location.pathname,
        fatal: false,
        extra: { order_code: props.orderCode, step },
      }),
    }).catch(() => {});
  } catch {
    // Reporting is never worth a second error.
  }
}

async function load() {
  loading.value = true;
  loadError.value = null;
  submitError.value = null;
  preview.value = null;

  try {
    const response = await fetch(route('admin.order.ship.preview', props.orderCode), {
      headers: { Accept: 'application/json' },
    });
    const body = await response.json();

    if (!response.ok) {
      loadError.value = body?.message || `HTTP ${response.status}`;
      reportClientError(loadError.value, 'ship_preview');
      return;
    }

    preview.value = body;
    cities.value = body.destination.city.options || [];
    form.value = {
      governorate_id: body.destination.governorate.selected_id,
      city_id: body.destination.city.selected_id,
      cash: body.order.cod_amount,
      contents: body.payload.shipment?.contents || '',
      special_instructions: '',
    };
  } catch (error) {
    loadError.value = error?.message || 'The shipping preview could not be loaded.';
    reportClientError(loadError.value, 'ship_preview');
  } finally {
    loading.value = false;
  }
}

/* A different governorate means a different set of cities, and the city that
   was chosen is not in it — so it is cleared rather than carried over, which
   would submit a city belonging to the previous governorate. */
async function onGovernorateChange() {
  form.value.city_id = null;
  cities.value = [];

  if (!form.value.governorate_id) return;

  citiesLoading.value = true;

  try {
    const url = `${route('admin.order.ship.cities')}?governorate_id=${form.value.governorate_id}`;
    const response = await fetch(url, { headers: { Accept: 'application/json' } });
    const body = await response.json();

    if (!response.ok) {
      submitError.value = body?.message || `HTTP ${response.status}`;
      reportClientError(submitError.value, 'ship_cities');
      return;
    }

    cities.value = body.options || [];
  } catch (error) {
    submitError.value = error?.message || 'The ABS city list could not be loaded.';
    reportClientError(submitError.value, 'ship_cities');
  } finally {
    citiesLoading.value = false;
  }
}

function submit() {
  if (!canSubmit.value || submitting.value) return;

  submitting.value = true;
  submitError.value = null;

  router.post(route('admin.order.ship', props.orderCode), {
    governorate_id: form.value.governorate_id,
    city_id: form.value.city_id,
    cash: form.value.cash === '' || form.value.cash === null ? null : Number(form.value.cash),
    contents: form.value.contents || null,
    special_instructions: form.value.special_instructions || null,
  }, {
    preserveScroll: true,
    onError: (errors) => {
      /* Kept open on the error, with everything the admin typed still in it:
         the usual failure here is one wrong city, and reloading the page to
         report it would throw away the rest of the form. */
      submitError.value = Object.values(errors).flat().join(' ');
      reportClientError(submitError.value, 'ship_submit');
    },
    onSuccess: () => emit('close'),
    onFinish: () => {
      submitting.value = false;
    },
  });
}

function close() {
  if (submitting.value) return;
  emit('close');
}

function onKeydown(event) {
  if (event.key === 'Escape') close();
}

watch(() => props.open, (open) => {
  if (open) {
    load();
    window.addEventListener('keydown', onKeydown);
  } else {
    window.removeEventListener('keydown', onKeydown);
  }
});

onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>
