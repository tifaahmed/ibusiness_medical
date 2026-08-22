<template>
  <div class="flex flex-col sm:flex-row gap-2 justify-end pt-2">
    <Link
      :href="listHref"
      class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 px-3 py-1.5"
    >
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
        <path d="M15 18l-6-6 6-6"></path>
      </svg>
      {{ backLabel }}
    </Link>
    <slot />
    <!-- A read-only account has no edit route to reach, so the button is not
         rendered at all rather than shown disabled. -->
    <Link
      v-if="editHref"
      :href="editHref"
      class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-8 px-3 py-1.5"
    >
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
      </svg>
      {{ editLabel }}
    </Link>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
  listHref: { type: String, required: true },
  // Null hides the button — pass null when the viewer cannot edit.
  editHref: { type: String, default: null },
  backLabel: { type: String, default: 'Back to List' },
  editLabel: { type: String, default: 'Edit' },
});
</script>
