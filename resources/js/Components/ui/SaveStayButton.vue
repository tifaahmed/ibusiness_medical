<template>
  <button
    v-if="isEdit"
    type="submit"
    data-save-stay
    data-testid="save-and-stay"
    :disabled="saving"
    class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium border bg-background hover:bg-muted h-9 px-4 min-w-[120px] disabled:pointer-events-none disabled:opacity-50"
    @click="wantsStay = true"
  >
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
      <path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 3v6h-6"/>
    </svg>
    {{ saving ? 'Saving…' : 'Save & stay' }}
  </button>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { wantsStay, saving } from '@/composables/useSaveAndStay';

// Only an existing record has somewhere to stay: every edit page ends in /edit.
const page = usePage();
const isEdit = computed(() => String(page.url || '').split('?')[0].replace(/\/$/, '').endsWith('/edit'));
</script>
