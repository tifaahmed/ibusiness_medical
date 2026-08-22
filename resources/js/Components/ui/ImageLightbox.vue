<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-[100] flex flex-col bg-black/90 backdrop-blur-sm"
      role="dialog"
      aria-modal="true"
      @click.self="close"
    >
      <!-- Toolbar -->
      <div class="flex items-center gap-2 p-3 text-white" @click.stop>
        <span v-if="images.length > 1" class="text-sm tabular-nums opacity-80">
          {{ index + 1 }} / {{ images.length }}
        </span>
        <span v-if="current?.alt" class="truncate text-sm opacity-80">{{ current.alt }}</span>

        <div class="ml-auto flex items-center gap-1">
          <span class="mr-1 text-xs tabular-nums opacity-70">{{ Math.round(scale * 100) }}%</span>
          <button type="button" :class="toolBtn" title="Zoom out (−)" @click="zoomBy(1 / STEP)">
            <svg v-bind="icon"><circle cx="11" cy="11" r="8" /><path d="m21 21-4.3-4.3" /><path d="M8 11h6" /></svg>
          </button>
          <button type="button" :class="toolBtn" title="Zoom in (+)" @click="zoomBy(STEP)">
            <svg v-bind="icon"><circle cx="11" cy="11" r="8" /><path d="m21 21-4.3-4.3" /><path d="M11 8v6" /><path d="M8 11h6" /></svg>
          </button>
          <button type="button" :class="toolBtn" title="Reset (0)" @click="reset">
            <svg v-bind="icon"><path d="M3 12a9 9 0 1 0 3-6.7L3 8" /><path d="M3 3v5h5" /></svg>
          </button>
          <a :href="current?.url" target="_blank" rel="noopener" :class="toolBtn" title="Open in a new tab">
            <svg v-bind="icon"><path d="M15 3h6v6" /><path d="M10 14 21 3" /><path d="M21 14v7H3V3h7" /></svg>
          </a>
          <button type="button" :class="toolBtn" title="Close (Esc)" @click="close">
            <svg v-bind="icon"><path d="M18 6 6 18" /><path d="m6 6 12 12" /></svg>
          </button>
        </div>
      </div>

      <!-- Stage. The wheel listener is passive:false so zooming does not also
           scroll the page underneath. -->
      <div
        ref="stage"
        class="relative flex-1 overflow-hidden select-none"
        :class="scale > 1 ? (dragging ? 'cursor-grabbing' : 'cursor-grab') : 'cursor-zoom-in'"
        @click.self="close"
        @mousedown="startDrag"
        @dblclick="toggleZoom"
      >
        <img
          v-if="current"
          :key="current.url"
          :src="current.url"
          :alt="current.alt || ''"
          draggable="false"
          class="absolute inset-0 m-auto max-h-full max-w-full object-contain"
          :style="{
            transform: `translate(${offset.x}px, ${offset.y}px) scale(${scale})`,
            transition: dragging ? 'none' : 'transform 120ms ease-out',
          }"
        />
      </div>

      <!-- Paging -->
      <template v-if="images.length > 1">
        <button
          type="button"
          :class="[navBtn, 'left-2']"
          title="Previous (←)"
          @click.stop="step(-1)"
        >
          <svg v-bind="icon"><path d="m15 18-6-6 6-6" /></svg>
        </button>
        <button
          type="button"
          :class="[navBtn, 'right-2']"
          title="Next (→)"
          @click.stop="step(1)"
        >
          <svg v-bind="icon"><path d="m9 18 6-6-6-6" /></svg>
        </button>

        <div class="flex justify-center gap-2 overflow-x-auto p-3" @click.stop>
          <button
            v-for="(img, i) in images"
            :key="img.url + i"
            type="button"
            @click="go(i)"
          >
            <img
              :src="img.url"
              :alt="img.alt || ''"
              class="h-14 w-14 rounded border-2 object-cover transition"
              :class="i === index ? 'border-white' : 'border-transparent opacity-50 hover:opacity-90'"
            />
          </button>
        </div>
      </template>

      <p class="pb-3 text-center text-[11px] text-white/50">
        Scroll to zoom · drag to pan · double-click to toggle · Esc to close
      </p>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

/**
 * A full-screen image viewer with zoom and pan.
 *
 * `index` is the whole state: a number opens the viewer on that image, null
 * closes it. The parent keeps it, so the same viewer serves several thumbnail
 * grids on one page without any of them owning it.
 */
const props = defineProps({
  images: { type: Array, default: () => [] },
  index: { type: Number, default: null },
});

