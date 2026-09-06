<template>
  <button
    type="button"
    class="inline-flex items-center cursor-pointer justify-center gap-1.5 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium border bg-background hover:bg-muted h-8 sm:h-9 px-2 sm:px-3 md:px-4 py-2"
    :title="t.facility?.location_bulk_hint || 'Find the map coordinates and Google Maps link for every branch from its address'"
    @click="openDialog"
  >
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path>
      <circle cx="12" cy="10" r="3"></circle>
    </svg>
    <span class="hidden sm:inline">{{ t.facility?.location_bulk || 'Fill locations with AI' }}</span>
    <span class="sm:hidden">{{ t.facility?.location_short || 'Map' }}</span>
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
            <h2 class="text-base font-semibold">{{ t.facility?.location_bulk_title || 'Fill branch locations with AI' }}</h2>
            <p class="text-xs text-muted-foreground">
              {{ t.facility?.location_bulk_description || 'AI reads each branch address, works out its latitude and longitude, and builds the Google Maps link from them. Check the pins afterwards — the coordinates are read from the written address, not surveyed.' }}
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
              <input type="checkbox" v-model="overwrite" class="mt-0.5" />
              <span>
                {{ t.facility?.location_bulk_overwrite || 'Redo every branch' }}
                <span class="block text-[11px] text-muted-foreground">
                  {{ t.facility?.location_bulk_overwrite_hint || 'Overwrites coordinates that are already set. Off = only branches missing coordinates or a map link.' }}
                </span>
              </span>
            </label>
          </div>

          <!-- Progress -->
          <div v-else class="space-y-3">
            <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
              <div class="h-full rounded-full bg-primary transition-all" :style="{ width: progressPct + '%' }"></div>
            </div>
            <p class="text-xs text-muted-foreground">
              {{ processed }} / {{ total }} {{ t.common?.done || 'done' }}
              <span v-if="waitNotice" class="text-amber-500"> · {{ waitNotice }}</span>
              <span v-else-if="phase === 'running'"> · {{ t.facility?.location_working || 'working…' }}</span>
              <span v-else-if="phase === 'done'"> · {{ t.facility?.location_finished || 'finished' }}</span>
            </p>
            <p v-if="skippedNoAddress" class="text-[11px] text-amber-500">
              {{ skippedNoAddress }} {{ t.facility?.location_no_address || 'branch(es) skipped — no address, city or governorate to search with.' }}
            </p>

            <ul class="divide-y divide-border rounded-md border border-border text-xs">
              <li v-for="row in log" :key="row.id" class="flex items-start justify-between gap-2 p-2">
                <span class="min-w-0">
                  <span class="block truncate">{{ row.label }}</span>
                  <a
                    v-if="row.google_location_url"
                    :href="row.google_location_url"
                    target="_blank"
                    rel="noopener"
                    class="block truncate text-[11px] text-primary hover:underline"
                  >
                    {{ row.matched_place || `${row.latitude}, ${row.longitude}` }}
                  </a>
                  <span v-else-if="row.message" class="block truncate text-[11px] text-muted-foreground">{{ row.message }}</span>
                </span>
                <span class="flex shrink-0 items-center gap-2">
                  <span v-if="row.confidence && row.state === 'ok'" :class="confidenceClass(row.confidence)">{{ row.confidence }}</span>
                  <span :class="badgeClass(row.state)">{{ stateLabel(row.state) }}</span>
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
            class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground shadow-xs transition hover:bg-primary/90 disabled:opacity-50 disabled:pointer-events-none"
            @click="start"
          >
            {{ t.common?.start || 'Start' }}
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
const overwrite = ref(false);

const queue = ref([]);
const chunk = ref(3);
const processed = ref(0);
const total = ref(0);
const skippedNoAddress = ref(0);
const log = ref([]);
const errorMessage = ref("");
const waitNotice = ref("");
let cancelled = false;

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

// The provider's free tier caps requests per minute. When the server reports
// the quota is spent, pause briefly (with a visible countdown) and retry the
// same slice rather than burning through the rest of the list with errors.
const RATE_LIMIT_WAIT_SECONDS = 10;

const waitForRateLimit = async () => {
  for (let left = RATE_LIMIT_WAIT_SECONDS; left > 0 && !cancelled; left -= 1) {
    waitNotice.value = `AI rate limit reached — resuming in ${left}s`;
    await sleep(1000);
  }
  waitNotice.value = "";
};

const progressPct = computed(() => (total.value ? Math.round((processed.value / total.value) * 100) : 0));

const badgeClass = (state) => {
  if (state === "ok") return "text-emerald-500";
  if (state === "error") return "text-destructive";
  if (state === "not_found") return "text-amber-500";
  return "text-muted-foreground";
};

const confidenceClass = (confidence) => {
  if (confidence === "high") return "text-emerald-500";
  if (confidence === "medium") return "text-amber-500";
  return "text-muted-foreground";
};

const stateLabel = (state) => {
  if (state === "ok") return t.value.facility?.location_state_ok || "located";
  if (state === "not_found") return t.value.facility?.location_state_not_found || "not found";
  if (state === "error") return t.value.facility?.location_state_error || "error";
  return t.value.facility?.location_state_skip || "skipped";
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
  queue.value = [];
  processed.value = 0;
  total.value = 0;
  skippedNoAddress.value = 0;
  log.value = [];
  errorMessage.value = "";
  waitNotice.value = "";
  cancelled = false;
};

const upsertLog = (result) => {
  const existing = log.value.find((row) => row.id === result.id);
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
    const { data } = await axios.post(route("admin.facility.location.bulk.begin"), {
      mode: overwrite.value ? "all" : "missing",
    });

    chunk.value = data.chunk || 3;
    queue.value = [...(data.branches || [])];
    total.value = queue.value.length;
    skippedNoAddress.value = data.skipped_no_address || 0;

    if (total.value === 0) {
      phase.value = "done";
      useNotification().success(
        t.value.facility?.location_nothing_to_do || "Nothing to do — every branch already has a location."
      );
      return;
    }

    await runQueue();

    if (!cancelled) {
      phase.value = "done";
      useNotification().success(t.value.facility?.location_sweep_done || "Location sweep finished.");
    }
  } catch (error) {
    errorMessage.value = error?.response?.data?.message || "The sweep failed to start. Please try again.";
    phase.value = "idle";
  }
};

const runQueue = async () => {
  let i = 0;
  let firstBatch = true;

  while (i < queue.value.length) {
    if (cancelled) return;
    if (!firstBatch) {
      await sleep(1000); // one second pause between batches to ease AI rate limits
      if (cancelled) return;
    }
    firstBatch = false;

    const batch = queue.value.slice(i, i + chunk.value);

    let data;
    try {
      ({ data } = await axios.post(route("admin.facility.location.bulk.step"), {
        ids: batch.map((row) => row.id),
        mode: overwrite.value ? "all" : "missing",
      }));
    } catch (error) {
      errorMessage.value = error?.response?.data?.message || "A batch failed — stopped early.";
      return;
    }

    if (data.rate_limited) {
      await waitForRateLimit();
      firstBatch = true;
      continue;
    }

    (data.results || []).forEach((result) => {
      upsertLog(result);
      processed.value += 1;
    });
    i += chunk.value;
  }
};
</script>
