<template>
  <div class="rich-text-editor">
    <!-- The surface is the positioning context for the image resize overlay;
         Quill drops its toolbar in here too. -->
    <div ref="surface" class="rich-text-editor__surface">
      <div
        ref="editor"
        :class="[
          'quill-container',
          error ? 'quill-error' : 'quill-normal',
          direction === 'rtl' ? 'quill-rtl' : ''
        ]"
      ></div>

      <EditorImageResizer
        v-if="resizableImages"
        :target="activeImage"
        :container="surface"
        :revision="revision"
        @preview="previewImageWidth"
        @commit="commitImageWidth"
        @reset="resetImageSize"
      />
    </div>

    <p v-if="isUploading" class="mt-1 text-xs text-muted-foreground">Uploading image…</p>
    <p v-if="error" class="mt-1 text-sm text-red-600">{{ error }}</p>
  </div>
</template>

<script setup>
/**
 * The one place the app talks to Quill. Everything else uses this component's
 * props/events, so swapping the underlying editor is a change confined here.
 */
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Quill from 'quill';
import 'quill/dist/quill.snow.css';
import EditorImageResizer from './EditorImageResizer.vue';

const props = defineProps({
  modelValue: {
    type: String,
    default: ''
  },
  placeholder: {
    type: String,
    default: 'Write something...'
  },
  error: String,
  direction: {
    type: String,
    default: 'ltr'
  },
  // async (File) => url. When given, images picked from the toolbar, pasted or
  // dropped are uploaded through it instead of being inlined as base64.
  imageUploader: {
    type: Function,
    default: null
  },
  // Click an image to get drag handles and size presets.
  resizableImages: {
    type: Boolean,
    default: true
  },
  toolbar: {
    type: Array,
    default: () => [
      ['bold', 'italic', 'underline', 'strike'],
      ['blockquote'],
      [{ 'header': 1 }, { 'header': 2 }],
      [{ 'list': 'ordered'}, { 'list': 'bullet' }],
      [{ 'script': 'sub'}, { 'script': 'super' }],
      [{ 'indent': '-1'}, { 'indent': '+1' }],
      [{ 'direction': 'rtl' }],
      [{ 'size': ['small', false, 'large', 'huge'] }],
      [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
      [{ 'color': [] }, { 'background': [] }],
      [{ 'font': [] }],
      [{ 'align': [] }],
      ['link'],
      ['clean'],
    ]
  }
});

const emit = defineEmits(['update:modelValue']);
const editor = ref(null);
const surface = ref(null);
let quill = null;

const isUploading = ref(false);
const activeImage = ref(null);
// Re-measure trigger for the overlay: the editor knows when things moved.
const revision = ref(0);

const pushContent = () => emit('update:modelValue', quill.root.innerHTML);

const applyDirection = (direction) => {
  if (!quill) return;
  quill.root.setAttribute('dir', direction === 'rtl' ? 'rtl' : 'ltr');
};

// --- Image uploads --------------------------------------------------------

// Uploads run one file at a time so the images land in the order they were given.
const insertUploadedImages = async (index, files) => {
  let at = index;
  isUploading.value = true;

  try {
    for (const file of files) {
      const url = await props.imageUploader(file);
      if (!url) continue;
      quill.insertEmbed(at, 'image', url, 'user');
      at += 1;
    }
    quill.setSelection(at, 0, 'silent');
    pushContent();
  } catch (error) {
    console.error('Failed to upload editor image:', error);
  } finally {
    isUploading.value = false;
  }
};

// Toolbar image button: pick a file, upload it, embed the returned URL.
const pickAndUploadImage = () => {
  const input = document.createElement('input');
  input.type = 'file';
  input.accept = 'image/*';
  input.multiple = true;

  input.onchange = () => {
    const files = Array.from(input.files || []);
    if (!files.length) return;
    const range = quill.getSelection(true);
    insertUploadedImages(range ? range.index : quill.getLength(), files);
  };

  input.click();
};

// --- Image sizing ---------------------------------------------------------

/** Width is written through Quill so it survives in the document model. */
const formatActiveImage = (formats) => {
  const image = activeImage.value;
  if (!image || !quill) return;

  const blot = Quill.find(image);

  if (blot) {
    const index = quill.getIndex(blot);

    Object.entries(formats).forEach(([name, value]) => {
      quill.formatText(index, 1, name, value ?? false, 'user');
    });
  } else {
    // Content loaded straight into the DOM may not have a blot yet; the
    // attributes are what gets saved either way.
    Object.entries(formats).forEach(([name, value]) => {
      if (value) {
        image.setAttribute(name, value);
      } else {
        image.removeAttribute(name);
      }
    });
  }

  pushContent();
  revision.value += 1;
};

// Live feedback while a handle is dragged — the model is updated once, on drop.
const previewImageWidth = (width) => {
  if (!activeImage.value) return;
  activeImage.value.removeAttribute('height');
  activeImage.value.setAttribute('width', width);
  revision.value += 1;
};

const commitImageWidth = (width) => {
  formatActiveImage({ height: null, width });
};

const resetImageSize = () => {
  formatActiveImage({ height: null, width: null });
};

const selectImage = (event) => {
  const image = event.target instanceof HTMLImageElement ? event.target : null;
  activeImage.value = image;

  if (image) {
    // Flush any DOM-only content into the document so the image has a blot.
    quill.update();
    const blot = Quill.find(image);
    if (blot) quill.setSelection(quill.getIndex(blot), 1, 'user');
    revision.value += 1;
  }
};

// A click anywhere else — including outside the editor — puts the handles away.
const clearImageSelectionOnOutsideClick = (event) => {
  if (!activeImage.value) return;
  if (surface.value?.contains(event.target)) return;
  activeImage.value = null;
};

// --- Lifecycle ------------------------------------------------------------

/**
 * The image button only appears when the host gave us somewhere to upload to —
 * without that Quill would inline the file as base64 straight into the column.
 */
const toolbarContainer = () => {
  if (!props.imageUploader) return props.toolbar;

  const groups = props.toolbar.map((group) => (Array.isArray(group) ? [...group] : group));
  if (groups.some((group) => Array.isArray(group) && group.includes('image'))) return groups;

  const linkGroup = groups.find((group) => Array.isArray(group) && group.includes('link'));
  if (linkGroup) {
    linkGroup.push('image');
    return groups;
  }

  return [...groups, ['image']];
};

onMounted(() => {
  const modules = {
    toolbar: {
      container: toolbarContainer(),
      handlers: props.imageUploader ? { image: pickAndUploadImage } : {}
    }
  };

  if (props.imageUploader) {
    // Covers pasted and dropped images — Quill routes both through this module.
    modules.uploader = {
      mimetypes: ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'],
      handler: (range, files) => insertUploadedImages(range ? range.index : quill.getLength(), files)
    };
  }

  quill = new Quill(editor.value, {
    theme: 'snow',
    placeholder: props.placeholder,
    modules
  });

  applyDirection(props.direction);

  quill.on('text-change', () => {
    // The selected image may have just been deleted or moved.
    if (activeImage.value && !quill.root.contains(activeImage.value)) {
      activeImage.value = null;
    }
    revision.value += 1;
    pushContent();
  });

  quill.root.addEventListener('click', selectImage);
  quill.root.addEventListener('scroll', () => { revision.value += 1; });
  document.addEventListener('mousedown', clearImageSelectionOnOutsideClick);

  if (props.modelValue) {
    quill.root.innerHTML = props.modelValue;
  }
});

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', clearImageSelectionOnOutsideClick);
});

