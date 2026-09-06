<template>
  <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
    <div data-slot="card-header" class="px-4 sm:px-6 pt-4 sm:pt-6">
      <h2 class="text-base font-semibold flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-golden-yellow">
          <path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 0 0-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 0 0-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 0 0-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 0 0-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 0 0 1.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
          <circle cx="12" cy="12" r="3"/>
        </svg>
        {{ setting ? (t.setting?.edit_title || 'Edit setting') : (t.setting?.create_title || 'New setting') }}
      </h2>
      <p class="text-xs text-muted-foreground mt-1">
        {{ t.setting?.form_help || 'The key is what the site reads this value by. The type decides how the value is stored and shown.' }}
      </p>
    </div>

    <div data-slot="card-content" class="p-4 sm:p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <FormInput
          v-model="slug"
          :label="t.setting?.key || 'Key'"
          required
          :error="error('slug')"
          :placeholder="'deilar_phone'"
        />
        <p class="mt-1 text-[11px] text-muted-foreground">
          {{ t.setting?.key_help || 'Lowercase letters, numbers, dashes and underscores. Read in code as SiteSettings::get(\'key\').' }}
        </p>
        <p v-if="setting && slug !== originalSlug" class="mt-1 text-[11px] text-amber-500">
          {{ t.setting?.key_changed || 'Changing the key means anything already reading the old one falls back to its default.' }}
        </p>
      </div>

      <div>
        <FormSelect
          v-model="valueType"
          :label="t.setting?.value_type || 'Value type'"
          :options="typeOptions"
          required
          :error="error('value_type')"
        />
        <p class="mt-1 text-[11px] text-muted-foreground">{{ typeHint }}</p>
      </div>

      <div class="md:col-span-2">
        <FormTranslatableInput
          v-model="name"
          :label="t.setting?.item_name || 'Name'"
          required
          :error="nameError"
          :locales="['ar', 'en']"
          :hint="t.setting?.name_help || 'The label shown in this list. Not used by the site itself.'"
        />
      </div>

      <!-- The value editor follows the type: a switch for a flag, a file for
           an image, a textarea for long text or JSON, a plain field otherwise. -->
      <div class="md:col-span-2">
        <template v-if="valueType === 'boolean'">
          <FormCheckbox
            v-model="booleanValue"
            :label="t.setting?.value || 'Value'"
            :error="error('value')"
          />
          <p class="mt-1 text-[11px] text-muted-foreground">
            {{ t.setting?.boolean_help || 'Stored as 1 or 0, and read back as true / false.' }}
          </p>
        </template>

        <template v-else-if="valueType === 'image'">
          <label class="block text-sm font-medium mb-2">{{ t.setting?.value || 'Value' }}</label>
          <ImageFileInput
            :key="imageInputKey"
            :initial-preview="currentImageUrl"
            :max-size="maxImageSize"
            :crop="false"
            :accepted-types="['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp', 'image/avif']"
            @file-selected="onImageSelected"
            @error="onImageError"
          />
          <p v-if="imageError || error('value_image')" class="mt-1 text-sm text-destructive">{{ imageError || error('value_image') }}</p>
          <p class="mt-1 text-[11px] text-muted-foreground">
            {{ t.setting?.image_help || 'The file is uploaded and stored on the site; the row keeps its path, never a link to somewhere else.' }}
          </p>
          <p v-if="storedImagePath" class="mt-1 text-[11px] text-muted-foreground font-mono" dir="ltr">{{ storedImagePath }}</p>
        </template>

        <template v-else-if="valueType === 'text' || valueType === 'json'">
          <FormTextarea
            v-model="value"
            :label="t.setting?.value || 'Value'"
            :rows="valueType === 'json' ? 8 : 4"
            :error="error('value')"
          />
          <p v-if="valueType === 'json'" class="mt-1 text-[11px] text-muted-foreground">
            {{ t.setting?.json_help || 'Must be valid JSON — an object, a list, or a single value.' }}
          </p>
        </template>

        <template v-else>
          <FormInput
            v-model="value"
            :label="t.setting?.value || 'Value'"
            :type="inputType"
            :error="error('value')"
            :placeholder="valuePlaceholder"
          />
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { FormInput, FormSelect, FormTextarea, FormCheckbox, FormTranslatableInput, ImageFileInput } from "@/Components/form";
import { useSettingStore } from "../Stores/SettingStore";
import { usePage } from "@inertiajs/vue3";
import { storeToRefs } from "pinia";
import { computed, ref, watch } from "vue";

