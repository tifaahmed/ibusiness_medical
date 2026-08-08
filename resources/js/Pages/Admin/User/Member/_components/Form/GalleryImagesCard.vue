<template>
  <div class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
    <div class="py-2 px-6">
      <div class="title-golden leading-none font-semibold flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
          <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/>
          <circle cx="9" cy="9" r="2"/>
          <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
        </svg>
        {{ t.member?.gallery || 'Gallery' }}
      </div>
      <p class="text-xs text-muted-foreground mt-1">
        {{ t.member?.gallery_help || 'Upload multiple supporting images for this membership.' }}
      </p>
    </div>

    <div class="px-6">
      <div
        class="flex flex-col items-center justify-center w-full min-h-32 border-2 border-gray-300 dark:border-gray-600 border-dashed rounded-lg cursor-pointer bg-card hover:bg-accent/50 transition-colors duration-200 py-6"
        :class="{ 'border-red-500 dark:border-red-400': memberStore.validationErrors?.gallery_images }"
        @dragover.prevent="dragOver = true"
        @dragleave="dragOver = false"
        @drop.prevent="handleDrop"
        @click="$refs.fileInput.click()"
      >
        <i class="uil uil-cloud-upload text-4xl mb-2 text-muted-foreground"></i>
        <p class="text-sm text-muted-foreground">
          <span class="font-semibold text-foreground">{{ t.member?.gallery_click_upload || 'Click to upload' }}</span>
          {{ t.member?.gallery_or_drag || 'or drag and drop multiple images' }}
        </p>
        <p class="text-xs text-muted-foreground mt-1">PNG, JPG, JPEG, WEBP, AVIF (MAX. 5MB each)</p>
        <input
          ref="fileInput"
          type="file"
          class="hidden"
          accept="image/*"
          multiple
          @change="handleFileSelect"
        />
      </div>

      <p
        v-if="memberStore.validationErrors?.gallery_images"
        class="text-sm text-destructive mt-2"
      >
        {{ memberStore.validationErrors.gallery_images }}
      </p>

      <!-- Existing (already uploaded) images -->
      <div v-if="visibleExisting.length > 0" class="mt-4">
        <p class="text-xs font-medium text-muted-foreground mb-2">
          {{ t.member?.gallery_existing || 'Existing images' }}
        </p>
        <div class="grid grid-cols-3 sm:grid-cols-4 gap-2">
          <div
            v-for="img in visibleExisting"
            :key="`existing-${img.id}`"
            class="relative group aspect-square rounded-md overflow-hidden border border-border"
          >
            <img :src="img.url" :alt="img.name" class="w-full h-full object-cover" />
            <button
              type="button"
              class="absolute top-1 right-1 p-1 bg-white/90 backdrop-blur-sm rounded-full shadow-md hover:bg-red-50 transition-all opacity-0 group-hover:opacity-100"
              :title="t.member?.gallery_remove || 'Remove'"
              @click.stop="markExistingForRemoval(img.id)"
            >
              <i class="uil uil-times text-base text-red-600"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- New (about-to-upload) previews -->
      <div v-if="newPreviews.length > 0" class="mt-4">
        <p class="text-xs font-medium text-muted-foreground mb-2">
          {{ t.member?.gallery_pending || 'New uploads' }}
        </p>
        <div class="grid grid-cols-3 sm:grid-cols-4 gap-2">
          <div
            v-for="(preview, index) in newPreviews"
            :key="`new-${index}`"
            class="relative group aspect-square rounded-md overflow-hidden border border-border"
          >
            <img :src="preview" alt="New upload" class="w-full h-full object-cover" />
            <button
              type="button"
              class="absolute top-1 right-1 p-1 bg-white/90 backdrop-blur-sm rounded-full shadow-md hover:bg-red-50 transition-all opacity-0 group-hover:opacity-100"
              :title="t.member?.gallery_remove || 'Remove'"
              @click.stop="removeNewFile(index)"
            >
              <i class="uil uil-times text-base text-red-600"></i>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from "vue";
import { useMemberStore } from "../../Stores/MemberStore";
import { usePage } from "@inertiajs/vue3";

const memberStore = useMemberStore();
const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const fileInput = ref(null);
const dragOver = ref(false);
const newPreviews = ref([]);

// Existing items that haven't been flagged for removal.
const visibleExisting = computed(() =>
  (memberStore.form.gallery_existing || []).filter(
    (img) => !(memberStore.form.gallery_remove_ids || []).includes(img.id)
  )
);

// Rebuild data-URL previews any time the staged file list changes.
watch(
  () => memberStore.form.gallery_images,
  (files) => {
    if (!Array.isArray(files) || files.length === 0) {
      newPreviews.value = [];
      return;
    }
    Promise.all(
      files.map(
        (file) =>
          new Promise((resolve) => {
            const reader = new FileReader();
            reader.onload = (e) => resolve(e.target.result);
            reader.onerror = () => resolve(null);
            reader.readAsDataURL(file);
          })
      )
    ).then((results) => {
      newPreviews.value = results.filter(Boolean);
    });
  },
  { immediate: true, deep: true }
);

const acceptFiles = (files) => {
  const accepted = ["image/jpeg", "image/png", "image/jpg", "image/webp", "image/gif", "image/avif"];
  const max = 5 * 1024 * 1024;
  return Array.from(files).filter(
    (f) => accepted.includes(f.type) && f.size <= max
  );
};

const handleFileSelect = (event) => {
  const valid = acceptFiles(event.target.files);
  if (valid.length === 0) return;
  memberStore.form.gallery_images = [
    ...(memberStore.form.gallery_images || []),
    ...valid,
  ];
  if (fileInput.value) fileInput.value.value = "";
};

const handleDrop = (event) => {
  dragOver.value = false;
  const valid = acceptFiles(event.dataTransfer.files);
  if (valid.length === 0) return;
  memberStore.form.gallery_images = [
    ...(memberStore.form.gallery_images || []),
    ...valid,
  ];
};

const removeNewFile = (index) => {
  const current = [...(memberStore.form.gallery_images || [])];
  current.splice(index, 1);
  memberStore.form.gallery_images = current;
};

const markExistingForRemoval = (id) => {
  const current = [...(memberStore.form.gallery_remove_ids || [])];
  if (!current.includes(id)) {
    current.push(id);
    memberStore.form.gallery_remove_ids = current;
  }
};
</script>
