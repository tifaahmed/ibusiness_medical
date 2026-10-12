<template>
  <AdminUserLayout>
    <div class="w-full max-w-full space-y-2 sm:space-y-3 md:space-y-4 p-2 sm:p-3 md:p-4 lg:p-6">
      <!-- Header -->
      <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border py-2 sm:py-3 md:py-4 shadow-sm w-full">
        <div class="flex flex-row items-center justify-between py-2 px-3 sm:px-4 md:px-6 gap-2 sm:gap-4">
          <div class="title-golden flex items-center gap-2 min-w-0 font-semibold">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon flex-shrink-0">
              <circle cx="12" cy="12" r="9"></circle>
              <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
            <span class="text-sm sm:text-base truncate min-w-0">
              Change Log &mdash; {{ subject.name || `User #${subject.id}` }}
              <span v-if="subject.email" class="text-muted-foreground font-normal text-xs">({{ subject.email }})</span>
              <span v-if="!subject.exists" class="ml-1 text-xs text-red-300">deleted</span>
            </span>
          </div>
          <Link
            :href="route('admin.admin-users.index')"
            class="inline-flex items-center cursor-pointer justify-center gap-1.5 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input h-8 sm:h-9 px-3"
          >
            Back
          </Link>
        </div>
        <div class="px-3 sm:px-4 md:px-6 pt-2 w-full sm:w-60">
          <label class="text-xs font-medium mb-1 block">Action</label>
          <select v-model="action" @change="applyFilter" class="h-8 sm:h-9 w-full rounded-md border border-input bg-background px-2 text-xs">
            <option value="">All actions</option>
            <option v-for="(meta, key) in ACTIONS" :key="key" :value="key">{{ meta.label }}</option>
          </select>
        </div>
      </div>

      <!-- Entries -->
      <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
        <div v-if="logs?.data?.length > 0" class="divide-y divide-border">
          <div v-for="log in logs.data" :key="log.id" class="p-3 sm:p-4 space-y-2">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
              <span :class="['inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-xs font-medium border', meta(log.action).cls]">
                <span v-html="meta(log.action).icon"></span>{{ meta(log.action).label }}
              </span>
              <span class="text-sm text-foreground">
                by
                <span v-if="log.admin" class="font-medium">{{ log.admin.name }}</span>
                <span v-else class="italic text-muted-foreground">system / unknown</span>
                <span v-if="log.admin?.email" class="text-muted-foreground text-xs"> ({{ log.admin.email }})</span>
              </span>
              <span class="text-xs text-muted-foreground ml-auto">{{ log.created_at }}</span>
            </div>

            <ul v-if="log.changed_fields.length" class="text-xs space-y-1">
              <li v-for="f in log.changed_fields" :key="f" class="flex flex-wrap items-baseline gap-1.5">
                <span class="font-medium text-foreground">{{ f }}:</span>
                <template v-if="f === 'password'">
                  <span class="text-amber-300">changed</span>
                </template>
                <template v-else>
                  <span v-if="log.old_values && f in log.old_values" class="rounded bg-red-500/10 text-red-300 px-1.5 py-0.5 line-through">{{ show(log.old_values[f]) }}</span>
                  <span v-if="log.new_values && f in log.new_values" class="rounded bg-emerald-500/10 text-emerald-300 px-1.5 py-0.5">{{ show(log.new_values[f]) }}</span>
                </template>
              </li>
            </ul>
            <ul v-else-if="log.new_values || log.old_values" class="text-xs space-y-1 text-muted-foreground">
              <li v-for="(v, k) in (log.new_values || log.old_values)" :key="k">
                <span class="font-medium text-foreground">{{ k }}:</span> {{ show(v) }}
              </li>
            </ul>

            <div v-if="log.ip_address" class="text-[11px] text-muted-foreground truncate" :title="log.user_agent">
              {{ log.ip_address }}<span v-if="log.user_agent"> · {{ log.user_agent }}</span>
            </div>
          </div>

          <div class="px-3 sm:px-4 py-3">
            <Pagination v-if="logs?.links?.length > 0" :links="logs.links" />
          </div>
        </div>

        <div v-else class="p-12 text-center space-y-3">
          <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-golden-yellow/10 border border-golden-yellow/20">
            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-golden-yellow">
              <circle cx="12" cy="12" r="9"></circle>
              <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
          </div>
          <h3 class="text-xl font-bold text-foreground">No changes recorded</h3>
          <p class="text-muted-foreground text-sm">Changes made from now on will be listed here with who made them.</p>
        </div>
      </div>
    </div>
  </AdminUserLayout>
</template>

<script setup>
import { ref } from "vue";
import { Link, router } from "@inertiajs/vue3";
import AdminUserLayout from "./AdminUserLayout.vue";
import Pagination from "@/Pages/_components/Pagination.vue";

const props = defineProps({
  subject: { type: Object, required: true },
  logs: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
});

const svg = (d) => `<svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="${d}"/></svg>`;
const ACTIONS = {
  created: { label: "Created", cls: "bg-emerald-500/15 text-emerald-300 border-emerald-500/30", icon: svg("M12 5v14M5 12h14") },
  updated: { label: "Updated", cls: "bg-blue-500/15 text-blue-300 border-blue-500/30", icon: svg("M4 20h4L18.5 9.5a2.1 2.1 0 00-3-3L5 17v3z") },
  blocked: { label: "Blocked", cls: "bg-red-500/15 text-red-300 border-red-500/40", icon: svg("M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636") },
  unblocked: { label: "Unblocked", cls: "bg-teal-500/15 text-teal-300 border-teal-500/30", icon: svg("M5 13l4 4L19 7") },
  deleted: { label: "Deleted", cls: "bg-red-500/15 text-red-300 border-red-500/30", icon: svg("M6 7h12M9 7V4h6v3m-8 0l1 13h8l1-13") },
};
const meta = (a) => ACTIONS[a] || { label: a, cls: "bg-muted text-muted-foreground border-border", icon: "" };

const action = ref(props.filters.action || "");

function applyFilter() {
  router.get(route("admin.admin-users.logs", props.subject.id), action.value ? { action: action.value } : {}, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
}

function show(v) {
  if (Array.isArray(v)) return v.length ? v.join(", ") : "—";
  if (typeof v === "boolean") return v ? "yes" : "no";
  if (v === null || v === undefined || v === "") return "—";
  return String(v);
}
</script>
