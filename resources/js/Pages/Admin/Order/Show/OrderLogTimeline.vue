<template>
  <div class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
    <div class="p-3 border-b border-border flex flex-wrap items-center justify-between gap-2">
      <div class="flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-golden-yellow">
          <path d="M12 8v4l3 3"></path>
          <path d="M3.05 11a9 9 0 1 1 .5 4"></path><path d="M3 21v-6h6"></path>
        </svg>
        <h2 class="text-lg font-semibold">{{ t.order?.activity_log || 'Activity Log' }}</h2>
        <span class="text-xs text-muted-foreground">{{ logs.length }}</span>
      </div>

      <!-- Views outnumber changes, so the trail opens on the changes and the
           reads are one click away rather than pushed off the bottom. -->
      <div class="flex items-center gap-1 rounded-md border border-border p-0.5">
        <button
          v-for="option in filterOptions"
          :key="option.value"
          type="button"
          @click="filter = option.value"
          :class="[
            'px-2 py-1 text-xs rounded transition-colors cursor-pointer',
            filter === option.value ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted',
          ]"
        >
          {{ option.label }}
        </button>
      </div>
    </div>

    <div v-if="visibleLogs.length" class="p-3">
      <ol class="relative space-y-3 ps-5 border-s border-border">
        <li v-for="log in visibleLogs" :key="log.id" class="relative">
          <span
            class="absolute -start-[26px] top-1.5 flex h-3 w-3 items-center justify-center rounded-full border-2 border-card"
            :class="dotClass(log.action)"
          ></span>

          <div class="rounded-lg border border-border bg-background/40 p-2.5 space-y-1.5">
            <div class="flex flex-wrap items-center gap-2">
              <span :class="['inline-flex items-center rounded-md border px-1.5 py-0.5 text-xs font-medium capitalize', logActionClass[log.action] || 'border-border bg-muted/50 text-muted-foreground']">
                {{ logActionLabel(t, log.action) }}
              </span>
              <span class="text-xs text-muted-foreground">
                {{ log.admin?.name || (t.order?.log_system || 'System / storefront') }}
              </span>
              <span class="text-xs text-muted-foreground tabular-nums ms-auto">{{ log.created_at }}</span>
            </div>

            <!-- What actually moved, field by field. Reads carry no values at
                 all, which is why nothing is rendered for them. -->
            <div v-if="changeRows(log).length" class="space-y-1">
              <div
                v-for="row in changeRows(log)"
                :key="`${log.id}-${row.field}`"
                class="flex flex-wrap items-baseline gap-1.5 text-xs"
              >
                <span class="font-medium text-muted-foreground">{{ fieldLabel(row.field) }}</span>
                <span v-if="row.from !== null" class="rounded bg-red-500/10 px-1 py-0.5 text-red-400 line-through break-all">{{ row.from }}</span>
                <svg v-if="row.from !== null" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-muted-foreground shrink-0">
                  <path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path>
                </svg>
                <span class="rounded bg-emerald-500/10 px-1 py-0.5 text-emerald-400 break-all">{{ row.to }}</span>
              </div>
            </div>

            <p v-if="log.ip_address" class="text-[11px] text-muted-foreground" dir="ltr">
              {{ log.ip_address }}
            </p>
          </div>
        </li>
      </ol>
    </div>

    <div v-else class="p-6 text-center text-sm text-muted-foreground">
      {{ t.order?.no_logs || 'Nothing has been recorded against this order yet.' }}
    </div>
  </div>
</template>

<script setup>
import { usePage } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import { logActionClass, logActionLabel } from "../orderDisplay.js";

const props = defineProps({
  logs: {
    type: Array,
    default: () => [],
  },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

/** Reads: logged, but never what an admin came to this panel to find. */
const VISIT_ACTIONS = ['viewed', 'edit_viewed'];

const filter = ref('changes');

const filterOptions = computed(() => [
  { value: 'changes', label: t.value.order?.log_changes || 'Changes' },
  { value: 'visits', label: t.value.order?.log_visits || 'Visits' },
  { value: 'all', label: t.value.common?.all || 'All' },
]);

const visibleLogs = computed(() => {
  if (filter.value === 'all') return props.logs;
  const isVisit = (log) => VISIT_ACTIONS.includes(log.action);
  return props.logs.filter(log => (filter.value === 'visits' ? isVisit(log) : !isVisit(log)));
});

const dotClass = (action) => ({
  created: 'bg-emerald-500',
  updated: 'bg-blue-500',
  deleted: 'bg-red-500',
  payment_status_changed: 'bg-amber-500',
  delivery_status_changed: 'bg-violet-500',
  order_status_changed: 'bg-emerald-500',
  products_changed: 'bg-sky-500',
  canceled: 'bg-zinc-500',
}[action] || 'bg-muted-foreground/50');

const fieldLabel = (field) => t.value.order?.[field] || field.replace(/_/g, ' ');

/**
 * The old/new pair rendered as one row per field that moved.
 *
 * `changed_fields` is only filled when both sides were recorded, so a log with
 * new values and no old ones (an order created, a receipt attached) falls back
 * to listing what it does have. Objects and arrays are stringified rather than
 * dropped: a line-level change is still worth showing, even compactly.
 */
const changeRows = (log) => {
  const oldValues = log.old_values || {};
  const newValues = log.new_values || {};
  const fields = log.changed_fields?.length
    ? log.changed_fields
    : Object.keys(newValues);

  return fields
    .map(field => ({
      field,
      from: field in oldValues ? render(oldValues[field]) : null,
      to: render(newValues[field]),
    }))
    .filter(row => row.to !== null || row.from !== null);
};

const render = (value) => {
  if (value === null || value === undefined) return null;
  if (typeof value === 'boolean') return value ? 'yes' : 'no';
  if (Array.isArray(value)) return value.length ? `${value.length} item(s)` : '—';
  if (typeof value === 'object') return JSON.stringify(value);
  return String(value) === '' ? '—' : String(value);
};
</script>
