<template>
  <div class="space-y-3">
    <!-- Facility Branch Information Card -->
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] !items-start gap-1.5 py-2 px-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-building title-icon">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
            <polyline points="9 22 9 12 15 12 15 22"></polyline>
          </svg>
          {{ t.facility_branch?.information || 'Facility Branch Information' }}
        </div>
      </div>
      <div data-slot="card-content" class="px-6 space-y-4">
        <!-- Row 1: Parent Facility -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">
          <div data-slot="form-item" class="grid gap-1">
            <FormSelect
              v-model="formFacilityId"
              :label="t.facility_branch?.parent_facility || 'Parent Facility'"
              :options="facilityOptions"
              :error="facilityBranchStore.validationErrors?.facility_id"
              :placeholder="t.facility?.all || 'Select a facility'"
              required
            />
          </div>
        </div>


        <!-- Row 2: Branch Name + Address -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">
          <div data-slot="form-item" class="grid gap-1">
            <FormTranslatableInput
              v-model="formName"
              :label="t.facility_branch?.branch_name || 'Branch Name'"
              :error="facilityBranchStore.validationErrors?.name"
              :placeholder="t.facility_branch?.branch_name_placeholder || 'Enter branch name'"
              :locales="['ar', 'en']"
            />
          </div>

          <div data-slot="form-item" class="grid gap-1">
            <FormTranslatableInput
              v-model="formAddress"
              :label="t.common?.address || 'Address'"
              :error="facilityBranchStore.validationErrors?.address"
              :placeholder="t.facility_branch?.address_placeholder || 'Enter branch address'"
              :locales="['ar', 'en']"
            />
          </div>
        </div>

        <!-- Row 3: Governorate + City -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">
          <div data-slot="form-item" class="grid gap-1">
            <FormSelect
              v-model="facilityBranchStore.form.governorate_id"
              :label="t.governorate?.label || 'Governorate'"
              :options="governorateOptions"
              :error="facilityBranchStore.validationErrors?.governorate_id"
              :placeholder="t.governorate?.select || 'Select a governorate'"
            />
          </div>

          <div data-slot="form-item" class="grid gap-1">
            <FormSelect
              v-model="facilityBranchStore.form.city_id"
              :label="t.city?.label || 'City'"
              :options="cityOptions"
              :error="facilityBranchStore.validationErrors?.city_id"
              :placeholder="t.city?.select || 'Select a city'"
            />
          </div>
        </div>

        <!-- Row 4: Latitude + Longitude -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">
          <div data-slot="form-item" class="grid gap-1">
            <FormInput
              v-model="facilityBranchStore.form.latitude"
              :label="t.facility?.latitude || 'Latitude'"
              type="number"
              step="any"
              :placeholder="t.facility?.latitude_placeholder || 'e.g. 30.0444'"
              :error="facilityBranchStore.validationErrors?.latitude"
            />
          </div>

          <div data-slot="form-item" class="grid gap-1">
            <FormInput
              v-model="facilityBranchStore.form.longitude"
              :label="t.facility?.longitude || 'Longitude'"
              type="number"
              step="any"
              :placeholder="t.facility?.longitude_placeholder || 'e.g. 31.2357'"
              :error="facilityBranchStore.validationErrors?.longitude"
            />
          </div>
        </div>

        <!-- Row 4b: Google Location URL -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">
          <div data-slot="form-item" class="grid gap-1">
            <FormInput
              v-model="facilityBranchStore.form.google_location_url"
              :label="t.facility_branch?.google_location_url || 'Google Location URL'"
              type="url"
              :placeholder="t.facility_branch?.google_location_url_placeholder || 'https://maps.app.goo.gl/...'"
              :error="facilityBranchStore.validationErrors?.google_location_url"
            />
          </div>
        </div>

        <!-- Row 5: Phone Numbers -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">
          <div data-slot="form-item" class="grid gap-1">
            <label class="block text-sm font-medium text-white mb-2">
              {{ t.facility_branch?.phone_numbers || 'Phone Numbers' }}
              <span class="text-xs text-white/70 ml-1">{{ t.facility_branch?.phone_help || '(one per line)' }}</span>
            </label>
            <textarea
              v-model="phoneText"
              :class="[
                'w-full py-2 px-3 border border-border text-foreground placeholder:text-white/70 focus:border-ring dark:bg-input/30 bg-transparent focus:outline-none rounded-md min-h-[80px] focus:ring-[3px] focus:ring-ring/50',
                facilityBranchStore.validationErrors?.phone ? 'border-destructive focus:border-destructive focus:ring-destructive/20 dark:focus:ring-destructive/40' : ''
              ]"
              :placeholder="t.facility_branch?.phone_placeholder || 'Enter phone numbers, one per line\nExample:\n+20 123 456 7890\n+20 987 654 3210'"
            ></textarea>
            <p v-if="facilityBranchStore.validationErrors?.phone" class="mt-1 text-sm text-destructive">
              {{ facilityBranchStore.validationErrors.phone }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { FormTranslatableInput, FormSelect, FormInput } from "@/Components/form";
import { useFacilityBranchStore } from "../Stores/FacilityBranchStore";
import { computed, watch } from "vue";
import { storeToRefs } from "pinia";
import { usePage } from "@inertiajs/vue3";

const props = defineProps({
  facilities: {
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

const facilityBranchStore = useFacilityBranchStore();
const { form } = storeToRefs(facilityBranchStore);
const page = usePage();
const locale = computed(() => page.props.locale || 'ar');
const t = computed(() => page.props.translations?.admin || {});

// Helper to get translated name - accepts locale for template usage
const getTranslatedName = (name) => {
  if (!name) return '';
  if (typeof name === 'string') return name;
  if (typeof name === 'object') {
    const currentLocale = locale.value;
    return name[currentLocale] || name['ar'] || name['en'] || Object.values(name)[0] || '';
  }
  return '';
};

// Convert facilities to select options
const facilityOptions = computed(() => {
  const currentLocale = locale.value; // Ensure locale is tracked as dependency
  return props.facilities.map(facility => {
    const name = typeof facility.name === 'object'
      ? (facility.name[currentLocale] || facility.name['ar'] || facility.name['en'] || Object.values(facility.name)[0] || '')
      : facility.name;
    const branchesCount = facility.branches_count ?? 0;
    const branchesLabel = currentLocale === 'ar'
      ? `(${branchesCount} فروع)`
      : `(${branchesCount} branches)`;
    return {
      value: facility.id,
      label: `${name} ${branchesLabel}`
    };
  });
});

// Get selected facility details
const selectedFacility = computed(() => {
  if (!form.value.facility_id) return null;
  return props.facilities.find(f => f.id === form.value.facility_id) || null;
});

const governorateOptions = computed(() => {
  const currentLocale = locale.value;
  return props.governorates.map(governorate => ({
    value: governorate.id,
    label: typeof governorate.name === 'object'
      ? (governorate.name[currentLocale] || governorate.name['ar'] || governorate.name['en'] || Object.values(governorate.name)[0] || '')
      : governorate.name
  }));
});

const cityOptions = computed(() => {
  const currentLocale = locale.value;
  const selectedGov = facilityBranchStore.form.governorate_id;
  return props.cities
    .filter(city => !selectedGov || String(city.governorate_id) === String(selectedGov))
    .map(city => ({
      value: city.id,
      label: typeof city.name === 'object'
        ? (city.name[currentLocale] || city.name['ar'] || city.name['en'] || Object.values(city.name)[0] || '')
        : city.name
    }));
});

watch(() => facilityBranchStore.form.governorate_id, (newGov, oldGov) => {
  if (oldGov !== undefined && newGov !== oldGov) {
    const stillValid = props.cities.some(
      c => String(c.id) === String(facilityBranchStore.form.city_id) && String(c.governorate_id) === String(newGov)
    );
    if (!stillValid) {
      facilityBranchStore.form.city_id = '';
    }
  }
});

// Ensure we always have a valid facility_id for the form
const formFacilityId = computed({
  get: () => {
    return form.value.facility_id || '';
  },
  set: (value) => {
    form.value.facility_id = value;
  }
});

// Ensure we always have a valid name object for the form
const formName = computed({
  get: () => {
    const name = form.value.name;
    if (!name || typeof name !== 'object' || Array.isArray(name)) {
      return {};
    }
    return name;
  },
  set: (value) => {
    form.value.name = value;
  }
});

// Ensure we always have a valid address object for the form
const formAddress = computed({
  get: () => {
    const address = form.value.address;
    if (!address || typeof address !== 'object' || Array.isArray(address)) {
      return {};
    }
    return address;
  },
  set: (value) => {
    form.value.address = value;
  }
});

// Handle phone as textarea (one per line)
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
      .split('\n')
      .map(p => p.trim())
      .filter(p => p.length > 0);
  }
});
</script>

<style lang="scss" scoped></style>
