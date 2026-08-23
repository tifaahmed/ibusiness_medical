<template>
  <div v-if="target && box" class="image-resizer" :style="boxStyle">
    <span
      v-for="corner in corners"
      :key="corner"
      :class="['image-resizer__handle', `image-resizer__handle--${corner}`]"
      @pointerdown="startDrag($event, corner)"
    ></span>

    <!-- mousedown is swallowed so clicking a preset never steals the caret,
         which is what would drop the image out of the editor's selection. -->
    <div class="image-resizer__bar" :style="barStyle" @mousedown.prevent>
      <button
        v-for="preset in presets"
        :key="preset.value"
        type="button"
        :class="{ 'is-active': currentPercent === preset.value }"
        @click="applyPercent(preset.value)"
      >
        {{ preset.label }}
      </button>
      <button type="button" @click="$emit('reset')">Original</button>
      <span class="image-resizer__size">{{ currentPercent ? `${currentPercent}%` : 'auto' }}</span>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
  // The <img> being resized, and the positioned element to place the overlay in.
  target: { type: Object, default: null },
  container: { type: Object, default: null },
  // Bumped by the editor whenever the content moved, to re-measure.
  revision: { type: Number, default: 0 },
  presets: {
    type: Array,
    default: () => [
      { label: 'S', value: 25 },
      { label: 'M', value: 50 },
      { label: 'L', value: 75 },
      { label: 'Full', value: 100 },
    ]
  },
  minPercent: { type: Number, default: 5 },
});

const emit = defineEmits(['preview', 'commit', 'reset']);

const box = ref(null);
const currentPercent = ref(null);

const boxStyle = computed(() => (box.value ? {
  top: `${box.value.top}px`,
  left: `${box.value.left}px`,
  width: `${box.value.width}px`,
  height: `${box.value.height}px`,
} : {}));

// The bar sits above the image, or inside its top edge when there is no room.
const barStyle = computed(() => (box.value && box.value.top < 30 ? { top: '4px' } : { top: '-28px' }));

const corners = ['nw', 'ne', 'sw', 'se'];

/** Width available to the image — percentages resolve against its parent block. */
const contentWidth = () => props.target?.parentElement?.clientWidth || 0;

const measure = () => {
  if (!props.target || !props.container) {
    box.value = null;
    return;
  }

  const image = props.target.getBoundingClientRect();
  const host = props.container.getBoundingClientRect();

  box.value = {
    top: image.top - host.top,
    left: image.left - host.left,
    width: image.width,
    height: image.height,
  };

  const available = contentWidth();
  currentPercent.value = available ? Math.round((image.width / available) * 100) : null;
};

const toPercent = (px) => {
  const available = contentWidth();
  if (!available) return null;
  const percent = Math.round((px / available) * 100);
  return Math.min(100, Math.max(props.minPercent, percent));
};

const applyPercent = (percent) => {
  emit('commit', `${percent}%`);
};

const startDrag = (event, corner) => {
  if (!props.target) return;

  event.preventDefault();
  event.stopPropagation();

  const startX = event.clientX;
  const startWidth = props.target.getBoundingClientRect().width;
  // West handles grow the image as the pointer moves left.
  const towardsWest = corner.endsWith('w');
  let latest = null;

  const onMove = (moveEvent) => {
    const delta = (moveEvent.clientX - startX) * (towardsWest ? -1 : 1);
    const percent = toPercent(startWidth + delta);
    if (percent === null || percent === latest) return;
    latest = percent;
    emit('preview', `${percent}%`);
  };

  const onUp = () => {
    window.removeEventListener('pointermove', onMove);
    window.removeEventListener('pointerup', onUp);
    if (latest !== null) emit('commit', `${latest}%`);
  };

  window.addEventListener('pointermove', onMove);
  window.addEventListener('pointerup', onUp);
};

let observer = null;

const observe = () => {
  observer?.disconnect();
  if (!props.target || typeof ResizeObserver === 'undefined') return;
  observer = new ResizeObserver(measure);
  observer.observe(props.target);
  if (props.container) observer.observe(props.container);
};

watch(() => props.target, () => {
  measure();
  observe();
}, { immediate: true });

watch(() => props.revision, measure);

onMounted(() => {
  window.addEventListener('resize', measure);
  window.addEventListener('scroll', measure, true);
  observe();
});

onBeforeUnmount(() => {
  window.removeEventListener('resize', measure);
  window.removeEventListener('scroll', measure, true);
  observer?.disconnect();
});

defineExpose({ measure });
</script>

<style scoped>
.image-resizer {
  position: absolute;
  pointer-events: none;
  outline: 1px solid #b89b6a;
  z-index: 5;
}

.image-resizer__handle {
  position: absolute;
  width: 10px;
  height: 10px;
  background: #b89b6a;
  border: 1px solid #fff;
  border-radius: 2px;
  pointer-events: auto;
}

.image-resizer__handle--nw { top: -5px; left: -5px; cursor: nwse-resize; }
.image-resizer__handle--ne { top: -5px; right: -5px; cursor: nesw-resize; }
.image-resizer__handle--sw { bottom: -5px; left: -5px; cursor: nesw-resize; }
.image-resizer__handle--se { bottom: -5px; right: -5px; cursor: nwse-resize; }

.image-resizer__bar {
  position: absolute;
  left: 0;
  display: flex;
  align-items: center;
  gap: 2px;
  padding: 2px 4px;
  border-radius: 4px;
  background: #1f2937;
  border: 1px solid #374151;
  box-shadow: 0 1px 3px rgb(0 0 0 / 40%);
  pointer-events: auto;
  white-space: nowrap;
}

.image-resizer__bar button {
  padding: 1px 6px;
  border-radius: 3px;
  font-size: 11px;
  line-height: 16px;
  color: #fff;
}

.image-resizer__bar button:hover {
  background: #374151;
}

.image-resizer__bar button.is-active {
  background: #b89b6a;
  color: #1f2937;
}

.image-resizer__size {
  padding-inline: 4px;
  font-size: 11px;
  color: rgb(255 255 255 / 60%);
}
</style>
