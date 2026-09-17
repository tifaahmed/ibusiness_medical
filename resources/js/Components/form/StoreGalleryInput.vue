<template>
  <div class="w-full">
    <label class="block text-sm font-medium mb-2">
      {{ label }}
      <span v-if="hint" class="text-xs text-muted-foreground ms-1">{{ hint }}</span>
    </label>

    <div v-if="existingItems.length || newItems.length" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3 mb-3">
      <div
        v-for="item in existingItems"
        :key="`existing-${item.id}`"
        class="relative group rounded-lg overflow-hidden border border-border aspect-square bg-muted"
      >
        <img v-if="item.type === 'image'" :src="item.url" class="w-full h-full object-cover" loading="lazy" />
        <video v-else :src="item.url" class="w-full h-full object-cover" muted preload="metadata" />
        <span v-if="item.type === 'video'" class="absolute bottom-1 left-1 rounded bg-black/60 px-1.5 py-0.5 text-[10px] text-white">▶ video</span>
        <button
          type="button"
          @click="removeExisting(item.id)"
          class="absolute top-1 right-1 p-1 bg-black/60 rounded-full opacity-0 group-hover:opacity-100 transition-opacity hover:bg-red-500/80"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 6 6 18"></path><path d="m6 6 12 12"></path>
          </svg>
        </button>
      </div>

      <div
        v-for="(item, idx) in newItems"
        :key="`new-${idx}`"
        class="relative group rounded-lg overflow-hidden border border-border aspect-square bg-muted"
      >
        <img v-if="item.kind === 'image'" :src="item.preview" class="w-full h-full object-cover" loading="lazy" />
        <video v-else :src="item.preview" class="w-full h-full object-cover" muted preload="metadata" />
        <span v-if="item.kind === 'video'" class="absolute bottom-1 left-1 rounded bg-black/60 px-1.5 py-0.5 text-[10px] text-white">▶ video</span>
        <button
          type="button"
          @click="removeNew(idx)"
          class="absolute top-1 right-1 p-1 bg-black/60 rounded-full opacity-0 group-hover:opacity-100 transition-opacity hover:bg-red-500/80"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 6 6 18"></path><path d="m6 6 12 12"></path>
          </svg>
        </button>
      </div>
    </div>

    <div
      class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 dark:border-gray-600 border-dashed rounded-lg cursor-pointer bg-card hover:bg-accent/50 transition-colors duration-200"
      @dragover.prevent="dragOver = true"
      @dragleave="dragOver = false"
      @drop.prevent="handleDrop"
      @click="$refs.fileInput.click()"
      :class="{ 'drag-over': dragOver }"
    >
      <div class="flex flex-col items-center justify-center pt-3 pb-3 text-center px-3">
        <p class="mb-1 text-sm text-muted-foreground">
          <span class="font-semibold text-foreground">Click to upload</span> or drag and drop
        </p>
        <p class="text-xs text-muted-foreground">Images or videos — max {{ maxSize }}MB each</p>
        <p v-if="error" class="mt-2 text-xs text-destructive font-medium">{{ error }}</p>
      </div>
    </div>

    <input
      type="file"
      class="hidden"
      accept="image/*,video/*"
      multiple
      @change="handleFileSelect"
      ref="fileInput"
    />

    <p v-if="errorText" class="mt-1 text-sm text-destructive">{{ errorText }}</p>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  label: { type: String, default: 'Gallery' },
  hint: { type: String, default: '' },
  maxSize: { type: Number, default: 5 },
  // Existing saved items: [{ id, url, type: 'image'|'video' }]
  existingItems: { type: Array, default: () => [] },
  // Server-side messages, keyed as the request reports them.
  errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['remove-existing', 'update:images', 'update:videos']);

const fileInput = ref(null);
const dragOver = ref(false);
const error = ref('');

// { file, kind: 'image'|'video', preview }
const newItems = ref([]);

const removeExisting = (id) => emit('remove-existing', id);

const commit = () => {
  emit('update:images', newItems.value.filter(i => i.kind === 'image').map(i => i.file));
  emit('update:videos', newItems.value.filter(i => i.kind === 'video').map(i => i.file));
};

const removeNew = (idx) => {
  newItems.value.splice(idx, 1);
  commit();
};

const kindOf = (file) => {
  if (file.type.startsWith('video/')) return 'video';
  if (file.type.startsWith('image/')) return 'image';
  return null;
};

const acceptFiles = async (files) => {
  error.value = '';
  const maxBytes = props.maxSize * 1024 * 1024;
  const accepted = [];

  for (const file of files) {
    const kind = kindOf(file);
    if (!kind) {
      error.value = 'Only image or video files are accepted.';
      continue;
    }
    if (file.size > maxBytes) {
      error.value = `Each file must be ${props.maxSize}MB or smaller.`;
      continue;
    }
    accepted.push({ file, kind, preview: URL.createObjectURL(file) });
  }

  newItems.value.push(...accepted);
  commit();
};

const handleFileSelect = (event) => {
  const files = Array.from(event.target.files || []);
  if (files.length) acceptFiles(files);
  if (fileInput.value) fileInput.value.value = '';
};

const handleDrop = (event) => {
  dragOver.value = false;
  const files = Array.from(event.dataTransfer.files || []);
  if (files.length) acceptFiles(files);
};

const errorText = computed(() => {
  const list = props.errors?.gallery_images || props.errors?.gallery_videos;
  if (list) return Array.isArray(list) ? list[0] : list;
  return '';
});
</script>

<style scoped>
.drag-over {
  @apply border-blue-500 bg-blue-50 dark:bg-blue-900/30 transition-all duration-200;
}
</style>
