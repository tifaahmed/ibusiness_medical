<!-- components/FormCheckbox.vue -->
<template>
  <div class="flex items-center group">
    <Checkbox
      :id="id"
      :modelValue="modelValue"
      @update:modelValue="$emit('update:modelValue', $event)"
      :error="error"
      :disabled="disabled"
    />
    <div class="ml-3 text-sm">
      <label
        :for="id"
        class="font-medium text-white transition-colors duration-200 cursor-pointer"
      >
        {{ label }}
        <span v-if="required" class="text-destructive ml-0.5">*</span>
      </label>
      <p v-if="error" class="text-destructive mt-1 text-xs">{{ error }}</p>
    </div>
  </div>
</template>

<script setup>
import Checkbox from "@/Components/ui/Checkbox.vue";

defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  label: String,
  required: Boolean,
  error: String,
  disabled: {
    type: Boolean,
    default: false
  },
  id: {
    type: String,
    default: () => `checkbox-${Math.random().toString(36).substr(2, 9)}`,
  },
});

defineEmits(["update:modelValue"]);
</script>
