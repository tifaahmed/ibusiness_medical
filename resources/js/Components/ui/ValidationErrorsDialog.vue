<template>
  <Teleport to="body">
    <div
      v-if="open && rows.length"
      class="fixed inset-0 z-[110] flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
      role="dialog"
      aria-modal="true"
      @click.self="close"
    >
      <div class="w-full max-w-lg overflow-hidden rounded-xl border border-border bg-card text-card-foreground shadow-xl">
        <div class="flex items-start gap-3 border-b border-border p-4">
          <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-destructive/15 text-destructive">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="m21.7 16.4-8-13.4a2 2 0 0 0-3.4 0l-8 13.4A2 2 0 0 0 4 19.4h16a2 2 0 0 0 1.7-3z"></path>
              <path d="M12 9v4"></path><path d="M12 17h.01"></path>
            </svg>
          </span>
          <div class="min-w-0">
            <h2 class="text-base font-semibold">{{ title }}</h2>
            <p class="text-xs text-muted-foreground">
              {{ rows.length }} problem(s) stopped the save. Fix them and submit again.
            </p>
          </div>
          <button
            type="button"
            class="ml-auto rounded-md p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
            title="Close (Esc)"
            @click="close"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 6 6 18"></path><path d="m6 6 12 12"></path>
            </svg>
          </button>
        </div>

        <ul class="max-h-[60vh] divide-y divide-border overflow-y-auto">
          <li v-for="row in rows" :key="`${row.key}-${row.message}`">
            <button
              type="button"
              class="flex w-full flex-col items-start gap-0.5 p-3 text-start transition hover:bg-muted/50"
              @click="pick(row.key)"
            >
              <span class="text-xs font-semibold text-muted-foreground">{{ labelFor(row.key) }}</span>
              <span class="text-sm text-destructive">{{ row.message }}</span>
            </button>
          </li>
        </ul>

        <div class="flex justify-end gap-2 border-t border-border p-3">
          <button
            type="button"
            class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground shadow-xs transition hover:bg-primary/90"
            @click="close"
          >
            Got it
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
/**
 * Lists every validation error behind one submit — the inline messages sit on
 * fields that may be on a different tab or scrolled out of view, so on their
 * own they are easy to miss.
 */
import { computed, onBeforeUnmount, onMounted } from 'vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  // Flat or nested map of field key -> message(s), as produced by the client
  // validator and by Laravel.
  errors: { type: Object, default: () => ({}) },
  title: { type: String, default: 'Please fix these fields' },
  // Optional key -> human label overrides, e.g. { 'name.ar': 'Name (Arabic)' }.
  labels: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:open', 'select']);

const LOCALE_LABELS = { ar: 'Arabic', en: 'English' };

const flatten = (value, path = []) => {
  if (value === null || value === undefined || value === false) return [];
  if (typeof value === 'string') return value ? [{ key: path.join('.'), message: value }] : [];
  if (Array.isArray(value)) return value.flatMap((item, index) => flatten(item, typeof item === 'string' ? path : [...path, index]));
  if (typeof value === 'object') return Object.entries(value).flatMap(([key, item]) => flatten(item, [...path, key]));
  return [{ key: path.join('.'), message: String(value) }];
};

const rows = computed(() => {
  const seen = new Set();

  return flatten(props.errors).filter((row) => {
    const id = `${row.key}|${row.message}`;
    if (seen.has(id)) return false;
    seen.add(id);
    return true;
  });
});

const humanize = (part) => part.replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase());

const labelFor = (key) => {
  if (props.labels[key]) return props.labels[key];

  const parts = key.split('.');
  const last = parts[parts.length - 1];

  if (LOCALE_LABELS[last]) {
    const base = parts.slice(0, -1).join('.');
    return `${props.labels[base] || humanize(base)} (${LOCALE_LABELS[last]})`;
  }

  return humanize(parts.join(' '));
};

const close = () => emit('update:open', false);

const pick = (key) => {
  emit('select', key);
  close();
};

const onKeydown = (event) => {
  if (event.key === 'Escape' && props.open) close();
};

onMounted(() => document.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));
</script>
