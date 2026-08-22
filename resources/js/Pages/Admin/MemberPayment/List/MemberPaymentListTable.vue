<template>
  <div data-slot="card" class="bg-card text-card-foreground flex flex-col h-full lg:h-auto rounded-xl border border-border shadow-sm">
    <div v-if="payments?.data?.length > 0" class="flex flex-col h-full lg:h-auto min-h-0 lg:min-h-fit">
      <div class="flex-1 min-h-0 lg:min-h-fit overflow-y-auto lg:overflow-y-visible overflow-x-auto">
        <div data-slot="table-container" class="relative w-full py-3 sm:py-4">
          <table data-slot="table" class="w-full caption-bottom text-xs sm:text-sm min-w-full">
            <thead data-slot="table-header" class="[&_tr]:border-b [&_tr]:border-border">
              <tr data-slot="table-row" class="hover:bg-muted/50 border-b border-border transition-colors">
                <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap">{{ t.col_member || 'Member' }}</th>
                <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap hidden lg:table-cell">{{ t.col_partner || 'Partner' }}</th>
                <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-right align-middle font-medium whitespace-nowrap">{{ t.col_amount || 'Amount' }}</th>
                <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-center align-middle font-medium whitespace-nowrap hidden sm:table-cell">{{ t.col_type || 'Type' }}</th>
                <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-center align-middle font-medium whitespace-nowrap hidden sm:table-cell">{{ t.col_months || 'Months' }}</th>
                <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-center align-middle font-medium whitespace-nowrap hidden md:table-cell">{{ t.col_period || 'Period' }}</th>
                <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap hidden lg:table-cell">{{ t.col_notes || 'Notes' }}</th>
                <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap hidden lg:table-cell">{{ t.col_date || 'Date' }}</th>
                <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap w-20 sm:w-28 text-center">{{ t.col_actions || 'Actions' }}</th>
              </tr>
            </thead>
            <tbody data-slot="table-body" class="[&_tr:last-child]:border-0">
              <tr
                v-for="payment in payments.data"
                :key="payment.id"
                data-slot="table-row"
                class="data-[state=selected]:bg-muted border-b border-border transition-colors hover:bg-muted/50"
              >
                <td class="p-2 sm:p-3 align-middle">
                  <div class="space-y-0.5">
                    <div class="font-semibold text-xs sm:text-sm text-foreground">{{ payment.membership_number || '-' }}</div>
                    <div class="text-xs text-muted-foreground">{{ payment.user_name || '-' }}</div>
                    <div v-if="payment.user_phone" class="text-[11px] text-muted-foreground/70 flex items-center gap-1">
                      <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                      <span>{{ payment.user_phone }}</span>
                    </div>
                    <div v-if="payment.user_email" class="text-[11px] text-muted-foreground/70 flex items-center gap-1 truncate max-w-[220px]" :title="payment.user_email">
                      <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                      <span>{{ payment.user_email }}</span>
                    </div>
                  </div>
                </td>
                <td class="p-2 sm:p-3 align-middle hidden lg:table-cell">
                  <div class="text-xs text-muted-foreground">{{ payment.partner_name || '-' }}</div>
                </td>
                <td class="p-2 sm:p-3 align-middle text-right">
                  <span class="font-medium text-xs sm:text-sm text-foreground">{{ formatAmount(payment.amount) }}</span>
                </td>
                <td class="p-2 sm:p-3 align-middle text-center hidden sm:table-cell">
                  <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                    :class="typeBadgeClass(payment.type)"
                  >{{ typeLabel(payment.type) }}</span>
                </td>
                <td class="p-2 sm:p-3 align-middle text-center hidden sm:table-cell">
                  <span class="text-xs sm:text-sm text-foreground">{{ payment.months_paid }}</span>
                </td>
                <td class="p-2 sm:p-3 align-middle text-center hidden md:table-cell">
                  <span class="text-xs text-muted-foreground whitespace-nowrap">{{ payment.from_date }} → {{ payment.to_date }}</span>
                </td>
                <td class="p-2 sm:p-3 align-middle hidden lg:table-cell">
                  <div class="text-xs text-muted-foreground max-w-[200px] truncate">{{ payment.notes || '-' }}</div>
                </td>
                <td class="p-2 sm:p-3 align-middle hidden lg:table-cell">
                  <div class="text-xs text-muted-foreground whitespace-nowrap">{{ payment.created_at }}</div>
                </td>
                <td class="p-2 align-middle text-center">
                  <div class="flex items-center justify-center gap-2">
                    <Link
                      :href="route('admin.member-payment.show', payment.id)"
                      class="inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5 text-golden-yellow hover:!bg-golden-yellow/10 hover:!text-golden-yellow"
                      :title="t.action_view || 'View'"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3">
                        <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                      </svg>
                    </Link>
                    <Link
                      :href="route('admin.member-payment.edit', payment.id)"
                      class="inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5 text-emerald-bright hover:!bg-emerald-bright/10 hover:!text-emerald-bright"
                      title="Edit"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3">
                        <path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"></path>
                      </svg>
                    </Link>
                    <button
                      @click="$emit('delete', payment.id)"
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

      <div class="border-t border-border flex-shrink-0">
        <div class="px-3 sm:px-4 md:px-6 py-2.5 sm:py-3 md:py-4 w-full">
          <div class="flex flex-row items-center justify-between gap-2 sm:gap-3 w-full flex-wrap">
            <div class="text-xs sm:text-sm text-muted-foreground order-1 flex-shrink-0">
              <span class="hidden sm:inline">{{ payments.meta?.from || 0 }}-{{ payments.meta?.to || 0 }} / {{ payments.meta?.total || 0 }}</span>
              <span class="sm:hidden">{{ payments.meta?.from || 0 }}-{{ payments.meta?.to || 0 }}/{{ payments.meta?.total || 0 }}</span>
            </div>
            <div class="flex items-center gap-2 order-2 flex-shrink-0">
              <p class="text-xs sm:text-sm font-medium whitespace-nowrap hidden sm:inline">{{ t.rows_per_page || 'Rows per page' }}</p>
              <select
                :value="payments.meta?.per_page || 15"
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
              <Pagination v-if="payments?.meta?.links?.length > 0" :links="payments?.meta?.links" />
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-else data-slot="card-content" class="p-12">
      <div class="text-center max-w-md mx-auto space-y-6">
        <div class="inline-flex items-center justify-center w-20 h-20 sm:w-24 sm:h-24 rounded-full bg-golden-yellow/10 border border-golden-yellow/20 mb-6 shadow-lg shadow-golden-yellow/10">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-10 h-10 sm:w-12 sm:h-12 text-golden-yellow subtle-float">
            <path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2z"></path>
            <path d="M12 6v6l4 2"></path>
          </svg>
        </div>
        <h3 class="text-xl sm:text-2xl font-bold mb-1 text-foreground">{{ t.not_found || 'No Payments Found' }}</h3>
        <p class="text-muted-foreground text-sm sm:text-base leading-relaxed">{{ t.not_found_message || 'No payments match your current filters.' }}</p>
        <Link
          v-if="canWrite"
          :href="route('admin.member-payment.create')"
          data-slot="button"
          class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2 has-[>svg]:px-3 btn-golden"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
            <path d="M5 12h14"></path>
            <path d="M12 5v14"></path>
          </svg>
          {{ t.add_new || 'Add Payment' }}
        </Link>
      </div>
    </div>
  </div>
</template>

<script setup>
import Pagination from "@/Pages/_components/Pagination.vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import { computed } from "vue";
import { usePermissions } from '@/composables/usePermissions';

const { canManage } = usePermissions();
// Create/export/import are writes: hidden from read-only accounts,
// and refused by the routes behind them either way.
const canWrite = computed(() => canManage('manage member payments', 'manage own member payments', 'manage partner member payments'));


defineProps({
  payments: { type: Object, required: true },
});

defineEmits(['delete']);

const page = usePage();
const t = computed(() => page.props.translations?.admin?.member_payment || {});

const formatAmount = (amount) => {
  if (amount === null || amount === undefined) return '-';
  return Number(amount).toFixed(2);
};

const typeLabel = (type) => {
  const labels = {
    commission: t.value.type_commission || 'Commission',
    profit: t.value.type_profit || 'Profit',
    free: t.value.type_free || 'Free',
  };
  return labels[type] || type || '—';
};

const typeBadgeClass = (type) => {
  if (type === 'free') return 'bg-purple-500/20 text-purple-500';
  if (type === 'profit') return 'bg-blue-500/20 text-blue-500';
  return 'bg-amber-500/20 text-amber-500';
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
