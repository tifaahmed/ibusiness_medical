<template>
  <div class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
    <div v-if="title" class="p-3 border-b border-border flex items-center justify-between gap-2">
      <h2 class="text-lg font-semibold">{{ title }}</h2>
      <slot name="header-actions" />
    </div>
    <div class="p-3">
      <slot />
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: { type: String, default: '' },
});
</script>
