<template>
  <div 
    class="bg-white rounded-3xl p-8 shadow-lg hover:shadow-2xl transition-all hover:transform hover:-translate-y-2 relative"
    :class="{ 'border-2 border-[#B89B6A] scale-105': popular }"
  >
    <div v-if="popular" class="absolute -top-4 left-1/2 transform -translate-x-1/2 bg-[#B89B6A] text-white px-4 md:px-6 py-1.5 md:py-2 rounded-full text-xs md:text-sm font-semibold">
      Most Popular
    </div>
    <div class="text-center mb-8">
      <h3 class="text-xl md:text-2xl font-bold text-[#1E3943] mb-2">{{ name }}</h3>
      <p class="text-sm md:text-base text-[#A0A0A0] mb-6">{{ description }}</p>
      <div class="text-3xl md:text-5xl font-bold text-[#1E3943] mb-2">
        ${{ price }}<span class="text-base md:text-xl text-[#A0A0A0]">/mo</span>
      </div>
    </div>
    <ul class="space-y-4 mb-8">
      <li v-for="feature in features" :key="feature" class="flex items-center">
        <svg class="w-5 h-5 text-[#B89B6A] mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        <span class="text-sm md:text-base text-[#A0A0A0]">{{ feature }}</span>
      </li>
    </ul>
    <button 
      class="w-full py-3 rounded-lg transition-colors"
      :class="popular ? 'bg-gradient-to-r from-[#C6A76C] to-[#B89B6A] text-white hover:shadow-lg' : 'bg-[#1E3943] text-white hover:bg-[#13292F]'"
    >
      Get Started
    </button>
  </div>
</template>

<script setup>
defineProps({
  name: String,
  price: Number,
  description: String,
  features: Array,
  popular: Boolean,
});
</script>

