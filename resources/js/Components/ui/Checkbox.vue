<template>
  <button
    type="button"
    :id="id"
    @click="toggle"
    :disabled="disabled"
    :class="[
      'relative inline-flex items-center justify-center h-5 w-5 rounded border-2 transition-all duration-200 ease-in-out cursor-pointer focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-transparent',
      checked
        ? 'bg-primary border-primary text-white'
        : 'bg-transparent border-white/50 text-transparent hover:border-white',
      error
        ? 'border-destructive focus:ring-destructive/50'
        : 'focus:ring-primary/50',
      disabled
        ? 'opacity-50 cursor-not-allowed'
        : ''
    ]"
    :aria-checked="checked"
    :aria-invalid="error ? 'true' : undefined"
    role="checkbox"
  >
    <!-- Checkmark icon -->
    <svg
      v-if="checked"
      class="h-3.5 w-3.5"
      fill="none"
      stroke="currentColor"
      stroke-width="3"
      viewBox="0 0 24 24"
    >
      <path
        stroke-linecap="round"
        stroke-linejoin="round"
        d="M5 13l4 4L19 7"
      />
    </svg>
  </button>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  },
  id: {
    type: String,
    default: () => `checkbox-${Math.random().toString(36).substr(2, 9)}`
  },
  error: {
    type: String,
    default: null
  },
  disabled: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(['update:modelValue', 'change']);

const checked = computed(() => props.modelValue);

const toggle = () => {
  if (!props.disabled) {
    const newValue = !checked.value;
    emit('update:modelValue', newValue);
    emit('change', newValue);
  }
};
</script>

