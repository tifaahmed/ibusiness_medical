<template>
  <button
    type="button"
    class="inline-flex items-center cursor-pointer justify-center gap-1.5 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium border bg-background hover:bg-muted h-8 sm:h-9 px-2 sm:px-3 md:px-4 py-2"
    :title="t.facility?.english_bulk_hint || 'Translate / fix empty or Arabic English fields across all facilities'"
    @click="openDialog"
  >
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M5 8h14M5 8a2 2 0 0 1 0-4h14a2 2 0 0 1 0 4M5 8v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8"></path>
    </svg>
    <span class="hidden sm:inline">{{ t.facility?.english_bulk || 'Fix English with AI' }}</span>
    <span class="sm:hidden">{{ t.facility?.english_bulk_short || 'EN' }}</span>
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
            <h2 class="text-base font-semibold">{{ t.facility?.english_bulk || 'Fix English with AI' }}</h2>
            <p class="text-xs text-muted-foreground">
              {{ t.facility?.english_bulk_description || 'AI fills or repairs English names, descriptions and branch addresses that are empty, hold Arabic text, or just copy the Arabic. Arabic values are never changed. Applied immediately.' }}
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

        <div class="max-h-[60vh] overflow-y-auto p-4 space-y-3">
          <p v-if="phase === 'idle'" class="text-sm text-muted-foreground">
            {{ t.facility?.english_bulk_intro || 'This scans every facility you can manage and fixes the ones with dirty English data.' }}
          </p>

          <template v-else>
            <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
              <div class="h-full rounded-full bg-primary transition-all" :style="{ width: progressPct + '%' }"></div>
            </div>
            <p class="text-xs text-muted-foreground">
              {{ (t.facility?.english_bulk_progress || ':processed / :total facilities').replace(':processed', processed).replace(':total', total) }}
              <span v-if="waitNotice" class="text-amber-500"> · {{ waitNotice }}</span>
              <span v-else-if="phase === 'running'"> · {{ t.common?.working || 'working…' }}</span>
              <span v-else-if="phase === 'done'"> · {{ (t.facility?.english_bulk_finished || 'finished · :count field(s) updated').replace(':count', totalApplied) }}</span>
            </p>

            <ul class="divide-y divide-border rounded-md border border-border text-xs">
              <li v-for="row in log" :key="row.slug" class="flex items-center justify-between gap-2 p-2">
                <span class="truncate">{{ row.slug }}</span>
                <span class="flex shrink-0 items-center gap-2">
                  <span :class="row.state === 'error' ? 'text-destructive' : 'text-emerald-500'">
                    {{ row.state === 'error' ? (t.common?.error || 'error') : (t.facility?.english_bulk_row_fixed || ':count fixed').replace(':count', row.applied) }}
                  </span>
                  <button
                    v-if="row.state === 'error' && row.message"
                    type="button"
                    class="rounded-full text-muted-foreground hover:bg-muted hover:text-foreground"
                    :title="t.common?.view_full_error || 'View full error'"
                    @click="openErrorDetail(row)"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path>
                    </svg>
                  </button>
                </span>
              </li>
            </ul>
            <p v-if="errorMessage" class="text-xs text-destructive">{{ errorMessage }}</p>
          </template>
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
            class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground shadow-xs transition hover:bg-primary/90"
            @click="start"
          >
            {{ t.facility?.english_bulk_start || 'Scan & fix' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- Separate popup, above the sweep dialog, just showing one row's full
       error reason instead of squeezing it next to the badge. -->
  <Teleport to="body">
    <div
      v-if="errorDetailRow"
      class="fixed inset-0 z-[120] flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
      role="dialog"
      aria-modal="true"
      @click.self="closeErrorDetail"
    >
      <div class="w-full max-w-md overflow-hidden rounded-xl border border-border bg-card text-card-foreground shadow-xl">
        <div class="flex items-start gap-3 border-b border-border p-4">
          <div class="min-w-0">
            <h2 class="text-base font-semibold truncate">{{ errorDetailRow.slug }}</h2>
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
          <p class="whitespace-pre-wrap break-words text-sm text-destructive">{{ errorDetailRow.message }}</p>
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
const slugs = ref([]);
const chunk = ref(3);
const processed = ref(0);
const total = ref(0);
const totalApplied = ref(0);
const log = ref([]);
const errorMessage = ref("");
const waitNotice = ref("");
const errorDetailRow = ref(null);
let cancelled = false;

const openErrorDetail = (row) => {
  errorDetailRow.value = row;
};

const closeErrorDetail = () => {
  errorDetailRow.value = null;
};

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

// Gemini's free tier allows ~15 requests/minute. When the server reports the
// quota is spent, pause briefly (with a visible countdown) and retry the same
// slice rather than burning through the rest of the list with errors. If the
// window has not cleared yet the retry 429s again and we simply wait again.
const RATE_LIMIT_WAIT_SECONDS = 10;

const waitForRateLimit = async () => {
  for (let left = RATE_LIMIT_WAIT_SECONDS; left > 0 && !cancelled; left -= 1) {
    waitNotice.value = (t.value.common?.ai_rate_limit_wait || 'AI rate limit reached — resuming in :seconds s').replace(':seconds', left);
    await sleep(1000);
  }
  waitNotice.value = "";
};

const progressPct = computed(() => (total.value ? Math.round((processed.value / total.value) * 100) : 0));

const openDialog = () => {
  reset();
  open.value = true;
};

const closeDialog = () => {
  cancelled = true;
  open.value = false;
  if (phase.value === "done" && totalApplied.value > 0) {
    router.reload({ only: ["facilities"] });
  }
};

const reset = () => {
  phase.value = "idle";
  slugs.value = [];
  processed.value = 0;
  total.value = 0;
  totalApplied.value = 0;
  log.value = [];
  errorMessage.value = "";
  waitNotice.value = "";
  errorDetailRow.value = null;
  cancelled = false;
};

const start = async () => {
  errorMessage.value = "";
  phase.value = "running";

  try {
    const { data } = await axios.post(route("admin.facility.english.bulk.begin"));
    chunk.value = data.chunk || 3;
    slugs.value = [...data.slugs];
    total.value = slugs.value.length;

    if (total.value === 0) {
      phase.value = "done";
      useNotification().success(t.value.facility?.english_bulk_clean || "Every facility's English data is already clean.");
      return;
    }

    let i = 0;
    let firstBatch = true;
    while (i < slugs.value.length) {
      if (cancelled) return;
      if (!firstBatch) {
        await sleep(1000); // one second pause between batches to ease AI rate limits
        if (cancelled) return;
      }
      firstBatch = false;
      const batch = slugs.value.slice(i, i + chunk.value);

      let res;
      try {
        res = await axios.post(route("admin.facility.english.bulk.step"), { slugs: batch });
      } catch (error) {
        errorMessage.value = error?.response?.data?.message || t.value.common?.batch_failed || "A batch failed — stopped early.";
        return;
      }

      if (res.data.rate_limited) {
        // Quota for this minute is spent — wait, then retry the same slice.
        await waitForRateLimit();
        firstBatch = true;
        continue;
      }

      (res.data.results || []).forEach((row) => {
        log.value.unshift(row);
        processed.value += 1;
        totalApplied.value += row.applied || 0;
      });
      i += chunk.value;
    }

    if (!cancelled) {
      phase.value = "done";
      useNotification().success((t.value.facility?.english_bulk_done || 'Done — :count English field(s) updated.').replace(':count', totalApplied.value));
    }
  } catch (error) {
    errorMessage.value = error?.response?.data?.message || t.value.facility?.english_bulk_start_failed || "The scan failed to start. Please try again.";
    phase.value = "idle";
  }
};
</script>