const emit = defineEmits(['update:index']);

const MIN = 1;
const MAX = 8;
const STEP = 1.4;

const icon = {
  xmlns: 'http://www.w3.org/2000/svg',
  width: 18,
  height: 18,
  viewBox: '0 0 24 24',
  fill: 'none',
  stroke: 'currentColor',
  'stroke-width': 2,
  'stroke-linecap': 'round',
  'stroke-linejoin': 'round',
};

const toolBtn = 'inline-flex h-9 w-9 items-center justify-center rounded-md text-white/90 hover:bg-white/15 hover:text-white transition-colors cursor-pointer';
const navBtn = 'absolute top-1/2 -translate-y-1/2 inline-flex h-11 w-11 items-center justify-center rounded-full bg-black/50 text-white hover:bg-black/80 transition-colors cursor-pointer';

const stage = ref(null);
const scale = ref(1);
const offset = ref({ x: 0, y: 0 });
const dragging = ref(false);

const index = computed(() => props.index);
const open = computed(() => index.value !== null && index.value >= 0 && index.value < props.images.length);
const current = computed(() => (open.value ? props.images[index.value] : null));

const clamp = (value) => Math.min(MAX, Math.max(MIN, value));

const reset = () => {
  scale.value = 1;
  offset.value = { x: 0, y: 0 };
};

const close = () => emit('update:index', null);

const go = (i) => {
  reset();
  emit('update:index', i);
};

const step = (delta) => {
  const count = props.images.length;
  if (!count) return;
  go((index.value + delta + count) % count);
};

/* Zoom about a point, so what sits under the cursor stays under the cursor.
   The point is measured from the stage's centre because that is where the
   transform's origin is. */
const zoomAt = (factor, px = 0, py = 0) => {
  const next = clamp(scale.value * factor);
  const ratio = next / scale.value;

  offset.value = {
    x: px - (px - offset.value.x) * ratio,
    y: py - (py - offset.value.y) * ratio,
  };
  scale.value = next;

  // Back at full view there is nothing to pan to; drop any leftover offset.
  if (next === MIN) offset.value = { x: 0, y: 0 };
};

const zoomBy = (factor) => zoomAt(factor);

const toggleZoom = (event) => {
  if (scale.value > 1) {
    reset();

    return;
  }
  const rect = stage.value?.getBoundingClientRect();
  if (!rect) return;
  zoomAt(2.5, event.clientX - rect.left - rect.width / 2, event.clientY - rect.top - rect.height / 2);
};

const onWheel = (event) => {
  event.preventDefault();
  const rect = stage.value?.getBoundingClientRect();
  if (!rect) return;
  zoomAt(
    event.deltaY < 0 ? STEP : 1 / STEP,
    event.clientX - rect.left - rect.width / 2,
    event.clientY - rect.top - rect.height / 2
  );
};

const startDrag = (event) => {
  if (scale.value <= 1 || event.button !== 0) return;
  event.preventDefault();
  dragging.value = true;

  const start = { x: event.clientX - offset.value.x, y: event.clientY - offset.value.y };

  const move = (e) => {
    offset.value = { x: e.clientX - start.x, y: e.clientY - start.y };
  };
  const up = () => {
    dragging.value = false;
    window.removeEventListener('mousemove', move);
    window.removeEventListener('mouseup', up);
  };

  window.addEventListener('mousemove', move);
  window.addEventListener('mouseup', up);
};

const onKey = (event) => {
  const keys = {
    Escape: close,
    ArrowLeft: () => step(-1),
    ArrowRight: () => step(1),
    '+': () => zoomBy(STEP),
    '=': () => zoomBy(STEP),
    '-': () => zoomBy(1 / STEP),
    0: reset,
  };
  const handler = keys[event.key];
  if (!handler) return;
  event.preventDefault();
  handler();
};

/* The listeners and the page's frozen scroll live exactly as long as the
   viewer does — including when the parent closes it without a click. */
const teardown = () => {
  window.removeEventListener('keydown', onKey);
  stage.value?.removeEventListener('wheel', onWheel);
  document.body.style.overflow = '';
};

watch(open, async (isOpen) => {
  if (!isOpen) {
    teardown();
    reset();

    return;
  }

  reset();
  document.body.style.overflow = 'hidden';
  window.addEventListener('keydown', onKey);
  // The stage only exists once v-if has rendered it.
  await nextTick();
  stage.value?.addEventListener('wheel', onWheel, { passive: false });
});

onBeforeUnmount(teardown);
</script>
