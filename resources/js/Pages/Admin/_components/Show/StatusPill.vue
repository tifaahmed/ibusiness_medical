<template>
  <span
    class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset"
    :class="active
      ? 'bg-emerald-500/10 text-emerald-500 ring-emerald-500/20'
      : 'bg-red-500/10 text-red-500 ring-red-500/20'"
  >
    {{ active ? activeLabel : inactiveLabel }}
  </span>
</template>

<script setup>
defineProps({
  active: { type: Boolean, default: false },
  activeLabel: { type: String, default: 'Active' },
  inactiveLabel: { type: String, default: 'Inactive' },
});
</script>
