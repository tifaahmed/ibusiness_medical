<template>
  <button
    type="button"
    class="inline-flex items-center cursor-pointer justify-center gap-1.5 sm:gap-2 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 sm:h-9 px-2 sm:px-3 md:px-4 py-2 flex-shrink-0"
    @click="openDialog"
  >
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M12 3v3m0 12v3M5.6 5.6l2.1 2.1m8.6 8.6 2.1 2.1M3 12h3m12 0h3M5.6 18.4l2.1-2.1m8.6-8.6 2.1-2.1"></path>
    </svg>
    <span class="hidden sm:inline">Fill SEO with AI</span>
    <span class="sm:hidden">SEO</span>
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
            <h2 class="text-base font-semibold">Fill product SEO with AI</h2>
            <p class="text-xs text-muted-foreground">
              AI writes bilingual meta title / description / keywords for products that are missing them.
              Review each product afterwards.
            </p>
          </div>
          <button
            type="button"
            class="ml-auto rounded-md p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
            title="Close (Esc)"
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
                Generate missing SEO copy
                <span class="block text-[11px] text-muted-foreground">Only products where meta title or description is completely empty.</span>
              </span>
            </label>
            <label class="flex items-start gap-2 text-sm">
              <input type="checkbox" v-model="doOg" class="mt-0.5" />
              <span>
                Copy the product image into an empty share image
                <span class="block text-[11px] text-muted-foreground">Products with a large image but no Open Graph image.</span>
              </span>
            </label>
            <label class="flex items-start gap-2 text-sm">
              <input type="checkbox" v-model="overwrite" class="mt-0.5" />
              <span>
                Regenerate SEO for every product
                <span class="block text-[11px] text-muted-foreground">Overwrites existing values. Off = only fill blanks.</span>
              </span>
            </label>
          </div>

          <!-- Progress -->
          <div v-else class="space-y-3">
            <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
              <div class="h-full rounded-full bg-primary transition-all" :style="{ width: progressPct + '%' }"></div>
            </div>
            <p class="text-xs text-muted-foreground">
              {{ processed }} / {{ totalUnits }} done
              <span v-if="waitNotice" class="text-amber-500"> · {{ waitNotice }}</span>
              <span v-else-if="phase === 'running'"> · working…</span>
              <span v-else-if="phase === 'done'"> · finished</span>
            </p>

            <ul class="divide-y divide-border rounded-md border border-border text-xs">
              <li v-for="row in log" :key="row.slug" class="flex items-center justify-between gap-2 p-2">
                <span class="truncate">{{ row.slug }}</span>
                <span class="flex shrink-0 gap-2">
                  <span v-if="row.seo !== 'skip'" :class="badgeClass(row.seo)">SEO {{ row.seo }}</span>
                  <span v-if="row.og !== 'skip'" :class="badgeClass(row.og)">image {{ row.og }}</span>
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
            {{ phase === 'done' ? 'Close' : 'Cancel' }}
          </button>
          <button
            v-if="phase === 'idle'"
            type="button"
            :disabled="!doSeo && !doOg"
            class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground shadow-xs transition hover:bg-primary/90 disabled:opacity-50 disabled:pointer-events-none"
            @click="start"
          >
            Start
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, ref } from "vue";
import { router } from "@inertiajs/vue3";
import { useNotification } from "@/composables/useNotification";

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
let cancelled = false;

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

// Gemini's free tier allows ~15 requests/minute. When the server reports the
// quota is spent, pause briefly (with a visible countdown) and retry the same
// slice rather than burning through the rest of the list with errors. If the
// window has not cleared yet the retry 429s again and we simply wait again.
const RATE_LIMIT_WAIT_SECONDS = 10;

const waitForRateLimit = async () => {
  for (let left = RATE_LIMIT_WAIT_SECONDS; left > 0 && !cancelled; left -= 1) {
    waitNotice.value = `AI rate limit reached — resuming in ${left}s`;
    await sleep(1000);
  }
  waitNotice.value = "";
};

const progressPct = computed(() => (totalUnits.value ? Math.round((processed.value / totalUnits.value) * 100) : 0));

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
    router.reload({ only: ["products"] });
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
    const { data } = await axios.post(route("admin.product.seo.bulk.begin"), {
      mode: overwrite.value ? "all" : "missing",
    });

    chunk.value = data.chunk || 3;
    seoSlugs.value = doSeo.value ? [...data.seo_slugs] : [];
    ogSlugs.value = doOg.value ? [...data.og_slugs] : [];

    // Units of work = SEO jobs + image jobs (a product needing both counts twice).
    totalUnits.value = seoSlugs.value.length + ogSlugs.value.length;

    if (totalUnits.value === 0) {
      phase.value = "done";
      useNotification().success("Nothing to do — every product already has what you selected.");
      return;
    }

    await runQueue();

    if (!cancelled) {
      phase.value = "done";
      useNotification().success("SEO sweep finished.");
    }
  } catch (error) {
    errorMessage.value = error?.response?.data?.message || "The sweep failed to start. Please try again.";
    phase.value = "idle";
  }
};

const runQueue = async () => {
  // Build one queue of {slug, do_seo, do_og} grouped so each step is <= chunk products.
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
      ({ data } = await axios.post(route("admin.product.seo.bulk.step"), {
        slugs: batch,
        do_seo: batchSeo.length > 0,
        do_og: batchOg.length > 0,
        mode: overwrite.value ? "all" : "missing",
      }));
    } catch (error) {
      errorMessage.value = error?.response?.data?.message || "A batch failed — stopped early.";
      return;
    }

    if (data.rate_limited) {
      // Quota for this minute is spent — wait, then retry the same slice.
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
