<template>
  <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
    <div v-if="roles?.data?.length > 0">
      <div class="overflow-x-auto">
        <div data-slot="table-container" class="relative w-full py-3 sm:py-4">
          <table data-slot="table" class="w-full caption-bottom text-xs sm:text-sm">
            <thead class="[&_tr]:border-b [&_tr]:border-border">
              <tr class="hover:bg-muted/50 border-b border-border transition-colors">
                <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap w-[180px] sm:w-[220px]">
                  Role
                </th>
                <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap text-center hidden sm:table-cell w-24">
                  Users
                </th>
                <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap text-center">
                  Permissions
                </th>
                <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap w-28 sm:w-32 text-center">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody class="[&_tr:last-child]:border-0">
              <tr
                v-for="role in roles.data"
                :key="role.id"
                class="border-b border-border transition-colors hover:bg-muted/50"
              >
                <td class="p-2 sm:p-3 align-middle w-[180px] sm:w-[220px]">
                  <div class="flex items-center gap-2 sm:gap-3">
                    <div :class="['flex-shrink-0 h-8 w-8 sm:h-10 sm:w-10 rounded-full flex items-center justify-center font-semibold text-xs sm:text-sm', iconBg(role.name)]">
                      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                      </svg>
                    </div>
                    <div class="flex-1 min-w-0 overflow-hidden">
                      <div class="font-semibold text-sm sm:text-base text-foreground truncate flex items-center gap-2" :title="role.name">
                        <span class="truncate">{{ role.name }}</span>
                        <span
                          v-if="role.name === 'admin'"
                          class="text-[9px] uppercase tracking-wide font-semibold text-blue-300 border border-blue-500/40 bg-blue-500/10 rounded px-1.5 py-0.5 whitespace-nowrap"
                        >
                          Login only
                        </span>
                      </div>
                      <div v-if="role.is_protected" class="text-xs text-amber-300 flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                          <rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect>
                          <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        Protected
                      </div>
                    </div>
                  </div>
                </td>
                <td class="p-2 align-middle text-center hidden sm:table-cell">
                  <span class="inline-flex items-center rounded-md px-2 py-0.5 bg-muted text-foreground text-xs font-medium">
                    {{ role.users_count ?? 0 }}
                  </span>
                </td>
                <td class="p-2 align-middle text-center">
                  <div v-if="role.permissions.length === 0" class="text-muted-foreground text-xs">—</div>
                  <div v-else class="flex flex-wrap items-center justify-center gap-1">
                    <span
                      v-for="p in role.permissions"
                      :key="p"
                      class="inline-flex items-center rounded-md px-1.5 py-0.5 bg-amber-500/15 text-amber-300 border border-amber-500/30 text-xs whitespace-nowrap"
                    >
                      {{ p }}
                    </span>
                  </div>
                </td>
                <td class="p-2 align-middle whitespace-nowrap text-center">
                  <div class="flex items-center justify-center gap-2">
                    <template v-if="role.is_protected">
                      <span class="text-xs text-muted-foreground italic">locked</span>
                    </template>
                    <template v-else>
                      <Link
                        :href="route('admin.roles.edit', role.id)"
                        class="inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5 text-emerald-bright hover:!bg-emerald-bright/10 hover:!text-emerald-bright"
                        title="Edit"
                      >
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-square-pen w-3 h-3">
                          <path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                          <path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"></path>
                        </svg>
                      </Link>
                      <button
                        type="button"
                        @click="$emit('delete', role)"
                        :disabled="(role.users_count ?? 0) > 0"
                        :title="(role.users_count ?? 0) > 0 ? 'Cannot delete: role is in use' : 'Delete'"
                        class="inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all border bg-background shadow-xs hover:bg-destructive hover:text-white dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5 text-destructive hover:!bg-destructive/10 hover:!text-destructive disabled:opacity-50 disabled:cursor-not-allowed"
                      >
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash-2 w-3 h-3">
                          <path d="M3 6h18"></path>
                          <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                          <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                        </svg>
                      </button>
                    </template>
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
              <span class="hidden sm:inline">Showing {{ roles.meta?.from || 0 }} to {{ roles.meta?.to || 0 }} of {{ roles.meta?.total || 0 }} results</span>
              <span class="sm:hidden">{{ roles.meta?.from || 0 }}-{{ roles.meta?.to || 0 }}/{{ roles.meta?.total || 0 }}</span>
            </div>
            <div class="flex items-center gap-2 order-2 flex-shrink-0">
              <p class="text-xs sm:text-sm font-medium whitespace-nowrap hidden sm:inline">Rows per page</p>
              <p class="text-xs sm:text-sm font-medium whitespace-nowrap sm:hidden">Per page</p>
              <select
                :value="roles.meta?.per_page || 15"
                @change="handlePerPageChange"
                dir="ltr"
                translate="no"
                class="border-input rounded-md border bg-transparent px-2 sm:px-3 py-1.5 sm:py-2 text-xs sm:text-sm shadow-xs h-7 sm:h-8 w-[60px] sm:w-[70px] cursor-pointer"
              >
                <option value="10">10</option>
                <option value="15">15</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
              </select>
            </div>
            <div class="order-3 flex-shrink-0 min-w-0">
              <Pagination v-if="roles?.meta?.links?.length > 0" :links="roles?.meta?.links" />
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-else class="p-12">
      <div class="text-center max-w-md mx-auto space-y-6">
        <div class="inline-flex items-center justify-center w-20 h-20 sm:w-24 sm:h-24 rounded-full bg-golden-yellow/10 border border-golden-yellow/20 mb-6 shadow-lg shadow-golden-yellow/10">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-shield w-10 h-10 sm:w-12 sm:h-12 text-golden-yellow">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
          </svg>
        </div>
        <h3 class="text-xl sm:text-2xl font-bold mb-1 text-foreground">No Roles Found</h3>
        <p class="text-muted-foreground text-sm sm:text-base leading-relaxed">No roles match your search.</p>
        <Link
          :href="route('admin.roles.create')"
          class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2 btn-golden"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus w-4 h-4">
            <path d="M5 12h14"></path>
            <path d="M12 5v14"></path>
          </svg>
          Add Role
        </Link>
      </div>
    </div>
  </div>
</template>

<script setup>
import Pagination from "@/Pages/_components/Pagination.vue";
import { Link, router } from "@inertiajs/vue3";

defineProps({
  roles: { type: Object, required: true },
});

defineEmits(["delete"]);

function iconBg(name) {
  if (name === "super_admin") return "bg-pink-500/20 text-pink-300 border border-pink-500/40";
  if (name === "admin") return "bg-blue-500/20 text-blue-300 border border-blue-500/40";
  if (name === "editor") return "bg-emerald-500/20 text-emerald-300 border border-emerald-500/40";
  if (name === "member") return "bg-cyan-500/20 text-cyan-300 border border-cyan-500/40";
  return "bg-muted text-muted-foreground border border-border";
}

function handlePerPageChange(event) {
  const perPage = event.target.value;
  const url = new URL(window.location.href);
  url.searchParams.set("per_page", perPage);
  url.searchParams.set("page", "1");
  router.visit(url.toString(), { preserveState: false, preserveScroll: false });
}
</script>
