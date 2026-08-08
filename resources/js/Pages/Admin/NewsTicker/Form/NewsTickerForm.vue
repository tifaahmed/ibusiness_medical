<template>
  <div class="space-y-3">
    <!-- Title Card -->
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] !items-start gap-1.5 py-2 px-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <path d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"></path>
          </svg>
          {{ t.news_ticker?.title || 'Title' }}
        </div>
      </div>
      <div data-slot="card-content" class="px-6 space-y-4">
        <FormTranslatableInput
          v-model="formTitle"
          :label="t.news_ticker?.title || 'Title'"
          :error="newsTickerStore.validationErrors?.['title.ar'] || newsTickerStore.validationErrors?.['title.en']"
          :placeholder="t.news_ticker?.title_placeholder || 'Enter the news title'"
          :locales="['ar', 'en']"
          required
        />
      </div>
    </div>

    <!-- Description Card -->
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] !items-start gap-1.5 py-2 px-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
          </svg>
          {{ t.news_ticker?.description || 'Description' }}
        </div>
      </div>
      <div data-slot="card-content" class="px-6 space-y-4">
        <label class="block text-sm font-medium text-white mb-2">
          {{ t.news_ticker?.description || 'Description' }}
          <span class="text-destructive">*</span>
        </label>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-muted-foreground mb-1">
              {{ t.common?.arabic || 'Arabic' }}
            </label>
            <textarea
              v-model="formDescriptionAr"
              dir="rtl"
              rows="5"
              :placeholder="t.news_ticker?.description_placeholder_ar || 'Enter the description in Arabic'"
              :class="[
                'w-full py-2 px-3 border border-border text-foreground placeholder:text-white/70 focus:border-ring dark:bg-input/30 bg-transparent focus:outline-none rounded-md focus:ring-[3px] focus:ring-ring/50 leading-relaxed',
                newsTickerStore.validationErrors?.['description.ar'] ? 'border-destructive focus:border-destructive focus:ring-destructive/20' : ''
              ]"
            ></textarea>
            <p v-if="newsTickerStore.validationErrors?.['description.ar']" class="mt-1 text-sm text-destructive">
              {{ newsTickerStore.validationErrors['description.ar'] }}
            </p>
          </div>
          <div>
            <label class="block text-xs font-medium text-muted-foreground mb-1">
              {{ t.common?.english || 'English' }}
            </label>
            <textarea
              v-model="formDescriptionEn"
              dir="ltr"
              rows="5"
              :placeholder="t.news_ticker?.description_placeholder_en || 'Enter the description in English'"
              :class="[
                'w-full py-2 px-3 border border-border text-foreground placeholder:text-white/70 focus:border-ring dark:bg-input/30 bg-transparent focus:outline-none rounded-md focus:ring-[3px] focus:ring-ring/50 leading-relaxed',
                newsTickerStore.validationErrors?.['description.en'] ? 'border-destructive focus:border-destructive focus:ring-destructive/20' : ''
              ]"
            ></textarea>
            <p v-if="newsTickerStore.validationErrors?.['description.en']" class="mt-1 text-sm text-destructive">
              {{ newsTickerStore.validationErrors['description.en'] }}
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Settings Card -->
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] !items-start gap-1.5 py-2 px-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <circle cx="12" cy="12" r="3"></circle>
            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
          </svg>
          {{ t.common?.settings || 'Settings' }}
        </div>
      </div>
      <div data-slot="card-content" class="px-6 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
          <div data-slot="form-item" class="grid gap-1">
            <FormInput
              v-model="formCategory"
              :label="t.news_ticker?.category || 'Category'"
              :error="newsTickerStore.validationErrors?.category"
              :placeholder="t.news_ticker?.category_placeholder || 'Enter category'"
            />
          </div>
          <div data-slot="form-item" class="grid gap-1">
            <FormInput
              v-model="formSortOrder"
              :label="t.news_ticker?.sort_order || 'Sort Order'"
              :error="newsTickerStore.validationErrors?.sort_order"
              :placeholder="t.news_ticker?.sort_order_placeholder || 'Enter sort order'"
              type="number"
              min="0"
            />
          </div>
          <div data-slot="form-item" class="grid gap-1">
            <label class="block text-sm font-medium text-white mb-2">
              {{ t.news_ticker?.image || 'Image' }}
              <span class="text-xs text-muted-foreground ml-2">({{ t.common?.optional || 'Optional' }} - 5MB)</span>
            </label>
            <ImageFileInput
              :max-size="5"
              :accepted-types="['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp', 'image/avif']"
              :initial-preview="initialImagePreview"
              @file-selected="handleImageSelected"
              @error="(err) => imageError = err"
            />
            <p v-if="newsTickerStore.validationErrors?.image || imageError" class="mt-1 text-sm text-destructive">
              {{ newsTickerStore.validationErrors?.image || imageError }}
            </p>
          </div>
          <div data-slot="form-item" class="grid gap-1">
            <label class="block text-sm font-medium text-white mb-2">
              {{ t.news_ticker?.mobile_image || 'Mobile Image' }}
              <span class="text-xs text-muted-foreground ml-2">({{ t.common?.optional || 'Optional' }} - 5MB)</span>
            </label>
            <ImageFileInput
              :max-size="5"
              :accepted-types="['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp', 'image/avif']"
              :initial-preview="initialMobileImagePreview"
              @file-selected="handleMobileImageSelected"
              @error="(err) => mobileImageError = err"
            />
            <p v-if="newsTickerStore.validationErrors?.mobile_image || mobileImageError" class="mt-1 text-sm text-destructive">
              {{ newsTickerStore.validationErrors?.mobile_image || mobileImageError }}
            </p>
          </div>
          <div data-slot="form-item" class="grid gap-1">
            <label class="block text-sm font-medium text-white mb-2">
              {{ t.common?.status || 'Status' }}
            </label>
            <div class="flex items-center gap-3">
              <label class="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  v-model="formIsActive"
                  class="w-4 h-4 text-primary bg-transparent border-border focus:ring-ring focus:ring-2 rounded"
                />
                <span class="text-sm text-foreground">{{ t.common?.active || 'Active' }}</span>
              </label>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { FormTranslatableInput, FormInput } from "@/Components/form";
