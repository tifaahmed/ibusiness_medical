<template>
  <button
    type="button"
    class="inline-flex items-center cursor-pointer justify-center gap-1.5 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium border bg-background hover:bg-muted h-8 sm:h-9 px-2 sm:px-3 md:px-4 py-2"
    :title="t.facility?.seo_bulk_hint || 'Generate the missing meta title / description / keywords across all facilities'"
    @click="openDialog"
  >
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M12 3v3m0 12v3M5.6 5.6l2.1 2.1m8.6 8.6 2.1 2.1M3 12h3m12 0h3M5.6 18.4l2.1-2.1m8.6-8.6 2.1-2.1"></path>
    </svg>
    <span class="hidden sm:inline">{{ t.facility?.seo_bulk || 'Fill SEO with AI' }}</span>
    <span class="sm:hidden">{{ t.facility?.seo_bulk_short || 'SEO' }}</span>
  </button>

  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-[110] flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
      role="dialog"
      aria-modal="true"
      @click.self="closeDialog"
    >
      <div class="w-full max-w-lg overflow-hidden rounded-xl border border-border bg-card text-card-foreground shadow-xl">
        <div class="flex items-start gap-3 border-b border-border p-4">
          <div class="min-w-0">
            <h2 class="text-base font-semibold">{{ t.facility?.seo_bulk_title || 'Fill facility SEO with AI' }}</h2>
            <p class="text-xs text-muted-foreground">
              {{ t.facility?.seo_bulk_description || 'AI writes bilingual meta title / description / keywords for facilities that are missing them. Review each facility afterwards.' }}
            </p>
          </div>
          <button
            type="button"
            class="ml-auto rounded-md p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
            :title="t.common?.close_esc || 'Close (Esc)'"
            @click="closeDialog"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 6 6 18"></path><path d="m6 6 12 12"></path>
            </svg>
          </button>
        </div>

        <div class="max-h-[60vh] overflow-y-auto p-4 space-y-4">
          <!-- Options -->
          <div v-if="phase === 'idle'" class="space-y-3">
            <label class="flex items-start gap-2 text-sm">
              <input type="checkbox" v-model="doSeo" class="mt-0.5" />
              <span>
                {{ t.facility?.seo_bulk_do_seo || 'Generate missing SEO copy' }}
                <span class="block text-[11px] text-muted-foreground">{{ t.facility?.seo_bulk_do_seo_hint || 'Only facilities where meta title or description is completely empty.' }}</span>
              </span>
            </label>
            <label class="flex items-start gap-2 text-sm">
              <input type="checkbox" v-model="doOg" class="mt-0.5" />
              <span>
                {{ t.facility?.seo_bulk_do_og || 'Copy the cover image into an empty share image' }}
                <span class="block text-[11px] text-muted-foreground">{{ t.facility?.seo_bulk_do_og_hint || 'Facilities with a cover image but no Open Graph image.' }}</span>
              </span>
            </label>
            <label class="flex items-start gap-2 text-sm">
              <input type="checkbox" v-model="overwrite" class="mt-0.5" />
              <span>
                {{ t.facility?.seo_bulk_overwrite || 'Regenerate SEO for every facility' }}
                <span class="block text-[11px] text-muted-foreground">{{ t.facility?.seo_bulk_overwrite_hint || 'Overwrites existing values. Off = only fill blanks.' }}</span>
              </span>
            </label>
          </div>

          <!-- Progress -->
          <div v-else class="space-y-3">
            <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
              <div class="h-full rounded-full bg-primary transition-all" :style="{ width: progressPct + '%' }"></div>
            </div>
            <p class="text-xs text-muted-foreground">
              {{ processed }} / {{ totalUnits }} {{ t.common?.done || 'done' }}
              <span v-if="waitNotice" class="text-amber-500"> · {{ waitNotice }}</span>
              <span v-else-if="phase === 'running'"> · {{ t.common?.working || 'working…' }}</span>
              <span v-else-if="phase === 'done'"> · {{ t.common?.finished || 'finished' }}</span>
            </p>

            <ul class="divide-y divide-border rounded-md border border-border text-xs">
              <li v-for="row in log" :key="row.slug" class="flex items-center justify-between gap-2 p-2">
                <span class="truncate">{{ row.slug }}</span>
                <span class="flex shrink-0 items-center gap-2">
                  <span v-if="row.seo !== 'skip'" :class="badgeClass(row.seo)">{{ t.facility?.seo_bulk_short || 'SEO' }} {{ stateLabel(row.seo) }}</span>
                  <button
                    v-if="row.seo === 'error' && row.seo_message"
                    type="button"
                    class="rounded-full text-muted-foreground hover:bg-muted hover:text-foreground"
                    :title="t.common?.view_full_error || 'View full error'"
                    @click="openErrorDetail(row.slug, row.seo_message)"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path>
                    </svg>
                  </button>
                  <span v-if="row.og !== 'skip'" :class="badgeClass(row.og)">{{ t.facility?.seo_bulk_badge_image || 'image' }} {{ stateLabel(row.og) }}</span>
                  <button
                    v-if="row.og === 'error' && row.og_message"
                    type="button"
                    class="rounded-full text-muted-foreground hover:bg-muted hover:text-foreground"
                    :title="t.common?.view_full_error || 'View full error'"
                    @click="openErrorDetail(row.slug, row.og_message)"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path>
                    </svg>
                  </button>
                </span>
              </li>
            </ul>
            <p v-if="errorMessage" class="text-xs text-destructive">{{ errorMessage }}</p>
          </div>
        </div>

        <div class="flex justify-end gap-2 border-t border-border p-3">
          <button
            type="button"
            class="inline-flex h-9 items-center justify-center rounded-md border border-border bg-background px-4 text-sm font-medium transition hover:bg-muted"
            @click="closeDialog"
          >
            {{ phase === 'done' ? (t.common?.close || 'Close') : (t.common?.cancel || 'Cancel') }}
          </button>
          <button
            v-if="phase === 'idle'"
            type="button"
            :disabled="!doSeo && !doOg"
            class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground shadow-xs transition hover:bg-primary/90 disabled:opacity-50 disabled:pointer-events-none"
            @click="start"
          >
            {{ t.common?.start || 'Start' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- Separate popup, above the sweep dialog, just showing one row's full
       error reason — the badge alone has no room for the whole message. -->
  <Teleport to="body">
    <div
      v-if="errorDetail"
      class="fixed inset-0 z-[120] flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
      role="dialog"
      aria-modal="true"
      @click.self="closeErrorDetail"
    >
      <div class="w-full max-w-md overflow-hidden rounded-xl border border-border bg-card text-card-foreground shadow-xl">
        <div class="flex items-start gap-3 border-b border-border p-4">
          <div class="min-w-0">
            <h2 class="text-base font-semibold truncate">{{ errorDetail.slug }}</h2>
            <p class="text-xs text-muted-foreground">{{ t.common?.error_reason || 'Error reason' }}</p>
          </div>
          <button
            type="button"
            class="ml-auto rounded-md p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
            :title="t.common?.close_esc || 'Close (Esc)'"
            @click="closeErrorDetail"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 6 6 18"></path><path d="m6 6 12 12"></path>
            </svg>
          </button>
        </div>
        <div class="max-h-[50vh] overflow-y-auto p-4">
          <p class="whitespace-pre-wrap break-words text-sm text-destructive">{{ errorDetail.message }}</p>
        </div>
        <div class="flex justify-end border-t border-border p-3">
          <button
            type="button"
            class="inline-flex h-9 items-center justify-center rounded-md border border-border bg-background px-4 text-sm font-medium transition hover:bg-muted"
            @click="closeErrorDetail"
          >
            {{ t.common?.close || 'Close' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, ref } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import { useNotification } from "@/composables/useNotification";

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const open = ref(false);
const phase = ref("idle"); // idle | running | done
const doSeo = ref(true);
const doOg = ref(true);
const overwrite = ref(false);

const seoSlugs = ref([]);
const ogSlugs = ref([]);
const chunk = ref(3);
const processed = ref(0);
const totalUnits = ref(0);
const log = ref([]);
const errorMessage = ref("");
const waitNotice = ref("");
const errorDetail = ref(null);
let cancelled = false;

const openErrorDetail = (slug, message) => {
  errorDetail.value = { slug, message };
};

const closeErrorDetail = () => {
  errorDetail.value = null;
};

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

// The provider's free tier caps requests per minute. When the server reports
// the quota is spent, pause briefly (with a visible countdown) and retry the
// same slice rather than burning through the rest of the list with errors. If
// the window has not cleared yet the retry 429s again and we simply wait again.
const RATE_LIMIT_WAIT_SECONDS = 10;

const waitForRateLimit = async () => {
  for (let left = RATE_LIMIT_WAIT_SECONDS; left > 0 && !cancelled; left -= 1) {
    waitNotice.value = (t.value.common?.ai_rate_limit_wait || 'AI rate limit reached — resuming in :seconds s').replace(':seconds', left);
    await sleep(1000);
  }
  waitNotice.value = "";
};

const progressPct = computed(() => (totalUnits.value ? Math.round((processed.value / totalUnits.value) * 100) : 0));

const stateLabel = (state) => t.value.facility?.[`seo_bulk_state_${state}`] || state;

const badgeClass = (state) => {
  if (state === "ok") return "text-emerald-500";
  if (state === "error") return "text-destructive";
  return "text-muted-foreground";
};

const openDialog = () => {
  resetState();
  open.value = true;
};

const closeDialog = () => {
  cancelled = true;
  open.value = false;
  if (phase.value === "done") {
    router.reload({ only: ["facilities"] });
  }
};

const resetState = () => {
  phase.value = "idle";
  seoSlugs.value = [];
  ogSlugs.value = [];
  processed.value = 0;
  totalUnits.value = 0;
  log.value = [];
  errorMessage.value = "";
  waitNotice.value = "";
  errorDetail.value = null;
  cancelled = false;
};

const upsertLog = (result) => {
  const existing = log.value.find((r) => r.slug === result.slug);
  if (existing) {
    Object.assign(existing, result);
  } else {
    log.value.unshift(result);
  }
};

const start = async () => {
  errorMessage.value = "";
  phase.value = "running";

  try {
    const { data } = await axios.post(route("admin.facility.seo.bulk.begin"), {
      mode: overwrite.value ? "all" : "missing",
    });

    chunk.value = data.chunk || 3;
    seoSlugs.value = doSeo.value ? [...data.seo_slugs] : [];
    ogSlugs.value = doOg.value ? [...data.og_slugs] : [];

    // Units of work = SEO jobs + image jobs (a facility needing both counts twice).
    totalUnits.value = seoSlugs.value.length + ogSlugs.value.length;

    if (totalUnits.value === 0) {
      phase.value = "done";
      useNotification().success(t.value.facility?.seo_bulk_nothing || "Nothing to do — every facility already has what you selected.");
      return;
    }

    await runQueue();

    if (!cancelled) {
      phase.value = "done";
      useNotification().success(t.value.facility?.seo_bulk_done || "SEO sweep finished.");
    }
  } catch (error) {
    errorMessage.value = error?.response?.data?.message || t.value.common?.sweep_start_failed || "The sweep failed to start. Please try again.";
    phase.value = "idle";
  }
};

const runQueue = async () => {
  const seoSet = new Set(seoSlugs.value);
  const ogSet = new Set(ogSlugs.value);
  const allSlugs = [...new Set([...seoSlugs.value, ...ogSlugs.value])];

  let i = 0;
  let firstBatch = true;
  while (i < allSlugs.length) {
    if (cancelled) return;
    if (!firstBatch) {
      await sleep(1000); // one second pause between batches to ease AI rate limits
      if (cancelled) return;
    }
    firstBatch = false;

    const batch = allSlugs.slice(i, i + chunk.value);
    const batchSeo = batch.filter((s) => seoSet.has(s));
    const batchOg = batch.filter((s) => ogSet.has(s));

    let data;
    try {
      ({ data } = await axios.post(route("admin.facility.seo.bulk.step"), {
        slugs: batch,
        do_seo: batchSeo.length > 0,
        do_og: batchOg.length > 0,
        mode: overwrite.value ? "all" : "missing",
      }));
    } catch (error) {
      errorMessage.value = error?.response?.data?.message || t.value.common?.batch_failed || "A batch failed — stopped early.";
      return;
    }

    if (data.rate_limited) {
      await waitForRateLimit();
      firstBatch = true;
      continue;
    }

    (data.results || []).forEach((result) => {
      upsertLog(result);
      if (seoSet.has(result.slug)) processed.value += 1;
      if (ogSet.has(result.slug)) processed.value += 1;
    });
    i += chunk.value;
  }
};
</script>
