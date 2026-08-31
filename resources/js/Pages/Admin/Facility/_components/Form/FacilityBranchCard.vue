<template>
  <div class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
    <div class="py-2 px-6">
      <div class="title-golden leading-none font-semibold flex items-center justify-between">
        <div class="flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
            <polyline points="9 22 9 12 15 12 15 22"></polyline>
          </svg>
          {{ t.facility_branch?.title || 'Facility Branches' }}
        </div>
        <button
          @click="openAddForm"
          type="button"
          class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded-md bg-primary text-primary-foreground hover:bg-primary/90 transition-colors"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 12h14"></path>
            <path d="M12 5v14"></path>
          </svg>
          {{ t.facility_branch?.add_branch || 'Add Branch' }}
        </button>
      </div>
    </div>

    <div class="px-6">
      <!-- Branches List -->
      <div v-if="modelValue && modelValue.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div
          v-for="(branch, index) in modelValue"
          :key="branch.id || index"
          class="p-4 bg-accent/30 rounded-lg border-2 transition-colors hover:bg-accent/50"
          :class="statusBorderClass(branchStatus(branch))"
        >
          <div class="flex items-start justify-between gap-4">
            <div class="flex-1 min-w-0">
              <div class="mb-2">
                <div class="flex items-center gap-2 mb-1">
                  <h4 class="font-semibold text-white">
                    {{ getTranslatedName(branch.name) || (t.facility_branch?.unnamed_branch || 'Unnamed Branch') }}
                  </h4>
                  <span
                    v-if="branchStatus(branch) !== 'unchanged'"
                    class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                    :class="statusBadgeClass(branchStatus(branch))"
                  >
                    {{ branchStatus(branch) === 'added'
                      ? (t.common?.new || 'New')
                      : (t.common?.edited || 'Edited') }}
                  </span>
                </div>
                <p v-if="getTranslatedName(branch.address)" class="text-sm text-white/80 mb-2">
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline mr-1 text-white/50">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                    <circle cx="12" cy="10" r="3"></circle>
                  </svg>
                  {{ getTranslatedName(branch.address) }}
                </p>
                <p v-if="branchLocation(branch)" class="text-sm text-white/80 mb-2">
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline mr-1 text-white/50">
                    <path d="M3 21h18"></path>
                    <path d="M5 21V7l8-4v18"></path>
                    <path d="M19 21V11l-6-4"></path>
                  </svg>
                  {{ branchLocation(branch) }}
                </p>
                <p v-if="hasCoordinates(branch)" class="text-sm text-white/80 mb-2">
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline mr-1 text-white/50">
                    <circle cx="12" cy="12" r="3"></circle>
                    <path d="M12 2v3"></path>
                    <path d="M12 19v3"></path>
                    <path d="M2 12h3"></path>
                    <path d="M19 12h3"></path>
                  </svg>
                  {{ Number(branch.latitude) }}, {{ Number(branch.longitude) }}
                </p>
              </div>
              <div v-if="branch.phone && branch.phone.length > 0" class="flex flex-wrap gap-2 text-xs text-white/70">
                <span
                  v-for="(phone, phoneIndex) in branch.phone"
                  :key="phoneIndex"
                  class="inline-flex items-center gap-1"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white/50">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                  </svg>
                  {{ phone }}
                </span>
              </div>
            </div>
            <div class="flex gap-2 flex-shrink-0">
              <button
                @click="editBranch(index)"
                type="button"
                class="p-2 rounded-md hover:bg-accent transition-colors"
                title="Edit"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
              </button>
              <button
                @click="deleteBranch(index)"
                type="button"
                class="p-2 rounded-md hover:bg-destructive/20 transition-colors"
                title="Delete"
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
      <div v-else class="text-center py-8 text-white">
        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="mx-auto mb-4 opacity-50 text-white/70">
          <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
          <polyline points="9 22 9 12 15 12 15 22"></polyline>
        </svg>
        <p class="text-white">{{ t.facility_branch?.no_branches || 'No branches added yet.' }}</p>
        <p class="text-sm mt-1 text-white/80">{{ t.facility_branch?.add_branch_help || 'Click "Add Branch" to get started.' }}</p>
      </div>
    </div>

    <!-- Add / Edit Branch modal -->
    <Teleport to="body">
      <div
        v-if="isFormOpen"
        class="fixed inset-0 z-[110] flex items-start justify-center overflow-y-auto bg-black/70 p-4 backdrop-blur-sm"
        role="dialog"
        aria-modal="true"
        @click.self="cancelForm"
      >
        <div class="my-8 w-full max-w-2xl overflow-hidden rounded-xl border border-border bg-card text-card-foreground shadow-xl">
          <div class="flex items-start gap-3 border-b border-border p-4">
            <h3 class="text-sm font-semibold text-white">
              {{ editingIndex !== null ? (t.facility_branch?.edit_branch || 'Edit Branch') : (t.facility_branch?.add_new_branch || 'Add New Branch') }}
            </h3>
            <button
              type="button"
              class="ml-auto rounded-md p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
              :title="t.common?.close || 'Close (Esc)'"
              @click="cancelForm"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 6 6 18"></path><path d="m6 6 12 12"></path>
              </svg>
            </button>
          </div>

          <!-- .stop: this form is nested inside the page form; without it the submit
               event bubbles up and triggers a full facility save. -->
          <form @submit.prevent.stop="handleSubmit">
            <div class="max-h-[70vh] overflow-y-auto p-4 space-y-4">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <FormTranslatableInput
                    v-model="form.name"
                    :label="t.facility_branch?.branch_name || 'Branch Name'"
                    :error="errors.name"
                    :placeholder="t.facility_branch?.branch_name_placeholder || 'Enter branch name'"
                    :locales="['ar', 'en']"
                  />
                </div>
                <div>
                  <FormTranslatableInput
                    v-model="form.address"
                    :label="t.common?.address || 'Address'"
                    :error="errors.address"
                    :placeholder="t.facility_branch?.address_placeholder || 'Enter branch address'"
                    :locales="['ar', 'en']"
                  />
                </div>
                <div>
                  <FormSelect
                    v-model="form.governorate_id"
                    :label="t.governorate?.label || 'Governorate'"
                    :options="governorateOptions"
                    :error="errors.governorate_id"
                    :placeholder="t.governorate?.select || 'Select a governorate'"
                  />
                </div>
                <div>
                  <FormSelect
                    v-model="form.city_id"
                    :label="t.city?.label || 'City'"
                    :options="cityOptions"
                    :error="errors.city_id"
                    :placeholder="t.city?.select || 'Select a city'"
                  />
                </div>
                <div>
                  <FormInput
                    v-model="form.latitude"
                    :label="t.facility?.latitude || 'Latitude'"
                    type="number"
                    step="any"
                    :placeholder="t.facility?.latitude_placeholder || 'e.g. 30.0444'"
                    :error="errors.latitude"
                  />
                </div>
                <div>
                  <FormInput
                    v-model="form.longitude"
                    :label="t.facility?.longitude || 'Longitude'"
                    type="number"
                    step="any"
                    :placeholder="t.facility?.longitude_placeholder || 'e.g. 31.2357'"
                    :error="errors.longitude"
                  />
                </div>
                <div class="md:col-span-2">
                  <label class="block text-sm font-medium text-white mb-2">
                    {{ t.facility_branch?.phone_numbers || 'Phone Numbers' }}
                    <span class="text-xs text-white/70 ml-1">{{ t.facility_branch?.phone_help || '(one per line)' }}</span>
                  </label>
                  <textarea
                    v-model="phoneText"
                    :class="[
                      'w-full py-2 px-3 border border-border text-foreground placeholder:text-white/70 focus:border-ring dark:bg-input/30 bg-transparent focus:outline-none rounded-md min-h-[80px] focus:ring-[3px] focus:ring-ring/50',
                      errors.phone ? 'border-destructive focus:border-destructive focus:ring-destructive/20 dark:focus:ring-destructive/40' : ''
                    ]"
                    :placeholder="t.facility_branch?.phone_placeholder || 'Enter phone numbers, one per line\nExample:\n+20 123 456 7890\n+20 987 654 3210'"
                  ></textarea>
                  <p v-if="errors.phone" class="mt-1 text-sm text-destructive">{{ errors.phone }}</p>
                </div>
              </div>
            </div>
            <div class="flex gap-3 justify-end border-t border-border p-3">
              <button
                type="button"
                @click="cancelForm"
                class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all border bg-background text-white shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2"
              >
                {{ t.common?.cancel || 'Cancel' }}
              </button>
              <button
                type="submit"
                class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2"
              >
                {{ editingIndex !== null ? (t.common?.update || 'Update') : (t.common?.add || 'Add') }} {{ t.facility_branch?.label || 'Branch' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { FormTranslatableInput, FormSelect, FormInput } from '@/Components/form';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});
