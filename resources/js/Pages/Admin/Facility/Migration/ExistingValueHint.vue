<template>
  <p
    v-if="text"
    class="mt-0.5 flex gap-1 rounded-sm border-l-2 border-amber-600 bg-amber-100 px-1 py-0.5 text-[10px] leading-tight text-amber-900 dark:border-amber-400 dark:bg-amber-900/50 dark:text-amber-100"
    :title="`This site holds ${text} — importing replaces it`"
  >
    <span class="shrink-0 font-semibold">now:</span>
    <span class="break-words">{{ text }}</span>
  </p>
</template>

<script setup>
import { computed } from 'vue';
import { oldValue } from './existingValue.js';

const props = defineProps({
  /* The snapshot of the row as it stands on this site, or null when the import
     would create it. */
  existing: { type: Object, default: null },
  path: { type: String, required: true },
  current: { type: [String, Number, Array, Object, null], default: null },
  /* Set for a select: compares the row pointed at rather than the spelling. */
  choice: { type: Boolean, default: false },
  /* Set for a coordinate: 30.0444200 and 30.04442 are the same place. */
  numeric: { type: Boolean, default: false },
});

const text = computed(() =>
  oldValue(props.existing, props.path, props.current, props.choice, props.numeric)
);
</script>
