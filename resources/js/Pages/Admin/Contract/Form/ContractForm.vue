<template>
  <div class="space-y-3">
    <!-- Contract Information Card -->
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] !items-start gap-1.5 py-2 px-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
            <polyline points="14 2 14 8 20 8"></polyline>
            <line x1="16" y1="13" x2="8" y2="13"></line>
            <line x1="16" y1="17" x2="8" y2="17"></line>
          </svg>
          {{ t.contract?.information || 'Contract Information' }}
        </div>
      </div>
      <div data-slot="card-content" class="px-6 space-y-4">
        <!-- Name (Translatable) -->
        <div class="grid grid-cols-1 gap-4 items-start">
          <div data-slot="form-item" class="grid gap-1">
            <FormTranslatableInput
              v-model="formName"
              :label="t.contract?.name || 'Name'"
              :error="contractStore.validationErrors?.['name.ar'] || contractStore.validationErrors?.['name.en']"
              :placeholder="t.contract?.name_placeholder || 'Enter contract name'"
              :locales="['ar', 'en']"
              required
            />
          </div>
        </div>

        <!-- Description (Rich Text - Translatable) -->
        <div class="grid grid-cols-1 gap-4 items-start">
          <div data-slot="form-item" class="grid gap-1">
            <label class="block text-sm font-medium text-white mb-2">
              {{ t.contract?.description || 'Description' }}
            </label>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <!-- Arabic Description -->
              <div>
                <label class="block text-xs font-medium text-muted-foreground mb-1">
                  {{ t.common?.arabic || 'Arabic' }}
                </label>
                <QuillTextEditor
                  v-model="formDescriptionAr"
                  :placeholder="t.contract?.description_placeholder || 'Enter contract description in Arabic'"
                  :error="contractStore.validationErrors?.['description.ar']"
                  :toolbar="quillToolbar"
                />
                <p v-if="contractStore.validationErrors?.['description.ar']" class="mt-1 text-sm text-destructive">
                  {{ contractStore.validationErrors['description.ar'] }}
                </p>
              </div>
              <!-- English Description -->
              <div>
                <label class="block text-xs font-medium text-muted-foreground mb-1">
                  {{ t.common?.english || 'English' }}
                </label>
                <QuillTextEditor
                  v-model="formDescriptionEn"
                  :placeholder="t.contract?.description_placeholder || 'Enter contract description in English'"
                  :error="contractStore.validationErrors?.['description.en']"
                  :toolbar="quillToolbar"
                />
                <p v-if="contractStore.validationErrors?.['description.en']" class="mt-1 text-sm text-destructive">
                  {{ contractStore.validationErrors['description.en'] }}
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Sort Order and Active Status -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
          <div data-slot="form-item" class="grid gap-1">
            <FormInput
              v-model="formSortOrder"
              :label="t.contract?.sort_order || 'Sort Order'"
              :error="contractStore.validationErrors?.sort_order"
              :placeholder="t.contract?.sort_order_placeholder || 'Enter sort order'"
              type="number"
              min="0"
            />
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

    <!-- Phones Card -->
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] !items-start gap-1.5 py-2 px-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
          </svg>
          {{ t.contract?.phones || 'Phones' }}
        </div>
      </div>
      <div data-slot="card-content" class="px-6 space-y-4">
        <!-- Phone Rows -->
        <div v-for="(phone, index) in form.phones" :key="index" class="flex items-start gap-3">
          <div class="flex-1">
            <FormInput
              v-model="form.phones[index].number"
              :label="index === 0 ? (t.contract?.phone_number || 'Phone Number') : ''"
              :error="contractStore.validationErrors?.[`phones.${index}.number`]"
              :placeholder="t.contract?.phone_number_placeholder || 'Enter phone number'"
            />
          </div>
          <div class="w-44">
            <FormSelect
              v-model="form.phones[index].type"
              :label="index === 0 ? (t.contract?.phone_type || 'Type') : ''"
              :options="phoneTypeOptions"
              :error="contractStore.validationErrors?.[`phones.${index}.type`]"
              :placeholder="t.contract?.select_type || 'Select type'"
            />
          </div>
          <button
            type="button"
            @click="removePhone(index)"
            class="text-destructive hover:text-destructive/80 transition-colors mt-1"
            :class="{ 'mt-8': index === 0 }"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 6h18"></path>
              <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
              <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
            </svg>
          </button>
        </div>

        <!-- Add Phone Button -->
        <button
          type="button"
          @click="addPhone"
          class="inline-flex items-center gap-2 rounded-md border border-dashed border-border px-4 py-2 text-sm text-muted-foreground hover:text-foreground hover:border-foreground transition-colors"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
          </svg>
          {{ t.contract?.add_phone || 'Add Phone' }}
        </button>
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
          {{ t.contract?.image || 'Image' }}
        </div>
      </div>
      <div data-slot="card-content" class="px-6 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
          <div data-slot="form-item" class="grid gap-1">
            <label class="block text-sm font-medium text-white mb-2">
              {{ t.contract?.logo_image || 'Logo Image' }}
              <span class="text-xs text-muted-foreground ml-2">({{ t.offer?.optional || 'Optional' }} - Max 5MB)</span>
            </label>
            <ImageFileInput
              :max-size="5"
              :accepted-types="['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp', 'image/avif']"
              :initial-preview="initialImagePreview"
              @file-selected="handleImageSelected"
              @error="(err) => imageError = err"
            />
            <p v-if="contractStore.validationErrors?.image || imageError" class="mt-1 text-sm text-destructive">
              {{ contractStore.validationErrors?.image || imageError }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { FormTranslatableInput, FormInput, FormSelect } from "@/Components/form";
import QuillTextEditor from "@/Components/form/QuillTextEditor.vue";
import ImageFileInput from "@/Components/form/ImageFileInput.vue";
import { useContractStore } from "../Stores/ContractStore";
import { computed, ref } from "vue";
import { storeToRefs } from "pinia";
import { usePage } from "@inertiajs/vue3";

const props = defineProps({
  contract: {
    type: Object,
    default: () => null
  }
});

const contractStore = useContractStore();
const { form } = storeToRefs(contractStore);
const page = usePage();
const locale = computed(() => page.props.locale || 'ar');
const t = computed(() => page.props.translations?.admin || {});

const imageError = ref('');
const initialImagePreview = computed(() => props.contract?.image || '');

const phoneTypeOptions = computed(() => [
  { value: 'phone', label: t.value.contract?.type_phone || 'Phone' },
  { value: 'whatsapp', label: t.value.contract?.type_whatsapp || 'WhatsApp' },
]);

const handleImageSelected = (file) => {
  form.value.image = file;
  imageError.value = '';
};

const formName = computed({
  get: () => {
    const name = form.value.name;
    if (!name || typeof name !== 'object' || Array.isArray(name)) {
      return { ar: '', en: '' };
    }
    return name;
  },
  set: (value) => {
    form.value.name = value;
  }
});

const quillToolbar = [
  ['bold', 'italic', 'underline', 'strike'],
  ['blockquote'],
  [{ 'header': 1 }, { 'header': 2 }],
  [{ 'list': 'ordered'}, { 'list': 'bullet' }],
  [{ 'direction': 'rtl' }],
  [{ 'size': ['small', false, 'large', 'huge'] }],
  [{ 'color': [] }, { 'background': [] }],
  [{ 'align': [] }],
  ['link'],
  ['clean']
];

const formDescriptionAr = computed({
  get: () => {
    const desc = form.value.description;
    if (!desc || typeof desc !== 'object' || Array.isArray(desc)) return '';
    return desc.ar || '';
  },
  set: (value) => {
    const desc = form.value.description && typeof form.value.description === 'object' ? { ...form.value.description } : { ar: '', en: '' };
    desc.ar = value;
    form.value.description = desc;
  }
});

const formDescriptionEn = computed({
  get: () => {
    const desc = form.value.description;
    if (!desc || typeof desc !== 'object' || Array.isArray(desc)) return '';
    return desc.en || '';
  },
  set: (value) => {
    const desc = form.value.description && typeof form.value.description === 'object' ? { ...form.value.description } : { ar: '', en: '' };
    desc.en = value;
    form.value.description = desc;
  }
});

const formSortOrder = computed({
  get: () => form.value.sort_order ?? 0,
  set: (value) => {
    form.value.sort_order = value === '' || value === null ? 0 : parseInt(value);
  }
});

const addPhone = () => {
  form.value.phones.push({ number: '', type: 'phone' });
};

const removePhone = (index) => {
  form.value.phones.splice(index, 1);
};

const formIsActive = computed({
  get: () => form.value.is_active ?? true,
  set: (value) => {
    form.value.is_active = value;
  }
});
</script>

<style lang="scss" scoped></style>
