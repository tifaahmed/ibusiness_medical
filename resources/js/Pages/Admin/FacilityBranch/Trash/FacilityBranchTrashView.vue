<template>
  <FacilityBranchLayout>
    <div class="flex flex-col h-full lg:h-auto w-full max-w-full overflow-x-hidden">
      <div class="flex-shrink-0 space-y-2 sm:space-y-3 md:space-y-4 p-2 sm:p-3 md:p-4 lg:p-6 pb-2 sm:pb-3 md:pb-4 lg:pb-4 w-full max-w-full overflow-hidden">
        <div class="bg-card text-card-foreground flex flex-col gap-2 sm:gap-3 md:gap-4 rounded-xl border border-border py-2 sm:py-3 md:py-4 shadow-sm overflow-hidden w-full max-w-full">
          <div class="flex flex-row items-center justify-between py-2 px-3 sm:px-4 md:px-6 w-full overflow-hidden gap-2 sm:gap-4">
            <div class="leading-none font-semibold min-w-0 flex-1">
              <div class="title-golden min-w-0 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon sm:w-6 sm:h-6 flex-shrink-0">
                  <path d="M3 6h18"></path>
                  <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                  <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                  <line x1="10" x2="10" y1="11" y2="17"></line>
                  <line x1="14" x2="14" y1="11" y2="17"></line>
                </svg>
                <span class="text-sm sm:text-base truncate block min-w-0">{{ t.facility_branch?.trash_title || 'Trash - Deleted Branches' }}</span>
              </div>
            </div>

            <Link
              :href="route('admin.facility-branch.list')"
              class="inline-flex items-center cursor-pointer justify-center gap-1.5 sm:gap-2 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 sm:h-9 px-2 sm:px-3 md:px-4 py-2 flex-shrink-0"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 sm:h-4 sm:w-4">
                <path d="m12 19-7-7 7-7"></path>
                <path d="M19 12H5"></path>
              </svg>
              <span class="hidden sm:inline">{{ t.common?.back_to_list || 'Back to List' }}</span>
              <span class="sm:hidden">{{ t.common?.back || 'Back' }}</span>
            </Link>
          </div>

          <div class="px-2 sm:px-4 md:px-6 space-y-2 sm:space-y-3 md:space-y-4 w-full max-w-full overflow-hidden min-w-0">
            <form @submit.prevent="applySearch" class="flex items-center gap-2 max-w-md">
              <input
                v-model="search"
                type="text"
                :placeholder="t.common?.search || 'Search'"
                class="flex-1 h-9 px-3 text-sm rounded-md border border-input bg-background text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-primary"
              />
              <button
                type="submit"
                class="inline-flex items-center justify-center rounded-md border border-border bg-background px-3 h-9 text-sm hover:bg-muted"
              >
                {{ t.common?.search || 'Search' }}
              </button>
            </form>
          </div>
        </div>
      </div>

      <div class="flex-1 min-h-0 lg:flex-none w-full max-w-full px-2 sm:px-3 md:px-4 lg:px-6 pb-2 sm:pb-3 md:pb-4 lg:pb-6 overflow-hidden lg:overflow-visible">
        <FacilityBranchTrashTable :facility-branches="facilityBranches" @restore="handleRestore" @force-delete="handleForceDelete" />
      </div>
    </div>
  </FacilityBranchLayout>
</template>

<script setup>
import FacilityBranchLayout from "../FacilityBranchLayout.vue";
import FacilityBranchTrashTable from "./_components/FacilityBranchTrashTable.vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import { useNotification } from "@/composables/useNotification";

const props = defineProps({
  facilityBranches: {
    type: Object,
    required: true,
  },
  filters: {
    type: Object,
    default: () => ({ search: '' }),
  },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});
const notification = useNotification();

const search = ref(props.filters?.search || '');

const applySearch = () => {
  router.get(route('admin.facility-branch.trash'), { search: search.value }, { preserveState: true, preserveScroll: true });
};

const working = ref(false);

const handleRestore = (branch) => {
  if (working.value) return;

  const name = branch.name || branch.slug;
  const question = (t.value.facility_branch?.confirm_restore || 'Restore :name back to the list?').replace(':name', name);

  if (!confirm(question)) return;

  working.value = true;
  router.post(route('admin.facility-branch.restore', branch.slug), {}, {
    preserveScroll: true,
    onError: (errors) => {
      notification.error(
        Object.values(errors || {})[0]
          || t.value.facility_branch?.restored_failed
          || 'Could not restore the facility branch.'
      );
    },
    onFinish: () => { working.value = false; },
  });
};

const handleForceDelete = (branch) => {
  if (working.value) return;

  const name = branch.name || branch.slug;
  const question = (t.value.facility_branch?.confirm_force_delete || 'Permanently delete :name? This cannot be undone.')
    .replace(':name', name);

  if (!confirm(question)) return;

  working.value = true;
  router.delete(route('admin.facility-branch.force-delete', branch.slug), {
    preserveScroll: true,
    onError: (errors) => {
      notification.error(
        Object.values(errors || {})[0]
          || t.value.facility_branch?.force_deleted_failed
          || 'Could not permanently delete the facility branch.'
      );
    },
    onFinish: () => { working.value = false; },
  });
};
</script>
