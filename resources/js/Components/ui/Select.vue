<template>
  <SearchableSelect
    :modelValue="modelValue"
    :options="options"
    :placeholder="placeholder"
    :id="id"
    :error="error"
    :disabled="disabled"
    @update:modelValue="$emit('update:modelValue', $event)"
    @change="$emit('change', $event)"
  />
</template>

<script setup>
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

defineProps({
  modelValue: [String, Number, null],
  options: { type: Array, required: true, default: () => [] },
  placeholder: { type: String, default: 'Select...' },
  id: { type: String, default: () => `select-${Math.random().toString(36).substr(2, 9)}` },
  error: { type: String, default: null },
  disabled: { type: Boolean, default: false },
});

defineEmits(['update:modelValue', 'change']);
</script>
