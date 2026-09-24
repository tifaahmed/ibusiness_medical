<template>
  <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
    <div v-if="facilityBranches?.data?.length > 0">
      <div class="overflow-x-auto">
        <div data-slot="table-container" class="relative w-full py-3 sm:py-4">
          <table data-slot="table" class="w-full caption-bottom text-xs sm:text-sm">
            <thead data-slot="table-header" class="[&_tr]:border-b [&_tr]:border-border">
              <tr data-slot="table-row" class="border-b border-border">
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap min-w-[180px]">
                  {{ t.common?.name || 'Name' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap min-w-[160px] hidden md:table-cell">
                  {{ t.facility?.title || 'Facility' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap w-40 text-center hidden lg:table-cell">
                  {{ t.facility_branch?.deleted_at || 'Deleted at' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap w-40 text-center hidden lg:table-cell">
                  {{ t.facility_branch?.deleted_by || 'Deleted by' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap w-32 text-center">
                  {{ t.common?.actions || 'Actions' }}
                </th>
              </tr>
            </thead>
            <tbody data-slot="table-body" class="[&_tr:last-child]:border-0">
              <tr
                v-for="branch in facilityBranches.data"
                :key="branch.id"
                data-slot="table-row"
                class="border-b border-border transition-colors hover:bg-muted/50 opacity-75"
              >
                <td data-slot="table-cell" class="p-2 sm:p-3 align-middle">
                  <div class="min-w-0">
                    <span class="font-semibold text-sm text-foreground block break-words line-through decoration-muted-foreground/60">
                      {{ branchName(branch) }}
                    </span>
                    <span class="text-xs text-muted-foreground block mt-0.5">{{ branch.slug }}</span>
                  </div>
                </td>
                <td data-slot="table-cell" class="p-2 align-middle hidden md:table-cell">
                  <span class="text-sm text-foreground">{{ facilityName(branch) }}</span>
                </td>
                <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap text-center hidden lg:table-cell">
                  <span class="text-xs text-muted-foreground tabular-nums">{{ branch.deleted_at || '—' }}</span>
                </td>
                <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap text-center hidden lg:table-cell">
                  <span class="text-xs text-muted-foreground">{{ branch.deleted_by?.name || '—' }}</span>
                </td>
                <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap text-center">
                  <div class="inline-flex items-center gap-1">
                    <button
                      v-if="canWrite"
                      type="button"
                      @click="$emit('restore', branch)"
                      class="inline-flex items-center justify-center rounded-md border border-border bg-background p-1.5 text-emerald-600 transition-colors hover:bg-emerald-600/10 dark:text-emerald-400"
                      :title="t.facility_branch?.restore_branch || 'Restore branch'"
                      :aria-label="t.facility_branch?.restore_branch || 'Restore branch'"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path>
                        <path d="M3 3v5h5"></path>
                      </svg>
                    </button>
                    <button
                      v-if="canWrite"
                      type="button"
                      @click="$emit('force-delete', branch)"
                      class="inline-flex items-center justify-center rounded-md border border-border bg-background p-1.5 text-destructive transition-colors hover:bg-destructive/10"
                      :title="t.facility_branch?.force_delete_branch || 'Delete permanently'"
                      :aria-label="t.facility_branch?.force_delete_branch || 'Delete permanently'"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 6h18"></path>
                        <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                        <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                        <line x1="10" x2="10" y1="11" y2="17"></line>
                        <line x1="14" x2="14" y1="11" y2="17"></line>
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="border-t border-border flex-shrink-0">
        <div class="border-t border-border/50 px-3 sm:px-4 md:px-6 py-2.5 sm:py-3 md:py-4 w-full">
          <div class="flex flex-row items-center justify-between gap-2 sm:gap-3 lg:gap-4 w-full flex-wrap">
            <div class="text-xs sm:text-sm text-muted-foreground order-1 flex-shrink-0 min-w-0">
              <span class="hidden sm:inline">{{ (t.common?.showing_results || 'Showing :from to :to of :total results').replace(':from', facilityBranches.meta?.from || 0).replace(':to', facilityBranches.meta?.to || 0).replace(':total', facilityBranches.meta?.total || 0) }}</span>
              <span class="sm:hidden">{{ facilityBranches.meta?.from || 0 }}-{{ facilityBranches.meta?.to || 0 }}/{{ facilityBranches.meta?.total || 0 }}</span>
            </div>
            <div class="order-3 flex-shrink-0 min-w-0">
              <Pagination v-if="facilityBranches?.meta?.links?.length > 0" :links="facilityBranches?.meta?.links" />
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-else data-slot="card-content" class="p-12">
      <div class="text-center max-w-md mx-auto space-y-6">
        <div class="inline-flex items-center justify-center w-20 h-20 sm:w-24 sm:h-24 rounded-full bg-golden-yellow/10 border border-golden-yellow/20 mb-6 shadow-lg shadow-golden-yellow/10">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-10 h-10 sm:w-12 sm:h-12 text-golden-yellow subtle-float">
            <path d="M3 6h18"></path>
            <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
            <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
            <line x1="10" x2="10" y1="11" y2="17"></line>
            <line x1="14" x2="14" y1="11" y2="17"></line>
          </svg>
        </div>
        <h3 class="text-xl sm:text-2xl font-bold mb-1 text-foreground">{{ t.facility_branch?.trash_empty || 'Trash is Empty' }}</h3>
        <p class="text-muted-foreground text-sm sm:text-base leading-relaxed">{{ t.facility_branch?.trash_empty_message || 'No deleted branches found.' }}</p>
        <Link
          :href="route('admin.facility-branch.list')"
          class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2 btn-golden"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
            <path d="m12 19-7-7 7-7"></path>
            <path d="M19 12H5"></path>
          </svg>
          {{ t.common?.back_to_list || 'Back to List' }}
        </Link>
      </div>
    </div>
  </div>
</template>

<script setup>
import Pagination from "@/Pages/_components/Pagination.vue";
import { Link, usePage } from "@inertiajs/vue3";
import { computed } from "vue";
import { usePermissions } from "@/composables/usePermissions";

const props = defineProps({
  facilityBranches: {
    type: Object,
    required: true,
  },
});

defineEmits(['restore', 'force-delete']);

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const { canManage } = usePermissions();
const canWrite = computed(() => canManage('manage facility branches', 'manage own facility branches'));

// AdminFacilityBranchListResource resolves `name` to the current locale's
// string (unlike the facility list resource, which sends every translation).
const branchName = (branch) => branch.name || branch.slug;
const facilityName = (branch) => branch.facility?.name || branch.facility?.slug || '—';
</script>
