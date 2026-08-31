<template>
  <div class="w-full">
    <!-- Gallery preview (multiple mode) -->
    <template v-if="multiple">
      <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 mb-3">
        <div
          v-for="(file, idx) in selectedFiles"
          :key="idx"
          class="relative group rounded-lg overflow-hidden border border-border aspect-square"
        >
          <img :src="file.preview" class="w-full h-full object-cover" loading="lazy" />
          <button
            type="button"
            @click="removeFileAtIndex(idx)"
            class="absolute top-1 right-1 p-1 bg-black/60 rounded-full opacity-0 group-hover:opacity-100 transition-opacity hover:bg-red-500/80"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 6 6 18"></path><path d="m6 6 12 12"></path>
            </svg>
          </button>
        </div>
      </div>
      <div
        class="flex flex-col items-center justify-center w-full h-40 border-2 border-gray-300 dark:border-gray-600 border-dashed rounded-lg cursor-pointer bg-card hover:bg-accent/50 transition-colors duration-200"
        @dragover.prevent="dragOver = true"
        @dragleave="dragOver = false"
        @drop.prevent="handleDrop"
        @click="$refs.fileInput.click()"
        :class="{ 'drag-over': dragOver, 'border-red-500 dark:border-red-400': error }"
      >
        <div class="flex flex-col items-center justify-center pt-4 pb-4">
          <i class="uil uil-cloud-upload text-3xl mb-2 text-muted-foreground transition-colors"></i>
          <p class="mb-1 text-sm text-muted-foreground">
            <span class="font-semibold text-foreground">{{ t.image?.upload_click || 'Click to upload' }}</span> {{ t.image?.drag_drop || 'or drag and drop' }}
          </p>
          <p class="text-xs text-muted-foreground">
            {{ t.image?.formats || 'PNG, JPG, JPEG, AVIF' }} ({{ t.common?.max || 'MAX' }}. {{ maxSize }}MB)
          </p>
          <p v-if="error" class="mt-2 text-xs text-destructive font-medium">{{ error }}</p>
        </div>
      </div>
    </template>

    <!-- Single image mode -->
    <template v-else>
      <div
        v-if="!previewImage"
        class="flex flex-col items-center justify-center w-full h-64 border-2 border-gray-300 dark:border-gray-600 border-dashed rounded-lg cursor-pointer bg-card hover:bg-accent/50 transition-colors duration-200"
        @dragover.prevent="dragOver = true"
        @dragleave="dragOver = false"
        @drop.prevent="handleDrop"
        @click="$refs.fileInput.click()"
        :class="{ 'drag-over': dragOver, 'border-red-500 dark:border-red-400': error }"
      >
        <div class="flex flex-col items-center justify-center pt-5 pb-6">
          <i class="uil uil-cloud-upload text-4xl mb-4 text-muted-foreground transition-colors"></i>
          <p class="mb-2 text-sm text-muted-foreground">
            <span class="font-semibold text-foreground">{{ t.image?.upload_click || 'Click to upload' }}</span> {{ t.image?.drag_drop || 'or drag and drop' }}
          </p>
          <p class="text-xs text-muted-foreground">
            {{ t.image?.formats || 'PNG, JPG, JPEG, AVIF' }} ({{ t.common?.max || 'MAX' }}. {{ maxSize }}MB)
          </p>
          <p v-if="error" class="mt-2 text-xs text-destructive font-medium">{{ error }}</p>
        </div>
      </div>

      <div v-else class="relative w-full group">
        <img
          :src="previewImage"
          :alt="t.common?.preview || 'Preview'"
          class="w-full object-cover rounded-lg shadow-sm transition-opacity duration-200"
          :class="cropAspectRatio ? '' : 'h-64'"
          :style="cropAspectRatio ? { aspectRatio: String(cropAspectRatio) } : {}"
        />
        <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity duration-200 rounded-lg flex items-center justify-center gap-2">
          <button
            v-if="cropEnabled && cropSource"
            type="button"
            class="p-3 bg-white/90 backdrop-blur-sm rounded-full shadow-md hover:bg-blue-50 transition-all duration-200"
            @click="reopenCropper"
            :title="t.image?.crop_edit || 'Adjust crop'"
          >
            <i class="uil uil-crop-alt text-xl text-blue-600"></i>
          </button>
          <button
            type="button"
            class="p-3 bg-white/90 backdrop-blur-sm rounded-full shadow-md hover:bg-red-50 transition-all duration-200"
            @click="removeImage"
            :title="t.common?.remove || 'Remove image'"
          >
            <i class="uil uil-times text-xl text-red-600"></i>
          </button>
        </div>
      </div>
    </template>

    <input
      type="file"
      class="hidden"
      accept="image/*"
      :multiple="multiple"
      @change="handleFileSelect"
      ref="fileInput"
    />

    <ImageCropDialog
      v-if="cropEnabled"
      :open="cropOpen"
      :src="cropSource"
      :aspect-ratio="cropAspectRatio"
      :output-width="cropOutputWidth"
      :mime-type="cropMime"
      :file-name="cropFileName"
      @confirm="handleCropConfirm"
      @cancel="handleCropCancel"
    />
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import ImageCropDialog from '@/Components/ui/ImageCropDialog.vue';

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const props = defineProps({
  maxSize: {
    type: Number,
    default: 2
  },
  acceptedTypes: {
    type: Array,
    default: () => ['image/jpeg', 'image/png', 'image/jpg', 'image/avif']
  },
  initialPreview: {
    type: String,
    default: null
  },
  multiple: {
    type: Boolean,
    default: false
  },
  // Single-image mode: open a crop/preview dialog before accepting the upload
  // so the admin sees and controls how the image will be cut on the site.
  crop: {
    type: Boolean,
    default: true
  },
  // Lock the crop to this ratio (width / height, e.g. 3 for a 3:1 cover).
  // Null lets the admin draw any shape.
  cropAspectRatio: {
    type: Number,
    default: null
  },
  // Longest edge of the cropped export, in pixels (never upscales).
  cropOutputWidth: {
    type: Number,
    default: 2000
  }
});

