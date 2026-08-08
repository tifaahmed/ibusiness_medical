<template>
  <div class="animate-fade-in">
    <div class="text-3xl md:text-5xl font-bold text-[#B89B6A] mb-2">{{ value }}</div>
    <div class="text-sm md:text-base text-[#E6D3B1]">{{ label }}</div>
  </div>
</template>

<script setup>
defineProps({
  value: String,
  label: String,
});
</script>

<style scoped>
@keyframes fade-in {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}

.animate-fade-in {
  animation: fade-in 0.6s ease-in;
}
</style>

