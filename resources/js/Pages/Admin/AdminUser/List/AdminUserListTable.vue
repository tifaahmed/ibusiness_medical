<template>
  <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
    <div v-if="admins?.data?.length > 0">
      <div class="overflow-x-auto">
        <div data-slot="table-container" class="relative w-full py-3 sm:py-4">
          <table data-slot="table" class="w-full caption-bottom text-xs sm:text-sm">
            <thead data-slot="table-header" class="[&_tr]:border-b [&_tr]:border-border">
              <tr class="hover:bg-muted/50 border-b border-border transition-colors">
                <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap w-[180px] sm:w-[220px]">
                  User
                </th>
                <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap text-center hidden sm:table-cell">
                  Email
                </th>
                <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap text-center">
                  Roles
                </th>
                <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap text-center hidden lg:table-cell">
                  Extra Permissions
                </th>
                <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap text-center hidden lg:table-cell">
                  Created At
                </th>
                <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap w-28 sm:w-32 text-center">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody class="[&_tr:last-child]:border-0">
              <tr
                v-for="admin in admins.data"
                :key="admin.id"
                class="border-b border-border transition-colors hover:bg-muted/50"
              >
                <td class="p-2 sm:p-3 align-middle w-[180px] sm:w-[220px]">
                  <div class="flex items-center gap-2 sm:gap-3">
                    <div class="flex-shrink-0 h-8 w-8 sm:h-10 sm:w-10 rounded-full bg-sidebar-primary text-sidebar-primary-foreground flex items-center justify-center font-semibold text-xs sm:text-sm">
                      {{ getInitials(admin.name) }}
                    </div>
                    <div class="flex-1 min-w-0 overflow-hidden">
                      <div class="font-semibold text-sm sm:text-base text-foreground truncate" :title="admin.name">
                        {{ admin.name }}
                      </div>
                      <div v-if="admin.is_self" class="text-xs text-muted-foreground">(you)</div>
                    </div>
                  </div>
                </td>
                <td class="p-2 align-middle whitespace-nowrap text-center hidden sm:table-cell">
                  <span class="text-muted-foreground text-xs sm:text-sm truncate block max-w-[220px]" :title="admin.email">
                    {{ admin.email }}
                  </span>
                </td>
                <td class="p-2 align-middle text-center">
                  <div class="flex flex-wrap items-center justify-center gap-1">
                    <span
                      v-for="r in admin.roles"
                      :key="r"
                      :class="roleBadgeClass(r)"
                    >
                      {{ r }}
                    </span>
                  </div>
                </td>
                <td class="p-2 align-middle text-center hidden lg:table-cell">
                  <div v-if="admin.direct_permissions.length === 0" class="text-muted-foreground text-xs">—</div>
                  <div v-else class="flex flex-wrap items-center justify-center gap-1">
                    <span
                      v-for="p in admin.direct_permissions"
                      :key="p"
                      class="inline-flex items-center rounded-md px-1.5 py-0.5 bg-amber-500/15 text-amber-300 border border-amber-500/30 text-xs whitespace-nowrap"
                    >
                      {{ p }}
                    </span>
                  </div>
                </td>
                <td class="p-2 align-middle whitespace-nowrap text-center hidden lg:table-cell">
                  <span class="text-muted-foreground text-xs sm:text-sm">{{ formatDate(admin.created_at) }}</span>
                </td>
                <td class="p-2 align-middle whitespace-nowrap text-center">
                  <div class="flex items-center justify-center gap-2">
                    <template v-if="admin.is_super_admin">
                      <span class="text-xs text-muted-foreground italic">locked</span>
                    </template>
                    <template v-else>
                      <Link
                        :href="route('admin.admin-users.show', admin.id)"
                        class="inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5 text-sky-300 hover:!bg-sky-400/10 hover:!text-sky-300"
                        title="View"
                      >
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3">
                          <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"></path>
                          <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                      </Link>
                      <Link
                        :href="route('admin.admin-users.edit', admin.id)"
                        class="inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5 text-emerald-bright hover:!bg-emerald-bright/10 hover:!text-emerald-bright"
                        title="Edit"
                      >
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-square-pen w-3 h-3">
                          <path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                          <path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"></path>
                        </svg>
                      </Link>
                      <button
                        v-if="!admin.is_self"
                        type="button"
                        @click="$emit('delete', admin)"
                        class="inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all border bg-background shadow-xs hover:bg-destructive hover:text-white dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5 text-destructive hover:!bg-destructive/10 hover:!text-destructive"
                        title="Delete"
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
              <span class="hidden sm:inline">Showing {{ admins.meta?.from || 0 }} to {{ admins.meta?.to || 0 }} of {{ admins.meta?.total || 0 }} results</span>
              <span class="sm:hidden">{{ admins.meta?.from || 0 }}-{{ admins.meta?.to || 0 }}/{{ admins.meta?.total || 0 }}</span>
            </div>
            <div class="flex items-center gap-2 order-2 flex-shrink-0">
              <p class="text-xs sm:text-sm font-medium whitespace-nowrap hidden sm:inline">Rows per page</p>
              <p class="text-xs sm:text-sm font-medium whitespace-nowrap sm:hidden">Per page</p>
              <select
                :value="admins.meta?.per_page || 15"
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
              <Pagination
                v-if="admins?.meta?.links?.length > 0"
                :links="admins?.meta?.links"
              />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="p-12">
      <div class="text-center max-w-md mx-auto space-y-6">
        <div class="inline-flex items-center justify-center w-20 h-20 sm:w-24 sm:h-24 rounded-full bg-golden-yellow/10 border border-golden-yellow/20 mb-6 shadow-lg shadow-golden-yellow/10">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-users w-10 h-10 sm:w-12 sm:h-12 text-golden-yellow">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
            <circle cx="9" cy="7" r="4"></circle>
            <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
          </svg>
        </div>
        <h3 class="text-xl sm:text-2xl font-bold mb-1 text-foreground">No Admin Users Found</h3>
        <p class="text-muted-foreground text-sm sm:text-base leading-relaxed">No admins match your filters. Try adjusting your search criteria.</p>
        <Link
          :href="route('admin.admin-users.create')"
          class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2 btn-golden"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus w-4 h-4">
            <path d="M5 12h14"></path>
            <path d="M12 5v14"></path>
          </svg>
          Add Admin
        </Link>
      </div>
    </div>
  </div>
