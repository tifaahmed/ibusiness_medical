<template>
  <!-- Narrow enough for a table footer, and the shared select inside it, so the
       rows-per-page picker is the same control as every other select on the
       site rather than a bare browser one wearing its own colours. -->
  <div :class="['shrink-0', widthClass]">
    <Select
      :model-value="String(modelValue ?? 15)"
      :options="options"
      :id="id"
      :disabled="disabled"
      @update:model-value="$emit('update:modelValue', Number($event))"
    />
  </div>
</template>

<script setup>
import { computed } from 'vue';
import Select from '@/Components/ui/Select.vue';

const props = defineProps({
  modelValue: { type: [String, Number], default: 15 },
  /** How many rows a page may hold. */
  choices: { type: Array, default: () => [10, 15, 25, 50, 100] },
  widthClass: { type: String, default: 'w-[76px] sm:w-[88px]' },
  id: { type: String, default: undefined },
  disabled: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);

const options = computed(() =>
  props.choices.map(choice => ({ value: String(choice), label: String(choice) }))
);
</script>
