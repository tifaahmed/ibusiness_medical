<template>
  <div v-if="pairs.length">
    <label class="text-xs font-medium text-muted-foreground">{{ label }}</label>
    <div class="mt-0.5 space-y-1">
      <p
        v-for="pair in pairs"
        :key="pair.lang"
        class="text-sm"
        :class="[textClass, { 'whitespace-pre-wrap': multiline }]"
        :dir="pair.lang === 'AR' ? 'rtl' : 'ltr'"
      >
        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold border border-border bg-muted/50 text-muted-foreground me-1 align-middle">{{ pair.lang }}</span>
        {{ pair.text }}
      </p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useTranslatedField } from '@/composables/useTranslatedField';

const props = defineProps({
  label: { type: String, required: true },
  value: { type: [String, Object], default: null },
  multiline: { type: Boolean, default: false },
  textClass: { type: String, default: 'font-medium' },
});

const { translationPairs } = useTranslatedField();
const pairs = computed(() => translationPairs(props.value));
</script>
