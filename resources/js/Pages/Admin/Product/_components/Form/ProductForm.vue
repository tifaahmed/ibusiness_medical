<template>
  <div class="space-y-2 sm:space-y-3 md:space-y-4">
    <div class="bg-card text-card-foreground flex flex-col gap-2 sm:gap-3 md:gap-4 rounded-xl border border-border py-2 sm:py-3 md:py-4 shadow-sm">
      <div class="py-2 px-3 sm:px-4 md:px-6">
        <div class="title-golden leading-none font-semibold">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon sm:w-6 sm:h-6">
            <path d="m7.5 4.27 9 5.15"></path>
            <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"></path>
            <path d="m3.3 7 8.7 5 8.7-5"></path>
            <path d="M12 22V12"></path>
          </svg>
          <span class="text-sm sm:text-base">Product Information</span>
        </div>
      </div>
      <div class="px-3 sm:px-4 md:px-6 space-y-4">
        <FormTranslatableInput
          v-model="formName"
          label="Name"
          :error="productStore.validationErrors?.name"
          placeholder="Enter product name"
          required
          :locales="['ar', 'en']"
        />

        <FormTranslatableInput
          v-model="formShortSubject"
          label="Short Description"
          :error="productStore.validationErrors?.['short_subject']"
          placeholder="Enter short description"
          :locales="['ar', 'en']"
        />

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="space-y-2">
            <label class="text-sm font-medium">New Price</label>
            <input
              v-model="productStore.form.new_price"
              type="number"
              step="0.01"
              min="0"
              placeholder="0.00"
              class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
            />
          </div>
          <div class="space-y-2">
            <label class="text-sm font-medium">Old Price</label>
            <input
              v-model="productStore.form.old_price"
              type="number"
              step="0.01"
              min="0"
              placeholder="0.00"
              class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
            />
          </div>
        </div>

        <div class="space-y-2">
          <label class="text-sm font-medium">Product Type</label>
          <select
            v-model="productStore.form.product_type_id"
            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
          >
            <option value="">— None —</option>
            <option v-for="pt in productTypes" :key="pt.id" :value="pt.id">
              {{ getTranslatedName(pt.name) }}
            </option>
          </select>
        </div>

        <div v-if="tags.length" class="space-y-2">
          <label class="text-sm font-medium">Tags</label>
          <div class="flex flex-wrap gap-2">
            <label
              v-for="tag in tags"
              :key="tag.id"
              class="inline-flex items-center gap-1.5 px-2 py-1 rounded-md border cursor-pointer transition-colors text-xs"
              :class="productStore.form.tag_ids.includes(tag.id)
                ? 'border-primary bg-primary/10 text-primary'
                : 'border-border bg-background text-muted-foreground hover:bg-muted'"
            >
              <input
                type="checkbox"
                :value="tag.id"
                v-model="productStore.form.tag_ids"
                class="sr-only"
              />
              <span v-if="tag.icon">{{ tag.icon }}</span>
              {{ tag.name }}
            </label>
          </div>
        </div>
      </div>
    </div>

    <div class="bg-card text-card-foreground flex flex-col gap-2 sm:gap-3 md:gap-4 rounded-xl border border-border py-2 sm:py-3 md:py-4 shadow-sm">
      <div class="py-2 px-3 sm:px-4 md:px-6">
        <div class="title-golden leading-none font-semibold">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon sm:w-6 sm:h-6">
            <rect width="18" height="18" x="3" y="3" rx="2" ry="2"></rect>
            <circle cx="9" cy="9" r="2"></circle>
            <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"></path>
          </svg>
          <span class="text-sm sm:text-base">Images</span>
        </div>
      </div>
      <div class="px-3 sm:px-4 md:px-6 space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="space-y-2">
            <label class="text-sm font-medium">Large Image</label>
            <div v-if="existingLargeImage && !productStore.form.large_image" class="mb-2">
              <img :src="existingLargeImage" class="w-32 h-32 rounded-lg border border-border object-cover" />
              <p class="text-xs text-muted-foreground mt-1">Current image — upload new to replace</p>
            </div>
            <input
              type="file"
              accept="image/*"
              @change="handleLargeImage"
              class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 file:mr-2 file:py-1 file:px-2 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-muted file:text-muted-foreground"
            />
          </div>
          <div class="space-y-2">
            <label class="text-sm font-medium">Small Image</label>
            <div v-if="existingSmallImage && !productStore.form.small_image" class="mb-2">
              <img :src="existingSmallImage" class="w-24 h-24 rounded-lg border border-border object-cover" />
              <p class="text-xs text-muted-foreground mt-1">Current image — upload new to replace</p>
            </div>
            <input
              type="file"
              accept="image/*"
              @change="handleSmallImage"
              class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 file:mr-2 file:py-1 file:px-2 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-muted file:text-muted-foreground"
            />
          </div>
        </div>

        <div class="space-y-2">
          <label class="text-sm font-medium">Gallery</label>
          <div v-if="existingGallery?.length && !productStore.form.gallery.length" class="flex flex-wrap gap-2 mb-2">
            <img
              v-for="(img, i) in existingGallery"
              :key="i"
              :src="img"
              class="w-20 h-20 rounded-lg border border-border object-cover"
            />
            <p class="text-xs text-muted-foreground w-full">Current gallery — upload new files to replace</p>
          </div>
          <input
            type="file"
            accept="image/*"
            multiple
            @change="handleGallery"
            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 file:mr-2 file:py-1 file:px-2 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-muted file:text-muted-foreground"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { FormTranslatableInput } from "@/Components/form";
import { useProductStore } from "../../Stores/ProductStore";
import { computed } from "vue";
import { storeToRefs } from "pinia";

const props = defineProps({
  productTypes: { type: Array, default: () => [] },
  tags: { type: Array, default: () => [] },
  existingLargeImage: { type: String, default: null },
  existingSmallImage: { type: String, default: null },
  existingGallery: { type: Array, default: () => [] },
});

const productStore = useProductStore();
const { form } = storeToRefs(productStore);

const getTranslatedName = (name) => {
  if (typeof name === 'string') return name;
  if (typeof name === 'object' && name !== null) {
    return name['ar'] || name['en'] || Object.values(name)[0] || '';
  }
  return '';
};

const formName = computed({
  get: () => {
    const name = form.value.name;
    if (!name || typeof name !== 'object' || Array.isArray(name)) return {};
    return name;
  },
  set: (value) => { form.value.name = value; }
});

const formShortSubject = computed({
  get: () => {
    const val = form.value.short_subject;
    if (!val || typeof val !== 'object' || Array.isArray(val)) return {};
    return val;
  },
  set: (value) => { form.value.short_subject = value; }
});

const handleLargeImage = (e) => {
  productStore.form.large_image = e.target.files?.[0] || null;
};

const handleSmallImage = (e) => {
  productStore.form.small_image = e.target.files?.[0] || null;
};

const handleGallery = (e) => {
  productStore.form.gallery = Array.from(e.target.files || []);
};
</script>
