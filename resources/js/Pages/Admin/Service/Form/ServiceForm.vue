<template>
  <div class="space-y-3">
    <!-- Service Information Card -->
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] !items-start gap-1.5 py-2 px-6 [.border-b]:pb-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <rect x="3" y="7" width="18" height="13" rx="2"></rect>
            <path d="M9 7V5a2 2 0 012-2h2a2 2 0 012 2v2"></path>
            <line x1="3" y1="12" x2="21" y2="12"></line>
          </svg>
          {{ t.service?.information || 'Service Information' }}
        </div>
      </div>
      <div data-slot="card-content" class="px-6 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
          <FormInput
            v-model="formTitle"
            :label="t.service?.title_label || 'Title'"
            :error="serviceStore.validationErrors?.title"
            :placeholder="t.service?.title_placeholder || 'Enter service title'"
            required
          />
          <FormSelect
            v-model="formCategoryId"
            :label="t.service?.category || 'Category'"
            :options="categoryOptions"
            :error="serviceStore.validationErrors?.category_id"
            :placeholder="t.service?.category_placeholder || 'Select a category'"
            required
          />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
          <FormSelect
            v-model="formTag"
            :label="t.service?.tag || 'Tag'"
            :options="tagSelectOptions"
            :error="serviceStore.validationErrors?.tag"
            :placeholder="t.service?.tag_placeholder || 'Select a tag (optional)'"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-white mb-2">
            {{ t.service?.tags || 'Tags' }}
            <span v-if="serviceStore.validationErrors?.tag_ids" class="text-destructive ml-2 text-xs">{{ serviceStore.validationErrors.tag_ids }}</span>
          </label>
          <div v-if="tags.length > 0" class="flex flex-wrap gap-2">
            <button
              v-for="tagItem in tags"
              :key="tagItem.id"
              type="button"
              @click="toggleTag(tagItem.id)"
              class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset transition-opacity cursor-pointer"
              :style="tagChipStyle(tagItem, isTagSelected(tagItem.id))"
            >
              <span v-if="tagItem.icon">{{ tagItem.icon }}</span>
              {{ tagItem.name }}
            </button>
          </div>
          <p v-else class="text-xs text-muted-foreground">
            {{ t.service?.no_tags || 'No tags exist yet.' }}
          </p>
        </div>

        <FormInput
          v-model="formShortSubject"
          :label="t.service?.short_subject || 'Short Subject'"
          :error="serviceStore.validationErrors?.short_subject"
          :placeholder="t.service?.short_subject_placeholder || 'Enter a short subject'"
        />

        <div>
          <label class="block text-sm font-medium text-white mb-2">
            {{ t.service?.subject || 'Subject' }}
            <span v-if="serviceStore.validationErrors?.subject" class="text-destructive ml-2 text-xs">{{ serviceStore.validationErrors.subject }}</span>
          </label>
          <QuillTextEditor
            v-model="formSubject"
            :placeholder="t.service?.subject_placeholder || 'Enter the full subject / description'"
            :error="serviceStore.validationErrors?.subject"
          />
        </div>

        <div v-if="categoryOptions.length === 0" class="text-xs text-amber-500 bg-amber-500/10 border border-amber-500/20 rounded-md px-3 py-2">
          {{ t.service?.no_categories || 'No service categories exist yet. Create a service type before creating a service.' }}
        </div>
      </div>
    </div>

    <!-- Pricing Card -->
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] !items-start gap-1.5 py-2 px-6 [.border-b]:pb-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <line x1="12" x2="12" y1="2" y2="22"></line>
            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
          </svg>
          {{ t.service?.pricing || 'Pricing' }}
        </div>
      </div>
      <div data-slot="card-content" class="px-6 space-y-4">
        <div class="grid gap-1">
          <label class="block text-sm font-medium text-white mb-2">
            {{ t.service?.pricing_method || 'Discount Method' }}
          </label>
          <div class="flex gap-4">
            <label class="flex items-center gap-2 cursor-pointer">
              <input
                type="radio"
                v-model="pricingMode"
                value="old_price"
                class="w-4 h-4 text-primary bg-transparent border-border focus:ring-ring focus:ring-2"
              />
              <span class="text-sm text-foreground">{{ t.service?.old_price_mode || 'Old Price & Price' }}</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input
                type="radio"
                v-model="pricingMode"
                value="percent"
                class="w-4 h-4 text-primary bg-transparent border-border focus:ring-ring focus:ring-2"
              />
              <span class="text-sm text-foreground">{{ t.service?.discount_percent_mode || 'Discount %' }}</span>
            </label>
          </div>
        </div>

        <div v-if="pricingMode === 'old_price'" class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
          <FormInput
            v-model="formNewPrice"
            :label="t.service?.new_price || 'Price'"
            :error="serviceStore.validationErrors?.new_price"
            :placeholder="t.service?.new_price_placeholder || 'Enter price'"
            type="number"
            step="0.01"
            min="0"
          />
          <FormInput
            v-model="formOldPrice"
            :label="t.service?.old_price || 'Old Price'"
            :error="serviceStore.validationErrors?.old_price"
            :placeholder="t.service?.old_price_placeholder || 'Enter old price (optional)'"
            type="number"
            step="0.01"
            min="0"
          />
        </div>
        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
          <FormInput
            v-model="formDiscountPercent"
            :label="t.service?.discount_percent || 'Discount %'"
            :error="serviceStore.validationErrors?.discount_percent"
            :placeholder="t.service?.discount_percent_placeholder || 'Optional'"
            type="number"
            step="0.01"
            min="0"
            max="100"
          />
        </div>

        <div v-if="discountPreview.hasDiscount" class="bg-emerald-500/10 border border-emerald-500/20 rounded-lg p-4">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-emerald-500/20">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 text-emerald-500">
                  <line x1="19" x2="5" y1="5" y2="19"></line>
                  <circle cx="6.5" cy="6.5" r="2.5"></circle>
                  <circle cx="17.5" cy="17.5" r="2.5"></circle>
                </svg>
              </div>
              <div>
                <p class="text-sm font-semibold text-emerald-500">
                  {{ discountPreview.percentage }}% {{ t.service?.discount || 'Discount' }}
                </p>
                <p class="text-xs text-muted-foreground">
                  {{ t.service?.save || 'Save' }} {{ formatPrice(discountPreview.savings) }}
                </p>
              </div>
            </div>
            <div class="text-right">
              <p class="text-xs text-muted-foreground line-through">{{ formatPrice(formOldPrice) }}</p>
              <p class="text-lg font-bold text-emerald-500">{{ formatPrice(formNewPrice) }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Location Card -->
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] !items-start gap-1.5 py-2 px-6 [.border-b]:pb-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
            <path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
          </svg>
          {{ t.service?.location || 'Location' }}
        </div>
      </div>
      <div data-slot="card-content" class="px-6 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
          <FormSelect
            v-model="formGovernorateId"
            :label="t.governorate?.label || 'Governorate'"
            :options="governorateOptions"
            :error="serviceStore.validationErrors?.governorate_id"
            :placeholder="t.service?.governorate_placeholder || 'Select a governorate (optional)'"
          />
          <FormSelect
            v-model="formCityId"
            :label="t.city?.label || 'City'"
            :options="cityOptions"
            :error="serviceStore.validationErrors?.city_id"
            :placeholder="t.service?.city_placeholder || 'Select a city (optional)'"
            :disabled="!formGovernorateId"
          />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
          <FormInput
            v-model="formLat"
            :label="t.service?.lat || 'Latitude'"
            :error="serviceStore.validationErrors?.lat"
            :placeholder="t.service?.lat_placeholder || 'e.g. 30.0444'"
            type="number"
            step="0.0000001"
            min="-90"
            max="90"
          />
          <FormInput
            v-model="formLong"
            :label="t.service?.long || 'Longitude'"
            :error="serviceStore.validationErrors?.long"
            :placeholder="t.service?.long_placeholder || 'e.g. 31.2357'"
            type="number"
            step="0.0000001"
            min="-180"
            max="180"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-white mb-2">
            {{ t.service?.iframe_location || 'Map Embed (iframe)' }}
            <span v-if="serviceStore.validationErrors?.iframe_location" class="text-destructive ml-2 text-xs">{{ serviceStore.validationErrors.iframe_location }}</span>
          </label>
          <textarea
            v-model="formIframeLocation"
            rows="4"
            class="w-full rounded-md border border-border bg-transparent px-3 py-2 text-sm text-foreground shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring"
            :placeholder="t.service?.iframe_location_placeholder || 'Paste the Google Maps embed <iframe> code here'"
          ></textarea>
          <div v-if="formIframeLocation" class="mt-3 rounded-lg overflow-hidden border border-border" v-html="formIframeLocation"></div>
        </div>
      </div>
    </div>

    <!-- Images Card -->
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] !items-start gap-1.5 py-2 px-6 [.border-b]:pb-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/>
            <circle cx="9" cy="9" r="2"/>
            <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
          </svg>
          {{ t.service?.images || 'Images' }}
        </div>
      </div>
      <div data-slot="card-content" class="px-6 space-y-4">
        <div data-slot="form-item" class="grid gap-1">
          <label class="block text-sm font-medium text-white mb-2">
            {{ t.service?.main_image || 'Main Image' }}
            <span class="text-xs text-muted-foreground ml-2">({{ t.common?.optional || 'Optional' }} - 2MB)</span>
          </label>
          <ImageFileInput
            :max-size="2"
            :accepted-types="['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp', 'image/avif']"
            :initial-preview="initialImagePreview"
            @file-selected="handleImageSelected"
            @error="(err) => imageError = err"
          />
          <p v-if="serviceStore.validationErrors?.image || imageError" class="mt-1 text-sm text-destructive">
            {{ serviceStore.validationErrors?.image || imageError }}
          </p>
        </div>

        <div data-slot="form-item" class="grid gap-1">
          <label class="block text-sm font-medium text-white mb-2">
            {{ t.service?.gallery || 'Gallery' }}
            <span class="text-xs text-muted-foreground ml-2">({{ t.common?.optional || 'Optional' }} - 2MB each)</span>
          </label>

          <!-- Existing gallery images -->
          <div v-if="existingGallery.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
            <div
              v-for="img in existingGallery"
              :key="img.id"
              class="relative group rounded-lg overflow-hidden border border-border aspect-square"
            >
              <img :src="img.url" class="w-full h-full object-cover" loading="lazy" />
              <button
                type="button"
                @click="removeGalleryImage(img.id)"
                class="absolute top-1 right-1 p-1 bg-black/60 rounded-full opacity-0 group-hover:opacity-100 transition-opacity hover:bg-red-500/80"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M18 6 6 18"></path><path d="m6 6 12 12"></path>
                </svg>
              </button>
            </div>
          </div>

          <!-- Upload new gallery images -->
          <ImageFileInput
            :max-size="2"
            multiple
            :accepted-types="['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp', 'image/avif']"
            @files-selected="handleGallerySelected"
            @error="(err) => galleryError = err"
          />
          <p v-if="serviceStore.validationErrors?.gallery || galleryError" class="mt-1 text-sm text-destructive">
            {{ serviceStore.validationErrors?.gallery || galleryError }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { FormInput, FormSelect, QuillTextEditor } from "@/Components/form";
import ImageFileInput from "@/Components/form/ImageFileInput.vue";
import { useServiceStore } from "../Stores/ServiceStore";
import { computed, ref, watch } from "vue";
import { storeToRefs } from "pinia";
import { usePage } from "@inertiajs/vue3";
import { usePriceFormat } from '@/composables/usePriceFormat';

const { formatPrice } = usePriceFormat();

const props = defineProps({
  service: {
    type: Object,
    default: () => null
  },
  serviceTypes: {
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
  },
  tagOptions: {
    type: Array,
    default: () => []
  },
  tags: {
    type: Array,
    default: () => []
  }
});

const serviceStore = useServiceStore();
const { form } = storeToRefs(serviceStore);
const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

console.log('[ServiceForm] setup, props.service =', props.service);
watch(form, (newForm) => {
  console.log('[ServiceForm] store.form changed ->', newForm);
}, { immediate: true, deep: true });

const isEditMode = computed(() => !!props.service?.id);
const imageError = ref('');
const galleryError = ref('');

const initialImagePreview = computed(() => props.service?.image || '');
const existingGallery = computed(() => props.service?.gallery || []);

const handleImageSelected = (file) => {
  form.value.image = file;
  imageError.value = '';
};

const handleGallerySelected = (files) => {
  if (!form.value.gallery) form.value.gallery = [];
  if (Array.isArray(files)) {
    for (const file of files) {
      form.value.gallery.push(file);
    }
  } else if (files) {
    form.value.gallery.push(files);
  }
  galleryError.value = '';
};

const removeGalleryImage = (id) => {
  if (!form.value.deleted_gallery_ids) form.value.deleted_gallery_ids = [];
  form.value.deleted_gallery_ids.push(id);
  if (props.service?.gallery) {
    const idx = props.service.gallery.findIndex(img => img.id === id);
    if (idx !== -1) {
      props.service.gallery.splice(idx, 1);
    }
  }
};

const categoryOptions = computed(() =>
  props.serviceTypes.map(type => ({ value: type.id, label: type.name }))
);

const tagSelectOptions = computed(() => props.tagOptions);

const isTagSelected = (id) => (form.value.tag_ids || []).includes(id);

const toggleTag = (id) => {
  if (!form.value.tag_ids) form.value.tag_ids = [];
  const idx = form.value.tag_ids.indexOf(id);
  if (idx === -1) {
    form.value.tag_ids.push(id);
  } else {
    form.value.tag_ids.splice(idx, 1);
  }
};

const tagChipStyle = (tagItem, selected) => {
  const color = tagItem.color || '#6B7280';
  if (selected) {
    return {
      backgroundColor: color,
      color: '#fff',
      borderColor: color,
    };
  }
  return {
    backgroundColor: `${color}1A`,
    color,
    borderColor: `${color}33`,
  };
};

const governorateOptions = computed(() =>
  props.governorates.map(gov => ({ value: gov.id, label: gov.name }))
);

const cityOptions = computed(() => {
  if (!form.value.governorate_id) return [];
  return props.cities
    .filter(city => city.governorate_id === form.value.governorate_id)
    .map(city => ({ value: city.id, label: city.name }));
});

const formTitle = computed({
  get: () => form.value.title ?? '',
  set: (value) => { form.value.title = value; },
});

const formShortSubject = computed({
  get: () => form.value.short_subject ?? '',
  set: (value) => { form.value.short_subject = value; },
});

const formSubject = computed({
  get: () => form.value.subject ?? '',
  set: (value) => { form.value.subject = value; },
});

const formCategoryId = computed({
  get: () => form.value.category_id ?? '',
  set: (value) => { form.value.category_id = value; },
});

const formTag = computed({
  get: () => form.value.tag ?? '',
  set: (value) => { form.value.tag = value; },
});

const formNewPrice = computed({
  get: () => form.value.new_price,
  set: (value) => { form.value.new_price = value === '' || value === null ? null : value; },
});

const formOldPrice = computed({
  get: () => form.value.old_price,
  set: (value) => { form.value.old_price = value === '' || value === null ? null : value; },
});

const formDiscountPercent = computed({
  get: () => form.value.discount_percent,
  set: (value) => { form.value.discount_percent = value === '' || value === null ? null : value; },
});

// Admins pick one pricing method at a time. Selecting "Discount %" hides
// Price/Old Price entirely (and vice versa), so only the active method's
// field(s) ever get filled in and submitted.
const pricingMode = ref(
  !props.service?.old_price && props.service?.discount_percent ? 'percent' : 'old_price'
);

watch(pricingMode, (mode) => {
  if (mode === 'old_price') {
    form.value.discount_percent = null;
  } else {
    form.value.new_price = null;
    form.value.old_price = null;
  }
});

const formGovernorateId = computed({
  get: () => form.value.governorate_id ?? '',
  set: (value) => {
    form.value.governorate_id = value;
    form.value.city_id = '';
  },
});

const formCityId = computed({
  get: () => form.value.city_id ?? '',
  set: (value) => { form.value.city_id = value; },
});

const formLat = computed({
  get: () => form.value.lat ?? '',
  set: (value) => { form.value.lat = value === '' || value === null ? null : value; },
});

const formLong = computed({
  get: () => form.value.long ?? '',
  set: (value) => { form.value.long = value === '' || value === null ? null : value; },
});

const formIframeLocation = computed({
  get: () => form.value.iframe_location ?? '',
  set: (value) => { form.value.iframe_location = value; },
});

const discountPreview = computed(() => {
  const price = parseFloat(formNewPrice.value);
  const oldPrice = parseFloat(formOldPrice.value);

  if (!isNaN(price) && !isNaN(oldPrice) && oldPrice > price && price > 0) {
    const savings = oldPrice - price;
    const percentage = Math.round((savings / oldPrice) * 100);
    return { hasDiscount: true, savings, percentage };
  }

  return { hasDiscount: false, savings: 0, percentage: 0 };
});
</script>

<style lang="scss" scoped></style>