watch(() => props.modelValue, (newValue) => {
  if (newValue !== quill.root.innerHTML) {
    quill.root.innerHTML = newValue;
    activeImage.value = null;
  }
});

watch(() => props.direction, (newValue) => {
  applyDirection(newValue);
});
</script>

<style lang="scss" scoped>
.rich-text-editor {
  .rich-text-editor__surface {
    position: relative;
  }

  .quill-container {
    border-radius: 0.375rem;
    overflow: hidden;
  }

  .quill-normal {
    :deep(.ql-container) {
      border: 1px solid #d1d5db;
      border-top: none;
    }

    :deep(.ql-toolbar) {
      border: 1px solid #d1d5db;
      border-bottom: none;
    }
  }

  .quill-error {
    :deep(.ql-container) {
      border: 1px solid #fca5a5;
      border-top: none;
    }

    :deep(.ql-toolbar) {
      border: 1px solid #fca5a5;
      border-bottom: none;
    }
  }

  :deep(.ql-container) {
    border-bottom-left-radius: 0.375rem;
    border-bottom-right-radius: 0.375rem;
    min-height: 200px;
    font-size: 1rem;
  }

  :deep(.ql-editor) {
    min-height: 200px;
  }

  :deep(.ql-toolbar) {
    border-top-left-radius: 0.375rem;
    border-top-right-radius: 0.375rem;
    background-color: rgba(255, 255, 255, 0.05);
  }

  // Make toolbar icons white
  :deep(.ql-toolbar) {
    .ql-stroke {
      stroke: white !important;
    }

    .ql-fill {
      fill: white !important;
    }

    .ql-picker-label {
      color: white !important;
    }

    .ql-picker-options {
      background-color: #1f2937;
      border-color: #374151;
    }

    .ql-picker-item {
      color: white !important;

      &:hover {
        background-color: #374151;
      }
    }

    button {
      &:hover {
        color: white !important;

        .ql-stroke {
          stroke: #B89B6A !important;
        }

        .ql-fill {
          fill: #B89B6A !important;
        }
      }

      &.ql-active {
        .ql-stroke {
          stroke: #B89B6A !important;
        }

        .ql-fill {
          fill: #B89B6A !important;
        }
      }
    }
  }

  // RTL support
  .quill-rtl {
    :deep(.ql-editor) {
      direction: rtl;
      text-align: right;
    }

    :deep(.ql-editor.ql-blank::before) {
      left: auto;
      right: 15px;
      text-align: right;
    }
  }

  // Make editor text white
  :deep(.ql-editor) {
    color: white;

    &.ql-blank::before {
      color: rgba(255, 255, 255, 0.5);
    }
  }

  // Images sit inline in the flow; the resize overlay tracks their box.
  :deep(.ql-editor img) {
    max-width: 100%;
    height: auto;
    cursor: pointer;
  }
}
</style>
