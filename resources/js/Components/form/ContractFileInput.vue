<template>
  <div class="w-full">
    <!-- Existing contract (uploaded before) -->
    <div
      v-if="displayType === 'existing'"
      class="flex items-center gap-3 rounded-lg border border-border bg-card p-3"
    >
      <div class="shrink-0 w-10 h-10 rounded-md bg-muted/40 flex items-center justify-center text-golden-yellow">
        <!-- PDF icon -->
        <svg v-if="isPdf(existing.mime_type)" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path>
          <polyline points="14 2 14 8 20 8"></polyline>
        </svg>
        <!-- Image icon -->
        <svg v-else xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/>
          <circle cx="9" cy="9" r="2"/>
          <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
        </svg>
      </div>
      <div class="min-w-0 flex-1">
        <p class="text-sm font-medium text-white truncate">{{ existing.file_name }}</p>
        <p class="text-xs text-muted-foreground">{{ formatSize(existing.size) }}</p>
      </div>
      <button
        type="button"
        class="inline-flex items-center gap-1.5 rounded-md border border-border px-3 py-1.5 text-xs font-medium text-white hover:bg-accent transition-colors cursor-pointer"
        @click="openPreview"
      >
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
          <circle cx="12" cy="12" r="3"></circle>
        </svg>
        {{ t.facility?.preview || 'Preview' }}
      </button>
      <button
        type="button"
        class="inline-flex items-center rounded-md p-1.5 text-destructive hover:bg-destructive/10 transition-colors cursor-pointer"
        :title="t.facility?.remove || 'Remove'"
        @click="removeExisting"
      >
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M18 6 6 18"></path><path d="m6 6 12 12"></path>
        </svg>
      </button>
    </div>

    <!-- Newly selected file -->
    <div
      v-else-if="displayType === 'new'"
      class="flex items-center gap-3 rounded-lg border border-ring bg-card p-3"
    >
      <div class="shrink-0 w-10 h-10 rounded-md bg-muted/40 flex items-center justify-center text-golden-yellow">
        <svg v-if="isNewPdf" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path>
          <polyline points="14 2 14 8 20 8"></polyline>
        </svg>
        <svg v-else-if="isNewImage" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/>
          <circle cx="9" cy="9" r="2"/>
          <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
        </svg>
        <svg v-else xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path>
          <polyline points="14 2 14 8 20 8"></polyline>
        </svg>
      </div>
      <div class="min-w-0 flex-1">
        <p class="text-sm font-medium text-white truncate">{{ newFile.name }}</p>
        <p class="text-xs text-muted-foreground">{{ formatSize(newFile.size) }}</p>
      </div>
      <button
        type="button"
        class="inline-flex items-center gap-1.5 rounded-md border border-border px-3 py-1.5 text-xs font-medium text-white hover:bg-accent transition-colors cursor-pointer"
        @click="openPreview"
      >
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
          <circle cx="12" cy="12" r="3"></circle>
        </svg>
        {{ t.facility?.preview || 'Preview' }}
      </button>
      <button
        type="button"
        class="inline-flex items-center rounded-md p-1.5 text-destructive hover:bg-destructive/10 transition-colors cursor-pointer"
        :title="t.facility?.remove || 'Remove'"
        @click="removeNew"
      >
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M18 6 6 18"></path><path d="m6 6 12 12"></path>
        </svg>
      </button>
    </div>

    <!-- Upload dropzone -->
    <div
      v-else
      class="flex flex-col items-center justify-center w-full h-40 border-2 border-dashed rounded-lg cursor-pointer bg-card hover:bg-accent/50 transition-colors duration-200"
      :class="{ 'border-red-500 dark:border-red-400': error, 'drag-over': dragOver }"
      @dragover.prevent="dragOver = true"
      @dragleave="dragOver = false"
      @drop.prevent="handleDrop"
      @click="$refs.fileInput.click()"
    >
      <i class="uil uil-cloud-upload text-3xl mb-2 text-muted-foreground"></i>
      <p class="mb-1 text-sm text-muted-foreground">
        <span class="font-semibold text-foreground">{{ t.image?.upload_click || 'Click to upload' }}</span> {{ t.image?.drag_drop || 'or drag and drop' }}
      </p>
      <p class="text-xs text-muted-foreground">
        {{ t.facility?.contract_formats || 'PDF, JPEG, JPG, PNG, GIF, WEBP' }} ({{ t.common?.max || 'MAX' }}. {{ maxSize }}MB)
      </p>
      <p v-if="error" class="mt-2 text-xs text-destructive font-medium">{{ error }}</p>
    </div>

    <input
      type="file"
      class="hidden"
      :accept="acceptedTypes.join(',')"
      @change="handleFileSelect"
      ref="fileInput"
    />

    <!-- Preview Popup -->
    <Teleport to="body">
      <div
        v-if="previewOpen"
        class="fixed inset-0 z-[100] flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
      >
        <div class="absolute inset-0 bg-black/85 backdrop-blur-sm" @click="closePreview"></div>
        <div class="relative bg-card border border-border rounded-xl shadow-xl w-full max-w-4xl h-[90vh] flex flex-col overflow-hidden">
          <div class="flex items-center justify-between px-4 py-3 border-b border-border shrink-0">
            <p class="text-sm font-semibold text-white truncate">
              {{ displayType === 'new' ? newFile?.name : existing?.file_name }}
            </p>
            <div class="flex items-center gap-2 shrink-0">
              <a
                :href="previewUrl"
                target="_blank"
                rel="noopener"
                class="inline-flex items-center gap-1.5 rounded-md border border-border px-3 py-1.5 text-xs font-medium text-white hover:bg-accent transition-colors"
              >
                {{ t.facility?.open_in_new_tab || 'Open in new tab' }}
              </a>
              <button
                type="button"
                class="inline-flex items-center justify-center rounded-md p-1.5 text-white hover:bg-accent transition-colors cursor-pointer"
                :title="t.facility?.close || 'Close'"
                @click="closePreview"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M18 6 6 18"></path><path d="m6 6 12 12"></path>
                </svg>
              </button>
            </div>
          </div>
          <div class="flex-1 overflow-auto bg-black/40">
            <img
              v-if="previewKind === 'image'"
              :src="previewUrl"
              :alt="displayName"
              class="w-full h-full object-contain"
            />
            <iframe
              v-else-if="previewKind === 'pdf'"
              :src="previewUrl"
              class="w-full h-full border-0"
              :title="displayName"
            ></iframe>
            <div v-else class="w-full h-full flex flex-col items-center justify-center gap-3 text-muted-foreground">
              <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
              </svg>
              <p class="text-sm">{{ t.facility?.preview_not_available || 'Preview is not available for this file type.' }}</p>
              <a
                :href="previewUrl"
                target="_blank"
                rel="noopener"
                class="rounded-md border border-border px-3 py-1.5 text-xs font-medium text-white hover:bg-accent transition-colors"
              >
                {{ t.facility?.download || 'Download' }}
              </a>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onBeforeUnmount } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const props = defineProps({
  maxSize: {
    type: Number,
    default: 10,
  },
  acceptedTypes: {
    type: Array,
    default: () => [
      'image/jpeg',
      'image/jpg',
      'image/png',
      'image/gif',
      'image/webp',
      'image/avif',
      'application/pdf',
    ],
  },
  // Previously uploaded contract: { url, file_name, mime_type, size }
  initialContract: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(['file-selected', 'existing-removed', 'error']);

const fileInput = ref(null);
const newFile = ref(null);
const newObjectUrl = ref('');
const existingRemoved = ref(false);
const dragOver = ref(false);
const error = ref('');
const previewOpen = ref(false);

const existing = computed(() => (existingRemoved.value ? null : props.initialContract));

const isNewImage = computed(() => Boolean(newFile.value?.type?.startsWith('image/')));
const isNewPdf = computed(() => newFile.value?.type === 'application/pdf');

const displayType = computed(() => {
  if (newFile.value) return 'new';
  if (existing.value) return 'existing';
  return 'none';
});

const displayName = computed(() =>
  displayType.value === 'new' ? newFile.value?.name : existing.value?.file_name || ''
);

const previewUrl = computed(() => {
  if (displayType.value === 'new') return newObjectUrl.value;
  return existing.value?.url || '';
});

const previewKind = computed(() => {
  if (displayType.value === 'new') {
    if (isNewImage.value) return 'image';
    if (isNewPdf.value) return 'pdf';
    return 'other';
  }
  const mime = existing.value?.mime_type || '';
  if (mime.startsWith('image/')) return 'image';
  if (mime === 'application/pdf') return 'pdf';
  return 'other';
});

const validateFile = (file) => {
  if (!props.acceptedTypes.includes(file.type)) {
    error.value =
      t.value.facility?.contract_invalid ||
      'Invalid file. Allowed: JPEG, JPG, PNG, GIF, WEBP, AVIF, PDF.';
    return false;
  }
  const maxBytes = props.maxSize * 1024 * 1024;
  if (file.size > maxBytes) {
    error.value =
      t.value.facility?.contract_size ||
      `File size should not exceed ${props.maxSize}MB`;
    return false;
  }
  return true;
};

const handleFileSelect = (event) => {
  const file = event.target.files[0];
  if (!file) return;
  if (!validateFile(file)) {
    event.target.value = '';
    return;
  }
  setNewFile(file);
  event.target.value = '';
};

const handleDrop = (event) => {
  dragOver.value = false;
  const file = event.dataTransfer.files[0];
  if (!file) return;
  if (!validateFile(file)) return;
  setNewFile(file);
};

const setNewFile = (file) => {
  clearNewObjectUrl();
  error.value = '';
  newFile.value = file;
  newObjectUrl.value = URL.createObjectURL(file);
  emit('file-selected', file);
};

// Removing a freshly picked file falls back to the stored contract (if any).
const removeNew = () => {
  clearNewObjectUrl();
  newFile.value = null;
  emit('file-selected', null);
};

const removeExisting = () => {
  existingRemoved.value = true;
  emit('existing-removed');
};

const openPreview = () => {
  previewOpen.value = true;
};

const closePreview = () => {
  previewOpen.value = false;
};

const onKeydown = (e) => {
  if (e.key === 'Escape') closePreview();
};
window.addEventListener('keydown', onKeydown);
onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown);
  clearNewObjectUrl();
});

const clearNewObjectUrl = () => {
  if (newObjectUrl.value) {
    URL.revokeObjectURL(newObjectUrl.value);
    newObjectUrl.value = '';
  }
};

const formatSize = (bytes) => {
  const size = Number(bytes);
  if (!size || Number.isNaN(size)) return '';
  if (size >= 1024 * 1024) return (size / (1024 * 1024)).toFixed(2) + ' MB';
  if (size >= 1024) return (size / 1024).toFixed(1) + ' KB';
  return size + ' B';
};
</script>

<style scoped>
.drag-over {
  @apply border-blue-500 bg-blue-50/10 transition-all duration-200;
}
</style>
