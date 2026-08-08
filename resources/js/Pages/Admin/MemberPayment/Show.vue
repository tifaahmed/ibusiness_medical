<template>
  <MemberPaymentLayout>
    <div class="max-w-5xl mx-auto mt-4 sm:mt-6 px-3 sm:px-4 lg:px-6 pb-6 sm:pb-8">
      <div class="space-y-4 sm:space-y-6">

        <!-- Member Information -->
        <div class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
          <div class="p-4 sm:p-6 border-b border-border">
            <h2 class="text-lg font-semibold text-foreground">{{ t.member_info_title || 'Member Information' }}</h2>
          </div>
          <div class="p-4 sm:p-6">
            <div class="flex flex-col md:flex-row gap-4 sm:gap-6">
              <div class="flex-shrink-0">
                <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-full overflow-hidden bg-muted flex items-center justify-center">
                  <img
                    v-if="payment.user_avatar"
                    :src="payment.user_avatar"
                    :alt="payment.user_name"
                    class="w-full h-full object-cover"
                  />
                  <span v-else class="text-lg font-semibold text-muted-foreground">{{ (payment.user_name || '?')[0] }}</span>
                </div>
              </div>
              <div class="flex-1 grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                  <dt class="text-xs font-medium text-muted-foreground">{{ t.member_name || 'Name' }}</dt>
                  <dd class="text-sm font-medium text-foreground mt-0.5">{{ payment.user_name || '—' }}</dd>
                </div>
                <div>
                  <dt class="text-xs font-medium text-muted-foreground">{{ t.membership_number || 'Membership No.' }}</dt>
                  <dd class="text-sm font-medium text-foreground mt-0.5">{{ payment.membership_number || '—' }}</dd>
                </div>
                <div>
                  <dt class="text-xs font-medium text-muted-foreground">{{ t.user_phone || 'Phone' }}</dt>
                  <dd class="text-sm font-medium text-foreground mt-0.5">{{ payment.user_phone || '—' }}</dd>
                </div>
                <div>
                  <dt class="text-xs font-medium text-muted-foreground">{{ t.user_email || 'Email' }}</dt>
                  <dd class="text-sm font-medium text-foreground mt-0.5 truncate">{{ payment.user_email || '—' }}</dd>
                </div>
                <div>
                  <dt class="text-xs font-medium text-muted-foreground">{{ t.payment_type || 'Payment Type' }}</dt>
                  <dd class="text-sm font-medium text-foreground mt-0.5">{{ payment.payment_type || '—' }}</dd>
                </div>
                <div>
                  <dt class="text-xs font-medium text-muted-foreground">{{ t.registration_date || 'Registration Date' }}</dt>
                  <dd class="text-sm font-medium text-foreground mt-0.5">{{ payment.registration_date || '—' }}</dd>
                </div>
                <div>
                  <dt class="text-xs font-medium text-muted-foreground">{{ t.expiration_date || 'Expiration Date' }}</dt>
                  <dd class="text-sm font-medium text-foreground mt-0.5">{{ payment.expiration_date || '—' }}</dd>
                </div>
                <div>
                  <dt class="text-xs font-medium text-muted-foreground">{{ t.partner || 'Partner' }}</dt>
                  <dd class="text-sm font-medium text-foreground mt-0.5">{{ payment.partner_name || '—' }}</dd>
                </div>
                <div>
                  <dt class="text-xs font-medium text-muted-foreground">{{ t.is_paid || 'Status' }}</dt>
                  <dd class="mt-0.5">
                    <span
                      class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                      :class="payment.is_paid ? 'bg-green-500/20 text-green-500' : 'bg-red-500/20 text-red-500'"
                    >
                      {{ payment.is_paid ? (t.paid || 'Paid') : (t.unpaid || 'Unpaid') }}
                    </span>
                  </dd>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Payment Details -->
        <div class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
          <div class="p-4 sm:p-6 border-b border-border">
            <h2 class="text-lg font-semibold text-foreground">{{ t.payment_details_title || 'Payment Details' }}</h2>
          </div>
          <div class="p-4 sm:p-6">
            <dl class="grid grid-cols-2 md:grid-cols-4 gap-4">
              <div>
                <dt class="text-xs font-medium text-muted-foreground">{{ t.amount || 'Amount' }}</dt>
                <dd class="text-sm font-medium text-foreground mt-0.5">{{ formatAmount(payment.amount) }}</dd>
              </div>
              <div>
                <dt class="text-xs font-medium text-muted-foreground">{{ t.type || 'Type' }}</dt>
                <dd class="text-sm font-medium text-foreground mt-0.5">
                  <span
                    class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                    :class="typeBadgeClass(payment.type)"
                  >{{ typeLabel(payment.type) }}</span>
                </dd>
              </div>
              <div>
                <dt class="text-xs font-medium text-muted-foreground">{{ t.months_paid || 'Months Paid' }}</dt>
                <dd class="text-sm font-medium text-foreground mt-0.5">{{ payment.months_paid || '—' }}</dd>
              </div>
              <div>
                <dt class="text-xs font-medium text-muted-foreground">{{ t.from_date || 'From Date' }}</dt>
                <dd class="text-sm font-medium text-foreground mt-0.5">{{ payment.from_date || '—' }}</dd>
              </div>
              <div>
                <dt class="text-xs font-medium text-muted-foreground">{{ t.to_date || 'To Date' }}</dt>
                <dd class="text-sm font-medium text-foreground mt-0.5">{{ payment.to_date || '—' }}</dd>
              </div>
              <div class="col-span-full">
                <dt class="text-xs font-medium text-muted-foreground">{{ t.notes || 'Notes' }}</dt>
                <dd class="text-sm font-medium text-foreground mt-0.5">{{ payment.notes || '—' }}</dd>
              </div>
            </dl>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex justify-end gap-3">
          <Link
            :href="route('admin.member-payment.edit', payment.id)"
            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2 btn-golden"
          >
            {{ t.edit || 'Edit' }}
          </Link>
          <Link
            :href="route('admin.member-payment.list')"
            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2"
          >
            {{ t.back || 'Back' }}
          </Link>
        </div>
      </div>
    </div>
  </MemberPaymentLayout>
</template>

<script setup>
import MemberPaymentLayout from "./MemberPaymentLayout.vue";
import { Link, usePage } from "@inertiajs/vue3";
import { computed } from "vue";

defineProps({
  payment: { type: Object, required: true },
});

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
</script>
