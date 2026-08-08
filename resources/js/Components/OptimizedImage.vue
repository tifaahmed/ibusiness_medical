<template>
  <picture>
    <!-- WebP version with responsive sizes -->
    <source
      v-if="webpSrcset"
      :srcset="webpSrcset"
      type="image/webp"
      :sizes="sizes"
    >

    <!-- Regular format with responsive sizes -->
    <source
      v-if="regularSrcset"
      :srcset="regularSrcset"
      :sizes="sizes"
    >

    <!-- Fallback for browsers that don't support srcset -->
    <img
      :src="src"
      :alt="alt"
      :class="imgClass"
      :loading="loading"
      :width="width"
      :height="height"
      @load="onLoad"
      @error="onError"
    >
  </picture>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  src: {
    type: String,
    required: true
  },
  alt: {
    type: String,
    default: ''
  },
  imgClass: {
    type: String,
    default: ''
  },
  loading: {
    type: String,
    default: 'lazy',
    validator: (value) => ['lazy', 'eager'].includes(value)
  },
  width: {
    type: [String, Number],
    default: undefined
  },
  height: {
    type: [String, Number],
    default: undefined
  },
  sizes: {
    type: String,
    default: '100vw'
  },
  // Use thumbnail version for small displays
  useThumbnail: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(['load', 'error']);

// Get base path and extension
const getImageParts = (src) => {
  const extensionMatch = src.match(/^(.+)\.(jpg|jpeg|png|gif)$/i);
  if (extensionMatch) {
    return {
      base: extensionMatch[1],
      ext: extensionMatch[2]
    };
  }
  return null;
};

// Generate responsive srcset for WebP
const webpSrcset = computed(() => {
  const parts = getImageParts(props.src);
  if (!parts) return null;

  const srcsetParts = [];

  // Add thumbnail version (150w for partners, 600w for sliders)
  if (props.src.includes('/partners/')) {
    srcsetParts.push(`${parts.base}-thumb.webp 150w`);
    srcsetParts.push(`${parts.base}-medium.webp 300w`);
    srcsetParts.push(`${parts.base}.webp 400w`);
  } else if (props.src.includes('/slider/')) {
    srcsetParts.push(`${parts.base}-thumb.webp 600w`);
    srcsetParts.push(`${parts.base}-medium.webp 1200w`);
    srcsetParts.push(`${parts.base}.webp 1920w`);
  } else if (props.src.includes('/logo/')) {
    srcsetParts.push(`${parts.base}-thumb.webp 100w`);
    srcsetParts.push(`${parts.base}-medium.webp 250w`);
    srcsetParts.push(`${parts.base}.webp 500w`);
  } else {
    // Default sizes
    srcsetParts.push(`${parts.base}-thumb.webp 400w`);
    srcsetParts.push(`${parts.base}-medium.webp 800w`);
    srcsetParts.push(`${parts.base}.webp 1200w`);
  }

  return srcsetParts.join(', ');
});

// Generate responsive srcset for regular formats
const regularSrcset = computed(() => {
  const parts = getImageParts(props.src);
  if (!parts) return null;

  const srcsetParts = [];
  const ext = parts.ext;

  if (props.src.includes('/partners/')) {
    srcsetParts.push(`${parts.base}-thumb.${ext} 150w`);
    srcsetParts.push(`${parts.base}-medium.${ext} 300w`);
    srcsetParts.push(`${parts.base}.${ext} 400w`);
  } else if (props.src.includes('/slider/')) {
    srcsetParts.push(`${parts.base}-thumb.${ext} 600w`);
    srcsetParts.push(`${parts.base}-medium.${ext} 1200w`);
    srcsetParts.push(`${parts.base}.${ext} 1920w`);
  } else if (props.src.includes('/logo/')) {
    srcsetParts.push(`${parts.base}-thumb.${ext} 100w`);
    srcsetParts.push(`${parts.base}-medium.${ext} 250w`);
    srcsetParts.push(`${parts.base}.${ext} 500w`);
  } else {
    srcsetParts.push(`${parts.base}-thumb.${ext} 400w`);
    srcsetParts.push(`${parts.base}-medium.${ext} 800w`);
    srcsetParts.push(`${parts.base}.${ext} 1200w`);
  }

  return srcsetParts.join(', ');
});

const onLoad = (event) => {
  emit('load', event);
};

const onError = (event) => {
  emit('error', event);
};
</script>

<style scoped>
picture {
  display: contents;
}

img {
  display: block;
}
</style>
