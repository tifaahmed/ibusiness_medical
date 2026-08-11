<template>
  <div class="flex gap-2 border-b border-border" role="tablist">
    <button
      v-for="tab in tabs"
      :key="tab.key"
      type="button"
      role="tab"
      :aria-selected="modelValue === tab.key"
      @click="$emit('update:modelValue', tab.key)"
      :class="[
        'px-4 py-2 text-sm font-medium rounded-t-md border-b-2 -mb-px transition-colors inline-flex items-center gap-2 cursor-pointer',
        modelValue === tab.key
          ? 'border-primary text-primary font-semibold'
          : 'border-transparent text-muted-foreground hover:text-foreground hover:bg-muted',
      ]"
    >
      {{ tab.label }}
      <!-- Marks a tab holding validation errors the admin can't see from here. -->
      <span
        v-if="tab.hasError"
        class="inline-block w-1.5 h-1.5 rounded-full bg-destructive"
        :title="errorTitle"
      ></span>
    </button>
  </div>
</template>

<script setup>
defineProps({
  modelValue: { type: String, required: true },
  /** [{ key: 'seo', label: 'SEO', hasError: false }] */
  tabs: { type: Array, required: true },
  errorTitle: { type: String, default: 'This tab has errors' },
});

defineEmits(['update:modelValue']);
</script>
