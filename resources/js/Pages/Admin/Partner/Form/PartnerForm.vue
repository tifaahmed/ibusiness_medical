<template>
  <div class="space-y-3">
    <!-- Title Card -->
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] !items-start gap-1.5 py-2 px-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
            <circle cx="9" cy="7" r="4"></circle>
            <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
          </svg>
          {{ t.partner?.title || 'Title' }}
        </div>
      </div>
      <div data-slot="card-content" class="px-6 space-y-4">
        <FormInput
          v-model="formTitle"
          :label="t.partner?.title || 'Title'"
          :error="partnerStore.validationErrors?.title"
          :placeholder="t.partner?.title_placeholder || 'Enter the partner title'"
          required
        />
      </div>
    </div>

    <!-- Description Card -->
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] !items-start gap-1.5 py-2 px-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <path d="M12 2H2v10l9.29 9.29a2 2 0 0 0 2.83 0l8.17-8.17a2 2 0 0 0 0-2.83L12 2z"/>
            <circle cx="7" cy="7" r="1.5" fill="currentColor"/>
          </svg>
          {{ t.partner?.description_info || 'Description' }}
        </div>
      </div>
      <div data-slot="card-content" class="px-6 space-y-4">
        <FormTextarea
          v-model="formShortDescription"
          :label="t.partner?.short_description || 'Short Description'"
          :error="partnerStore.validationErrors?.short_description"
          :placeholder="t.partner?.short_description_placeholder || 'Enter a short description'"
          :rows="3"
        />
        <div data-slot="form-item" class="grid gap-1">
          <label class="block text-sm font-medium text-white mb-2">
            {{ t.partner?.description || 'Description' }}
          </label>
          <QuillTextEditor
            v-model="formDescription"
          />
          <p v-if="partnerStore.validationErrors?.description" class="mt-1 text-sm text-destructive">
            {{ partnerStore.validationErrors.description }}
          </p>
        </div>
      </div>
    </div>

    <!-- Image Card -->
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] !items-start gap-1.5 py-2 px-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/>
            <circle cx="9" cy="9" r="2"/>
            <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
          </svg>
          {{ t.partner?.image || 'Image' }}
        </div>
      </div>
      <div data-slot="card-content" class="px-6 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
          <div data-slot="form-item" class="grid gap-1">
            <label class="block text-sm font-medium text-white mb-2">
              {{ t.partner?.partner_image || 'Partner Image' }}
              <span v-if="!isEditMode" class="text-destructive">*</span>
              <span class="text-xs text-muted-foreground ml-2">(Max 5MB)</span>
            </label>
            <ImageFileInput
              :max-size="5"
              :accepted-types="['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp', 'image/avif']"
              :initial-preview="initialImagePreview"
              @file-selected="handleImageSelected"
              @error="(err) => imageError = err"
            />
            <p v-if="partnerStore.validationErrors?.image || imageError" class="mt-1 text-sm text-destructive">
              {{ partnerStore.validationErrors?.image || imageError }}
            </p>
          </div>
        </div>
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
          {{ t.partner?.header_image || 'Header Image' }}
        </div>
      </div>
      <div data-slot="card-content" class="px-6 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
          <div data-slot="form-item" class="grid gap-1">
            <label class="block text-sm font-medium text-white mb-2">
              {{ t.partner?.header_image || 'Header Image' }}
              <span class="text-xs text-muted-foreground ml-2">(Max 5MB)</span>
            </label>
            <ImageFileInput
              :max-size="5"
              :accepted-types="['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp', 'image/avif']"
              :initial-preview="headerImagePreview"
              @file-selected="handleHeaderImageSelected"
              @error="(err) => headerImageError = err"
            />
            <p v-if="partnerStore.validationErrors?.header_image || headerImageError" class="mt-1 text-sm text-destructive">
              {{ partnerStore.validationErrors?.header_image || headerImageError }}
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
          {{ t.partner?.gallery || 'Gallery' }}
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
            {{ t.partner?.add_gallery_images || 'Add Gallery Images' }}
            <span class="text-xs text-muted-foreground ml-2">(Max 5MB each)</span>
          </label>
          <ImageFileInput
            :max-size="5"
            multiple
            :accepted-types="['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp', 'image/avif']"
            @files-selected="handleGallerySelected"
            @error="(err) => galleryError = err"
          />
          <p v-if="partnerStore.validationErrors?.gallery || galleryError" class="mt-1 text-sm text-destructive">
            {{ partnerStore.validationErrors?.gallery || galleryError }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { FormInput, FormTextarea } from "@/Components/form";
import QuillTextEditor from "@/Components/form/QuillTextEditor.vue";
import ImageFileInput from "@/Components/form/ImageFileInput.vue";
import { usePartnerStore } from "../Stores/PartnerStore";
import { computed, ref, watch } from "vue";
import { storeToRefs } from "pinia";
import { usePage } from "@inertiajs/vue3";

const props = defineProps({
  partner: {
    type: Object,
    default: () => null,
  },
});

const partnerStore = usePartnerStore();
const { form } = storeToRefs(partnerStore);
const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const isEditMode = computed(() => !!props.partner?.id);
const imageError = ref('');
const headerImageError = ref('');
const galleryError = ref('');
const deletedGalleryIds = ref([]);

const initialImagePreview = computed(() => props.partner?.image || '');
const headerImagePreview = computed(() => props.partner?.header_image || '');
const existingGallery = computed(() => props.partner?.gallery || []);

const handleImageSelected = (file) => {
  form.value.image = file;
  imageError.value = '';
};

const handleHeaderImageSelected = (file) => {
  form.value.header_image = file;
  headerImageError.value = '';
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
  if (props.partner?.gallery) {
    const idx = props.partner.gallery.findIndex(img => img.id === id);
    if (idx !== -1) {
      props.partner.gallery.splice(idx, 1);
    }
  }
};

const formTitle = computed({
  get: () => form.value.title ?? '',
  set: (value) => {
    form.value.title = value;
  },
});

const formShortDescription = computed({
  get: () => form.value.short_description ?? '',
  set: (value) => { form.value.short_description = value; },
});

const formDescription = computed({
  get: () => form.value.description ?? '',
  set: (value) => { form.value.description = value; },
});

watch(deletedGalleryIds, (ids) => {
  form.value.deleted_gallery_ids = ids;
}, { deep: true });
</script>
