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
        <input
          :id="`${id}-${locale}`"
          :value="getLocaleValue(locale)"
          @input="updateLocaleValue(locale, $event.target.value)"
          type="text"
          :required="required && locale === 'ar'"
          :dir="locale === 'ar' ? 'rtl' : 'ltr'"
          :class="[
            'w-full py-2 px-3 border border-border text-foreground placeholder:text-white/70 focus:border-ring dark:bg-input/30 bg-transparent focus:outline-none rounded-md h-9 mt-1 focus:ring-[3px] focus:ring-ring/50',
            getLocaleError(locale)
              ? 'border-destructive focus:border-destructive focus:ring-destructive/20 dark:focus:ring-destructive/40'
              : '',
          ]"
          :placeholder="`Enter ${label.toLowerCase()} in ${locale.toUpperCase()}`"
          v-bind="$attrs"
        />
        <p v-if="getLocaleError(locale)" class="mt-1 text-sm text-destructive">{{ getLocaleError(locale) }}</p>
      </div>
    </div>
    <p v-if="error && typeof error === 'string'" class="mt-1 text-sm text-destructive">{{ error }}</p>
  </div>
</template>

<script setup>
import { computed, ref, watch } from "vue";

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

const id = computed(() => `translatable-input-${Math.random().toString(36).substr(2, 9)}`);

// Ensure modelValue is an object
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

watch(() => props.modelValue, (newVal) => {
  // Ensure it's always an object
  if (typeof newVal === 'string') {
    try {
      const parsed = JSON.parse(newVal);
      if (parsed && typeof parsed === 'object') {
        emit('update:modelValue', parsed);
      }
    } catch {}
  }
}, { deep: true });
</script>