const locale = computed(() => page.props.locale || 'ar');

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => []
  },
  governorates: {
    type: Array,
    default: () => []
  },
  cities: {
    type: Array,
    default: () => []
  }
});

const emit = defineEmits(['update:modelValue']);

const showAddForm = ref(false);
const editingIndex = ref(null);
const errors = ref({});

const isFormOpen = computed(() => showAddForm.value || editingIndex.value !== null);

const form = ref({
  name: {},
  address: {},
  phone: [],
  governorate_id: '',
  city_id: '',
  latitude: '',
  longitude: ''
});

/* ---- change tracking -------------------------------------------------------
   Snapshot the branches as the server last sent them so the list can outline
   which ones the admin has touched. The baseline is keyed by branch id and is
   re-taken whenever a fresh facility payload arrives (e.g. after "Save & stay").
--------------------------------------------------------------------------- */
const baseline = ref(new Map());

const sortedObject = (value) => {
  if (!value || typeof value !== 'object' || Array.isArray(value)) return value ?? null;
  return Object.keys(value).sort().reduce((acc, key) => {
    acc[key] = value[key];
    return acc;
  }, {});
};

const numeric = (value) => (value === '' || value === null || value === undefined ? null : Number(value));

const branchFingerprint = (branch) => JSON.stringify({
  name: sortedObject(branch.name),
  address: sortedObject(branch.address),
  phone: Array.isArray(branch.phone) ? branch.phone : (branch.phone ? [branch.phone] : []),
  governorate_id: numeric(branch.governorate_id),
  city_id: numeric(branch.city_id),
  latitude: numeric(branch.latitude),
  longitude: numeric(branch.longitude)
});