import ImageFileInput from "@/Components/form/ImageFileInput.vue";
import { useNewsTickerStore } from "../Stores/NewsTickerStore";
import { computed, ref } from "vue";
import { storeToRefs } from "pinia";
import { usePage } from "@inertiajs/vue3";

const props = defineProps({
  newsTicker: {
    type: Object,
    default: () => null,
  },
});

const newsTickerStore = useNewsTickerStore();
const { form } = storeToRefs(newsTickerStore);
const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const imageError = ref('');
const mobileImageError = ref('');
const initialImagePreview = computed(() => props.newsTicker?.image_url || '');
const initialMobileImagePreview = computed(() => props.newsTicker?.mobile_image_url || '');

const handleImageSelected = (file) => {
  newsTickerStore.form.image = file;
  imageError.value = '';
};

const handleMobileImageSelected = (file) => {
  newsTickerStore.form.mobile_image = file;
  mobileImageError.value = '';
};

const formTitle = computed({
  get: () => {
    const value = form.value.title;
    if (!value || typeof value !== 'object' || Array.isArray(value)) return { ar: '', en: '' };
    return value;
  },
  set: (value) => {
    form.value.title = value;
  },
});

const formDescriptionAr = computed({
  get: () => {
    const value = form.value.description;
    if (!value || typeof value !== 'object' || Array.isArray(value)) return '';
    return value.ar || '';
  },
  set: (value) => {
    const next = form.value.description && typeof form.value.description === 'object' && !Array.isArray(form.value.description)
      ? { ...form.value.description }
      : { ar: '', en: '' };
    next.ar = value;
    form.value.description = next;
  },
});

const formDescriptionEn = computed({
  get: () => {
    const value = form.value.description;
    if (!value || typeof value !== 'object' || Array.isArray(value)) return '';
    return value.en || '';
  },
  set: (value) => {
    const next = form.value.description && typeof form.value.description === 'object' && !Array.isArray(form.value.description)
      ? { ...form.value.description }
      : { ar: '', en: '' };
    next.en = value;
    form.value.description = next;
  },
});

const formCategory = computed({
  get: () => form.value.category || '',
  set: (value) => {
    form.value.category = value;
  },
});

const formSortOrder = computed({
  get: () => form.value.sort_order ?? 0,
  set: (value) => {
    form.value.sort_order = value === '' || value === null ? 0 : parseInt(value);
  },
});

const formIsActive = computed({
  get: () => form.value.is_active ?? true,
  set: (value) => {
    form.value.is_active = value;
  },
});
</script>
