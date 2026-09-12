<template>
  <button
    v-if="hasErrors"
    type="button"
    title="View all errors"
    @click="open = true"
    class="absolute -top-1.5 -end-1.5 flex h-4 w-4 items-center justify-center rounded-full bg-destructive text-[10px] font-bold leading-none text-destructive-foreground ring-2 ring-card"
  >
    i
  </button>
  <ValidationErrorsDialog
    v-model:open="open"
    :errors="errors || {}"
    :debug-log="debugLog"
    :title="title"
  />
</template>

<script setup>
/**
 * The small red "i" badge that sits on the corner of a submit button across
 * the admin, plus the dialog it opens — bundled together so every form only
 * has to drop this one component next to its submit button instead of
 * re-wiring a ref and a computed for every single form.
 *
 * Place it inside a `position: relative` wrapper around the submit button
 * (see any Create/Edit view that already uses it) so the badge lands on the
 * button's corner rather than the page's.
 */
import { computed, ref } from 'vue';
import ValidationErrorsDialog from './ValidationErrorsDialog.vue';

const props = defineProps({
  // Flat or nested map of field key -> message(s).
  errors: { type: Object, default: () => ({}) },
  // { request: {...}, response: {...} } — see resources/js/utils/errorTrack.js.
  debugLog: { type: Object, default: null },
  title: { type: String, default: 'Please fix these fields' },
});

const open = ref(false);

const hasErrors = computed(() =>
  (!!props.errors && Object.keys(props.errors).length > 0) || !!props.debugLog?.response
);
</script>
