<!-- components/FormTextarea.vue -->
<template>
  <div>
    <label
      :for="id"
      class="block text-sm font-medium text-white mb-1"
    >
      {{ label }}
      <span v-if="required" class="text-destructive">*</span>
    </label>
    <textarea
      :id="id"
      :value="modelValue"
      @input="$emit('update:modelValue', $event.target.value)"
      :required="required"
      :class="[
        'w-full py-2 px-3 border border-border text-foreground placeholder:text-white/70 focus:border-ring dark:bg-input/30 bg-transparent focus:outline-none rounded-md mt-1 focus:ring-[3px] focus:ring-ring/50',
        error
          ? 'border-destructive focus:border-destructive focus:ring-destructive/20 dark:focus:ring-destructive/40'
          : '',
      ]"
      v-bind="$attrs"
      :rows="rows"
    ></textarea>
    <p v-if="error" class="mt-1 text-sm text-destructive">{{ error }}</p>
  </div>
</template>

<script setup>
import { computed } from "vue";

const props = defineProps({
  modelValue: [String, Number],
  label: String,
  required: Boolean,
  error: String,
  rows: [String, Number]
});

defineEmits(["update:modelValue"]);

const id = computed(() => `textarea-${Math.random().toString(36).substr(2, 9)}`);
</script>
