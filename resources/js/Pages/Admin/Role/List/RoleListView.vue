<template>
  <RoleLayout>
    <div class="w-full max-w-full">
      <div class="space-y-2 sm:space-y-3 md:space-y-4 p-2 sm:p-3 md:p-4 lg:p-6 pb-2 sm:pb-3 md:pb-4 lg:pb-4 w-full max-w-full">
        <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-2 sm:gap-3 md:gap-4 rounded-xl border border-border py-2 sm:py-3 md:py-4 shadow-sm overflow-hidden w-full max-w-full">
          <div data-slot="card-header" class="flex flex-row items-center justify-between py-2 px-3 sm:px-4 md:px-6 w-full overflow-hidden gap-2 sm:gap-4">
            <div data-slot="card-title" class="leading-none font-semibold min-w-0 flex-1">
              <div class="title-golden min-w-0 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon sm:w-6 sm:h-6 flex-shrink-0">
                  <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                </svg>
                <span class="text-sm sm:text-base truncate block min-w-0">Roles & Permissions</span>
              </div>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
              <Link
                :href="route('admin.roles.create')"
                class="inline-flex items-center cursor-pointer justify-center gap-1.5 sm:gap-2 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium transition-all bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-8 sm:h-9 px-2 sm:px-3 md:px-4 py-2 flex-shrink-0 btn-golden"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus h-3.5 w-3.5 sm:h-4 sm:w-4">
                  <path d="M5 12h14"></path>
                  <path d="M12 5v14"></path>
                </svg>
                <span class="hidden sm:inline">Add Role</span>
                <span class="sm:hidden">Add</span>
              </Link>
            </div>
          </div>

          <div data-slot="card-content" class="px-2 sm:px-4 md:px-6 space-y-2 sm:space-y-3 md:space-y-4 w-full max-w-full overflow-hidden min-w-0">
            <RoleListFilterContent :initial-filters="filters" @filter-change="handleFilterChange" />
          </div>
        </div>
      </div>

      <div v-if="$page.props.flash?.success" class="mx-2 sm:mx-3 md:mx-4 lg:mx-6 mb-3 rounded-md border border-emerald-500/30 bg-emerald-500/10 text-emerald-300 px-3 py-2 text-sm">
        {{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.errors?.role" class="mx-2 sm:mx-3 md:mx-4 lg:mx-6 mb-3 rounded-md border border-destructive/30 bg-destructive/10 text-destructive px-3 py-2 text-sm">
        {{ $page.props.errors.role }}
      </div>

      <div class="w-full max-w-full px-2 sm:px-3 md:px-4 lg:px-6 pb-2 sm:pb-3 md:pb-4 lg:pb-6">
        <RoleListTable :roles="roles" @delete="handleDelete" />
      </div>
    </div>
  </RoleLayout>
</template>

<script setup>
import RoleLayout from "../RoleLayout.vue";
import RoleListFilterContent from "./RoleListFilterContent.vue";
import RoleListTable from "./RoleListTable.vue";
import { Link, router } from "@inertiajs/vue3";
import { ref } from "vue";

const props = defineProps({
  roles: { type: Object, required: true },
  filters: { type: Object, default: () => ({ search: "" }) },
});

const filters = ref(props.filters || { search: "" });

const handleFilterChange = (newFilters) => {
  filters.value = newFilters;
};

const handleDelete = (role) => {
  if (!confirm(`Delete role "${role.name}"?`)) return;
  router.delete(route("admin.roles.destroy", role.id), { preserveScroll: true });
};
</script>
