<template>
  <button
    type="button"
    class="inline-flex items-center cursor-pointer justify-center gap-1.5 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium border bg-background hover:bg-muted h-8 sm:h-9 px-2 sm:px-3 md:px-4 py-2"
    title="Translate / fix empty or Arabic English fields across all facilities"
    @click="openDialog"
  >
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M5 8h14M5 8a2 2 0 0 1 0-4h14a2 2 0 0 1 0 4M5 8v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8"></path>
    </svg>
    <span class="hidden sm:inline">Fix English with AI</span>
    <span class="sm:hidden">EN</span>
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
            <h2 class="text-base font-semibold">Fix English with AI</h2>
            <p class="text-xs text-muted-foreground">
              AI fills or repairs English names, descriptions and branch addresses that are empty,
              hold Arabic text, or just copy the Arabic. Arabic values are never changed. Applied immediately.
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

        <div class="max-h-[60vh] overflow-y-auto p-4 space-y-3">
          <p v-if="phase === 'idle'" class="text-sm text-muted-foreground">
            This scans every facility you can manage and fixes the ones with dirty English data.
          </p>

          <template v-else>
            <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
              <div class="h-full rounded-full bg-primary transition-all" :style="{ width: progressPct + '%' }"></div>
            </div>
            <p class="text-xs text-muted-foreground">
              {{ processed }} / {{ total }} facilities
              <span v-if="waitNotice" class="text-amber-500"> · {{ waitNotice }}</span>
              <span v-else-if="phase === 'running'"> · working…</span>
              <span v-else-if="phase === 'done'"> · finished · {{ totalApplied }} field(s) updated</span>
            </p>

            <ul class="divide-y divide-border rounded-md border border-border text-xs">
              <li v-for="row in log" :key="row.slug" class="flex items-center justify-between gap-2 p-2">
                <span class="truncate">{{ row.slug }}</span>
                <span class="shrink-0" :class="row.state === 'error' ? 'text-destructive' : 'text-emerald-500'">
                  {{ row.state === 'error' ? (row.message || 'error') : `${row.applied} fixed` }}
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
            {{ phase === 'done' ? 'Close' : 'Cancel' }}
          </button>
          <button
            v-if="phase === 'idle'"
            type="button"
            class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground shadow-xs transition hover:bg-primary/90"
            @click="start"
          >
            Scan &amp; fix
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
const slugs = ref([]);
const chunk = ref(3);
const processed = ref(0);
const total = ref(0);
const totalApplied = ref(0);
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
      useNotification().success("Every facility's English data is already clean.");
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
        errorMessage.value = error?.response?.data?.message || "A batch failed — stopped early.";
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
      useNotification().success(`Done — ${totalApplied.value} English field(s) updated.`);
    }
  } catch (error) {
    errorMessage.value = error?.response?.data?.message || "The scan failed to start. Please try again.";
    phase.value = "idle";
  }
};
</script>
