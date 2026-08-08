<template>
  <div class="tinymce-editor-container">
    <Editor
      v-model="content"
      :init="editorConfig"
      @onInit="handleEditorInit"
    />
  </div>
</template>

<script setup>
import { ref, watch, computed } from "vue";
import Editor from "@tinymce/tinymce-vue";
import axios from "axios";

const props = defineProps({
  modelValue: {
    type: String,
    default: "",
  },
  toolbar: {
    type: String,
    default: "full",
  },
  school_title: {
    type: String,
    default: "",
    required: false,
  },
  rtl: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["update:modelValue"]);
const content = ref(props.modelValue);
const editor = ref(null);

const editorConfig = computed(() => ({
  height: 500,
  menubar: true,
  directionality: props.rtl ? 'rtl' : 'ltr',
  plugins: [
    "advlist",
    "autolink",
    "lists",
    "link",
    "image",
    "charmap",
    "preview",
    "anchor",
    "searchreplace",
    "visualblocks",
    "code",
    "fullscreen",
    "insertdatetime",
    "media",
    "table",
    "help",
    "wordcount",
    "emoticons",
  ],
  toolbar:
    props.toolbar === "full"
      ? "undo redo | formatselect | bold italic backcolor | \
        alignleft aligncenter alignright alignjustify | \
        bullist numlist outdent indent | removeformat | image table | help"
      : "undo redo | formatselect | bold italic | alignleft alignright | image | help",
  content_style: "body { font-family:Helvetica,Arial,sans-serif; font-size:14px }",
  images_upload_handler: async (blobInfo, progress) => {
    try {
      const formData = new FormData();
      formData.append("image", blobInfo.blob(), blobInfo.filename());
      formData.append("school_", blobInfo.blob(), blobInfo.filename());

      const response = await axios.post("/api/editor/image/upload", formData, {
        headers: {
          "Content-Type": "multipart/form-data",
        },
        onUploadProgress: (e) => {
          progress((e.loaded / e.total) * 100);
        },
      });

      if (response.data?.data?.files?.[0]) {
        return `${response.data.data.baseurl}${response.data.data.files[0]}`;
      } else {
        throw new Error("Invalid response format");
      }
    } catch (error) {
      console.error("Upload error:", error);
      throw new Error("Image upload failed");
    }
  },
  automatic_uploads: true,
  file_picker_types: "image",
  images_upload_credentials: true,
  relative_urls: false,
  remove_script_host: false,
  convert_urls: true,
}));

const handleEditorInit = (evt, editor) => {
  editor.value = editor;
};

// Watch for content changes
watch(content, (newContent) => {
  emit("update:modelValue", newContent);
});

// Watch for prop changes
watch(
  () => props.modelValue,
  (newValue) => {
    if (newValue !== content.value) {
      content.value = newValue;
    }
  }
);
</script>

<style lang="scss" scoped>
.tinymce-editor-container {
  width: 100%;

  :deep(.tox-tinymce) {
    border-radius: 0.375rem;
  }

  :deep(.tox-editor-container) {
    background: white;
  }
}
</style>
