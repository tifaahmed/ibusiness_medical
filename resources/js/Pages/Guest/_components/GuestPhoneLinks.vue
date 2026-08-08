<template>
  <div v-if="phones.length > 0" class="mt-2">
    <div class="flex flex-row flex-wrap items-center gap-1 md:gap-1.5">
      <!-- First phone + compact toggle kept on the same line -->
      <div class="inline-flex items-center gap-1 shrink-0 max-w-full">
        <a
          :href="`tel:${phones[0]}`"
          class="inline-flex items-center gap-1 md:gap-1.5 text-xs text-[#B89B6A] hover:text-[#8B7355] transition-colors bg-[#B89B6A]/5 hover:bg-[#B89B6A]/10 rounded-lg px-2 md:px-2.5 py-1 md:py-1.5 border border-[#B89B6A]/15 hover:border-[#B89B6A]/30 min-w-0"
        >
          <svg class="w-3 h-3 md:w-3.5 md:h-3.5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
          </svg>
          <span class="font-medium truncate">{{ phones[0] }}</span>
        </a>

        <button
          v-if="hasMore && !expanded"
          type="button"
          @click="expanded = true"
          :aria-label="moreAriaLabel"
          class="inline-flex shrink-0 items-center justify-center w-5 h-5 md:w-auto md:h-auto md:gap-1 text-[#B89B6A] hover:text-[#8B7355] transition-colors bg-[#B89B6A]/5 hover:bg-[#B89B6A]/10 rounded-md md:rounded-lg md:px-2 md:py-1.5 border border-[#B89B6A]/15 hover:border-[#B89B6A]/30"
        >
          <span class="text-[9px] md:hidden font-bold leading-none">+{{ extraCount }}</span>
          <svg xmlns="http://www.w3.org/2000/svg" class="hidden md:block w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="m6 9 6 6 6-6"/>
          </svg>
          <span class="hidden md:inline text-xs font-semibold">{{ moreLabel }}</span>
        </button>

        <button
          v-if="hasMore && expanded"
          type="button"
          @click="expanded = false"
          :aria-label="lessLabel"
          class="inline-flex shrink-0 items-center justify-center w-5 h-5 md:w-auto md:h-auto md:gap-1 text-[#B89B6A] hover:text-[#8B7355] transition-colors bg-[#B89B6A]/5 hover:bg-[#B89B6A]/10 rounded-md md:rounded-lg md:px-2 md:py-1.5 border border-[#B89B6A]/15 hover:border-[#B89B6A]/30"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="w-2.5 h-2.5 md:w-3 md:h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="m18 15-6-6-6 6"/>
          </svg>
          <span class="hidden md:inline text-xs font-semibold">{{ lessLabel }}</span>
        </button>
      </div>

      <!-- Extra phones when expanded -->
      <a
        v-for="(phone, index) in extraPhones"
        v-show="expanded"
        :key="`${phone}-${index}`"
        :href="`tel:${phone}`"
        class="inline-flex items-center gap-1 md:gap-1.5 text-xs text-[#B89B6A] hover:text-[#8B7355] transition-colors bg-[#B89B6A]/5 hover:bg-[#B89B6A]/10 rounded-lg px-2 md:px-2.5 py-1 md:py-1.5 border border-[#B89B6A]/15 hover:border-[#B89B6A]/30 w-fit"
      >
        <svg class="w-3 h-3 md:w-3.5 md:h-3.5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
        </svg>
        <span class="font-medium">{{ phone }}</span>
      </a>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  phone: { type: [String, Array, null], default: null },
  moreLabelTemplate: { type: String, default: '+:count more' },
  lessLabel: { type: String, default: 'Show less' },
});

const expanded = ref(false);

const phones = computed(() => {
  const phone = props.phone;
  if (!phone) return [];
  if (typeof phone === 'string' && phone.trim() !== '') {
    return [phone.trim()];
  }
  if (Array.isArray(phone)) {
    return phone.filter(p => p && String(p).trim().length > 0).map(p => String(p).trim());
  }
  return [];
});

const hasMore = computed(() => phones.value.length > 1);
const extraCount = computed(() => phones.value.length - 1);
const extraPhones = computed(() => phones.value.slice(1));

const moreLabel = computed(() => {
  const template = props.moreLabelTemplate.includes('more_phones')
    ? '+:count more'
    : props.moreLabelTemplate;
  return template.replace(':count', String(extraCount.value));
});

const moreAriaLabel = computed(() => moreLabel.value);
</script>
