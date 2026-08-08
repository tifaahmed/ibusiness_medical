<template>
  <div data-slot="card" class="bg-card text-card-foreground flex flex-col h-full lg:h-auto rounded-xl border border-border shadow-sm">
    <div v-if="usages?.data?.length > 0" class="flex flex-col h-full lg:h-auto min-h-0 lg:min-h-fit">
      <div class="flex-1 min-h-0 lg:min-h-fit overflow-y-auto lg:overflow-y-visible overflow-x-auto">
        <div data-slot="table-container" class="relative w-full py-3 sm:py-4">
          <table data-slot="table" class="w-full caption-bottom text-xs sm:text-sm min-w-full">
            <thead data-slot="table-header" class="[&_tr]:border-b [&_tr]:border-border">
              <tr data-slot="table-row" class="hover:bg-muted/50 border-b border-border transition-colors">
                <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap">{{ t.col_membership || 'Membership' }}</th>
                <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap hidden sm:table-cell">{{ t.col_facility || 'Facility' }}</th>
                <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap hidden md:table-cell">{{ t.col_branch || 'Branch' }}</th>
                <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap hidden md:table-cell">{{ t.col_type || 'Type' }}</th>
                <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-right align-middle font-medium whitespace-nowrap">{{ t.col_amount || 'Amount' }}</th>
                <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap hidden lg:table-cell">{{ t.col_description || 'Description' }}</th>
                <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap w-20 sm:w-28 text-center">{{ t.col_actions || 'Actions' }}</th>
              </tr>
            </thead>
            <tbody data-slot="table-body" class="[&_tr:last-child]:border-0">
              <tr
                v-for="usage in usages.data"
                :key="usage.id"
                data-slot="table-row"
                class="data-[state=selected]:bg-muted border-b border-border transition-colors hover:bg-muted/50"
              >
                <td class="p-2 sm:p-3 align-middle">
                  <div class="space-y-0.5">
                    <div class="font-semibold text-xs sm:text-sm text-foreground">{{ usage.membership_number || '-' }}</div>
                    <div class="text-xs text-muted-foreground">{{ usage.user_name || '-' }}</div>
                    <!-- Mobile: show facility below member -->
                    <div class="sm:hidden text-xs text-muted-foreground mt-1">{{ usage.facility_name || '-' }}</div>
                  </div>
                </td>
                <td class="p-2 sm:p-3 align-middle hidden sm:table-cell">
                  <div class="text-xs sm:text-sm text-foreground">{{ usage.facility_name || '-' }}</div>
                </td>
                <td class="p-2 sm:p-3 align-middle hidden md:table-cell">
                  <div class="text-xs sm:text-sm text-muted-foreground">{{ usage.facility_branch_name || '-' }}</div>
                </td>
                <td class="p-2 sm:p-3 align-middle hidden md:table-cell">
                  <div class="text-xs sm:text-sm text-muted-foreground">{{ usage.facility_type_name || '-' }}</div>
                </td>
                <td class="p-2 sm:p-3 align-middle text-right">
                  <span class="font-medium text-xs sm:text-sm text-foreground">{{ formatAmount(usage.amount) }}</span>
                </td>
                <td class="p-2 sm:p-3 align-middle hidden lg:table-cell">
                  <div class="text-xs text-muted-foreground max-w-[200px] truncate">{{ usage.description || '-' }}</div>
                </td>
                <td class="p-2 align-middle text-center">
                  <div class="flex items-center justify-center gap-2">
                    <Link
                      :href="route('admin.membership-usage.edit', usage.id)"
                      class="inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5 text-emerald-bright hover:!bg-emerald-bright/10 hover:!text-emerald-bright"
                      title="Edit"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3">
                        <path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"></path>
                      </svg>
                    </Link>
                    <button
                      @click="$emit('delete', usage.id)"
                      class="inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all border bg-background shadow-xs hover:bg-destructive hover:text-white dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5 text-destructive hover:!bg-destructive/10 hover:!text-destructive"
                      title="Delete"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3">
                        <path d="M3 6h18"></path>
                        <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                        <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagination -->
      <div class="border-t border-border flex-shrink-0">
        <div class="px-3 sm:px-4 md:px-6 py-2.5 sm:py-3 md:py-4 w-full">
          <div class="flex flex-row items-center justify-between gap-2 sm:gap-3 w-full flex-wrap">
            <div class="text-xs sm:text-sm text-muted-foreground order-1 flex-shrink-0">
              <span class="hidden sm:inline">{{ usages.meta?.from || 0 }}-{{ usages.meta?.to || 0 }} / {{ usages.meta?.total || 0 }}</span>
              <span class="sm:hidden">{{ usages.meta?.from || 0 }}-{{ usages.meta?.to || 0 }}/{{ usages.meta?.total || 0 }}</span>
            </div>
            <div class="flex items-center gap-2 order-2 flex-shrink-0">
              <p class="text-xs sm:text-sm font-medium whitespace-nowrap hidden sm:inline">{{ t.rows_per_page || 'Rows per page' }}</p>
              <select
                :value="usages.meta?.per_page || 15"
                @change="handlePerPageChange"
                dir="ltr"
                translate="no"
                class="border-input focus-visible:border-ring focus-visible:ring-ring/50 rounded-md border bg-transparent px-2 sm:px-3 py-1.5 sm:py-2 text-xs sm:text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] h-7 sm:h-8 w-[60px] sm:w-[70px] cursor-pointer"
              >
                <option value="10">10</option>
                <option value="15">15</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
              </select>
            </div>
            <div class="order-3 flex-shrink-0">
              <Pagination v-if="usages?.meta?.links?.length > 0" :links="usages?.meta?.links" />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else data-slot="card-content" class="p-12">
      <div class="text-center max-w-md mx-auto space-y-6">
        <div class="inline-flex items-center justify-center w-20 h-20 sm:w-24 sm:h-24 rounded-full bg-golden-yellow/10 border border-golden-yellow/20 mb-6 shadow-lg shadow-golden-yellow/10">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-10 h-10 sm:w-12 sm:h-12 text-golden-yellow subtle-float">
            <path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2z"></path>
            <path d="M12 8v4l3 3"></path>
          </svg>
        </div>
        <h3 class="text-xl sm:text-2xl font-bold mb-1 text-foreground">{{ t.not_found || 'No Membership Usages Found' }}</h3>
        <p class="text-muted-foreground text-sm sm:text-base leading-relaxed">{{ t.not_found_message || 'No usages match your current filters. Try adjusting your search criteria.' }}</p>
        <Link
          :href="route('admin.membership-usage.create')"
          data-slot="button"
          class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2 has-[>svg]:px-3 btn-golden"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
            <path d="M5 12h14"></path>
            <path d="M12 5v14"></path>
          </svg>
          {{ t.add_new || 'Add Membership Usage' }}
        </Link>
      </div>
    </div>
  </div>
</template>

<script setup>
import Pagination from "@/Pages/_components/Pagination.vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import { computed } from "vue";

defineProps({
  usages: { type: Object, required: true },
});

defineEmits(['delete']);

const page = usePage();
const t = computed(() => page.props.translations?.admin?.membership_usage || {});

const formatAmount = (amount) => {
  if (amount === null || amount === undefined) return '-';
  return Number(amount).toFixed(2);
};

const handlePerPageChange = (event) => {
  const perPage = event.target.value;
  const currentUrl = new URL(window.location.href);
  currentUrl.searchParams.set('per_page', perPage);
  currentUrl.searchParams.set('page', '1');
  router.visit(currentUrl.toString(), {
    preserveState: false,
    preserveScroll: false,
  });
};
</script>
