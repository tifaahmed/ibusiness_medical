<template>
  <div>
    <label
      :for="id"
      class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1"
    >
      {{ label }}
      <span v-if="required" class="text-red-500">*</span>
    </label>
    <textarea
      :id="id"
      :value="modelValue"
      @input="$emit('update:modelValue', $event.target.value)"
      :required="required"
      :class="[
        'w-full py-2 px-3 border border-gray-200 focus:border-secondary-600 dark:border-gray-800 dark:focus:border-secondary-600 bg-transparent focus:outline-none rounded mt-1 focus:ring-0',
        error
          ? 'border-red-300 focus:border-red-500 focus:ring-red-500'
          : 'border-gray-300 focus:border-secondary-500 focus:ring-secondary-500',
      ]"
      v-bind="$attrs"
    ></textarea>
    <p v-if="error" class="mt-1 text-sm text-red-600">{{ error }}</p>
  </div>
</template>

<script setup>
import { computed } from "vue";

const props = defineProps({
  modelValue: [String, Number],
  label: String,
  required: Boolean,
  error: String,
});

defineEmits(["update:modelValue"]);

const id = computed(() => `textarea-${Math.random().toString(36).substr(2, 9)}`);
</script>
