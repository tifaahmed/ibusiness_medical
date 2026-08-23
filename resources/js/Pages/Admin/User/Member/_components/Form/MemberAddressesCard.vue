<template>
  <div class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
    <div class="py-2 px-6">
      <div class="title-golden leading-none font-semibold flex items-center justify-between">
        <div class="flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path>
            <circle cx="12" cy="10" r="3"></circle>
          </svg>
          {{ t.member?.addresses || 'Addresses' }}
        </div>
        <button
          v-if="!showAddForm"
          @click.stop="showAddForm = true; editingAddress = null"
          type="button"
          class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded-md bg-primary text-primary-foreground hover:bg-primary/90 transition-colors"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 12h14"></path>
            <path d="M12 5v14"></path>
          </svg>
          {{ t.member?.add_address || 'Add Address' }}
        </button>
      </div>
    </div>

    <div class="px-6">
      <!-- Add/Edit Form -->
      <div v-if="showAddForm || editingAddress" class="mb-6 p-4 bg-accent/50 rounded-lg border border-border">
        <h3 class="text-sm font-semibold mb-4 text-white">
          {{ editingAddress ? (t.member?.edit_address || 'Edit Address') : (t.member?.add_new_address || 'Add New Address') }}
        </h3>
        <form @submit.stop.prevent="handleSubmit" class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <FormSelect
              v-model="form.type"
              :label="t.member?.address_type || 'Address Type'"
              :options="typeOptions"
              :error="errors.type"
              required
            />
            <div class="flex items-end pb-2">
              <span
                class="inline-flex items-center px-2.5 py-1 rounded text-xs font-medium border"
                :class="typeBadgeClass(form.type)"
              >
                {{ getTypeLabel(form.type) }}
              </span>
            </div>
            <FormSelect
              v-model="form.governorate_id"
              :label="t.member?.governorate || 'Governorate'"
              :options="[{ value: null, label: t.member?.none_option || '— None —' }, ...governorateOptions]"
              :error="errors.governorate_id"
              :placeholder="t.member?.governorate_placeholder || 'Select a governorate (optional)'"
            />
            <FormSelect
              v-model="form.city_id"
              :label="t.member?.city || 'City'"
              :options="[{ value: null, label: t.member?.none_option || '— None —' }, ...cityOptions]"
              :error="errors.city_id"
              :placeholder="form.governorate_id ? (t.member?.city_placeholder || 'Select a city (optional)') : (t.member?.city_select_governorate_first || 'Select a governorate first')"
              :disabled="!form.governorate_id"
            />
            <FormTextarea
              v-model="form.address"
              :label="t.member?.address_label || 'Address'"
              :error="errors.address"
              rows="2"
              :placeholder="t.member?.address_placeholder || 'Area / address description'"
            />
            <FormInput
              v-model="form.street"
              :label="t.member?.street || 'Street'"
              :error="errors.street"
              :placeholder="t.member?.street_placeholder || 'Enter street'"
            />
            <FormInput
              v-model="form.building_number"
              :label="t.member?.building_number || 'Building Number'"
              :error="errors.building_number"
              :placeholder="t.member?.building_number_placeholder || 'Enter building number'"
            />
            <FormInput
              v-model="form.apartment_number"
              :label="t.member?.apartment_number || 'Apartment Number'"
              :error="errors.apartment_number"
              :placeholder="t.member?.apartment_number_placeholder || 'Enter apartment number'"
            />
            <FormInput
              v-model="form.floor_number"
              :label="t.member?.floor_number || 'Floor Number'"
              :error="errors.floor_number"
              :placeholder="t.member?.floor_number_placeholder || 'Enter floor number'"
            />
            <FormInput
              v-model="form.special_mark"
              :label="t.member?.special_mark || 'Special Mark in the Building'"
              :error="errors.special_mark"
              :placeholder="t.member?.special_mark_placeholder || 'e.g. next to the pharmacy, green gate'"
            />
          </div>
          <div class="flex gap-3 justify-end">
            <button
              type="button"
              @click.stop="cancelForm"
              class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2"
            >
              {{ t.common?.cancel || 'Cancel' }}
            </button>
            <button
              type="submit"
              :disabled="isSubmitting"
              class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2"
            >
              {{ editingAddress ? (t.member?.update_address || 'Update Address') : (t.member?.add_address || 'Add Address') }}
            </button>
          </div>
        </form>
      </div>

      <!-- Addresses List -->
      <div v-if="addresses.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div
          v-for="address in addresses"
          :key="address.id"
          class="p-4 bg-accent/30 rounded-lg border border-border hover:bg-accent/50 transition-colors"
        >
          <div class="flex items-start justify-between gap-4">
            <div class="flex items-start gap-3 flex-1 min-w-0">
              <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-muted flex items-center justify-center border border-border">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white/70">
                  <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path>
                  <circle cx="12" cy="10" r="3"></circle>
                </svg>
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1 flex-wrap">
                  <span
                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium border"
                    :class="typeBadgeClass(address.type)"
                  >
                    {{ address.type_label || getTypeLabel(address.type) }}
                  </span>
                </div>
                <p v-if="address.governorate_label || address.city_label" class="text-sm text-white mb-1">
                  <span class="font-medium">{{ [address.governorate_label, address.city_label].filter(Boolean).join(' - ') }}</span>
                </p>
                <p v-if="address.address" class="text-sm text-white/80 mb-2">{{ address.address }}</p>
                <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-white/70">
                  <span v-if="address.street">
                    {{ t.member?.street || 'Street' }}: {{ address.street }}
                  </span>
                  <span v-if="address.building_number">
                    {{ t.member?.building_number || 'Building' }}: {{ address.building_number }}
                  </span>
                  <span v-if="address.apartment_number">
                    {{ t.member?.apartment_number || 'Apartment' }}: {{ address.apartment_number }}
                  </span>
                  <span v-if="address.floor_number">
                    {{ t.member?.floor_number || 'Floor' }}: {{ address.floor_number }}
                  </span>
                </div>
                <p v-if="address.special_mark" class="text-xs text-white/60 mt-2 italic">
                  {{ t.member?.special_mark_short || 'Mark' }}: {{ address.special_mark }}
                </p>
              </div>
            </div>
            <div class="flex gap-2 flex-shrink-0">
              <button
                @click.stop="editAddress(address)"
                type="button"
                class="p-2 rounded-md hover:bg-accent transition-colors"
                :title="t.common?.edit || 'Edit'"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
              </button>
              <button
                @click.stop="deleteAddress(address)"
                type="button"
                class="p-2 rounded-md hover:bg-destructive/20 transition-colors"
                :title="t.common?.delete || 'Delete'"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-destructive">
                  <polyline points="3 6 5 6 21 6"></polyline>
                  <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                </svg>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else-if="!showAddForm" class="text-center py-8 text-white/70">
        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="mx-auto mb-4 opacity-50 text-white/50">
          <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path>
          <circle cx="12" cy="10" r="3"></circle>
        </svg>
        <p class="text-white">{{ t.member?.no_addresses || 'No addresses added yet.' }}</p>
        <p class="text-sm mt-1 text-white/80">{{ t.member?.add_address_help || 'Click "Add Address" to get started.' }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { FormInput, FormSelect, FormTextarea } from '@/Components/form';
import { useNotification } from '@/composables/useNotification';

const props = defineProps({
  addresses: {
    type: Array,
    default: () => []
  },
  userSlug: {
    type: String,
    required: true
  },
  membershipSlug: {
    type: String,
    required: false,
    default: null
  }
});

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const notification = useNotification();
const showAddForm = ref(false);
const editingAddress = ref(null);
const isSubmitting = ref(false);
const errors = ref({});

const typeOptions = computed(() => {
  const options = page.props.addressTypeOptions || [];
  return options.map(opt => ({
    value: opt.value,
    label: t.value.member?.[`address_type_${opt.value}`] || opt.label
  }));
});

const governorateOptions = computed(() =>
  (page.props.governorateOptions || []).map(g => ({ value: g.value, label: g.label }))
);

const cityOptions = computed(() => {
  const id = form.value.governorate_id;
  if (!id) return [];
  const g = (page.props.governorateOptions || []).find(g => g.value === id || g.value === Number(id));
  return g ? g.cities.map(c => ({ value: c.value, label: c.label })) : [];
});

const emptyForm = () => ({
  type: 'home',
  address: '',
  street: '',
  governorate_id: null,
  city_id: null,
  building_number: '',
  apartment_number: '',
  floor_number: '',
  special_mark: ''
});

const form = ref(emptyForm());

// Keep the city select honest when the governorate changes.
watch(() => form.value.governorate_id, () => {
  const currentCityId = form.value.city_id;
  if (currentCityId && !cityOptions.value.some(c => c.value === currentCityId)) {
    form.value.city_id = null;
  }
});

const resetForm = () => {
  form.value = emptyForm();
  errors.value = {};
};

const cancelForm = () => {
  showAddForm.value = false;
  editingAddress.value = null;
  resetForm();
};

const editAddress = (address) => {
  editingAddress.value = address;
  showAddForm.value = true;
  form.value = {
    type: address.type || 'home',
    address: address.address || '',
    street: address.street || '',
    governorate_id: address.governorate_id ?? null,
    city_id: address.city_id ?? null,
    building_number: address.building_number || '',
    apartment_number: address.apartment_number || '',
    floor_number: address.floor_number || '',
    special_mark: address.special_mark || ''
  };
  errors.value = {};
};

const getTypeLabel = (value) => {
  const option = typeOptions.value.find(opt => opt.value === value);
  return option ? option.label : value;
};

const typeBadgeClass = (value) => ({
  'bg-sky-500/25 text-sky-200 border-sky-500/40': value === 'home',
  'bg-violet-500/25 text-violet-200 border-violet-500/40': value === 'work',
  'bg-amber-500/25 text-amber-200 border-amber-500/40': value === 'other',
});

const buildPayload = () => {
  const payload = {
    type: form.value.type,
    address: form.value.address || '',
    street: form.value.street || '',
    governorate_id: form.value.governorate_id ?? '',
    city_id: form.value.city_id ?? '',
    building_number: form.value.building_number || '',
    apartment_number: form.value.apartment_number || '',
    floor_number: form.value.floor_number || '',
    special_mark: form.value.special_mark || '',
  };

  if (payload.address === '') delete payload.address;
  if (payload.street === '') delete payload.street;
  if (payload.governorate_id === '') delete payload.governorate_id;
  if (payload.city_id === '') delete payload.city_id;
  if (payload.building_number === '') delete payload.building_number;
  if (payload.apartment_number === '') delete payload.apartment_number;
  if (payload.floor_number === '') delete payload.floor_number;
  if (payload.special_mark === '') delete payload.special_mark;

  return payload;
};

const handleSubmit = () => {
  if (!props.membershipSlug) {
    notification.error(t.value.member?.no_active_membership || 'No active membership found. Please create a membership first.');
    return;
  }

  errors.value = {};
  isSubmitting.value = true;

  if (editingAddress.value) {
    router.put(
      route('admin.user.membership.address.update', [
        props.userSlug,
        props.membershipSlug,
        editingAddress.value.id
      ]),
      buildPayload(),
      {
        preserveScroll: true,
        onSuccess: () => {
          notification.success(t.value.member?.address_updated || 'Address updated successfully');
          cancelForm();
          router.reload({ only: ['member'] });
        },
        onError: (pageErrors) => {
          errors.value = pageErrors;
          notification.error(t.value.member?.fix_errors || 'Please fix the errors and try again');
        },
        onFinish: () => {
          isSubmitting.value = false;
        }
      }
    );
  } else {
    router.post(
      route('admin.user.membership.address.store', [
        props.userSlug,
        props.membershipSlug
      ]),
      buildPayload(),
      {
        preserveScroll: true,
        onSuccess: () => {
          notification.success(t.value.member?.address_added || 'Address added successfully');
          cancelForm();
          router.reload({ only: ['member'] });
        },
        onError: (pageErrors) => {
          errors.value = pageErrors;
          notification.error(t.value.member?.fix_errors || 'Please fix the errors and try again');
        },
        onFinish: () => {
          isSubmitting.value = false;
        }
      }
    );
  }
};

const deleteAddress = (address) => {
  if (!props.membershipSlug) {
    notification.error(t.value.member?.no_active_membership || 'No active membership found.');
    return;
  }

  if (!confirm(t.value.member?.confirm_delete_address || 'Are you sure you want to delete this address?')) {
    return;
  }

  router.delete(
    route('admin.user.membership.address.destroy', [
      props.userSlug,
      props.membershipSlug,
      address.id
    ]),
    {
      preserveScroll: true,
      onSuccess: () => {
        notification.success(t.value.member?.address_deleted || 'Address deleted successfully');
        router.reload({ only: ['member'] });
      },
      onError: () => {
        notification.error(t.value.member?.failed_delete_address || 'Failed to delete address');
      }
    }
  );
};
</script>

<style scoped></style>
