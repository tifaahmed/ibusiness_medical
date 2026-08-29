<!--
  The order's PDF, shown before it is written to disk.

  It renders the very file the download writes — `previewOrderPdf` hands over
  a blob URL for the same jsPDF document `exportOrderPdf` saves — so what an
  admin checks here (an Arabic name that shaped wrong, a page cut through a
  product row, a margin column on a copy meant for the customer) is what would
  have gone out. The browser's own PDF viewer does the paging and zooming.
-->
<template>
  <Teleport to="body">
    <div
      v-if="url"
      class="fixed inset-0 z-[100] flex flex-col bg-black/90 backdrop-blur-sm"
      role="dialog"
      aria-modal="true"
      @click.self="$emit('close')"
    >
      <div class="flex items-center gap-2 p-3 text-white" @click.stop>
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 opacity-80">
          <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path>
          <path d="M14 2v5h5"></path>
        </svg>
        <span class="truncate text-sm font-medium">{{ title }}</span>
        <span class="hidden truncate font-mono text-xs opacity-60 sm:inline">{{ filename }}</span>

        <div class="ms-auto flex items-center gap-1">
          <!-- A blob URL is same-origin with this page, so the browser saves
               it under `download` rather than navigating to it. -->
          <a :href="url" :download="filename" :class="toolBtn" :title="t.order?.export_download || 'Download'">
            <svg v-bind="icon"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><path d="M7 10l5 5 5-5" /><path d="M12 15V3" /></svg>
            <span class="hidden text-xs sm:inline">{{ t.order?.export_download || 'Download' }}</span>
          </a>
          <a :href="url" target="_blank" rel="noopener" :class="toolBtn" :title="t.common?.open_in_new_tab || 'Open in a new tab'">
            <svg v-bind="icon"><path d="M15 3h6v6" /><path d="M10 14 21 3" /><path d="M21 14v7H3V3h7" /></svg>
          </a>
          <button type="button" :class="toolBtn" :title="t.common?.close || 'Close (Esc)'" @click="$emit('close')">
            <svg v-bind="icon"><path d="M18 6 6 18" /><path d="m6 6 12 12" /></svg>
          </button>
        </div>
      </div>

      <!-- `dir=ltr`: the viewer's own chrome is laid out by the browser, and an
           RTL admin page would otherwise flip its scrollbar onto the document. -->
      <div class="flex-1 overflow-hidden p-3 pt-0" dir="ltr" @click.stop>
        <iframe
          :src="url"
          :title="title"
          class="h-full w-full rounded-lg border-0 bg-white shadow-2xl"
        ></iframe>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { onBeforeUnmount, watch } from "vue";
import { usePage } from "@inertiajs/vue3";
import { computed } from "vue";

const props = defineProps({
  /** Blob URL of the rendered document; null closes the dialog. */
  url: { type: String, default: null },
  filename: { type: String, default: '' },
  title: { type: String, default: '' },
});

const emit = defineEmits(['close']);

const t = computed(() => usePage().props.translations?.admin || {});

const toolBtn = 'inline-flex items-center gap-1.5 rounded-md px-2 py-1.5 text-white/90 transition hover:bg-white/15 hover:text-white cursor-pointer';
const icon = {
  xmlns: 'http://www.w3.org/2000/svg',
  width: 16,
  height: 16,
  viewBox: '0 0 24 24',
  fill: 'none',
  stroke: 'currentColor',
  'stroke-width': 2,
  'stroke-linecap': 'round',
  'stroke-linejoin': 'round',
};

const onKeydown = (event) => {
  if (event.key === 'Escape') emit('close');
};

/* Listen only while a document is open, so Esc anywhere else on the page is
   still the page's own. */
watch(() => props.url, (url) => {
  if (url) window.addEventListener('keydown', onKeydown);
  else window.removeEventListener('keydown', onKeydown);
}, { immediate: true });

onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>