const captureBaseline = () => {
  const map = new Map();
  (props.modelValue || []).forEach((branch) => {
    if (branch.id != null) {
      map.set(branch.id, branchFingerprint(branch));
    }
  });
  baseline.value = map;
};

const branchStatus = (branch) => {
  if (branch.id == null || !baseline.value.has(branch.id)) return 'added';
  return baseline.value.get(branch.id) === branchFingerprint(branch) ? 'unchanged' : 'changed';
};

const statusBorderClass = (status) => ({
  added: 'border-emerald-500',
  changed: 'border-amber-500',
  unchanged: 'border-border'
}[status]);

const statusBadgeClass = (status) => ({
  added: 'bg-emerald-500/15 text-emerald-400',
  changed: 'bg-amber-500/15 text-amber-400'
}[status]);

// A new facility payload from the server (fresh object identity) resets the
// baseline; edits made in the browser keep the current baseline so they stay
// highlighted until the next save. nextTick lets the parent push the fresh
// branches down as our modelValue first.
watch(() => page.props.facility, () => nextTick(captureBaseline));

const optionLabel = (name) => {
  if (typeof name === 'object' && name !== null) {
    return name[locale.value] || name['ar'] || name['en'] || Object.values(name)[0] || '';
  }
  return name;
};

const governorateOptions = computed(() =>
  props.governorates.map(governorate => ({
    value: governorate.id,
    label: optionLabel(governorate.name)
  }))
);

const cityOptions = computed(() => {
  const selectedGov = form.value.governorate_id;
  return props.cities
    .filter(city => !selectedGov || String(city.governorate_id) === String(selectedGov))
    .map(city => ({
      value: city.id,
      label: optionLabel(city.name)
    }));
});

// Clear the selected city when it no longer belongs to the chosen governorate
watch(() => form.value.governorate_id, (newGov, oldGov) => {
  if (oldGov !== undefined && newGov !== oldGov) {
    const stillValid = props.cities.some(
      c => String(c.id) === String(form.value.city_id) && String(c.governorate_id) === String(newGov)
    );
    if (!stillValid) {
      form.value.city_id = '';
    }
  }
});

const phoneText = computed({
  get: () => {
    if (!form.value.phone || !Array.isArray(form.value.phone)) {
      return '';
    }
    return form.value.phone.filter(p => p && p.trim()).join('\n');
  },
  set: (value) => {
    if (!value || !value.trim()) {
      form.value.phone = [];
      return;
    }
    form.value.phone = value
      .split(/[\n/\\،,;|]| [-–—] /)
      .map(p => p.trim())
      .filter(p => p.length > 0);
  }
});

const resetForm = () => {
  form.value = {
    name: {},
    address: {},
    phone: [],
    governorate_id: '',
    city_id: '',
    latitude: '',
    longitude: ''
  };
  errors.value = {};
};

const openAddForm = () => {
  resetForm();
  editingIndex.value = null;
  showAddForm.value = true;
};

