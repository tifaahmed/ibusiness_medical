<template>
  <div class="quill-editor">
    <div 
      ref="editor" 
      :class="[
        'quill-container',
        error ? 'quill-error' : 'quill-normal'
      ]"
    ></div>
    <p v-if="error" class="mt-1 text-sm text-red-600">{{ error }}</p>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import Quill from 'quill';
import 'quill/dist/quill.snow.css';

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
      ['clean'],
    ]
  }
});

const emit = defineEmits(['update:modelValue']);
const editor = ref(null);
let quill = null;

onMounted(() => {
  quill = new Quill(editor.value, {
    theme: 'snow',
    placeholder: props.placeholder,
    modules: {
      toolbar: props.toolbar
    }
  });

  quill.on('text-change', () => {
    const content = quill.root.innerHTML;
    emit('update:modelValue', content);
  });

  if (props.modelValue) {
    quill.root.innerHTML = props.modelValue;
  }
});

watch(() => props.modelValue, (newValue) => {
  if (newValue !== quill.root.innerHTML) {
    quill.root.innerHTML = newValue;
  }
});
</script>

<style lang="scss" scoped>
.quill-editor {
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

  // Make editor text white
  :deep(.ql-editor) {
    color: white;

    &.ql-blank::before {
      color: rgba(255, 255, 255, 0.5);
    }
  }
}
</style>