const props = defineProps({
  setting: {
    type: Object,
    default: () => null
  },
  valueTypes: {
    type: Array,
    default: () => []
  },
  // Megabytes. Sent by the server, which caps it at what PHP will accept —
  // an upload past that limit never reaches validation, so the browser has to
  // be the one to stop it.
  maxImageSize: {
    type: Number,
    default: 5
  }
});

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const settingStore = useSettingStore();
const { form } = storeToRefs(settingStore);

const originalSlug = props.setting?.slug || '';
const imageError = ref('');
// Bumped to reset the file input when the admin switches type away from image
// and back — otherwise it keeps showing a preview for a value no longer held.
const imageInputKey = ref(0);

const error = (field) => settingStore.validationErrors?.[field] || '';

const nameError = computed(() =>
  settingStore.validationErrors?.['name.ar']
  || settingStore.validationErrors?.['name.en']
  || settingStore.validationErrors?.name
  || ''
);

const field = (key) => computed({
  get: () => form.value[key],
  set: (v) => { form.value[key] = v; },
});

const slug = field('slug');
const name = field('name');
const value = field('value');

const valueType = computed({
  get: () => form.value.value_type,
  set: (v) => {
    form.value.value_type = v;
    // A value typed for one shape rarely survives another: switching to a flag
    // or an image starts the value clean rather than carrying a stale string.
    if (v === 'boolean') {
      form.value.value = form.value.value === '1' ? '1' : '0';
    } else if (v === 'image') {
      form.value.value = '';
      imageInputKey.value += 1;
    } else if (form.value.value === '0' || form.value.value === '1') {
      // Leaving boolean: the stored flag is meaningless as text.
      form.value.value = '';
    }
  },
});

const booleanValue = computed({
  get: () => form.value.value === '1' || form.value.value === true,
  set: (v) => { form.value.value = v ? '1' : '0'; },
});

const typeOptions = computed(() =>
  props.valueTypes.map((type) => ({
    value: type,
    label: t.value.setting?.types?.[type] || type,
  }))
);

const TYPE_HINTS = {
  string: 'A single line of text.',
  text: 'A longer passage — an address, a paragraph.',
  number: 'A number. Read back as a number, not text.',
  boolean: 'A yes / no switch.',
  url: 'A full web address, starting with http:// or https://.',
  email: 'An email address.',
  phone: 'A phone number, stored exactly as typed.',
  image: 'A file uploaded to the site. The row stores its path.',
  json: 'Structured data — read back as an array.',
};

const typeHint = computed(() =>
  t.value.setting?.type_hints?.[valueType.value] || TYPE_HINTS[valueType.value] || ''
);

// `type="number"` on a decimal-friendly field, plain text elsewhere: email and
// url validation is done server-side, and the browser's own is stricter than
// what an admin may legitimately want to store.
const inputType = computed(() => (valueType.value === 'number' ? 'number' : 'text'));

const PLACEHOLDERS = {
  url: 'https://deilar.com',
  email: 'info@deilar.com',
  phone: '01020709993',
  number: '0',
};
const valuePlaceholder = computed(() => PLACEHOLDERS[valueType.value] || '');

// On edit, the image already stored: shown as the preview, and kept unless the
// admin picks a new file or clears it.
const currentImageUrl = computed(() => (props.setting?.value_type === 'image' ? props.setting?.image_url : null) || null);
const storedImagePath = computed(() => (props.setting?.value_type === 'image' ? props.setting?.value : '') || '');

// ImageFileInput emits null when the admin clears the preview. On edit that has
// to become an explicit delete, otherwise the stored file survives the save.
const onImageSelected = (file) => {
  form.value.value_image = file || null;
  form.value.value_image_delete = !file && Boolean(currentImageUrl.value);
  imageError.value = '';
};

const onImageError = (message) => {
  imageError.value = message || '';
};

watch(() => props.setting, (next) => {
  if (next?.id) settingStore.setSetting(next);
}, { deep: true });
</script>

<style lang="scss" scoped></style>
