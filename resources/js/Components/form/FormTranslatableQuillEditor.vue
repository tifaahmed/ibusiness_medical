<template>
  <div>
    <label class="block text-sm font-medium text-white mb-1">
      {{ label }}
      <span v-if="required" class="text-destructive">*</span>
    </label>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div v-for="locale in locales" :key="locale">
        <label :for="`${id}-${locale}`" class="block text-xs font-medium text-muted-foreground mb-1">
          {{ locale.toUpperCase() }}
        </label>
        <QuillTextEditor
          :id="`${id}-${locale}`"
          :model-value="getLocaleValue(locale)"
          :placeholder="`Enter ${label.toLowerCase()} in ${locale.toUpperCase()}`"
          :error="getLocaleError(locale)"
          @update:model-value="updateLocaleValue(locale, $event)"
        />
      </div>
    </div>
    <p v-if="error && typeof error === 'string'" class="mt-1 text-sm text-destructive">{{ error }}</p>
  </div>
</template>

<script setup>
import { computed } from "vue";
import QuillTextEditor from "./QuillTextEditor.vue";

const props = defineProps({
  modelValue: {
    type: [Object, String],
    default: () => ({})
  },
  label: String,
  required: Boolean,
  error: [String, Object, Array],
  locales: {
    type: Array,
    default: () => ['ar', 'en']
  },
});

const emit = defineEmits(["update:modelValue"]);

const id = computed(() => `translatable-quill-${Math.random().toString(36).substr(2, 9)}`);

const currentValue = computed(() => {
  if (typeof props.modelValue === 'string') {
    try {
      return JSON.parse(props.modelValue) || {};
    } catch {
      return {};
    }
  }
  return props.modelValue || {};
});

const getLocaleValue = (locale) => {
  return currentValue.value[locale] || '';
};

const updateLocaleValue = (locale, value) => {
  const updated = { ...currentValue.value, [locale]: value };
  emit('update:modelValue', updated);
};

const getLocaleError = (locale) => {
  if (!props.error) return null;
  if (typeof props.error === 'string') return props.error;
  if (Array.isArray(props.error)) {
    const localeError = props.error.find(e => e && typeof e === 'object' && e.locale === locale);
    return localeError ? localeError.message : null;
  }
  if (typeof props.error === 'object') {
    return props.error[locale] || null;
  }
  return null;
};
</script>
