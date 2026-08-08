<template>
  <div class="bg-[#F9F6F1] rounded-2xl p-4 md:p-8 hover:transform hover:-translate-y-2 transition-all shadow-lg">
    <div class="flex items-center mb-3 md:mb-4">
      <div class="flex text-[#B89B6A]">
        <svg v-for="n in rating" :key="n" class="w-4 h-4 md:w-5 md:h-5 fill-current" fill="currentColor" viewBox="0 0 20 20">
          <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
        </svg>
      </div>
    </div>
    <p class="text-xs md:text-base text-[#A0A0A0] mb-4 md:mb-6 italic line-clamp-3 md:line-clamp-none">{{ text }}</p>
    <div class="flex items-center">
      <OptimizedImage
        :src="avatar"
        :alt="name"
        img-class="w-8 h-8 md:w-12 md:h-12 rounded-full mr-2 md:mr-4 flex-shrink-0"
        loading="lazy"
      />
      <div class="min-w-0">
        <div class="text-xs md:text-base font-semibold text-[#1E3943] truncate">{{ name }}</div>
        <div class="text-xs md:text-sm text-[#A0A0A0] truncate">{{ role }}</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import OptimizedImage from '@/Components/OptimizedImage.vue';

defineProps({
  name: String,
  role: String,
  text: String,
  avatar: String,
  rating: Number,
});
</script>

