<template>
  <div class="space-y-3">
    <!-- Name Card -->
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] !items-start gap-1.5 py-2 px-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
            <circle cx="9" cy="7" r="4"></circle>
            <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
          </svg>
          {{ t.sales?.name || 'Name' }}
        </div>
      </div>
      <div data-slot="card-content" class="px-6 space-y-4">
        <FormTranslatableInput
          v-model="formName"
          :label="t.sales?.name || 'Name'"
          :error="salesStore.validationErrors?.['name.ar'] || salesStore.validationErrors?.['name.en']"
          :placeholder="t.sales?.name_placeholder || 'Enter the sales name'"
          required
        />
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
          {{ t.sales?.image || 'Image' }}
        </div>
      </div>
      <div data-slot="card-content" class="px-6 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
          <div data-slot="form-item" class="grid gap-1">
            <label class="block text-sm font-medium text-white mb-2">
              {{ t.sales?.sales_image || 'Sales Image' }}
              <span class="text-xs text-muted-foreground ml-2">({{ t.common?.optional || 'Optional' }} - 2MB)</span>
            </label>
            <ImageFileInput
              :max-size="2"
              :accepted-types="['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp', 'image/avif']"
              :initial-preview="initialImagePreview"
              @file-selected="handleImageSelected"
              @error="(err) => imageError = err"
            />
            <p v-if="salesStore.validationErrors?.image || imageError" class="mt-1 text-sm text-destructive">
              {{ salesStore.validationErrors?.image || imageError }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { FormTranslatableInput } from "@/Components/form";
import ImageFileInput from "@/Components/form/ImageFileInput.vue";
import { useSalesStore } from "../Stores/SalesStore";
import { computed, ref } from "vue";
import { storeToRefs } from "pinia";
import { usePage } from "@inertiajs/vue3";

const props = defineProps({
  sale: {
    type: Object,
    default: () => null,
  },
});

const salesStore = useSalesStore();
const { form } = storeToRefs(salesStore);
const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const isEditMode = computed(() => !!props.sale?.id);
const imageError = ref('');
const initialImagePreview = computed(() => props.sale?.image || '');

const handleImageSelected = (file) => {
  form.value.image = file;
  imageError.value = '';
};

const formName = computed({
  get: () => {
    const value = form.value.name;
    if (!value || typeof value !== 'object' || Array.isArray(value)) return { ar: '', en: '' };
    return value;
  },
  set: (value) => {
    form.value.name = value;
  },
});
</script>
