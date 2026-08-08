<template>
  <div class="space-y-3">
    <!-- Attached To Card (Polymorphic Selector) -->
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] !items-start gap-1.5 py-2 px-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-link title-icon">
            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
          </svg>
          {{ t.offer?.attached_to || 'Attached To' }}
        </div>
      </div>
      <div data-slot="card-content" class="px-6 space-y-4">
        <!-- Polymorphic Type Selector (Radio Buttons) -->
        <div class="grid gap-1">
          <label class="block text-sm font-medium text-white mb-2">
            {{ t.offer?.select_type || 'Select Type' }}
            <span class="text-destructive ml-1">*</span>
          </label>
          <div class="flex gap-4">
            <label class="flex items-center gap-2 cursor-pointer">
              <input
                type="radio"
                v-model="formOfferableType"
                value="App\Models\Facility"
                class="w-4 h-4 text-primary bg-transparent border-border focus:ring-ring focus:ring-2"
              />
              <span class="text-sm text-foreground">{{ t.facility?.label || 'Facility' }}</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input
                type="radio"
                v-model="formOfferableType"
                value="App\Models\FacilityBranch"
                class="w-4 h-4 text-primary bg-transparent border-border focus:ring-ring focus:ring-2"
              />
              <span class="text-sm text-foreground">{{ t.facility_branch?.label || 'Facility Branch' }}</span>
            </label>
          </div>
          <p v-if="offerStore.validationErrors?.offerable_type" class="mt-1 text-sm text-destructive">
            {{ offerStore.validationErrors.offerable_type }}
          </p>
        </div>

        <!-- Facility Filter (Only shown when Branch is selected) -->
        <div v-if="formOfferableType === 'App\\Models\\FacilityBranch'" class="grid gap-1">
          <FormSelect
            v-model="selectedFacilityFilter"
            :label="t.facility?.filter || 'Filter by Facility'"
            :options="facilityFilterOptions"
            :placeholder="t.facility?.filter_placeholder || 'Select facility to filter branches'"
          />
        </div>

        <!-- Dynamic Dropdown based on selected type -->
        <div v-if="formOfferableType" class="grid gap-1">
          <FormSelect
            v-model="formOfferableId"
            :label="offerableSelectLabel"
            :options="offerableOptions"
            :error="offerStore.validationErrors?.offerable_id"
            :placeholder="offerableSelectPlaceholder"
            required
          />
        </div>

        <!-- Preview Card showing selected facility/branch details -->
        <div v-if="selectedOfferable" class="bg-muted/30 rounded-lg p-4 border border-border">
          <h4 class="text-sm font-semibold text-white mb-3 flex items-center gap-2">
            <span :class="[
              'inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset',
              offerableTypeBadge.class
            ]">
              {{ offerableTypeBadge.label }}
            </span>
            {{ getTranslatedName(selectedOfferable.name) }}
          </h4>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div v-if="selectedOfferable.governorate">
              <label class="text-xs font-medium text-muted-foreground">{{ t.governorate?.label || 'Governorate' }}</label>
              <p class="text-sm font-medium mt-0.5 text-white">
                {{ getTranslatedName(selectedOfferable.governorate?.name) || t.common?.n_a || 'N/A' }}
              </p>
            </div>
            <div v-if="selectedOfferable.city">
              <label class="text-xs font-medium text-muted-foreground">{{ t.city?.label || 'City' }}</label>
              <p class="text-sm font-medium mt-0.5 text-white">
                {{ getTranslatedName(selectedOfferable.city?.name) || t.common?.n_a || 'N/A' }}
              </p>
            </div>
            <div v-if="selectedOfferable.address">
              <label class="text-xs font-medium text-muted-foreground">{{ t.common?.address || 'Address' }}</label>
              <p class="text-sm font-medium mt-0.5 text-white">
                {{ getTranslatedName(selectedOfferable.address) || t.common?.n_a || 'N/A' }}
              </p>
            </div>
            <div v-if="selectedOfferable.facility_type">
              <label class="text-xs font-medium text-muted-foreground">{{ t.facility_type?.label || 'Facility Type' }}</label>
              <p class="text-sm font-medium mt-0.5 text-white">
                {{ getTranslatedName(selectedOfferable.facility_type?.name) || t.common?.n_a || 'N/A' }}
              </p>
            </div>
            <div v-if="selectedOfferable.facility">
              <label class="text-xs font-medium text-muted-foreground">{{ t.facility_branch?.parent_facility || 'Parent Facility' }}</label>
              <p class="text-sm font-medium mt-0.5 text-white">
                {{ getTranslatedName(selectedOfferable.facility?.name) || t.common?.n_a || 'N/A' }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Offer Information Card -->
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] !items-start gap-1.5 py-2 px-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-tag title-icon">
            <path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z"></path>
            <path d="M7 7h.01"></path>
          </svg>
          {{ t.offer?.information || 'Offer Information' }}
        </div>
      </div>
      <div data-slot="card-content" class="px-6 space-y-4">
        <!-- Row 1: Title (Translatable) -->
        <div class="grid grid-cols-1 gap-4 items-start">
          <div data-slot="form-item" class="grid gap-1">
            <FormTranslatableInput
              v-model="formTitle"
              :label="t.offer?.title_label || 'Title'"
              :error="offerStore.validationErrors?.['title.ar'] || offerStore.validationErrors?.['title.en']"
              :placeholder="t.offer?.title_placeholder || 'Enter offer title'"
              :locales="['ar', 'en']"
              required
            />
          </div>
        </div>

        <!-- Row 2: Short Description (Translatable) -->
        <div class="grid grid-cols-1 gap-4 items-start">
          <div data-slot="form-item" class="grid gap-1">
            <FormTranslatableInput
              v-model="formShortDescription"
              :label="t.offer?.short_description || 'Short Description'"
              :error="offerStore.validationErrors?.['short_description.ar'] || offerStore.validationErrors?.['short_description.en']"
              :placeholder="t.offer?.short_description_placeholder || 'Enter short description'"
              :locales="['ar', 'en']"
            />
          </div>
        </div>

        <!-- Row 3: Full Description (Translatable - Rich Text Editor) -->
        <div class="grid grid-cols-1 gap-4 items-start">
          <div data-slot="form-item" class="grid gap-1">
            <label class="block text-sm font-medium text-white mb-2">
              {{ t.offer?.full_description || 'Full Description' }}
            </label>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-medium text-muted-foreground mb-1">
                  {{ t.common?.arabic || 'Arabic' }}
                </label>
                <QuillTextEditor
                  v-model="formFullDescription.ar"
                  :placeholder="t.offer?.full_description_placeholder || 'Enter full description in Arabic'"
                  :error="offerStore.validationErrors?.['full_description.ar']"
                  :toolbar="[
                    ['bold', 'italic', 'underline', 'strike'],
                    ['blockquote', 'code-block'],
                    [{ 'header': 1 }, { 'header': 2 }],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    [{ 'direction': 'rtl' }],
                    [{ 'size': ['small', false, 'large', 'huge'] }],
                    [{ 'color': [] }, { 'background': [] }],
                    [{ 'align': [] }],
                    ['link'],
                    ['clean']
                  ]"
                />
              </div>
              <div>
                <label class="block text-xs font-medium text-muted-foreground mb-1">
                  {{ t.common?.english || 'English' }}
                </label>
                <QuillTextEditor
                  v-model="formFullDescription.en"
                  :placeholder="t.offer?.full_description_placeholder || 'Enter full description in English'"
                  :error="offerStore.validationErrors?.['full_description.en']"
                  :toolbar="[
                    ['bold', 'italic', 'underline', 'strike'],
                    ['blockquote', 'code-block'],
                    [{ 'header': 1 }, { 'header': 2 }],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    [{ 'size': ['small', false, 'large', 'huge'] }],
                    [{ 'color': [] }, { 'background': [] }],
                    [{ 'align': [] }],
                    ['link'],
                    ['clean']
                  ]"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- Row 4: Phone, Price, and Old Price -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-start">
          <div data-slot="form-item" class="grid gap-1">
            <FormInput
              v-model="formPhone"
              :label="t.common?.phone || 'Phone'"
              :error="offerStore.validationErrors?.phone"
              :placeholder="t.offer?.phone_placeholder || 'Enter phone number'"
              type="text"
            />
          </div>
          <div data-slot="form-item" class="grid gap-1">
            <FormInput
              v-model="formPrice"
              :label="t.offer?.price || 'Price'"
              :error="offerStore.validationErrors?.price"
              :placeholder="t.offer?.price_placeholder || 'Enter price'"
              type="number"
              step="0.01"
              min="0"
            />
          </div>
          <div data-slot="form-item" class="grid gap-1">
            <FormInput
              v-model="formOldPrice"
              :label="t.offer?.old_price || 'Old Price'"
              :error="offerStore.validationErrors?.old_price"
              :placeholder="t.offer?.old_price_placeholder || 'Enter old price (optional)'"
              type="number"
              step="0.01"
              min="0"
            />
          </div>
        </div>

        <!-- Discount Preview -->
        <div v-if="discountPreview.hasDiscount" class="bg-emerald-500/10 border border-emerald-500/20 rounded-lg p-4">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-emerald-500/20">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-percent w-5 h-5 text-emerald-500">
                  <line x1="19" x2="5" y1="5" y2="19"></line>
                  <circle cx="6.5" cy="6.5" r="2.5"></circle>
                  <circle cx="17.5" cy="17.5" r="2.5"></circle>
                </svg>
              </div>
              <div>
                <p class="text-sm font-semibold text-emerald-500">
                  {{ discountPreview.percentage }}% {{ t.offer?.discount || 'Discount' }}
                </p>
                <p class="text-xs text-muted-foreground">
                  {{ t.offer?.save || 'Save' }} {{ formatPrice(discountPreview.savings) }}
                </p>
              </div>
            </div>
            <div class="text-right">
              <p class="text-xs text-muted-foreground line-through">{{ formatPrice(formOldPrice) }}</p>
              <p class="text-lg font-bold text-emerald-500">{{ formatPrice(formPrice) }}</p>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- Images Card -->
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] !items-start gap-1.5 py-2 px-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-image title-icon">
            <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/>
            <circle cx="9" cy="9" r="2"/>
            <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
          </svg>
          {{ t.offer?.images || 'Images' }}
        </div>
      </div>
      <div data-slot="card-content" class="px-6 space-y-4">
        <!-- Row 1: Main Image and Thumbnail -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
          <!-- Main Image Column -->
          <div class="space-y-4">
            <!-- Main Image -->
            <div data-slot="form-item" class="grid gap-1">
              <label class="block text-sm font-medium text-white mb-2">
                {{ t.offer?.main_image || 'Main Image' }}
                <span class="text-xs text-muted-foreground ml-2">({{ t.offer?.optional || 'Optional' }} - {{ maxSizeLabel('5MB') }})</span>
              </label>
              <ImageFileInput
                :max-size="5"
                :accepted-types="['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp', 'image/avif']"
                :initial-preview="initialImagePreview"
                @file-selected="handleImageSelected"
                @error="(err) => imageError = err"
              />
              <p v-if="offerStore.validationErrors?.image || imageError" class="mt-1 text-sm text-destructive">
                {{ offerStore.validationErrors?.image || imageError }}
              </p>
            </div>
            <!-- Mobile Image -->
            <div data-slot="form-item" class="grid gap-1">
              <label class="block text-sm font-medium text-white mb-2">
                {{ t.offer?.mobile_image || 'Mobile Image' }}
                <span class="text-xs text-muted-foreground ml-2">({{ t.offer?.optional || 'Optional' }} - {{ maxSizeLabel('5MB') }})</span>
              </label>
              <ImageFileInput
                :max-size="5"
                :accepted-types="['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp', 'image/avif']"
                :initial-preview="initialMobileImagePreview"
                @file-selected="handleMobileImageSelected"
                @error="(err) => mobileImageError = err"
              />
              <p v-if="offerStore.validationErrors?.mobile_image || mobileImageError" class="mt-1 text-sm text-destructive">
                {{ offerStore.validationErrors?.mobile_image || mobileImageError }}
              </p>
            </div>
          </div>

          <!-- Thumbnail Column -->
          <div class="space-y-4">
            <!-- Thumbnail -->
            <div data-slot="form-item" class="grid gap-1">
              <label class="block text-sm font-medium text-white mb-2">
                {{ t.offer?.thumbnail || 'Thumbnail' }}
                <span class="text-xs text-muted-foreground ml-2">({{ t.offer?.optional || 'Optional' }} - {{ maxSizeLabel('2MB') }})</span>
              </label>
              <ImageFileInput
                :max-size="2"
                :accepted-types="['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp', 'image/avif']"
                :initial-preview="initialThumbnailPreview"
                @file-selected="handleThumbnailSelected"
                @error="(err) => thumbnailError = err"
              />
              <p v-if="offerStore.validationErrors?.thumbnail || thumbnailError" class="mt-1 text-sm text-destructive">
                {{ offerStore.validationErrors?.thumbnail || thumbnailError }}
              </p>
            </div>
            <!-- Mobile Thumbnail -->
            <div data-slot="form-item" class="grid gap-1">
              <label class="block text-sm font-medium text-white mb-2">
                {{ t.offer?.mobile_thumbnail || 'Mobile Thumbnail' }}
                <span class="text-xs text-muted-foreground ml-2">({{ t.offer?.optional || 'Optional' }} - {{ maxSizeLabel('2MB') }})</span>
              </label>
              <ImageFileInput
                :max-size="2"
                :accepted-types="['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp', 'image/avif']"
                :initial-preview="initialMobileThumbnailPreview"
                @file-selected="handleMobileThumbnailSelected"
                @error="(err) => mobileThumbnailError = err"
              />
              <p v-if="offerStore.validationErrors?.mobile_thumbnail || mobileThumbnailError" class="mt-1 text-sm text-destructive">
                {{ offerStore.validationErrors?.mobile_thumbnail || mobileThumbnailError }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { FormTranslatableInput, FormSelect, FormInput } from "@/Components/form";
import ImageFileInput from "@/Components/form/ImageFileInput.vue";
import QuillTextEditor from "@/Components/form/QuillTextEditor.vue";
import { useOfferStore } from "../Stores/OfferStore";
import { computed, watch, ref } from "vue";
import { storeToRefs } from "pinia";
import { usePage } from "@inertiajs/vue3";
import { usePriceFormat } from '@/composables/usePriceFormat';

const { formatPrice } = usePriceFormat();

const props = defineProps({
  facilities: {
    type: Array,
    default: () => []
  },
  facilityBranches: {
    type: Array,
    default: () => []
  },
  offer: {
    type: Object,
    default: () => null
  }
});

const offerStore = useOfferStore();
const { form } = storeToRefs(offerStore);
const page = usePage();
const locale = computed(() => page.props.locale || 'ar');
const t = computed(() => page.props.translations?.admin || {});

// Facility filter for branches
const selectedFacilityFilter = ref('');

// Image handling
const imageError = ref('');
const mobileImageError = ref('');
const thumbnailError = ref('');
const mobileThumbnailError = ref('');
const initialImagePreview = computed(() => props.offer?.image || '');
const initialMobileImagePreview = computed(() => props.offer?.mobile_image || '');
const initialThumbnailPreview = computed(() => props.offer?.thumbnail || '');
const initialMobileThumbnailPreview = computed(() => props.offer?.mobile_thumbnail || '');

const handleImageSelected = (file) => {
  form.value.image = file;
  imageError.value = '';
};

const handleMobileImageSelected = (file) => {
  form.value.mobile_image = file;
  mobileImageError.value = '';
};

const handleThumbnailSelected = (file) => {
  form.value.thumbnail = file;
  thumbnailError.value = '';
};

const handleMobileThumbnailSelected = (file) => {
  form.value.mobile_thumbnail = file;
  mobileThumbnailError.value = '';
};

const getTranslatedName = (name) => {
  if (!name) return '';
  if (typeof name === 'string') return name;
  if (typeof name === 'object') {
    const currentLocale = locale.value;
    return name[currentLocale] || name['ar'] || name['en'] || Object.values(name)[0] || '';
  }
  return '';
};

const maxSizeLabel = (size) => {
  return (t.value.offer?.max_size || 'Max :size').replace(':size', size);
};

// Form field computed properties
const formTitle = computed({
  get: () => {
    const title = form.value.title;
    if (!title || typeof title !== 'object' || Array.isArray(title)) {
      return { ar: '', en: '' };
    }
    return title;
  },
  set: (value) => {
    form.value.title = value;
  }
});

const formShortDescription = computed({
  get: () => {
    const desc = form.value.short_description;
    if (!desc || typeof desc !== 'object' || Array.isArray(desc)) {
      return { ar: '', en: '' };
    }
    return desc;
  },
  set: (value) => {
    form.value.short_description = value;
  }
});

const formFullDescription = computed({
  get: () => {
    const desc = form.value.full_description;
    if (!desc || typeof desc !== 'object' || Array.isArray(desc)) {
      return { ar: '', en: '' };
    }
    return desc;
  },
  set: (value) => {
    form.value.full_description = value;
  }
});

const formPhone = computed({
  get: () => form.value.phone || '',
  set: (value) => {
    form.value.phone = value;
  }
});

const formPrice = computed({
  get: () => form.value.price,
  set: (value) => {
    form.value.price = value === '' || value === null ? null : value;
  }
});

const formOldPrice = computed({
  get: () => form.value.old_price,
  set: (value) => {
    form.value.old_price = value === '' || value === null ? null : value;
  }
});

const formOfferableType = computed({
  get: () => form.value.offerable_type || '',
  set: (value) => {
    form.value.offerable_type = value;
    // Reset offerable_id when type changes
    form.value.offerable_id = '';
  }
});

const formOfferableId = computed({
  get: () => form.value.offerable_id || '',
  set: (value) => {
    form.value.offerable_id = value;
  }
});

// Discount preview computation
const discountPreview = computed(() => {
  const price = parseFloat(formPrice.value);
  const oldPrice = parseFloat(formOldPrice.value);

  if (!isNaN(price) && !isNaN(oldPrice) && oldPrice > price && price > 0) {
    const savings = oldPrice - price;
    const percentage = Math.round((savings / oldPrice) * 100);
    return {
      hasDiscount: true,
      savings,
      percentage
    };
  }

  return {
    hasDiscount: false,
    savings: 0,
    percentage: 0
  };
});

// Facility filter options (for filtering branches)
const facilityFilterOptions = computed(() => {
  return [
    { value: '', label: t.value.common?.all || 'All Facilities' },
    ...props.facilities.map(facility => {
      const branchCount = facility.branches_count || 0;

      const facilityName = getTranslatedName(facility.name);
      const branchText = branchCount === 1
        ? (t.value.facility_branch?.singular || 'branch')
        : (t.value.facility_branch?.plural || 'branches');

      return {
        value: facility.id,
        label: `${facilityName} (${branchCount} ${branchText})`
      };
    })
  ];
});

// Polymorphic selector options
const offerableOptions = computed(() => {
  const currentLocale = locale.value;

  if (formOfferableType.value === 'App\\Models\\Facility') {
    return props.facilities.map(facility => ({
      value: facility.id,
      label: getTranslatedName(facility.name)
    }));
  } else if (formOfferableType.value === 'App\\Models\\FacilityBranch') {
    let branches = props.facilityBranches;

    // Filter branches by selected facility if a facility filter is applied
    if (selectedFacilityFilter.value) {
      branches = branches.filter(branch =>
        branch.facility && branch.facility.id === selectedFacilityFilter.value
      );
    }

    return branches.map(branch => {
      const branchName = getTranslatedName(branch.name);
      const parts = [branchName];
      if (branch.address) parts.push(getTranslatedName(branch.address));
      if (branch.city) parts.push(getTranslatedName(branch.city.name));
      if (branch.governorate) parts.push(getTranslatedName(branch.governorate.name));
      return {
        value: branch.id,
        label: parts.join('، ')
      };
    });
  }

  return [];
});

const offerableSelectLabel = computed(() => {
  if (formOfferableType.value === 'App\\Models\\Facility') {
    return t.value.facility?.select || 'Select Facility';
  } else if (formOfferableType.value === 'App\\Models\\FacilityBranch') {
    return t.value.facility_branch?.select || 'Select Facility Branch';
  }
  return t.value.offer?.select_item || 'Select Item';
});

const offerableSelectPlaceholder = computed(() => {
  if (formOfferableType.value === 'App\\Models\\Facility') {
    return t.value.facility?.placeholder || 'Choose a facility';
  } else if (formOfferableType.value === 'App\\Models\\FacilityBranch') {
    return t.value.facility_branch?.placeholder || 'Choose a facility branch';
  }
  return t.value.common?.select || 'Select';
});

const selectedOfferable = computed(() => {
  if (!formOfferableId.value) return null;

  if (formOfferableType.value === 'App\\Models\\Facility') {
    return props.facilities.find(f => f.id === formOfferableId.value) || null;
  } else if (formOfferableType.value === 'App\\Models\\FacilityBranch') {
    return props.facilityBranches.find(b => b.id === formOfferableId.value) || null;
  }

  return null;
});

const offerableTypeBadge = computed(() => {
  if (formOfferableType.value === 'App\\Models\\Facility') {
    return {
      label: t.value.facility?.label || 'Facility',
      class: 'bg-blue-500/10 text-blue-500 ring-blue-500/20'
    };
  } else if (formOfferableType.value === 'App\\Models\\FacilityBranch') {
    return {
      label: t.value.facility_branch?.label || 'Branch',
      class: 'bg-purple-500/10 text-purple-500 ring-purple-500/20'
    };
  }
  return {
    label: t.value.offer?.unknown || 'Unknown',
    class: 'bg-gray-500/10 text-gray-500 ring-gray-500/20'
  };
});

// Watch for facility filter changes - reset branch selection when filter changes
watch(selectedFacilityFilter, () => {
  if (formOfferableType.value === 'App\\Models\\FacilityBranch') {
    form.value.offerable_id = '';
  }
});

// Watch for type changes - reset facility filter when changing from Branch to Facility
watch(formOfferableType, (newType) => {
  if (newType !== 'App\\Models\\FacilityBranch') {
    selectedFacilityFilter.value = '';
  }
});
</script>

<style lang="scss" scoped></style>
