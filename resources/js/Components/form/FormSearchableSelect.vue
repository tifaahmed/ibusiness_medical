<template>
  <div>
    <label :for="id" class="block text-sm font-medium text-white mb-1">
      {{ label }}
      <span v-if="required" class="text-destructive">*</span>
    </label>
    <div class="mt-1">
      <SearchableSelect
        :id="id"
        :modelValue="modelValue"
        @update:modelValue="$emit('update:modelValue', $event)"
        @change="$emit('change', $event)"
        :options="options"
        :placeholder="placeholder || `Select ${label}`"
        :error="error"
      />
    </div>
    <p v-if="error" class="mt-1 text-sm text-destructive">{{ error }}</p>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

defineProps({
  modelValue: [String, Number, null],
  label: String,
  options: { type: Array, default: () => [] },
  required: Boolean,
  error: String,
  placeholder: String,
});

defineEmits(['update:modelValue', 'change']);

const id = computed(() => `searchable-select-${Math.random().toString(36).substr(2, 9)}`);
</script>
