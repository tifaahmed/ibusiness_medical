<template>
  <div>
    <label class="text-xs font-medium text-muted-foreground">{{ label }}</label>
    <slot>
      <p class="text-sm font-medium mt-0.5 break-words" :class="valueClass">{{ display }}</p>
    </slot>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  label: { type: String, required: true },
  value: { type: [String, Number, Boolean], default: null },
  // Shown when the value is empty. An em dash reads as "nothing here", which
  // is what a view screen should say rather than leaving a blank gap.
  placeholder: { type: String, default: '—' },
  valueClass: { type: String, default: '' },
});

const display = computed(() => {
  const value = props.value;
  if (value === null || value === undefined || value === '') return props.placeholder;
  return value;
});
</script>