const emit = defineEmits(['file-selected', 'files-selected', 'error']);

// The crop/preview dialog only makes sense for a single image.
const cropEnabled = computed(() => props.crop && !props.multiple);
const fileInput = ref(null);
const previewImage = ref(null);
const selectedFiles = ref([]);
const dragOver = ref(false);
const error = ref('');
const isUploading = ref(false);
const uploadProgress = ref(0);

// Crop dialog state (only used when cropAspectRatio is set).
const cropOpen = ref(false);
const cropSource = ref('');
const cropFileName = ref('image');
const cropMime = ref('image/jpeg');

watch(error, (newError) => {
  emit('error', newError);
});

const validateFile = (file) => {
  if (!props.acceptedTypes.includes(file.type)) {
    error.value = t.value.image?.invalid_format || 'Please upload a valid image file (PNG, JPG, JPEG, AVIF)';
    return false;
  }

  const maxBytes = props.maxSize * 1024 * 1024;
  if (file.size > maxBytes) {
    error.value = t.value.image?.size_limit || `Image size should not exceed ${props.maxSize}MB`;
    return false;
  }

  return true;
};

const processFileForPreview = (file) => {
  return new Promise((resolve) => {
    const reader = new FileReader();
    reader.onload = (e) => resolve({ file, preview: e.target.result });
    reader.onerror = () => resolve({ file, preview: null });
    reader.readAsDataURL(file);
  });
};

const handleFileSelect = async (event) => {
  const files = Array.from(event.target.files);
  if (!files.length) return;

  if (props.multiple) {
    const newFiles = [];
    error.value = '';

    for (const file of files) {
      if (!validateFile(file)) return;
      newFiles.push(file);
    }

    const previews = await Promise.all(newFiles.map(processFileForPreview));
    selectedFiles.value.push(...previews);

    emit('files-selected', selectedFiles.value.map(p => p.file));
    emit('file-selected', selectedFiles.value.map(p => p.file));

    if (fileInput.value) {
      fileInput.value.value = '';
    }
  } else {
    const file = files[0];
    if (file && validateFile(file)) {
      processFile(file);
    }
  }
};

const handleDrop = async (event) => {
  dragOver.value = false;
  const files = Array.from(event.dataTransfer.files);
  if (!files.length) return;

  if (props.multiple) {
    error.value = '';
    const newFiles = [];

    for (const file of files) {
      if (!validateFile(file)) return;
      newFiles.push(file);
    }

    const previews = await Promise.all(newFiles.map(processFileForPreview));
    selectedFiles.value.push(...previews);

    emit('files-selected', selectedFiles.value.map(p => p.file));
    emit('file-selected', selectedFiles.value.map(p => p.file));
  } else {
    const file = files[0];
    if (file && validateFile(file)) {
      processFile(file);
    }
  }
};

const removeFileAtIndex = (idx) => {
  selectedFiles.value.splice(idx, 1);
  emit('files-selected', selectedFiles.value.map(p => p.file));
  emit('file-selected', selectedFiles.value.map(p => p.file));
};

const openCropper = (file) => {
  cropFileName.value = file.name || 'image';
  cropMime.value = file.type || 'image/jpeg';
  const reader = new FileReader();
  reader.onload = (e) => {
    cropSource.value = e.target.result;
    cropOpen.value = true;
  };
  reader.onerror = () => {
    error.value = t.value.image?.read_error || 'Error reading file';
    emit('error', error.value);
  };
  reader.readAsDataURL(file);
};

const reopenCropper = () => {
  if (cropSource.value) cropOpen.value = true;
};

const handleCropConfirm = (file) => {
  cropOpen.value = false;
  error.value = '';
  const reader = new FileReader();
  reader.onload = (e) => { previewImage.value = e.target.result; };
  reader.readAsDataURL(file);
  emit('file-selected', file);
  if (fileInput.value) fileInput.value.value = '';
};

const handleCropCancel = () => {
  cropOpen.value = false;
  // Keep any image that was already accepted; just clear the native input so
  // re-picking the same file fires a change event again.
  if (fileInput.value) fileInput.value.value = '';
};

const processFile = (file) => {
  if (cropEnabled.value) {
    openCropper(file);
    return;
  }

  isUploading.value = true;
  uploadProgress.value = 0;

  const reader = new FileReader();

  reader.onprogress = (event) => {
    if (event.lengthComputable) {
      uploadProgress.value = Math.round((event.loaded / event.total) * 100);
    }
  };

  reader.onload = (e) => {
    uploadProgress.value = 100;
    setTimeout(() => {
      previewImage.value = e.target.result;
      emit('file-selected', file);
      isUploading.value = false;
    }, 500);
  };

  reader.onerror = () => {
    error.value = t.value.image?.read_error || 'Error reading file';
    emit('error', t.value.image?.read_error || 'Error reading file');
    isUploading.value = false;
  };

  reader.readAsDataURL(file);
};

const removeImage = () => {
  previewImage.value = null;
  error.value = '';
  cropSource.value = '';
  cropOpen.value = false;
  emit('file-selected', null);
  if (fileInput.value) {
    fileInput.value.value = '';
  }
};

watch(() => props.initialPreview, (newValue) => {
  previewImage.value = newValue || null;
}, { immediate: true });
</script>

<style scoped>
.drag-over {
  @apply border-blue-500 bg-blue-50 dark:bg-blue-900/30 transition-all duration-200;
}
</style>