const cancelForm = () => {
  showAddForm.value = false;
  editingIndex.value = null;
  resetForm();
};

const onKeydown = (event) => {
  if (event.key === 'Escape' && isFormOpen.value) {
    cancelForm();
  }
};

watch(isFormOpen, (open) => {
  document.body.style.overflow = open ? 'hidden' : '';
});

onMounted(() => {
  captureBaseline();
  window.addEventListener('keydown', onKeydown);
});
onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown);
  document.body.style.overflow = '';
});

const getTranslatedName = (name) => {
  if (!name) return '';
  if (typeof name === 'string') return name;
  if (typeof name === 'object' && name !== null) {
    return name[locale.value] || name['ar'] || name['en'] || Object.values(name)[0] || '';
  }
  return '';
};

const getGovernorateName = (id) => {
  if (id === null || id === undefined || id === '') return '';
  const gov = props.governorates.find(g => String(g.id) === String(id));
  return gov ? optionLabel(gov.name) : '';
};

const getCityName = (id) => {
  if (id === null || id === undefined || id === '') return '';
  const city = props.cities.find(c => String(c.id) === String(id));
  return city ? optionLabel(city.name) : '';
};

// "City, Governorate" for a saved branch (omits whichever is missing)
const branchLocation = (branch) => {
  return [getCityName(branch.city_id), getGovernorateName(branch.governorate_id)]
    .filter(Boolean)
    .join('، ');
};

const hasCoordinates = (branch) => {
  const valid = (v) => v !== null && v !== undefined && v !== '';
  return valid(branch.latitude) && valid(branch.longitude);
};

const editBranch = (index) => {
  editingIndex.value = index;
  showAddForm.value = true;
  const branch = props.modelValue[index];

  // Ensure name and address are objects
  let nameValue = branch.name || {};
  if (typeof nameValue === 'string') {
    try {
      nameValue = JSON.parse(nameValue);
    } catch {
      nameValue = { ar: nameValue, en: nameValue };
    }
  }
  if (!nameValue || typeof nameValue !== 'object' || Array.isArray(nameValue)) {
    nameValue = {};
  }

  let addressValue = branch.address || {};
  if (typeof addressValue === 'string') {
    try {
      addressValue = JSON.parse(addressValue);
    } catch {
      addressValue = { ar: addressValue, en: addressValue };
    }
  }
  if (!addressValue || typeof addressValue !== 'object' || Array.isArray(addressValue)) {
    addressValue = {};
  }

  form.value = {
    name: nameValue,
    address: addressValue,
    phone: Array.isArray(branch.phone) ? branch.phone : (branch.phone ? [branch.phone] : []),
    governorate_id: branch.governorate_id ?? '',
    city_id: branch.city_id ?? '',
    latitude: branch.latitude ?? '',
    longitude: branch.longitude ?? ''
  };
  errors.value = {};
};

const handleSubmit = () => {
  errors.value = {};

  // Basic validation
  const nameObj = form.value.name || {};
  const hasName = Object.keys(nameObj).some(key => nameObj[key] && nameObj[key].trim());
  if (!hasName) {
    errors.value.name = t.value?.facility_branch?.name_required || 'Branch name is required in at least one language';
    return;
  }

  const branchData = {
    id: editingIndex.value !== null && props.modelValue[editingIndex.value]?.id
      ? props.modelValue[editingIndex.value].id
      : null,
    name: form.value.name || {},
    address: form.value.address || {},
    phone: form.value.phone && form.value.phone.length > 0 ? form.value.phone : null,
    governorate_id: form.value.governorate_id || null,
    city_id: form.value.city_id || null,
    latitude: form.value.latitude !== '' && form.value.latitude !== null ? form.value.latitude : null,
    longitude: form.value.longitude !== '' && form.value.longitude !== null ? form.value.longitude : null
  };

  const currentBranches = [...props.modelValue];

  if (editingIndex.value !== null) {
    // Update existing branch
    currentBranches[editingIndex.value] = { ...currentBranches[editingIndex.value], ...branchData };
  } else {
    // Add new branch
    currentBranches.push(branchData);
  }

  emit('update:modelValue', currentBranches);
  cancelForm();
};

const deleteBranch = (index) => {
  if (!confirm(t.value?.facility_branch?.confirm_remove || 'Are you sure you want to remove this branch?')) {
    return;
  }

  const currentBranches = [...props.modelValue];
  currentBranches.splice(index, 1);
  emit('update:modelValue', currentBranches);
};
</script>

<style scoped></style>
