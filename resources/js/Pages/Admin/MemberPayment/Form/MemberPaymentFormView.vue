<template>
  <MemberPaymentLayout>
    <div class="container mx-auto px-4 py-6 md:px-6 lg:px-8 relative z-10">
      <div class="space-y-4">
        <form @submit.prevent="handleSubmit" class="space-y-4">
          <div class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
            <div class="py-2 px-6">
              <div class="title-golden leading-none font-semibold">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
                  <path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2z"></path>
                  <path d="M12 6v6l4 2"></path>
                </svg>
                {{ isEdit ? (t.edit_title || 'Edit Payment') : (t.create_title || 'Create Payment') }}
              </div>
            </div>
            <div class="px-6">
              <div class="space-y-4">
                <!-- Membership (Searchable Select) -->
                <FormSearchableSelect
                  v-model="memberPaymentStore.form.membership_id"
                  :label="t.membership || 'Membership'"
                  :options="membershipOptions"
                  :error="memberPaymentStore.validationErrors?.membership_id"
                  :placeholder="t.membership_placeholder || 'Select membership'"
                  required
                />

                <!-- Selected Member Info + Payment History -->
                <div v-if="selectedMembership" class="rounded-lg border border-border p-4 space-y-3 bg-muted/30">
                  <div class="flex items-center justify-between">
                    <Link :href="route('admin.user.membership.show', selectedMembership.slug)" class="font-semibold text-sm text-foreground hover:text-primary hover:underline inline-flex items-center gap-1">
                      {{ selectedMembership.user_name }}
                      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                    </Link>
                    <span class="text-xs text-muted-foreground">#{{ selectedMembership.membership_number }}</span>
                  </div>
                  <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                    <div>
                      <span class="text-muted-foreground">{{ t.payment_type_label || 'Payment Type' }}:</span>
                      <span class="ml-1 font-medium">{{ paymentTypeLabel }}</span>
                    </div>
                    <div>
                      <span class="text-muted-foreground">{{ t.registration_date_label || 'Registration' }}:</span>
                      <span class="ml-1 font-medium">{{ selectedMembership.registration_date || '—' }}</span>
                    </div>
                    <div>
                      <span class="text-muted-foreground">{{ t.total_months_paid || 'Total Months Paid' }}:</span>
                      <span class="ml-1 font-medium">{{ totalMonthsPaid }}</span>
                    </div>
                    <div>
                      <span class="text-muted-foreground">{{ t.covered_until || 'Covered Until' }}:</span>
                      <span class="ml-1 font-medium">{{ coveredUntil }}</span>
                    </div>
                  </div>
                  <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs pt-2 border-t border-border/50">
                    <div>
                      <span class="text-muted-foreground">{{ t.days_since_reg || 'Days Since Reg.' }}:</span>
                      <span class="ml-1 font-medium">{{ daysSinceRegistration }}</span>
                    </div>
                    <div>
                      <span class="text-muted-foreground">{{ t.days_covered || 'Days Covered' }}:</span>
                      <span class="ml-1 font-medium">{{ coveredDays }}</span>
                    </div>
                    <div>
                      <span class="text-muted-foreground">{{ t.outstanding_days || 'Outstanding Days' }}:</span>
                      <span class="ml-1 font-medium" :class="outstandingDays > 0 ? 'text-destructive' : outstandingDays < 0 ? 'text-green-500' : ''">{{ outstandingDays > 0 ? '+' + outstandingDays : outstandingDays }}</span>
                    </div>
                    <div>
                      <span class="text-muted-foreground">{{ t.payment_status || 'Payment Status' }}:</span>
                      <span class="ml-1 font-medium" :class="selectedMembership.is_paid ? 'text-green-500' : 'text-destructive'">{{ selectedMembership.is_paid ? (t.paid || 'Paid') : (t.unpaid || 'Unpaid') }}</span>
                    </div>
                  </div>

                  <!-- Previous Payments List -->
                  <div v-if="selectedMembership.payments.length > 0">
                    <h4 class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2">{{ t.previous_payments || 'Previous Payments' }}</h4>
                    <div class="overflow-x-auto">
                      <table class="w-full text-xs">
                        <thead>
                          <tr class="border-b border-border">
                            <th class="text-left py-1 px-2 font-medium text-muted-foreground">{{ t.col_amount || 'Amount' }}</th>
                            <th class="text-left py-1 px-2 font-medium text-muted-foreground">{{ t.col_months_paid || 'Months' }}</th>
                            <th class="text-left py-1 px-2 font-medium text-muted-foreground">{{ t.col_from_date || 'From' }}</th>
                            <th class="text-left py-1 px-2 font-medium text-muted-foreground">{{ t.col_to_date || 'To' }}</th>
                            <th class="text-left py-1 px-2 font-medium text-muted-foreground">{{ t.notes || 'Notes' }}</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr
                            v-for="payment in enrichedPayments"
                            :key="payment.id"
                            class="border-b border-border/50 transition-all duration-500"
                            :class="{
                              'bg-primary/10 hover:bg-primary/15': payment.isEditing,
                              'bg-amber-500/10 hover:bg-amber-500/15': payment.isShifted,
                            }"
                          >
                            <td class="py-1 px-2">
                              <span class="flex items-center gap-1">
                                <span v-if="payment.isEditing" class="inline-block w-1.5 h-1.5 rounded-full bg-primary animate-pulse shrink-0"></span>
                                <span v-if="payment.isShifted" class="inline-block w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse shrink-0"></span>
                                {{ payment.amount }}
                              </span>
                            </td>
                            <td class="py-1 px-2">{{ payment.months_paid }}</td>
                            <td class="py-1 px-2">
                              <span v-if="payment.isEditing" class="text-primary font-semibold">{{ payment.from_date }}</span>
                              <template v-else-if="payment.isShifted">
                                <span class="line-through text-muted-foreground/60">{{ payment.from_date }}</span>
                                <span class="ml-1 text-amber-400 font-semibold animate-fadeIn">{{ payment.new_from_date }}</span>
                              </template>
                              <span v-else>{{ payment.from_date }}</span>
                            </td>
                            <td class="py-1 px-2">
                              <template v-if="payment.isEditing">
                                <span v-if="payment.to_date !== payment.new_to_date" class="line-through text-muted-foreground/60">{{ payment.to_date }}</span>
                                <span :class="{'ml-1 text-amber-400 font-semibold animate-fadeIn': payment.to_date !== payment.new_to_date, 'text-primary font-semibold': payment.to_date === payment.new_to_date}">{{ payment.new_to_date }}</span>
                              </template>
                              <template v-else-if="payment.isShifted">
                                <span class="line-through text-muted-foreground/60">{{ payment.to_date }}</span>
                                <span class="ml-1 text-amber-400 font-semibold animate-fadeIn">{{ payment.new_to_date }}</span>
                              </template>
                              <span v-else>{{ payment.to_date }}</span>
                            </td>
                            <td class="py-1 px-2 max-w-[120px] truncate">{{ payment.notes || '—' }}</td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                    <div v-if="shiftedCount > 0" class="mt-2 flex items-center gap-1.5 text-xs text-amber-400 animate-fadeIn">
                      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                      <span>{{ shiftedCount }} {{ t.shifted_notice || 'subsequent payment(s) will be shifted' }}</span>
                    </div>
                  </div>
                  <p v-else class="text-xs text-muted-foreground">{{ t.no_previous_payments || 'No previous payments for this membership.' }}</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
                  <FormInput
                    v-model="memberPaymentStore.form.amount"
                    :label="t.amount || 'Amount'"
                    type="number"
                    step="0.01"
                    :error="memberPaymentStore.validationErrors?.amount"
                    :placeholder="t.amount_placeholder || '0.00'"
                    :disabled="memberPaymentStore.form.type === 'free'"
                    required
                  />
                  <div>
                    <label class="flex items-center gap-1.5 sm:gap-2 text-xs leading-none font-medium select-none w-full mb-2">
                      {{ t.type || 'Type' }}
                    </label>
                    <div class="grid grid-cols-3 gap-2">
                      <label
                        class="flex flex-col items-center justify-center gap-1.5 p-3 rounded-lg border-2 cursor-pointer transition-all"
                        :class="memberPaymentStore.form.type === 'commission'
                          ? 'border-amber-500 bg-amber-500/10 shadow-sm shadow-amber-500/20'
                          : 'border-border bg-transparent hover:border-amber-500/50 hover:bg-amber-500/5'"
                      >
                        <input type="radio" value="commission" v-model="memberPaymentStore.form.type" class="sr-only" />
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                          :class="memberPaymentStore.form.type === 'commission' ? 'text-amber-500' : 'text-muted-foreground'">
                          <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
                        </svg>
                        <span class="text-xs font-medium" :class="memberPaymentStore.form.type === 'commission' ? 'text-amber-500' : 'text-muted-foreground'">{{ t.type_commission || 'Commission' }}</span>
                      </label>
                      <label
                        class="flex flex-col items-center justify-center gap-1.5 p-3 rounded-lg border-2 cursor-pointer transition-all"
                        :class="memberPaymentStore.form.type === 'profit'
                          ? 'border-blue-500 bg-blue-500/10 shadow-sm shadow-blue-500/20'
                          : 'border-border bg-transparent hover:border-blue-500/50 hover:bg-blue-500/5'"
                      >
                        <input type="radio" value="profit" v-model="memberPaymentStore.form.type" class="sr-only" />
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                          :class="memberPaymentStore.form.type === 'profit' ? 'text-blue-500' : 'text-muted-foreground'">
                          <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                        </svg>
                        <span class="text-xs font-medium" :class="memberPaymentStore.form.type === 'profit' ? 'text-blue-500' : 'text-muted-foreground'">{{ t.type_profit || 'Profit' }}</span>
                      </label>
                      <label
                        class="flex flex-col items-center justify-center gap-1.5 p-3 rounded-lg border-2 cursor-pointer transition-all"
                        :class="memberPaymentStore.form.type === 'free'
                          ? 'border-purple-500 bg-purple-500/10 shadow-sm shadow-purple-500/20'
                          : 'border-border bg-transparent hover:border-purple-500/50 hover:bg-purple-500/5'"
                      >
                        <input type="radio" value="free" v-model="memberPaymentStore.form.type" class="sr-only" />
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                          :class="memberPaymentStore.form.type === 'free' ? 'text-purple-500' : 'text-muted-foreground'">
                          <path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2z"/><polyline points="9 12 11 14 15 10"/>
                        </svg>
                        <span class="text-xs font-medium" :class="memberPaymentStore.form.type === 'free' ? 'text-purple-500' : 'text-muted-foreground'">{{ t.type_free || 'Free' }}</span>
                      </label>
                    </div>
                  </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-start">
                  <FormInput
                    v-model="memberPaymentStore.form.months_paid"
                    :label="t.months_paid || 'Months Paid'"
                    type="number"
                    min="1"
                    :error="memberPaymentStore.validationErrors?.months_paid"
                    :placeholder="t.months_paid_placeholder || 'e.g. 3'"
                    required
                  />
                  <FormDateInput
                    v-model="memberPaymentStore.form.from_date"
                    :label="t.from_date || 'From Date'"
                    :error="memberPaymentStore.validationErrors?.from_date"
                    readonly
                    required
                  />
                  <FormDateInput
                    v-model="memberPaymentStore.form.to_date"
                    :label="t.to_date || 'To Date'"
                    :error="memberPaymentStore.validationErrors?.to_date"
                    readonly
                    required
                  />
                </div>
                <div>
                  <FormInput
                    v-model="memberPaymentStore.form.notes"
                    :label="t.notes || 'Notes'"
                    :error="memberPaymentStore.validationErrors?.notes"
                    :placeholder="t.notes_placeholder || 'Optional notes...'"
                  />
                </div>
              </div>
            </div>
          </div>

          <div class="flex justify-end gap-3">
            <Link
              :href="route('admin.member-payment.list')"
              class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2"
            >
              {{ t.cancel || 'Cancel' }}
            </Link>
            <button
              type="submit"
              :disabled="memberPaymentStore.form.processing"
              class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2 min-w-[140px] btn-golden"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                <path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"></path>
                <path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"></path>
                <path d="M7 3v4a1 1 0 0 0 1 1h7"></path>
              </svg>
              {{ isEdit ? (t.update_button || 'Update Payment') : (t.create_button || 'Create Payment') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </MemberPaymentLayout>
</template>

<script setup>
import MemberPaymentLayout from "../MemberPaymentLayout.vue";
import { FormInput, FormDateInput } from "@/Components/form";
import FormSearchableSelect from "@/Components/form/FormSearchableSelect.vue";
import { useMemberPaymentStore } from "../Stores/MemberPaymentStore";
import { Link, usePage } from "@inertiajs/vue3";
import { computed, onMounted, watch } from 'vue';

const props = defineProps({
  payment: { type: Object, default: null },
  memberships: { type: Array, default: () => [] },
});

const isEdit = computed(() => !!props.payment);

const page = usePage();
const t = computed(() => page.props.translations?.admin?.member_payment || {});

const memberPaymentStore = useMemberPaymentStore();

const membershipOptions = computed(() =>
  props.memberships.map(m => ({
    value: String(m.id),
    label: `${m.membership_number}${m.user_name ? ' — ' + m.user_name : ''}`,
  }))
);

const selectedMembership = computed(() => {
  if (!memberPaymentStore.form.membership_id) return null;
  return props.memberships.find(m => String(m.id) === String(memberPaymentStore.form.membership_id)) || null;
});

const totalMonthsPaid = computed(() => {
  if (!selectedMembership.value) return 0;
  return selectedMembership.value.payments.reduce((sum, p) => sum + (Number(p.months_paid) || 0), 0);
});

const coveredUntil = computed(() => {
  if (!selectedMembership.value || selectedMembership.value.payments.length === 0) return '—';
  const last = selectedMembership.value.payments[0];
  return last.to_date || '—';
});

const daysSinceRegistration = computed(() => {
  if (!selectedMembership.value?.registration_date) return '—';
  const reg = new Date(selectedMembership.value.registration_date);
  const now = new Date();
  const diff = Math.floor((now - reg) / (1000 * 60 * 60 * 24));
  return diff >= 0 ? diff : '—';
});

const coveredDays = computed(() => {
  if (!selectedMembership.value) return 0;
  return selectedMembership.value.payments.reduce((sum, p) => {
    if (!p.from_date || !p.to_date) return sum;
    const from = new Date(p.from_date);
    const to = new Date(p.to_date);
    const days = Math.floor((to - from) / (1000 * 60 * 60 * 24)) + 1;
    return sum + (days > 0 ? days : 0);
  }, 0);
});

const outstandingDays = computed(() => {
  if (daysSinceRegistration === '—') return 0;
  return daysSinceRegistration.value - coveredDays.value;
});

const paymentTypeLabel = computed(() => {
  if (!selectedMembership.value) return '—';
  return selectedMembership.value.payment_type === 'yearly' ? 'Yearly' : 'Monthly';
});

const addDays = (dateStr, days) => {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  d.setDate(d.getDate() + days);
  return d.toISOString().split('T')[0];
};

const diffInDays = computed(() => {
  if (!isEdit.value || !selectedMembership.value) return 0;
  const newToDate = memberPaymentStore.form.to_date;
  const oldToDate = props.payment?.to_date;
  if (!oldToDate || !newToDate) return 0;
  const oldD = new Date(oldToDate);
  const newD = new Date(newToDate);
  return Math.floor((newD - oldD) / (1000 * 60 * 60 * 24));
});

const enrichedPayments = computed(() => {
  const payments = selectedMembership.value?.payments || [];
  if (!isEdit.value || diffInDays.value === 0) {
    return payments.map(p => ({ ...p, isEditing: false, isShifted: false }));
  }

  const currentId = props.payment.id;
  const currentToDate = props.payment.to_date;
  const currentFromDate = props.payment.from_date;

  return payments.map(p => {
    if (p.id === currentId) {
      return {
        ...p,
        isEditing: true,
        isShifted: false,
        new_from_date: memberPaymentStore.form.from_date,
        new_to_date: memberPaymentStore.form.to_date,
      };
    }
    if (p.from_date && currentToDate && new Date(p.from_date) > new Date(currentToDate)) {
      return {
        ...p,
        isEditing: false,
        isShifted: true,
        new_from_date: addDays(p.from_date, diffInDays.value),
        new_to_date: addDays(p.to_date, diffInDays.value),
      };
    }
    return { ...p, isEditing: false, isShifted: false };
  });
});

const shiftedCount = computed(() =>
  enrichedPayments.value.filter(p => p.isShifted).length
);

const addMonths = (dateStr, months) => {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  d.setMonth(d.getMonth() + months);
  return d.toISOString().split('T')[0];
};

const autoCalcDates = () => {
  if (!selectedMembership.value) return;
  const months = parseInt(memberPaymentStore.form.months_paid) || 0;
  if (months <= 0) return;

  if (isEdit.value) {
    if (memberPaymentStore.form.from_date) {
      memberPaymentStore.form.to_date = addMonths(memberPaymentStore.form.from_date, months);
    }
  } else {
    const payments = selectedMembership.value.payments;
    let fromDate;
    if (payments.length > 0 && payments[0].to_date) {
      const last = new Date(payments[0].to_date);
      last.setDate(last.getDate() + 1);
      fromDate = last.toISOString().split('T')[0];
    } else if (selectedMembership.value.registration_date) {
      fromDate = selectedMembership.value.registration_date;
    } else {
      return;
    }
    memberPaymentStore.form.from_date = fromDate;
    memberPaymentStore.form.to_date = addMonths(fromDate, months);
  }
};

watch(() => memberPaymentStore.form.months_paid, () => {
  if (selectedMembership.value) autoCalcDates();
});

watch(() => memberPaymentStore.form.type, (newType) => {
  if (newType === 'free') {
    memberPaymentStore.form.amount = 0;
  }
});

watch(() => selectedMembership.value, (m) => {
  if (m && !isEdit.value) autoCalcDates();
});

onMounted(() => {
  if (props.payment) {
    memberPaymentStore.setPayment(props.payment);
  } else {
    memberPaymentStore.initializeForm();
    // ?membership_id= lets other pages (e.g. the member edit Payments tab)
    // land here with the membership already chosen.
    const requested = new URLSearchParams(window.location.search).get('membership_id');
    if (requested && props.memberships.some(m => String(m.id) === String(requested))) {
      memberPaymentStore.form.membership_id = requested;
    }
    autoCalcDates();
  }
});

const handleSubmit = () => {
  if (isEdit.value) {
    memberPaymentStore.updatePayment();
  } else {
    memberPaymentStore.submitForm();
  }
};
</script>

<style scoped>
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-4px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-fadeIn {
  animation: fadeIn 0.4s ease-out;
}
</style>
