<template>
  <MemberLayout>
    <!-- Mobile: flex column with contained scroll in table | Desktop: normal flow with page scroll -->
    <div class="flex flex-col h-full lg:h-auto w-full max-w-full overflow-x-hidden">
      <div class="flex-shrink-0 space-y-2 sm:space-y-3 md:space-y-4 p-2 sm:p-3 md:p-4 lg:p-6 pb-2 sm:pb-3 md:pb-4 lg:pb-4 w-full max-w-full overflow-hidden">
        <!-- Header Card with Actions and Filters -->
        <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-2 sm:gap-3 md:gap-4 rounded-xl border border-border py-2 sm:py-3 md:py-4 shadow-sm overflow-hidden w-full max-w-full">
          <div data-slot="card-header" class="flex flex-row items-center justify-between py-2 px-3 sm:px-4 md:px-6 w-full overflow-hidden gap-2 sm:gap-4">
            <div data-slot="card-title" class="leading-none font-semibold min-w-0 flex-1">
              <div class="title-golden min-w-0 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash2 title-icon sm:w-6 sm:h-6 flex-shrink-0">
                  <path d="M3 6h18"></path>
                  <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                  <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                  <line x1="10" x2="10" y1="11" y2="17"></line>
                  <line x1="14" x2="14" y1="11" y2="17"></line>
                </svg>
                <span class="text-sm sm:text-base truncate block min-w-0">{{ t.member?.trash_title || 'Trash - Deleted Memberships' }}</span>
              </div>
            </div>
            <Link
              :href="route('admin.user.membership.list')"
              data-slot="button"
              class="inline-flex items-center cursor-pointer justify-center gap-1.5 sm:gap-2 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 sm:h-9 px-2 sm:px-3 md:px-4 py-2 flex-shrink-0"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-left h-3.5 w-3.5 sm:h-4 sm:w-4">
                <path d="m12 19-7-7 7-7"></path>
                <path d="M19 12H5"></path>
              </svg>
              <span class="hidden sm:inline">{{ t.common?.back_to_list || 'Back to List' }}</span>
              <span class="sm:hidden">{{ t.common?.back || 'Back' }}</span>
            </Link>
          </div>
          
          <!-- Filter Content -->
          <div data-slot="card-content" class="px-2 sm:px-4 md:px-6 space-y-2 sm:space-y-3 md:space-y-4 w-full max-w-full overflow-hidden min-w-0">
            <UserMembershipListFilterContent :initial-filters="filters" route-name="admin.user.membership.trash" @filter-change="handleFilterChange" />
          </div>
        </div>

      </div>
      
      <!-- Table Card - Scrollable on mobile, full height on desktop -->
      <div class="flex-1 min-h-0 lg:min-h-fit w-full max-w-full px-2 sm:px-3 md:px-4 lg:px-6 pb-2 sm:pb-3 md:pb-4 lg:pb-6 overflow-hidden lg:overflow-visible">
        <TrashListTable :members="users" @restore="handleRestore" @force-delete="handleForceDelete" />
      </div>
    </div>
  </MemberLayout>
</template>

<script setup>
import MemberLayout from "../Member/MemberLayout.vue";
import UserMembershipListFilterContent from "../Member/List/UserMembershipListFilterContent.vue";
import TrashListTable from "./_components/TrashListTable.vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import { computed, ref } from "vue";

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const props = defineProps({
  users: {
    type: Object,
    required: true
  },
  filters: {
    type: Object,
    default: () => ({
      search: '',
      is_active: null
    })
  }
});

const filters = ref(props.filters || {
  search: '',
  is_active: null
});

const handleFilterChange = (newFilters) => {
  filters.value = newFilters;
};

const handleRestore = (userSlug) => {
  if (confirm('Are you sure you want to restore this user and their memberships?')) {
    router.post(route('admin.user.membership.restore', userSlug), {}, {
      preserveScroll: true,
      onSuccess: () => {
        // Optionally show a success message
      },
    });
  }
};

const handleForceDelete = (userSlug) => {
  if (confirm('Are you sure you want to permanently delete this user and their memberships? This action cannot be undone!')) {
    router.delete(route('admin.user.membership.force-delete', userSlug), {
      preserveScroll: true,
      onSuccess: () => {
        // Optionally show a success message
      },
    });
  }
};
</script>

