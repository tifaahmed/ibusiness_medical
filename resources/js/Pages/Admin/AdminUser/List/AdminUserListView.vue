<template>
  <AdminUserLayout>
    <div class="w-full max-w-full">
      <div class="space-y-2 sm:space-y-3 md:space-y-4 p-2 sm:p-3 md:p-4 lg:p-6 pb-2 sm:pb-3 md:pb-4 lg:pb-4 w-full max-w-full">
        <!-- Header Card -->
        <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-2 sm:gap-3 md:gap-4 rounded-xl border border-border py-2 sm:py-3 md:py-4 shadow-sm overflow-hidden w-full max-w-full">
          <div data-slot="card-header" class="flex flex-row items-center justify-between py-2 px-3 sm:px-4 md:px-6 w-full overflow-hidden gap-2 sm:gap-4">
            <div data-slot="card-title" class="leading-none font-semibold min-w-0 flex-1">
              <div class="title-golden min-w-0 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon sm:w-6 sm:h-6 flex-shrink-0">
                  <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                  <circle cx="9" cy="7" r="4"></circle>
                  <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                  <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                <span class="text-sm sm:text-base truncate block min-w-0">Admin Users</span>
              </div>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
              <Link
                v-if="canWrite"
                :href="route('admin.admin-users.create')"
                data-slot="button"
                class="inline-flex items-center cursor-pointer justify-center gap-1.5 sm:gap-2 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium transition-all bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-8 sm:h-9 px-2 sm:px-3 md:px-4 py-2 flex-shrink-0 btn-golden"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus h-3.5 w-3.5 sm:h-4 sm:w-4">
                  <path d="M5 12h14"></path>
                  <path d="M12 5v14"></path>
                </svg>
                <span class="hidden sm:inline">Add Admin User</span>
                <span class="sm:hidden">Add</span>
              </Link>
            </div>
          </div>

          <div data-slot="card-content" class="px-2 sm:px-4 md:px-6 space-y-2 sm:space-y-3 md:space-y-4 w-full max-w-full overflow-hidden min-w-0">
            <AdminUserListFilterContent
              :initial-filters="filters"
              @filter-change="handleFilterChange"
            />
          </div>
        </div>
      </div>

      <!-- Flash messages -->
      <div v-if="$page.props.flash?.success" class="mx-2 sm:mx-3 md:mx-4 lg:mx-6 mb-3 rounded-md border border-emerald-500/30 bg-emerald-500/10 text-emerald-300 px-3 py-2 text-sm">
        {{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.errors?.admin" class="mx-2 sm:mx-3 md:mx-4 lg:mx-6 mb-3 rounded-md border border-destructive/30 bg-destructive/10 text-destructive px-3 py-2 text-sm">
        {{ $page.props.errors.admin }}
      </div>

      <!-- Table Card -->
      <div class="w-full max-w-full px-2 sm:px-3 md:px-4 lg:px-6 pb-2 sm:pb-3 md:pb-4 lg:pb-6">
        <AdminUserListTable :admins="admins" @delete="handleDelete" />
      </div>
    </div>
  </AdminUserLayout>
</template>

<script setup>
import AdminUserLayout from "../AdminUserLayout.vue";
import AdminUserListFilterContent from "./AdminUserListFilterContent.vue";
import AdminUserListTable from "./AdminUserListTable.vue";
import { Link, router } from "@inertiajs/vue3";
import { ref } from "vue";
import { usePermissions } from '@/composables/usePermissions';
import { computed } from "vue";

const { canManage } = usePermissions();
// Create/export/import are writes: hidden from read-only accounts,
// and refused by the routes behind them either way.
const canWrite = computed(() => canManage('manage admin users', 'manage users'));


const props = defineProps({
  admins: { type: Object, required: true },
  filters: {
    type: Object,
    default: () => ({ search: "", role: null }),
  },
});

const filters = ref(props.filters || { search: "", role: null });

const handleFilterChange = (newFilters) => {
  filters.value = newFilters;
};

const handleDelete = (admin) => {
  if (!confirm(`Delete admin user ${admin.email}?`)) return;
  router.delete(route("admin.admin-users.destroy", admin.id), {
    preserveScroll: true,
  });
};
</script>
