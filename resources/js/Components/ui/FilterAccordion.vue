<template>
  <section class="w-full min-w-0 rounded-lg border border-border/60 bg-background/30">
    <button
      type="button"
      class="flex w-full items-center justify-between gap-2 px-3 py-2 text-xs sm:text-sm font-medium text-foreground cursor-pointer select-none"
      :aria-expanded="open"
      @click="toggle"
    >
      <span class="inline-flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-muted-foreground">
          <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
        </svg>
        {{ title }}
        <span v-if="activeCount > 0" class="rounded-full bg-primary/20 text-primary px-1.5 py-0.5 text-[10px] font-semibold tabular-nums">
          {{ activeCount }}
        </span>
      </span>
      <svg
        xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
        :class="['text-muted-foreground transition-transform', open ? 'rotate-180' : '']"
      >
        <path d="m6 9 6 6 6-6"></path>
      </svg>
    </button>

    <!-- v-show, not v-if: the filter inputs stay mounted so their state and
         any debounced search survive the panel being folded away. -->
    <div v-show="open" class="px-3 pb-3 pt-1">
      <slot />
    </div>
  </section>
</template>

<script setup>
import { ref } from "vue";

const props = defineProps({
  title: { type: String, default: "Filters" },
  // Distinguishes one page's remembered state from another's. Defaults to the
  // page path with ids folded away, so /admin/x/12 and /admin/x/13 share it.
  storageKey: { type: String, default: "" },
  // Optional "N active" badge on the header.
  activeCount: { type: Number, default: 0 },
  defaultOpen: { type: Boolean, default: true },
});

const key = () => {
  const suffix = props.storageKey
    || (typeof window !== "undefined" ? window.location.pathname.replace(/\/\d+(?=\/|$)/g, "/*") : "page");
  return `filters-accordion:${suffix}`;
};

// Storage can throw (private window, blocked site data): the panel then simply
// starts from its default and still works.
const read = () => {
  try {
    const v = window.localStorage.getItem(key());
    return v === null ? props.defaultOpen : v === "1";
  } catch {
    return props.defaultOpen;
  }
};

const open = ref(read());

const toggle = () => {
  open.value = !open.value;
  try {
    window.localStorage.setItem(key(), open.value ? "1" : "0");
  } catch {
    /* remembering is a convenience, never a requirement */
  }
};
</script>
