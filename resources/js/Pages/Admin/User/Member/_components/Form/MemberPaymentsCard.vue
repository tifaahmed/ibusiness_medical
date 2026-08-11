<template>
  <div class="rounded-xl border border-border bg-card p-4 sm:p-6 space-y-4">
    <!-- Header + add button -->
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h3 class="text-base font-semibold text-white flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/>
          <path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/>
        </svg>
        {{ t.member?.payments || 'Payments' }}
        <span v-if="membershipNumber" class="text-xs font-normal text-muted-foreground">— {{ membershipNumber }}</span>
      </h3>

      <div class="flex items-center gap-2">
        <Link
          :href="route('admin.member-payment.list')"
          class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-md text-sm font-medium transition-all border bg-background shadow-xs hover:bg-muted h-9 px-3"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="8" y1="6" x2="21" y2="6"></line>
            <line x1="8" y1="12" x2="21" y2="12"></line>
            <line x1="8" y1="18" x2="21" y2="18"></line>
            <line x1="3" y1="6" x2="3.01" y2="6"></line>
            <line x1="3" y1="12" x2="3.01" y2="12"></line>
            <line x1="3" y1="18" x2="3.01" y2="18"></line>
          </svg>
          <span class="hidden sm:inline">{{ t.member?.all_payments || 'All Payments' }}</span>
        </Link>
        <Link
          :href="createPaymentUrl"
          class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-md text-sm font-medium transition-all bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 btn-golden"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 12h14"></path>
            <path d="M12 5v14"></path>
          </svg>
          {{ t.member?.add_payment || 'Add Payment' }}
        </Link>
      </div>
    </div>

    <!-- Payments table -->
    <div v-if="payments.length" class="overflow-x-auto">
      <table class="w-full text-xs sm:text-sm">
        <thead>
          <tr class="border-b border-border">
            <th class="text-left text-muted-foreground font-medium px-2 py-1.5">{{ t.member?.payment_amount || 'Amount' }}</th>
            <th class="text-center text-muted-foreground font-medium px-2 py-1.5 hidden sm:table-cell">{{ t.member_payment?.type || 'Type' }}</th>
            <th class="text-center text-muted-foreground font-medium px-2 py-1.5">{{ t.member?.payment_months || 'Months' }}</th>
            <th class="text-center text-muted-foreground font-medium px-2 py-1.5 hidden sm:table-cell">{{ t.member?.payment_period || 'Period' }}</th>
            <th class="text-left text-muted-foreground font-medium px-2 py-1.5 hidden lg:table-cell">{{ t.member?.payment_notes || 'Notes' }}</th>
            <th class="text-left text-muted-foreground font-medium px-2 py-1.5 hidden md:table-cell">{{ t.member?.payment_date || 'Date' }}</th>
            <th class="text-center text-muted-foreground font-medium px-2 py-1.5 w-20">{{ t.common?.actions || 'Actions' }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="pmt in payments" :key="pmt.id" class="border-b border-border/50 hover:bg-muted/20">
            <td class="px-2 py-1.5 font-medium text-white whitespace-nowrap">{{ formatAmount(pmt.amount) }}</td>
            <td class="px-2 py-1.5 text-center hidden sm:table-cell">
              <span class="inline-flex items-center rounded-md border border-border px-1.5 py-0.5 text-[11px] font-medium text-muted-foreground">
                {{ typeLabel(pmt.type) }}
              </span>
            </td>
            <td class="px-2 py-1.5 text-center text-muted-foreground">{{ pmt.months_paid }}</td>
            <td class="px-2 py-1.5 text-center text-muted-foreground whitespace-nowrap hidden sm:table-cell">{{ pmt.from_date }} → {{ pmt.to_date }}</td>
            <td class="px-2 py-1.5 text-muted-foreground hidden lg:table-cell">{{ pmt.notes || '—' }}</td>
            <td class="px-2 py-1.5 text-muted-foreground whitespace-nowrap hidden md:table-cell">{{ pmt.created_at }}</td>
            <td class="px-2 py-1.5">
              <div class="flex items-center justify-center gap-1">
                <Link
                  :href="route('admin.member-payment.show', pmt.id)"
                  class="inline-flex items-center justify-center rounded-md h-7 w-7 border bg-background hover:bg-muted transition-colors"
                  :title="t.common?.view || 'View'"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                  </svg>
                </Link>
                <Link
                  :href="route('admin.member-payment.edit', pmt.id)"
                  class="inline-flex items-center justify-center rounded-md h-7 w-7 border bg-background hover:bg-muted transition-colors text-amber-500"
                  :title="t.common?.edit || 'Edit'"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                  </svg>
                </Link>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Empty state -->
    <div v-else class="rounded-lg border border-dashed border-border px-4 py-8 text-center space-y-2">
      <p class="text-sm text-muted-foreground">
        {{ t.member?.no_payments || 'No payments recorded for this membership.' }}
      </p>
      <p v-if="!membershipId" class="text-xs text-muted-foreground">
        {{ t.member?.payments_need_membership || 'Save the membership first, then payments can be recorded against it.' }}
      </p>
    </div>
  </div>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
  membership: {
    type: Object,
    default: () => null
  },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const payments = computed(() => props.membership?.member_payments || []);
const membershipId = computed(() => props.membership?.id ?? null);
const membershipNumber = computed(() => props.membership?.membership_number || '');

// Preselects this membership on the payment form so the admin doesn't have to
// find it again in the dropdown.
const createPaymentUrl = computed(() => {
  const base = route('admin.member-payment.create');
  return membershipId.value ? `${base}?membership_id=${membershipId.value}` : base;
});

// Matches the membership Show page's formatting so the same payment reads the
// same in both places.
const formatAmount = (amount) => {
  if (amount === null || amount === undefined) return '-';
  return Number(amount).toFixed(2);
};

const typeLabel = (type) => {
  const labels = {
    commission: t.value.member_payment?.type_commission || 'Commission',
    profit: t.value.member_payment?.type_profit || 'Profit',
    free: t.value.member_payment?.type_free || 'Free',
  };
  return labels[type] || type || '—';
};
</script>
