<template>
  <button
    type="button"
    class="inline-flex items-center cursor-pointer justify-center gap-1.5 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium border bg-background hover:bg-muted h-8 sm:h-9 px-2 sm:px-3 md:px-4 py-2"
    :title="hint"
    @click="openDialog"
  >
    <!-- One icon per sweep: a building for the place it names, a pin for the
         point on the map. -->
    <svg v-if="icon === 'place'" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M3 21h18"></path><path d="M5 21V7l8-4v18"></path><path d="M19 21V11l-6-4"></path>
    </svg>
    <svg v-else-if="icon === 'rename'" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M4 7V4h16v3"></path><path d="M9 20h6"></path><path d="M12 4v16"></path>
    </svg>
    <svg v-else xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path>
      <circle cx="12" cy="10" r="3"></circle>
    </svg>
    <span class="hidden sm:inline">{{ label }}</span>
    <span class="sm:hidden">{{ shortLabel }}</span>
    <span v-if="pending" class="rounded bg-primary/20 px-1.5 py-0.5 text-[10px] font-semibold tabular-nums">{{ pending }}</span>
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
            <h2 class="text-base font-semibold">{{ title }}</h2>
            <p class="text-xs text-muted-foreground">{{ description }}</p>
          </div>
          <button
            type="button"
            class="ml-auto rounded-md p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
            :title="t.common?.close || 'Close (Esc)'"
            @click="closeDialog"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 6 6 18"></path><path d="m6 6 12 12"></path>
            </svg>
          </button>
        </div>

        <div class="max-h-[60vh] overflow-y-auto p-4 space-y-4">
          <div v-if="phase === 'idle'" class="space-y-3">
            <label class="flex items-start gap-2 text-sm">
              <input type="checkbox" v-model="overwrite" class="mt-0.5" />
              <span>
                {{ overwriteLabel || t.facility_branch?.sweep_overwrite || 'Redo every branch' }}
                <span class="block text-[11px] text-muted-foreground">{{ overwriteHint }}</span>
              </span>
            </label>
          </div>

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
              {{ skippedNoAddress }} {{ t.facility_branch?.sweep_no_address || 'branch(es) skipped — no address to read from.' }}
            </p>

            <ul class="divide-y divide-border rounded-md border border-border text-xs">
              <li v-for="row in log" :key="row.id" class="flex items-start justify-between gap-2 p-2">
                <span class="min-w-0">
                  <span class="block truncate">{{ row.label }}</span>
                  <a
                    v-if="row.url"
                    :href="row.url"
                    target="_blank"
                    rel="noopener"
                    class="block truncate text-[11px] text-primary hover:underline"
                  >{{ row.detail }}</a>
                  <span v-else-if="row.detail" class="block truncate text-[11px] text-muted-foreground">{{ row.detail }}</span>
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
            {{ t.facility_branch?.sweep_start || 'Start' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
/**
 * One sweep over the branch list, driven from the browser.
 *
 * `begin` asks the server for the work list, then `step` is called with a small
 * slice at a time until it is done — so no single request has to outlive a
 * shared-hosting timeout, and the progress bar means something.
 *
 * All three sweeps on this page (place, GPS, rename) are this component with
 * different routes and wording: they answer the same shape, so the row
 * rendering and the rate-limit handling are shared rather than written three
 * times. The rename sweep calls no AI, so its rate-limit path simply never runs.
 */
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useNotification } from '@/composables/useNotification';

const props = defineProps({
  // Route names for the two halves of the sweep.
  beginRoute: { type: String, required: true },
  stepRoute: { type: String, required: true },
  // 'place' or 'location' — picks the button's icon.
  icon: { type: String, default: 'place' },
  label: { type: String, required: true },
  shortLabel: { type: String, required: true },
  hint: { type: String, default: '' },
  title: { type: String, required: true },
  description: { type: String, default: '' },
  overwriteLabel: { type: String, default: '' },
  overwriteHint: { type: String, default: '' },
  /* What the checkbox means, in the server's words. The AI sweeps default to
     'missing' and opt into 'all'; the rename sweep is the other way round — its
     whole point is to restyle every branch, so its checkbox is the careful
     choice rather than the thorough one. */
  checkedMode: { type: String, default: 'all' },
  uncheckedMode: { type: String, default: 'missing' },
  nothingToDo: { type: String, default: 'Nothing to do.' },
  finished: { type: String, default: 'Sweep finished.' },
  // How many rows the list says are still missing this, shown on the button.
  pending: { type: Number, default: 0 },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const open = ref(false);
const phase = ref('idle'); // idle | running | done
const overwrite = ref(false);

const queue = ref([]);
const chunk = ref(3);
const processed = ref(0);
const total = ref(0);
const skippedNoAddress = ref(0);
const log = ref([]);
const errorMessage = ref('');
const waitNotice = ref('');
let cancelled = false;

const mode = computed(() => (overwrite.value ? props.checkedMode : props.uncheckedMode));

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

/* The provider's free tier caps requests per minute. When the server reports
   the quota is spent it hands back the slice unprocessed, so the sweep waits
   here — with a visible countdown — and sends the same slice again, rather than
   burning through the rest of the list turning every remaining row into an
   error. Nothing is lost and nothing is skipped. */
const RATE_LIMIT_WAIT_SECONDS = 10;

const waitForRateLimit = async () => {
  for (let left = RATE_LIMIT_WAIT_SECONDS; left > 0 && !cancelled; left -= 1) {
    waitNotice.value = `AI rate limit reached — resuming in ${left}s`;
    await sleep(1000);
  }
  waitNotice.value = '';
};

const progressPct = computed(() => (total.value ? Math.round((processed.value / total.value) * 100) : 0));

const badgeClass = (state) => {
  if (state === 'ok') return 'text-emerald-500';
  if (state === 'error') return 'text-destructive';
  if (state === 'not_found') return 'text-amber-500';
  return 'text-muted-foreground';
};

const confidenceClass = (confidence) => {
  if (confidence === 'high') return 'text-emerald-500';
  if (confidence === 'medium') return 'text-amber-500';
  return 'text-muted-foreground';
};

const stateLabel = (state) => {
  if (state === 'ok') return t.value.facility_branch?.sweep_state_ok || 'filled';
  if (state === 'not_found') return t.value.facility?.location_state_not_found || 'not found';
  if (state === 'error') return t.value.facility?.location_state_error || 'error';
  return t.value.facility?.location_state_skip || 'skipped';
};

const openDialog = () => {
  resetState();
  open.value = true;
};

const closeDialog = () => {
  cancelled = true;
  open.value = false;
  // Whatever was written is on the rows behind the dialog, so the list is
  // re-read rather than left showing the state from before the sweep.
  if (phase.value === 'done' || processed.value > 0) {
    router.reload({ only: ['facilityBranches', 'incompleteCounts'] });
  }
};

const resetState = () => {
  phase.value = 'idle';
  queue.value = [];
  processed.value = 0;
  total.value = 0;
  skippedNoAddress.value = 0;
  log.value = [];
  errorMessage.value = '';
  waitNotice.value = '';
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
  errorMessage.value = '';
  phase.value = 'running';

  try {
    const { data } = await axios.post(route(props.beginRoute), { mode: mode.value });

    chunk.value = data.chunk || 3;
    queue.value = [...(data.branches || [])];
    total.value = queue.value.length;
    skippedNoAddress.value = data.skipped_no_address || 0;

    if (total.value === 0) {
      phase.value = 'done';
      useNotification().success(props.nothingToDo);
      return;
    }

    await runQueue();

    if (!cancelled) {
      phase.value = 'done';
      useNotification().success(props.finished);
    }
  } catch (error) {
    errorMessage.value = error?.response?.data?.message || 'The sweep failed to start. Please try again.';
    phase.value = 'idle';
  }
};

const runQueue = async () => {
  let i = 0;
  let firstBatch = true;

  while (i < queue.value.length) {
    if (cancelled) return;
    if (!firstBatch) {
      await sleep(1000); // one second between batches to ease AI rate limits
      if (cancelled) return;
    }
    firstBatch = false;

    const batch = queue.value.slice(i, i + chunk.value);

    let data;
    try {
      ({ data } = await axios.post(route(props.stepRoute), {
        ids: batch.map((row) => row.id),
        mode: mode.value,
      }));
    } catch (error) {
      errorMessage.value = error?.response?.data?.message || 'A batch failed — stopped early.';
      return;
    }

    // The slice was not processed: wait out the quota and send it again, with
    // `firstBatch` reset so the retry is not also delayed a second.
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
