<template>
  <div class="space-y-3">
    <!-- Basic Info Card -->
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] !items-start gap-1.5 py-2 px-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <path d="M12 2H2v10l9.29 9.29a2 2 0 0 0 2.83 0l8.17-8.17a2 2 0 0 0 0-2.83L12 2z"/>
            <circle cx="7" cy="7" r="1.5" fill="currentColor"/>
          </svg>
          {{ t.partner_offer?.basic_info || 'Basic Information' }}
        </div>
      </div>
      <div data-slot="card-content" class="px-6 space-y-4">
        <FormSelect
          v-model="formPartnerId"
          :label="t.partner_offer?.partner || 'Partner'"
          :options="partnerOptions"
          :error="partnerOfferStore.validationErrors?.partner_id"
          required
        />
        <FormInput
          v-model="formTitle"
          :label="t.partner_offer?.title || 'Title'"
          :error="partnerOfferStore.validationErrors?.title"
          :placeholder="t.partner_offer?.title_placeholder || 'Enter the offer title'"
          required
        />
        <FormTextarea
          v-model="formShortDescription"
          :label="t.partner_offer?.short_description || 'Short Description'"
          :error="partnerOfferStore.validationErrors?.short_description"
          :placeholder="t.partner_offer?.short_description_placeholder || 'Enter a short description'"
          :rows="3"
        />
        <div data-slot="form-item" class="grid gap-1">
          <label class="block text-sm font-medium text-white mb-2">
            {{ t.partner_offer?.description || 'Description' }}
          </label>
          <QuillTextEditor
            v-model="formDescription"
          />
          <p v-if="partnerOfferStore.validationErrors?.description" class="mt-1 text-sm text-destructive">
            {{ partnerOfferStore.validationErrors.description }}
          </p>
        </div>
      </div>
    </div>

    <!-- Pricing Card -->
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] !items-start gap-1.5 py-2 px-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <path d="M12 2a10 10 0 1 0 10 10h-10V2z"/>
            <path d="M12 12 2 12a10 10 0 0 0 10 10V12z"/>
          </svg>
          {{ t.partner_offer?.pricing || 'Pricing' }}
        </div>
      </div>
      <div data-slot="card-content" class="px-6 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <FormInput
            v-model="formOldPrice"
            type="number"
            step="0.01"
            min="0"
            :label="t.partner_offer?.old_price || 'Old Price'"
            :error="partnerOfferStore.validationErrors?.old_price"
            :placeholder="t.partner_offer?.old_price_placeholder || '0.00'"
          />
          <FormInput
            v-model="formNewPrice"
            type="number"
            step="0.01"
            min="0"
            :label="t.partner_offer?.new_price || 'New Price'"
            :error="partnerOfferStore.validationErrors?.new_price"
            :placeholder="t.partner_offer?.new_price_placeholder || '0.00'"
          />
        </div>
      </div>
    </div>

    <!-- Contact Card -->
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] !items-start gap-1.5 py-2 px-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <rect width="20" height="16" x="2" y="4" rx="2"/>
            <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
          </svg>
          {{ t.partner_offer?.contact || 'Contact' }}
        </div>
      </div>
      <div data-slot="card-content" class="px-6 space-y-4">
        <FormInput
          v-model="formPhoneNumber"
          :label="t.partner_offer?.phone_number || 'Phone Number'"
          :error="partnerOfferStore.validationErrors?.phone_number"
          :placeholder="t.partner_offer?.phone_number_placeholder || '+966 5X XXX XXXX'"
          dir="ltr"
        />
      </div>
    </div>

    <!-- Operator Card -->
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] !items-start gap-1.5 py-2 px-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
          </svg>
          {{ t.partner_offer?.operator || 'Operator' }}
        </div>
      </div>
      <div data-slot="card-content" class="px-6 space-y-4">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <button
            type="button"
            v-for="op in operators"
            :key="op.value"
            @click="formOperator = formOperator === op.value ? null : op.value"
            class="relative flex flex-col items-center gap-2 p-4 rounded-xl border-2 transition-all duration-200 cursor-pointer"
            :class="formOperator === op.value
              ? 'border-golden-yellow bg-golden-yellow/10 shadow-lg shadow-golden-yellow/20'
              : 'border-border bg-card hover:border-muted-foreground/30 hover:bg-accent/50'"
          >
            <div class="w-12 h-12 rounded-full bg-muted flex items-center justify-center overflow-hidden">
              <img :src="op.logo" :alt="op.title" class="w-full h-full object-cover" />
            </div>
            <div class="text-center">
              <p class="text-sm font-semibold text-foreground">{{ op.title }}</p>
              <p class="text-xs text-muted-foreground">{{ op.name }}</p>
            </div>
            <div
              v-if="formOperator === op.value"
              class="absolute top-2 right-2 w-5 h-5 rounded-full bg-golden-yellow flex items-center justify-center"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"/>
              </svg>
            </div>
          </button>
        </div>
        <input type="hidden" :value="formOperator" />
        <p v-if="partnerOfferStore.validationErrors?.operator" class="mt-1 text-sm text-destructive">
          {{ partnerOfferStore.validationErrors.operator }}
        </p>
      </div>
    </div>

    <!-- Header Image Card -->
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] !items-start gap-1.5 py-2 px-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/>
            <circle cx="9" cy="9" r="2"/>
            <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
          </svg>
          {{ t.partner_offer?.header_image || 'Header Image' }}
        </div>
      </div>
      <div data-slot="card-content" class="px-6 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
          <div data-slot="form-item" class="grid gap-1">
            <label class="block text-sm font-medium text-white mb-2">
              {{ t.partner_offer?.header_image || 'Header Image' }}
              <span class="text-xs text-muted-foreground ml-2">(Max 5MB)</span>
            </label>
            <ImageFileInput
              :max-size="5"
              :accepted-types="['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp', 'image/avif']"
              :initial-preview="headerImagePreview"
              @file-selected="handleHeaderImageSelected"
              @error="(err) => headerImageError = err"
            />
            <p v-if="partnerOfferStore.validationErrors?.header_image || headerImageError" class="mt-1 text-sm text-destructive">
              {{ partnerOfferStore.validationErrors?.header_image || headerImageError }}
            </p>
          </div>
          <div data-slot="form-item" class="grid gap-1">
            <label class="block text-sm font-medium text-white mb-2">
              {{ t.partner_offer?.mobile_header_image || 'Mobile Header Image' }}
              <span class="text-xs text-muted-foreground ml-2">(Max 5MB)</span>
            </label>
            <ImageFileInput
              :max-size="5"
              :accepted-types="['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp', 'image/avif']"
              :initial-preview="mobileHeaderImagePreview"
              @file-selected="handleMobileHeaderImageSelected"
              @error="(err) => mobileHeaderImageError = err"
            />
            <p v-if="partnerOfferStore.validationErrors?.mobile_header_image || mobileHeaderImageError" class="mt-1 text-sm text-destructive">
              {{ partnerOfferStore.validationErrors?.mobile_header_image || mobileHeaderImageError }}
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Small Image Card -->
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] !items-start gap-1.5 py-2 px-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/>
            <circle cx="9" cy="9" r="2"/>
            <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
          </svg>
          {{ t.partner_offer?.small_image || 'Small Image' }}
        </div>
      </div>
      <div data-slot="card-content" class="px-6 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
          <div data-slot="form-item" class="grid gap-1">
            <label class="block text-sm font-medium text-white mb-2">
              {{ t.partner_offer?.small_image || 'Small Image' }}
              <span class="text-xs text-muted-foreground ml-2">(Max 5MB)</span>
            </label>
            <ImageFileInput
              :max-size="5"
              :accepted-types="['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp', 'image/avif']"
              :initial-preview="smallImagePreview"
              @file-selected="handleSmallImageSelected"
              @error="(err) => smallImageError = err"
            />
            <p v-if="partnerOfferStore.validationErrors?.small_image || smallImageError" class="mt-1 text-sm text-destructive">
              {{ partnerOfferStore.validationErrors?.small_image || smallImageError }}
            </p>
          </div>
          <div data-slot="form-item" class="grid gap-1">
            <label class="block text-sm font-medium text-white mb-2">
              {{ t.partner_offer?.mobile_small_image || 'Mobile Small Image' }}
              <span class="text-xs text-muted-foreground ml-2">(Max 5MB)</span>
            </label>
            <ImageFileInput
              :max-size="5"
              :accepted-types="['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp', 'image/avif']"
              :initial-preview="mobileSmallImagePreview"
              @file-selected="handleMobileSmallImageSelected"
              @error="(err) => mobileSmallImageError = err"
            />
            <p v-if="partnerOfferStore.validationErrors?.mobile_small_image || mobileSmallImageError" class="mt-1 text-sm text-destructive">
              {{ partnerOfferStore.validationErrors?.mobile_small_image || mobileSmallImageError }}
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Gallery Card -->
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] !items-start gap-1.5 py-2 px-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/>
            <circle cx="9" cy="9" r="2"/>
            <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
          </svg>
          {{ t.partner_offer?.gallery || 'Gallery' }}
        </div>
      </div>
      <div data-slot="card-content" class="px-6 space-y-4">
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
        <div data-slot="form-item" class="grid gap-1">
          <label class="block text-sm font-medium text-white mb-2">
            {{ t.partner_offer?.add_gallery_images || 'Add Gallery Images' }}
            <span class="text-xs text-muted-foreground ml-2">(Max 5MB each)</span>
          </label>
          <ImageFileInput
            :max-size="5"
            multiple
            :accepted-types="['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp', 'image/avif']"
            @files-selected="handleGallerySelected"
            @error="(err) => galleryError = err"
          />
          <p v-if="partnerOfferStore.validationErrors?.gallery || galleryError" class="mt-1 text-sm text-destructive">
            {{ partnerOfferStore.validationErrors?.gallery || galleryError }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { FormInput, FormSelect, FormTextarea } from "@/Components/form";
import QuillTextEditor from "@/Components/form/QuillTextEditor.vue";
import ImageFileInput from "@/Components/form/ImageFileInput.vue";
import { usePartnerOfferStore } from "../Stores/PartnerOfferStore";
import { computed, ref, watch } from "vue";
import { storeToRefs } from "pinia";
import { usePage } from "@inertiajs/vue3";

const props = defineProps({
  partnerOffer: {
    type: Object,
    default: () => null,
  },
  partners: {
    type: Array,
    default: () => [],
  },
  operators: {
    type: Array,
    default: () => [],
  },
});

const partnerOfferStore = usePartnerOfferStore();
const { form } = storeToRefs(partnerOfferStore);
const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const isEditMode = computed(() => !!props.partnerOffer?.id);
const headerImageError = ref('');
const mobileHeaderImageError = ref('');
const smallImageError = ref('');
const mobileSmallImageError = ref('');
const galleryError = ref('');
const deletedGalleryIds = ref([]);

const partnerOptions = computed(() =>
  props.partners.map(p => ({ value: p.id, label: p.title }))
);

const headerImagePreview = computed(() => props.partnerOffer?.header_image || '');
const mobileHeaderImagePreview = computed(() => props.partnerOffer?.mobile_header_image || '');
const smallImagePreview = computed(() => props.partnerOffer?.small_image || '');
const mobileSmallImagePreview = computed(() => props.partnerOffer?.mobile_small_image || '');
const existingGallery = computed(() => props.partnerOffer?.gallery || []);

const handleHeaderImageSelected = (file) => {
  form.value.header_image = file;
  headerImageError.value = '';
};

const handleMobileHeaderImageSelected = (file) => {
  form.value.mobile_header_image = file;
  mobileHeaderImageError.value = '';
};

const handleSmallImageSelected = (file) => {
  form.value.small_image = file;
  smallImageError.value = '';
};

const handleMobileSmallImageSelected = (file) => {
  form.value.mobile_small_image = file;
  mobileSmallImageError.value = '';
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
  deletedGalleryIds.value.push(id);
  if (props.partnerOffer?.gallery) {
    const idx = props.partnerOffer.gallery.findIndex(img => img.id === id);
    if (idx !== -1) {
      props.partnerOffer.gallery.splice(idx, 1);
    }
  }
};

const formPartnerId = computed({
  get: () => form.value.partner_id ?? '',
  set: (value) => { form.value.partner_id = value; },
});

const formTitle = computed({
  get: () => form.value.title ?? '',
  set: (value) => { form.value.title = value; },
});

const formShortDescription = computed({
  get: () => form.value.short_description ?? '',
  set: (value) => { form.value.short_description = value; },
});

const formDescription = computed({
  get: () => form.value.description ?? '',
  set: (value) => { form.value.description = value; },
});

const formOldPrice = computed({
  get: () => form.value.old_price ?? '',
  set: (value) => { form.value.old_price = value; },
});

const formNewPrice = computed({
  get: () => form.value.new_price ?? '',
  set: (value) => { form.value.new_price = value; },
});

const formPhoneNumber = computed({
  get: () => form.value.phone_number ?? '',
  set: (value) => { form.value.phone_number = value; },
});

const formOperator = computed({
  get: () => form.value.operator ?? '',
  set: (value) => { form.value.operator = value; },
});

watch(deletedGalleryIds, (ids) => {
  form.value.deleted_gallery_ids = ids;
}, { deep: true });
</script>
