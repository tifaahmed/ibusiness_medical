<template>
  <div 
    class="bg-white rounded-2xl p-4 md:p-4 lg:p-8 hover:transform hover:-translate-y-2 transition-all shadow-lg hover:shadow-2xl cursor-pointer"
    @click="emit('click')"
  >
    <!-- Icon and Title Container: Side by side on mobile, stacked on desktop -->
    <div class="flex items-center gap-4 mb-4 md:flex-col md:items-center md:mb-6 md:gap-0">
      <div class="w-10 h-10 md:w-12 md:h-12 bg-gradient-to-r from-[#C6A76C] to-[#B89B6A] rounded-xl md:rounded-2xl flex items-center justify-center flex-shrink-0">
        <svg class="w-5 h-5 md:w-8 md:h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path v-if="icon === 'activity'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
          <path v-else-if="icon === 'heart-pulse'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
          <path v-else-if="icon === 'shield-check'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
          <path v-else-if="icon === 'users'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
        </svg>
      </div>
      <h3 class="text-sm md:text-xl font-bold text-[#1E3943] md:text-center lg:mb-3 md:mb-1">{{ title }}</h3>
    </div>
    <p class="text-sm md:text-base text-[#A0A0A0] mt-2 md:mt-0">{{ description }}</p>
  </div>
</template>

<script setup>
defineProps({
  icon: String,
  title: String,
  description: String,
});

const emit = defineEmits(['click']);
</script>

