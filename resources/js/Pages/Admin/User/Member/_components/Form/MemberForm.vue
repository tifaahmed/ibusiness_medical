<template>
  <div class="space-y-4">
    <!-- User Information Card -->
    <div class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div class="py-2 px-6">
        <div class="title-golden leading-none font-semibold">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path>
            <circle cx="12" cy="7" r="4"></circle>
          </svg>
          {{ t.member?.personal_information || 'Personal Information' }}
        </div>
      </div>
      <div class="px-6">
        <div class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
            <FormInput
              v-model="memberStore.form.name"
              :label="t.common?.name || 'Name'"
              :error="memberStore.validationErrors?.name"
              :placeholder="t.member?.name_placeholder || 'Enter member name'"
              required
            />
            <FormInput
              v-model="phone"
              :label="t.common?.phone || 'Phone'"
              type="tel"
              inputmode="numeric"
              :maxlength="PHONE_LENGTH"
              :counter-max="PHONE_LENGTH"
              :error="memberStore.validationErrors?.phone || phoneHint"
              :placeholder="t.member?.phone_placeholder || 'Enter phone number'"
              required
            />
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
            <FormInput
              v-model="nationalId"
              :label="t.member?.national_id || 'National ID Number'"
              inputmode="numeric"
              :maxlength="NATIONAL_ID_LENGTH"
              :counter-max="NATIONAL_ID_LENGTH"
              :error="memberStore.validationErrors?.national_id"
              :placeholder="t.member?.national_id_placeholder || 'Enter 14-digit National ID number'"
              required
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Duplicate Membership Number Modal -->
    <Modal :show="!!duplicateMember" @close="duplicateMember = null" max-width="lg">
      <div class="bg-card text-card-foreground rounded-xl border border-border shadow-lg overflow-hidden">
        <div class="px-5 py-4 border-b border-border flex items-center gap-3">
          <div class="w-9 h-9 rounded-full bg-destructive/10 text-destructive flex items-center justify-center flex-shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
              <line x1="12" y1="9" x2="12" y2="13"></line>
              <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
          </div>
          <div class="min-w-0">
            <h3 class="text-base font-semibold text-foreground">{{ t.member?.duplicate_title || 'Membership number already in use' }}</h3>
            <p class="text-xs text-muted-foreground">{{ t.member?.duplicate_subtitle || 'This membership number belongs to another member.' }}</p>
          </div>
        </div>
        <div v-if="duplicateMember" class="p-5 space-y-4">
          <div class="flex items-start gap-3">
            <img
              v-if="duplicateMember.avatar_url"
              :src="duplicateMember.avatar_url"
              :alt="duplicateMember.name"
              class="w-14 h-14 rounded-full object-cover flex-shrink-0"
            />
            <div class="flex-1 min-w-0 space-y-1">
              <div class="font-semibold text-foreground truncate" :title="duplicateMember.name">
                {{ duplicateMember.name }}
              </div>
              <div v-if="duplicateMember.email" class="text-xs text-muted-foreground truncate">{{ duplicateMember.email }}</div>
              <div v-if="duplicateMember.phone" class="text-xs text-muted-foreground truncate">{{ duplicateMember.phone }}</div>
            </div>
          </div>
          <dl class="grid grid-cols-2 gap-3 text-xs">
            <div>
              <dt class="text-muted-foreground">{{ t.member?.membership_number || 'Membership #' }}</dt>
              <dd class="font-mono text-foreground">{{ duplicateMember.membership?.membership_number }}</dd>
            </div>
            <div>
              <dt class="text-muted-foreground">{{ t.member?.status || 'Status' }}</dt>
              <dd class="text-foreground">{{ duplicateMember.membership?.is_active ? (t.member?.active || 'Active') : (t.member?.inactive || 'Inactive') }}</dd>
            </div>
            <div v-if="duplicateMember.membership?.registration_date">
              <dt class="text-muted-foreground">{{ t.member?.duplicate_registration || 'Registration' }}</dt>
              <dd class="text-foreground">{{ duplicateMember.membership.registration_date }}</dd>
            </div>
            <div v-if="duplicateMember.membership?.expiration_date">
              <dt class="text-muted-foreground">{{ t.member?.duplicate_expiration || 'Expiration' }}</dt>
              <dd class="text-foreground">{{ duplicateMember.membership.expiration_date }}</dd>
            </div>
            <div v-if="duplicateMember.membership?.company_name">
              <dt class="text-muted-foreground">{{ t.member?.duplicate_company || 'Company' }}</dt>
              <dd class="text-foreground truncate">{{ duplicateMember.membership.company_name }}</dd>
            </div>
            <div v-if="duplicateMember.membership?.partner_name">
              <dt class="text-muted-foreground">{{ t.member?.duplicate_partner || 'Partner' }}</dt>
              <dd class="text-foreground truncate">{{ duplicateMember.membership.partner_name }}</dd>
            </div>
          </dl>
        </div>
        <div class="px-5 py-3 border-t border-border flex justify-end gap-2">
          <button
            type="button"
            @click="duplicateMember = null"
            class="inline-flex items-center justify-center rounded-md text-sm font-medium border bg-background shadow-xs hover:bg-muted h-9 px-4"
          >
            {{ t.member?.close || 'Close' }}
          </button>
          <a
            v-if="duplicateMember?.slug"
            :href="route('admin.user.membership.edit', duplicateMember.slug)"
            target="_blank"
            class="inline-flex items-center justify-center rounded-md text-sm font-medium bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 btn-golden"
          >
            {{ t.member?.view_member_button || 'View Member' }}
          </a>
        </div>
      </div>
    </Modal>

    <!-- Membership Information Card -->
    <div class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div class="py-2 px-6">
        <div class="title-golden leading-none font-semibold">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"></path>
          </svg>
          {{ t.member?.membership_info || 'Membership Information' }}
        </div>
      </div>
      <div class="px-6">
        <div class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start mb-4">
            <FormTranslatableInput
              v-model="jobTitle"
              :label="t.member?.job_title || 'Job Title'"
              :error="memberStore.validationErrors?.job_title"
              :placeholder="t.member?.job_title_placeholder || 'e.g. Software Engineer'"
              :locales="['ar', 'en']"
            />
            <FormSelect
              v-model="memberStore.form.company_id"
              :label="t.member?.company || 'Company'"
              :options="[{ value: null, label: t.member?.none_option || '— None —' }, ...companyOptions]"
              :error="memberStore.validationErrors?.company_id"
              :placeholder="t.member?.company_placeholder || 'Select a company (optional)'"
              :required="requiresPaidMonthlyFields"
            />
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start mb-4">
            <div v-if="partnerLocked">
              <label class="block text-sm font-medium text-white mb-1">
                {{ t.member?.partner || 'Partner' }} <span class="text-destructive">*</span>
              </label>
              <input
                type="text"
                :value="lockedPartnerLabel"
                disabled
                class="w-full py-2 px-3 border border-border text-foreground placeholder:text-white/70 bg-muted/40 rounded-md cursor-not-allowed opacity-75"
              />
              <p class="mt-1 text-[11px] text-muted-foreground">
                {{ t.member?.partner_locked_help || 'Locked to your assigned partner.' }}
              </p>
              <p v-if="memberStore.validationErrors?.partner_id" class="mt-1 text-sm text-destructive">
                {{ memberStore.validationErrors.partner_id }}
              </p>
            </div>
            <FormSelect
              v-else
              v-model="memberStore.form.partner_id"
              :label="t.member?.partner || 'Partner'"
              :options="[{ value: null, label: t.member?.none_option || '— None —' }, ...partnerOptions]"
              :error="memberStore.validationErrors?.partner_id"
              :placeholder="t.member?.partner_placeholder || 'Select a partner (optional)'"
            />
            <FormSelect
              v-model="memberStore.form.sales_id"
              :label="t.sales?.name || 'Sales'"
              :options="[{ value: null, label: t.member?.none_option || '— None —' }, ...salesOptions]"
              :error="memberStore.validationErrors?.sales_id"
              :placeholder="t.member?.sales_placeholder || 'Select sales (optional)'"
              :required="requiresPaidMonthlyFields"
            />
          </div>
          <!-- Address — only governorate + city are shown here. The rest of the
               primary address (street, building, ...) is managed from the
               Addresses tab; its stored values are preserved on save because
               the form still carries them invisibly. -->
          <div class="rounded-lg border border-border p-4 mb-4 space-y-4">
            <div class="flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-golden-yellow">
                <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path>
                <circle cx="12" cy="10" r="3"></circle>
              </svg>
              <h3 class="text-sm font-semibold">{{ t.member?.address_information || 'Address' }}</h3>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <FormSelect
                v-model="memberStore.form.governorate_id"
                :label="t.member?.governorate || 'Governorate'"
                :options="governorateSelectOptions"
                :error="memberStore.validationErrors?.governorate_id"
                :placeholder="t.member?.governorate_select || 'Select a governorate'"
                required
                @update:modelValue="onGovernorateChange"
              />
              <FormSelect
                v-model="memberStore.form.city_id"
                :label="t.member?.city || 'City'"
                :options="[{ value: null, label: t.member?.none_option || '— None —' }, ...citySelectOptions]"
                :error="memberStore.validationErrors?.city_id"
                :placeholder="memberStore.form.governorate_id ? (t.member?.city_placeholder_required || 'Select a city') : (t.member?.city_select_governorate_first || 'Select a governorate first')"
                :disabled="!memberStore.form.governorate_id"
                required
              />
            </div>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-start">
            <div>
              <FormInput
                v-model="memberStore.form.membership_number"
                :label="t.member?.membership_number || 'Membership Number'"
                :error="memberStore.validationErrors?.membership_number || (duplicateMember ? (t.member?.number_in_use || 'This membership number is already in use.') : '')"
                :placeholder="t.member?.membership_number_placeholder || 'Leave empty to auto-generate'"
              />
              <p v-if="checkingNumber" class="text-xs text-muted-foreground mt-1">{{ t.member?.checking_number || 'Checking…' }}</p>
            </div>
            <FormDateInput
              v-model="memberStore.form.registration_date"
              :label="t.member?.registration_date || 'Registration Date'"
              :error="memberStore.validationErrors?.registration_date"
              required
            />
            <FormDateInput
              v-model="memberStore.form.expiration_date"
              :label="t.member?.expiration_date || 'Expiration Date'"
              :error="memberStore.validationErrors?.expiration_date"
              required
            >
              <template #label-action>
                <button
                  type="button"
                  :disabled="!memberStore.form.registration_date"
                  @click="setExpirationOneYearOut"
                  :title="memberStore.form.registration_date
                    ? (t.member?.expiration_one_year_hint || 'Set to one year after the registration date')
                    : (t.member?.expiration_needs_registration || 'Pick a registration date first')"
                  :aria-label="t.member?.expiration_one_year_hint || 'Set to one year after the registration date'"
                  class="inline-flex items-center gap-1 rounded-md border border-border px-1.5 py-0.5 text-[11px] font-medium text-muted-foreground transition-colors hover:text-foreground hover:border-foreground disabled:opacity-40 disabled:pointer-events-none cursor-pointer"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M8 2v4"></path>
                    <path d="M16 2v4"></path>
                    <rect width="18" height="18" x="3" y="4" rx="2"></rect>
                    <path d="M3 10h18"></path>
                    <path d="M12 14v4"></path>
                    <path d="M10 16h4"></path>
                  </svg>
                  {{ t.member?.expiration_one_year || '+1 year' }}
                </button>
              </template>
            </FormDateInput>
          </div>
          <div class="pt-2 space-y-4">
            <div>
              <FormCheckbox
                v-model="memberStore.form.is_active"
                :label="t.member?.active_membership || 'Active Membership'"
                :error="memberStore.validationErrors?.is_active"
              />
              <p class="text-xs text-white/70 mt-1">
                {{ t.member?.active_membership_help || 'When enabled, this membership will be set as active and all other memberships for this user will be deactivated.' }}
              </p>
            </div>
            <div>
              <FormCheckbox
                v-model="memberStore.form.is_visible"
                :label="t.member?.visible_membership || 'Visible Membership'"
                :error="memberStore.validationErrors?.is_visible"
              />
              <p class="text-xs text-white/70 mt-1">
                {{ t.member?.visible_membership_help || 'When enabled, this membership will be visible to the member on their card. Disable to hide this membership from the card view.' }}
              </p>
            </div>
            <div>
              <FormCheckbox
                v-model="memberStore.form.is_paid"
                :label="t.member?.paid_membership || 'Paid Membership'"
                :error="memberStore.validationErrors?.is_paid"
              />
              <p class="text-xs text-white/70 mt-1">
                {{ t.member?.paid_membership_help || 'Check if this membership has been paid for.' }}
              </p>
            </div>
            <div v-if="memberStore.form.is_paid">
              <FormSelect
                v-model="memberStore.form.payment_type"
                :label="t.member?.payment_type || 'Payment Type'"
                :options="paymentTypeOptions"
                :error="memberStore.validationErrors?.payment_type"
              />
              <p class="text-xs text-white/70 mt-1">
                {{ t.member?.payment_type_help || 'Payment type is set automatically based on partner association.' }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Payment Card — only when creating a new, paid membership -->
    <div v-if="showInitialPaymentCard" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div class="py-2 px-6">
        <div class="title-golden leading-none font-semibold">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2z"></path>
            <path d="M12 6v6l4 2"></path>
          </svg>
          {{ t.member?.payment_card_title || 'Payment' }}
        </div>
      </div>
      <div class="px-6">
        <div class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
            <FormInput
              v-model="memberStore.form.initial_payment_amount"
              :label="t.member_payment?.amount || 'Amount'"
              type="number"
              step="0.01"
              :error="memberStore.validationErrors?.initial_payment_amount"
              :placeholder="t.member_payment?.amount_placeholder || '0.00'"
              :disabled="memberStore.form.initial_payment_type === 'free'"
              required
            />
            <FormSelect
              v-model="memberStore.form.initial_payment_type"
              :label="t.member_payment?.type || 'Type'"
              :options="initialPaymentTypeOptions"
              :error="memberStore.validationErrors?.initial_payment_type"
            />
          </div>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-start">
            <FormInput
              v-model="memberStore.form.initial_payment_months_paid"
              :label="t.member_payment?.months_paid || 'Months Paid'"
              type="number"
              min="1"
              :error="memberStore.validationErrors?.initial_payment_months_paid"
              :placeholder="t.member_payment?.months_paid_placeholder || 'e.g. 3'"
              required
            />
            <FormDateInput
              v-model="memberStore.form.initial_payment_from_date"
              :label="t.member_payment?.from_date || 'From Date'"
              :error="memberStore.validationErrors?.initial_payment_from_date"
              readonly
              class="opacity-60 cursor-not-allowed"
              required
            />
            <FormDateInput
              v-model="memberStore.form.initial_payment_to_date"
              :label="t.member_payment?.to_date || 'To Date'"
              :error="memberStore.validationErrors?.initial_payment_to_date"
              readonly
              class="opacity-60 cursor-not-allowed"
              required
            />
          </div>
          <div>
            <FormInput
              v-model="memberStore.form.initial_payment_notes"
              :label="t.member_payment?.notes || 'Notes'"
              :error="memberStore.validationErrors?.initial_payment_notes"
              :placeholder="t.member_payment?.notes_placeholder || 'Optional notes...'"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { FormInput, FormDateInput, FormCheckbox, FormSelect, FormTranslatableInput } from "@/Components/form";
import Modal from "@/Components/Modal.vue";
import { useMemberStore } from "../../Stores/MemberStore";
import { usePage } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";
import { storeToRefs } from "pinia";

const memberStore = useMemberStore();
const { form } = storeToRefs(memberStore);
const page = usePage();
const t = computed(() => page.props.translations?.admin || {});
const companyOptions = computed(() => page.props.companyOptions || []);
const partnerOptions = computed(() => page.props.partnerOptions || []);
const salesOptions = computed(() => page.props.salesOptions || []);
const partnerLocked = computed(() => Boolean(page.props.partnerLocked));
const lockedPartnerId = computed(() => page.props.lockedPartnerId ?? null);
const lockedPartnerLabel = computed(() => {
  const id = lockedPartnerId.value;
  if (id === null) return "";
  const match = partnerOptions.value.find((p) => p.value === id || p.value === Number(id));
  return match?.label ?? "";
});

// Partner-scoped admins can't change the partner — keep the form value pinned
// to the locked partner id at all times, even if a stale value loads from edit.
watch(
  [partnerLocked, lockedPartnerId],
  ([locked, id]) => {
    if (locked && memberStore.form.partner_id !== id) {
      memberStore.form.partner_id = id;
    }
  },
  { immediate: true }
);
const requiresPaidMonthlyFields = computed(() => Boolean(memberStore.form.is_paid) && memberStore.form.payment_type === 'monthly');

// Phone and National ID are digit-only fields with a fixed length, so both are
// sanitised as they're typed and surfaced with a live counter. Phone must be an
// Egyptian mobile number, which always starts with 01.
const PHONE_LENGTH = 11;
const NATIONAL_ID_LENGTH = 14;
const PHONE_PREFIX = '01';

const digitsOnly = (value, max) => String(value ?? '').replace(/\D/g, '').slice(0, max);

const phone = computed({
  get: () => form.value.phone ?? '',
  set: (value) => { form.value.phone = digitsOnly(value, PHONE_LENGTH); },
});

const nationalId = computed({
  get: () => form.value.national_id ?? '',
  set: (value) => { form.value.national_id = digitsOnly(value, NATIONAL_ID_LENGTH); },
});

const phoneHint = computed(() => {
  const value = phone.value;
  if (!value) return '';
  if (!PHONE_PREFIX.startsWith(value) && !value.startsWith(PHONE_PREFIX)) {
    return t.value.member?.phone_must_start_with || 'Phone number must start with 01.';
  }
  return '';
});

const paymentTypeOptions = computed(() => [
  { value: 'yearly', label: t.value.member?.payment_type_yearly || 'Yearly' },
  { value: 'monthly', label: t.value.member?.payment_type_monthly || 'Monthly' },
]);

// Default payment_type to monthly when partner is selected
watch(() => memberStore.form.partner_id, (partnerId) => {
  if (partnerId && !memberStore.form.payment_type && memberStore.form.is_paid) {
    memberStore.form.payment_type = 'monthly';
  }
});

// Reset payment_type when is_paid becomes false
watch(() => memberStore.form.is_paid, (isPaid) => {
  if (!isPaid) {
    memberStore.form.payment_type = '';
  } else if (memberStore.form.partner_id && !memberStore.form.payment_type) {
    memberStore.form.payment_type = 'monthly';
  }
});

// Payment card — same fields as the standalone member-payment create form.
// On create, shown whenever the membership is paid. On edit, shown as soon as
// the admin marks the membership paid while it has no payment recorded yet, so
// the first payment can be entered here instead of in a second trip to the
// member-payment form. Once a payment exists it belongs to that module.
const showInitialPaymentCard = computed(() => {
  if (!memberStore.form.is_paid) return false;
  if (!memberStore.form.id) return true;
  return !memberStore.form.has_member_payments;
});

const initialPaymentTypeOptions = computed(() => [
  { value: 'commission', label: t.value.member_payment?.type_commission || 'Commission' },
  { value: 'profit', label: t.value.member_payment?.type_profit || 'Profit' },
  { value: 'free', label: t.value.member_payment?.type_free || 'Free' },
]);

const addMonths = (dateStr, months) => {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  d.setMonth(d.getMonth() + months);
  return d.toISOString().split('T')[0];
};

// Payment period is fully derived, never hand-edited: From Date always
// mirrors the membership's Registration Date, and To Date is From Date +
// Months Paid — both inputs are read-only in the template.
const recalcPaymentPeriod = () => {
  const months = parseInt(memberStore.form.initial_payment_months_paid) || 0;
  const fromDate = memberStore.form.registration_date;
  if (months <= 0 || !fromDate) return;
  memberStore.form.initial_payment_from_date = fromDate;
  memberStore.form.initial_payment_to_date = addMonths(fromDate, months);
};

// Shortcut on the Expiration Date label: the usual membership term is one year
// from registration, so offer it in one click rather than making admins count.
const setExpirationOneYearOut = () => {
  const registration = memberStore.form.registration_date;
  if (!registration) return;
  memberStore.form.expiration_date = addMonths(registration, 12);
};

watch(() => memberStore.form.initial_payment_months_paid, recalcPaymentPeriod);
watch(() => memberStore.form.registration_date, recalcPaymentPeriod);

watch(() => memberStore.form.initial_payment_type, (newType) => {
  if (newType === 'free') {
    memberStore.form.initial_payment_amount = 0;
  }
});

const governorateOptions = computed(() => page.props.governorateOptions || []);
const governorateSelectOptions = computed(() => governorateOptions.value.map(g => ({ value: g.value, label: g.label })));
const citySelectOptions = computed(() => {
  const id = memberStore.form.governorate_id;
  if (!id) return [];
  const g = governorateOptions.value.find(g => g.value === id || g.value === Number(id));
  return g?.cities ? g.cities.map(c => ({ value: c.value, label: c.label })) : [];
});

function onGovernorateChange() {
  const currentCityId = memberStore.form.city_id;
  if (!currentCityId) return;
  const stillValid = citySelectOptions.value.some(c => c.value === currentCityId);
  if (!stillValid) memberStore.form.city_id = null;
}

const jobTitle = computed({
  get: () => {
    const jt = form.value.job_title;
    if (!jt || typeof jt !== 'object' || Array.isArray(jt)) return {};
    return jt;
  },
  set: (value) => { form.value.job_title = value; },
});

const duplicateMember = ref(null);
const checkingNumber = ref(false);
let checkTimer = null;
let checkSeq = 0;

const editingUserId = computed(() => page.props.member?.id ?? null);
const editingMembershipId = computed(() => page.props.member?.membership?.id ?? null);

watch(() => form.value.membership_number, (raw) => {
  const value = String(raw ?? '').trim();
  if (checkTimer) clearTimeout(checkTimer);
  duplicateMember.value = null;
  if (!value) {
    checkingNumber.value = false;
    return;
  }
  checkingNumber.value = true;
  const seq = ++checkSeq;
  checkTimer = setTimeout(async () => {
    try {
      const params = { membership_number: value };
      if (editingMembershipId.value) params.exclude_membership_id = editingMembershipId.value;
      if (editingUserId.value) params.exclude_user_id = editingUserId.value;
      const { data } = await axios.get('/api/membership-number/check', { params });
      if (seq !== checkSeq) return;
      duplicateMember.value = data?.unique === false ? data.member : null;
    } catch (e) {
      if (seq === checkSeq) duplicateMember.value = null;
    } finally {
      if (seq === checkSeq) checkingNumber.value = false;
    }
  }, 400);
});
</script>

<style lang="scss" scoped></style>