</template>

<script setup>
import Pagination from "@/Pages/_components/Pagination.vue";
import { Link, router } from "@inertiajs/vue3";

defineProps({
  admins: { type: Object, required: true },
});

defineEmits(["delete"]);

function formatDate(iso) {
  if (!iso) return "—";
  const d = new Date(iso);
  return d.toLocaleDateString("en-GB", { day: "numeric", month: "short", year: "numeric" });
}

function getInitials(name) {
  if (!name) return "?";
  return name
    .split(" ")
    .map((p) => p.charAt(0))
    .join("")
    .slice(0, 2)
    .toUpperCase();
}

function roleBadgeClass(role) {
  const base =
    "inline-flex items-center rounded-md px-1.5 py-0.5 text-xs font-medium whitespace-nowrap border";
  if (role === "super_admin") return `${base} bg-pink-500/15 text-pink-300 border-pink-500/30`;
  if (role === "admin") return `${base} bg-blue-500/15 text-blue-300 border-blue-500/30`;
  if (role === "editor") return `${base} bg-emerald-500/15 text-emerald-300 border-emerald-500/30`;
  return `${base} bg-muted text-muted-foreground border-border`;
}

function handlePerPageChange(event) {
  const perPage = event.target.value;
  const url = new URL(window.location.href);
  url.searchParams.set("per_page", perPage);
  url.searchParams.set("page", "1");
  router.visit(url.toString(), { preserveState: false, preserveScroll: false });
}
</script>
