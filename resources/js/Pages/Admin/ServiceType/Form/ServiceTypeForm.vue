<template>
  <div class="space-y-3">
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] !items-start gap-1.5 py-2 px-6 [.border-b]:pb-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <path d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
            <path d="M9 7a1 1 0 11-2 0 1 1 0 012 0z"/>
          </svg>
          {{ t.serviceType?.information || 'Category Information' }}
        </div>
      </div>
      <div data-slot="card-content" class="px-6 space-y-4">
        <FormTranslatableInput
          v-model="formName"
          :label="t.serviceType?.name || 'Name'"
          :error="serviceTypeStore.validationErrors?.name"
          required
          :locales="['en', 'ar']"
        />

        <FormTextarea
          v-model="formDescription"
          :label="t.serviceType?.description || 'Description'"
          :error="serviceTypeStore.validationErrors?.description"
          :placeholder="t.serviceType?.description_placeholder || 'Enter description (optional)'"
          :rows="3"
        />

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-start">
          <FormSelect
            v-model="formIcon"
            :label="t.serviceType?.icon || 'Icon (emoji)'"
            :error="serviceTypeStore.validationErrors?.icon"
            :options="iconOptions"
            :placeholder="t.serviceType?.icon_placeholder || 'Select icon'"
          />
          <FormSelect
            v-model="formColor"
            :label="t.serviceType?.color || 'Color (hex)'"
            :error="serviceTypeStore.validationErrors?.color"
            :options="colorOptions"
            :placeholder="t.serviceType?.color_placeholder || 'Select color'"
          />
          <div class="pt-6">
            <FormCheckbox
              v-model="formIsActive"
              :label="t.serviceType?.is_active || 'Active'"
            />
          </div>
        </div>
      </div>
    </div>

    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] !items-start gap-1.5 py-2 px-6 [.border-b]:pb-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/>
            <circle cx="9" cy="9" r="2"/>
            <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
          </svg>
          {{ t.serviceType?.image || 'Image' }}
        </div>
      </div>
      <div data-slot="card-content" class="px-6 space-y-4">
        <ImageFileInput
          :max-size="2"
          :accepted-types="['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp', 'image/avif']"
          :initial-preview="initialImagePreview"
          @file-selected="handleImageSelected"
          @error="(err) => imageError = err"
        />
        <p v-if="serviceTypeStore.validationErrors?.image || imageError" class="mt-1 text-sm text-destructive">
          {{ serviceTypeStore.validationErrors?.image || imageError }}
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { FormInput, FormSelect, FormTextarea, FormCheckbox, FormTranslatableInput } from "@/Components/form";
import ImageFileInput from "@/Components/form/ImageFileInput.vue";
import { useServiceTypeStore } from "../Stores/ServiceTypeStore";
import { computed, ref } from "vue";
import { storeToRefs } from "pinia";
import { usePage } from "@inertiajs/vue3";

const props = defineProps({
  serviceType: {
    type: Object,
    default: () => null
  },
  iconOptions: {
    type: Array,
    default: () => []
  },
  colorOptions: {
    type: Array,
    default: () => []
  }
});

const serviceTypeStore = useServiceTypeStore();
const { form } = storeToRefs(serviceTypeStore);
const page = usePage();
const t = computed(() => page.props.translations?.admin || {});
const imageError = ref('');

const initialImagePreview = computed(() => props.serviceType?.image || '');

const handleImageSelected = (file) => {
  form.value.image = file;
  imageError.value = '';
};

const formName = computed({
  get: () => {
    const name = form.value.name;
    if (!name || typeof name !== 'object' || Array.isArray(name)) {
      return {};
    }
    return name;
  },
  set: (value) => { form.value.name = value; },
});

const formDescription = computed({
  get: () => form.value.description ?? '',
  set: (value) => { form.value.description = value; },
});

const formIcon = computed({
  get: () => form.value.icon ?? '',
  set: (value) => { form.value.icon = value; },
});

const formColor = computed({
  get: () => form.value.color ?? '',
  set: (value) => { form.value.color = value; },
});

const formIsActive = computed({
  get: () => form.value.is_active ?? true,
  set: (value) => { form.value.is_active = value; },
});
</script>

<style lang="scss" scoped></style>
