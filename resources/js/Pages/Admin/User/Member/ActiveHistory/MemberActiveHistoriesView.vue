<template>
  <MemberLayout>
    <div class="w-full max-w-full">
      <div class="space-y-2 sm:space-y-3 md:space-y-4 p-2 sm:p-3 md:p-4 lg:p-6 w-full max-w-full">
        <!-- Header -->
        <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border py-2 sm:py-3 md:py-4 shadow-sm w-full">
          <div class="flex flex-row items-center justify-between py-2 px-3 sm:px-4 md:px-6 gap-2 sm:gap-4">
            <div class="leading-none font-semibold min-w-0 flex-1">
              <div class="title-golden flex items-center gap-2 min-w-0">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon flex-shrink-0">
                  <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
                  <path d="M3 3v5h5"/>
                  <path d="M12 7v5l4 2"/>
                </svg>
                <span class="text-sm sm:text-base truncate min-w-0">
                  Active Status History &mdash; {{ member.name }}
                </span>
              </div>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
              <Link
                :href="route('admin.user.membership.list')"
                class="inline-flex items-center cursor-pointer justify-center gap-1.5 sm:gap-2 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 sm:h-9 px-2 sm:px-3 md:px-4 py-2"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 sm:h-4 sm:w-4">
                  <path d="m12 19-7-7 7-7"></path>
                  <path d="M19 12H5"></path>
                </svg>
                <span class="hidden sm:inline">Back to Members</span>
                <span class="sm:hidden">Back</span>
              </Link>
            </div>
          </div>
        </div>

        <!-- Histories Table -->
        <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
          <div v-if="histories?.data?.length > 0">
            <div class="overflow-x-auto">
              <table class="w-full caption-bottom text-xs sm:text-sm py-3 sm:py-4">
                <thead class="[&_tr]:border-b [&_tr]:border-border">
                  <tr class="border-b border-border transition-colors">
                    <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap w-44">When</th>
                    <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-center align-middle font-medium whitespace-nowrap">Previous Status</th>
                    <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-center align-middle font-medium whitespace-nowrap">New Status</th>
                    <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap">Changed By</th>
                  </tr>
                </thead>
                <tbody class="[&_tr:last-child]:border-0">
                  <tr v-for="history in histories.data" :key="history.id" class="border-b border-border transition-colors hover:bg-muted/50">
                    <td class="p-2 sm:p-3 align-middle whitespace-nowrap">
                      <div class="flex flex-col">
                        <span class="text-foreground">{{ formatDateTime(history.created_at) }}</span>
                        <span class="text-muted-foreground text-xs">{{ relativeTime(history.created_at) }}</span>
                      </div>
                    </td>
                    <td class="p-2 sm:p-3 align-middle text-center">
                      <span :class="statusBadgeClass(history.old_is_active)">
                        {{ history.old_is_active ? 'Active' : 'Inactive' }}
                      </span>
                    </td>
                    <td class="p-2 sm:p-3 align-middle text-center">
                      <span :class="statusBadgeClass(history.new_is_active)">
                        {{ history.new_is_active ? 'Active' : 'Inactive' }}
                      </span>
                      <svg v-if="history.old_is_active !== history.new_is_active" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline-block ml-1 text-amber-400">
                        <path d="M5 12h14"/>
                        <path d="m12 5 7 7-7 7"/>
                      </svg>
                    </td>
                    <td class="p-2 sm:p-3 align-middle">
                      <div v-if="history.changer" class="flex flex-col min-w-0">
                        <span class="font-medium text-foreground truncate">{{ history.changer.name }}</span>
                        <span class="text-muted-foreground text-xs truncate">{{ history.changer.email }}</span>
                      </div>
                      <span v-else class="text-muted-foreground italic text-xs">system / unknown</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="border-t border-border px-3 sm:px-4 md:px-6 py-2.5 sm:py-3">
              <div class="flex flex-row items-center justify-between gap-2 flex-wrap">
                <div class="text-xs sm:text-sm text-muted-foreground">
                  Showing {{ histories.meta?.from || 0 }} to {{ histories.meta?.to || 0 }} of {{ histories.meta?.total || 0 }}
                </div>
                <Pagination v-if="histories?.meta?.links?.length > 0" :links="histories.meta.links" />
              </div>
            </div>
          </div>

          <!-- Empty state -->
          <div v-else class="p-12 text-center">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-golden-yellow/10 border border-golden-yellow/20 mb-6">
              <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-golden-yellow">
                <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
                <path d="M3 3v5h5"/>
                <path d="M12 7v5l4 2"/>
              </svg>
            </div>
            <h3 class="text-xl font-bold mb-1 text-foreground">No history yet</h3>
            <p class="text-muted-foreground text-sm">
              The active status has never been changed for this member.
            </p>
          </div>
        </div>
      </div>
    </div>
  </MemberLayout>
</template>

<script setup>
import MemberLayout from "../MemberLayout.vue";
import Pagination from "@/Pages/_components/Pagination.vue";
import { Link } from "@inertiajs/vue3";

defineProps({
  member: { type: Object, required: true },
  histories: { type: Object, required: true },
});

const formatDateTime = (s) => {
  if (!s) return '-';
  return new Date(s).toLocaleString('en-US', {
    year: 'numeric', month: 'short', day: 'numeric',
    hour: '2-digit', minute: '2-digit',
  });
};

const relativeTime = (s) => {
  if (!s) return '';
  const diffMs = Date.now() - new Date(s).getTime();
  const sec = Math.floor(diffMs / 1000);
  if (sec < 60) return `${sec}s ago`;
  const min = Math.floor(sec / 60);
  if (min < 60) return `${min}m ago`;
  const hr = Math.floor(min / 60);
  if (hr < 24) return `${hr}h ago`;
  const days = Math.floor(hr / 24);
  if (days < 30) return `${days}d ago`;
  const months = Math.floor(days / 30);
  if (months < 12) return `${months}mo ago`;
  return `${Math.floor(months / 12)}y ago`;
};

const statusBadgeClass = (active) => active
  ? 'inline-flex items-center rounded-md px-2 py-1 text-xs font-semibold tracking-wide shadow-sm ring-1 bg-emerald-600 text-white ring-emerald-400/40'
  : 'inline-flex items-center rounded-md px-2 py-1 text-xs font-semibold tracking-wide shadow-sm ring-1 bg-rose-600 text-white ring-rose-400/40';
</script>
