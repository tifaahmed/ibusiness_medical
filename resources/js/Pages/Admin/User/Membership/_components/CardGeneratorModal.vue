<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="$emit('close')"></div>

    <!-- Modal -->
    <div class="relative z-10 w-full max-w-[1220px] mx-4 flex flex-col" style="height: 90vh;">
      <!-- Header -->
      <div class="flex items-center justify-between px-4 py-3 rounded-t-xl"
           style="background:#0f3460;border:1px solid rgba(184,148,90,0.3);border-bottom:none;">
        <span style="color:#D4AF6E;font-family:serif;font-size:15px;letter-spacing:.1em;text-transform:uppercase;font-weight:600;">
          ASH Health Care — Card Generator
        </span>
        <button @click="$emit('close')"
                class="text-gray-400 hover:text-white transition-colors p-1 rounded">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
               fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
          </svg>
        </button>
      </div>

      <!-- iframe -->
      <iframe
        src="/card-generator.html"
        class="flex-1 w-full rounded-b-xl"
        style="border:1px solid rgba(184,148,90,0.3);border-top:none;"
        frameborder="0"
        allow="downloads"
      ></iframe>
    </div>
  </div>
</template>

<script setup>
defineProps({ show: { type: Boolean, default: false } });
defineEmits(['close']);
</script>
