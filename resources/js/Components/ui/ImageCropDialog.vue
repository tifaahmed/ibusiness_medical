<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-[110] flex flex-col bg-black/90 backdrop-blur-sm"
      role="dialog"
      aria-modal="true"
    >
      <!-- Toolbar -->
      <div class="flex items-center gap-2 p-3 text-white">
        <span class="text-sm font-medium">{{ t.image?.crop_title || 'Adjust image' }}</span>
        <span class="hidden sm:inline text-xs opacity-70">
          {{ ratio
            ? (t.image?.crop_hint_locked || 'Drag the box to move · drag a corner to resize · the shape matches the website')
            : (t.image?.crop_hint_free || 'Drag the box to move · drag any handle to resize') }}
        </span>
        <button type="button" :class="toolBtn" class="ml-auto" :title="t.common?.close || 'Close (Esc)'" @click="cancel">
          <svg v-bind="icon"><path d="M18 6 6 18" /><path d="m6 6 12 12" /></svg>
        </button>
      </div>

      <!-- Stage -->
      <div class="flex-1 min-h-0 flex flex-col items-center justify-center gap-4 px-4 pb-2 overflow-auto">
        <div
          ref="stage"
          class="relative select-none touch-none"
          :style="stageStyle"
        >
          <img
            v-if="src"
            ref="img"
            :src="src"
            alt=""
            draggable="false"
            class="absolute inset-0 h-full w-full object-contain opacity-40"
            @load="onImgLoad"
          />

          <template v-if="ready">
            <!-- clipped, full-opacity view of the kept area -->
            <div
              class="absolute overflow-hidden ring-2 ring-golden-yellow"
              :style="{ left: crop.x + 'px', top: crop.y + 'px', width: crop.w + 'px', height: crop.h + 'px' }"
            >
              <img
                :src="src"
                alt=""
                draggable="false"
                class="absolute max-w-none"
                :style="{
                  left: (imageRect.x - crop.x) + 'px',
                  top: (imageRect.y - crop.y) + 'px',
                  width: imageRect.w + 'px',
                  height: imageRect.h + 'px',
                }"
              />
            </div>

            <!-- drag surface + handles -->
            <div
              class="absolute cursor-move"
              :style="{ left: crop.x + 'px', top: crop.y + 'px', width: crop.w + 'px', height: crop.h + 'px' }"
              @pointerdown="(e) => startDrag(e, 'move')"
            >
              <div class="pointer-events-none absolute inset-0">
                <div class="absolute inset-y-0 left-1/3 w-px bg-white/30"></div>
                <div class="absolute inset-y-0 left-2/3 w-px bg-white/30"></div>
                <div class="absolute inset-x-0 top-1/3 h-px bg-white/30"></div>
                <div class="absolute inset-x-0 top-2/3 h-px bg-white/30"></div>
              </div>
              <span
                v-for="h in handles"
                :key="h.id"
                :class="['absolute h-3.5 w-3.5 rounded-full bg-white border border-black/40', h.cls]"
                :style="h.style"
                @pointerdown.stop="(e) => startDrag(e, h.id)"
              ></span>
            </div>
          </template>
        </div>

        <!-- Live preview -->
        <div v-if="showPreview && ready" class="text-center text-white/80">
          <div class="mb-1 text-[11px] opacity-70">{{ t.image?.preview_label || 'Result' }}</div>
          <div
            class="overflow-hidden rounded-md border border-white/20 bg-black/40"
            :style="previewBoxStyle"
          >
            <img
              :src="src"
              alt=""
              draggable="false"
              class="max-w-none"
              :style="previewImgStyle"
            />
          </div>
          <div class="mt-1 text-[11px] tabular-nums opacity-60">{{ outSize.w }} × {{ outSize.h }} px</div>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex items-center justify-end gap-2 border-t border-white/10 p-3">
        <button type="button" :class="toolBtn" class="mr-auto w-auto px-3 gap-1.5" @click="resetCrop">
          <svg v-bind="icon"><path d="M3 12a9 9 0 1 0 3-6.7L3 8" /><path d="M3 3v5h5" /></svg>
          <span class="text-sm">{{ t.common?.reset || 'Reset' }}</span>
        </button>
        <button
          type="button"
          class="rounded-md border border-white/25 px-4 py-2 text-sm font-medium text-white/90 hover:bg-white/10 transition-colors cursor-pointer"
          @click="cancel"
        >
          {{ t.common?.cancel || 'Cancel' }}
        </button>
        <button
          type="button"
          class="rounded-md bg-golden-yellow px-4 py-2 text-sm font-semibold text-black hover:opacity-90 transition-opacity cursor-pointer disabled:opacity-50"
          :disabled="!ready || busy"
          @click="apply"
        >
          {{ busy ? (t.common?.processing || 'Processing…') : (t.image?.crop_apply || 'Apply') }}
        </button>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const props = defineProps({
  open: { type: Boolean, default: false },
  /** Source image as a data URL or object URL. */
  src: { type: String, default: '' },
  /** Locked crop ratio (width / height). Null / 0 lets the admin draw any shape. */
  aspectRatio: { type: Number, default: null },
  /** Longest edge of the exported image, in pixels (never upscales). */
  outputWidth: { type: Number, default: 2000 },
  /** MIME type for the export. JPEG / PNG / WEBP pass through (PNG keeps
   *  transparency); anything else (AVIF, GIF, …) is exported as JPEG, which
   *  every upload endpoint accepts. */
  mimeType: { type: String, default: 'image/jpeg' },
  quality: { type: Number, default: 0.9 },
  fileName: { type: String, default: 'image' },
  showPreview: { type: Boolean, default: true },
});

const emit = defineEmits(['confirm', 'cancel']);

const MIN = 24; // smallest crop box, in stage px
const icon = {
  xmlns: 'http://www.w3.org/2000/svg', width: 16, height: 16, viewBox: '0 0 24 24',
  fill: 'none', stroke: 'currentColor', 'stroke-width': 2, 'stroke-linecap': 'round', 'stroke-linejoin': 'round',
};
const toolBtn = 'inline-flex h-9 min-w-9 shrink-0 items-center justify-center rounded-md text-white/90 hover:bg-white/15 hover:text-white transition-colors cursor-pointer';

const stage = ref(null);
const img = ref(null);
const previewW = 240;

const ratio = computed(() => (props.aspectRatio && props.aspectRatio > 0 ? props.aspectRatio : null));

const nat = reactive({ w: 0, h: 0 });          // source image, natural px
const stageBox = reactive({ w: 0, h: 0 });      // stage element, css px
const imageRect = reactive({ x: 0, y: 0, w: 0, h: 0 }); // where the contained image sits in the stage
const crop = reactive({ x: 0, y: 0, w: 0, h: 0 });
const ready = ref(false);
const busy = ref(false);

const stageStyle = computed(() => {
  const vw = typeof window !== 'undefined' ? window.innerWidth : 1024;
  const vh = typeof window !== 'undefined' ? window.innerHeight : 768;
  const maxW = Math.min(900, vw - 48);
  const maxH = Math.max(220, vh - (props.showPreview ? 360 : 240));
  if (!nat.w || !nat.h) return { width: `${maxW}px`, height: `${Math.round(maxW * 0.6)}px` };
  const r = nat.w / nat.h;
  let w = maxW;
  let h = w / r;
  if (h > maxH) { h = maxH; w = h * r; }
  return { width: `${Math.round(w)}px`, height: `${Math.round(h)}px` };
});

const handles = computed(() => {
  const all = [
    { id: 'nw', cls: '-left-1.5 -top-1.5 cursor-nwse-resize' },
    { id: 'ne', cls: '-right-1.5 -top-1.5 cursor-nesw-resize' },
    { id: 'sw', cls: '-left-1.5 -bottom-1.5 cursor-nesw-resize' },
    { id: 'se', cls: '-right-1.5 -bottom-1.5 cursor-nwse-resize' },
    { id: 'n', cls: 'left-1/2 -ml-1.5 -top-1.5 cursor-ns-resize' },
    { id: 's', cls: 'left-1/2 -ml-1.5 -bottom-1.5 cursor-ns-resize' },
    { id: 'w', cls: '-left-1.5 top-1/2 -mt-1.5 cursor-ew-resize' },
    { id: 'e', cls: '-right-1.5 top-1/2 -mt-1.5 cursor-ew-resize' },
  ];
  // With a locked ratio the edge handles would fight the ratio, so only corners.
  return (ratio.value ? all.slice(0, 4) : all).map((h) => ({ ...h, style: {} }));
});

/* ---- geometry ---- */

const measure = () => {
  const el = stage.value;
  if (!el) return;
  const rect = el.getBoundingClientRect();
  stageBox.w = rect.width;
  stageBox.h = rect.height;
  if (!nat.w || !nat.h) return;
  const r = nat.w / nat.h;
  if (stageBox.w / stageBox.h > r) {
    imageRect.h = stageBox.h;
    imageRect.w = stageBox.h * r;
  } else {
    imageRect.w = stageBox.w;
    imageRect.h = stageBox.w / r;
  }
  imageRect.x = (stageBox.w - imageRect.w) / 2;
  imageRect.y = (stageBox.h - imageRect.h) / 2;
};

const clampCrop = () => {
  crop.w = Math.min(crop.w, imageRect.w);
  crop.h = Math.min(crop.h, imageRect.h);
  crop.x = Math.min(Math.max(crop.x, imageRect.x), imageRect.x + imageRect.w - crop.w);
  crop.y = Math.min(Math.max(crop.y, imageRect.y), imageRect.y + imageRect.h - crop.h);
};

const resetCrop = () => {
  measure();
  let w = imageRect.w;
  let h = imageRect.h;
  if (ratio.value) {
    h = w / ratio.value;
    if (h > imageRect.h) { h = imageRect.h; w = h * ratio.value; }
  }
  crop.w = w;
  crop.h = h;
  crop.x = imageRect.x + (imageRect.w - w) / 2;
  crop.y = imageRect.y + (imageRect.h - h) / 2;
};

const startDrag = (event, mode) => {
  if (event.button !== undefined && event.button !== 0) return;
  event.preventDefault();
  stage.value?.setPointerCapture?.(event.pointerId);
  const rect = stage.value.getBoundingClientRect();
  const origin = { ...crop };
  const start = { x: event.clientX, y: event.clientY };

  const move = (e) => {
    const dx = e.clientX - start.x;
    const dy = e.clientY - start.y;
    if (mode === 'move') {
      crop.x = origin.x + dx;
      crop.y = origin.y + dy;
      clampCrop();
      return;
    }
    resize(mode, e.clientX - rect.left, e.clientY - rect.top, origin);
  };
  const up = (e) => {
    stage.value?.releasePointerCapture?.(e.pointerId);
    window.removeEventListener('pointermove', move);
    window.removeEventListener('pointerup', up);
    window.removeEventListener('pointercancel', up);
  };
  window.addEventListener('pointermove', move);
  window.addEventListener('pointerup', up);
  window.addEventListener('pointercancel', up);
};

const resize = (handle, px, py, origin) => {
  const imgL = imageRect.x;
  const imgT = imageRect.y;
  const imgR = imageRect.x + imageRect.w;
  const imgB = imageRect.y + imageRect.h;

  let L = origin.x;
  let T = origin.y;
  let R = origin.x + origin.w;
  let B = origin.y + origin.h;

  if (handle.includes('w')) L = Math.min(Math.max(px, imgL), R - MIN);
  if (handle.includes('e')) R = Math.max(Math.min(px, imgR), L + MIN);
  if (handle.includes('n')) T = Math.min(Math.max(py, imgT), B - MIN);
  if (handle.includes('s')) B = Math.max(Math.min(py, imgB), T + MIN);

  if (ratio.value) {
    // Corner drag: width leads, height follows the ratio, anchored on the fixed corner.
    let w = R - L;
    let h = w / ratio.value;
    if (handle.includes('n')) T = B - h;
    else B = T + h;
    if (T < imgT || B > imgB) {
      h = handle.includes('n') ? B - imgT : imgB - T;
      w = h * ratio.value;
      if (T < imgT) T = imgT;
      if (B > imgB) B = imgB;
      if (handle.includes('w')) L = R - w;
      else R = L + w;
    }
  }

  crop.x = L;
  crop.y = T;
  crop.w = R - L;
  crop.h = B - T;
  clampCrop();
};

/* ---- preview ---- */

const previewScale = computed(() => (crop.w ? previewW / crop.w : 1));
const previewBoxStyle = computed(() => ({
  width: `${previewW}px`,
  aspectRatio: `${crop.w || 1} / ${crop.h || 1}`,
}));
const previewImgStyle = computed(() => ({
  width: `${imageRect.w * previewScale.value}px`,
  height: `${imageRect.h * previewScale.value}px`,
  marginLeft: `${(imageRect.x - crop.x) * previewScale.value}px`,
  marginTop: `${(imageRect.y - crop.y) * previewScale.value}px`,
}));

const natScale = computed(() => (imageRect.w ? nat.w / imageRect.w : 1));
const outSize = computed(() => {
  const sw = crop.w * natScale.value;
  const sh = crop.h * natScale.value;
  const w = Math.round(Math.min(props.outputWidth, sw));
  const h = Math.round(ratio.value ? w / ratio.value : w * (sh / sw || 1));
  return { w: w || 0, h: h || 0 };
});

/* ---- export ---- */

const onImgLoad = () => {
  const el = img.value;
  if (!el) return;
  nat.w = el.naturalWidth;
  nat.h = el.naturalHeight;
  if (!nat.w || !nat.h) return;
  ready.value = true;
  nextTick(() => { measure(); resetCrop(); });
};

const resolveMime = () => {
  const m = (props.mimeType || '').toLowerCase();
  return m === 'image/png' || m === 'image/jpeg' || m === 'image/webp' ? m : 'image/jpeg';
};

const apply = async () => {
  if (!ready.value || busy.value) return;
  busy.value = true;
  try {
    measure();
    const s = natScale.value;
    const sx = Math.max(0, (crop.x - imageRect.x) * s);
    const sy = Math.max(0, (crop.y - imageRect.y) * s);
    const sw = Math.min(nat.w - sx, crop.w * s);
    const sh = Math.min(nat.h - sy, crop.h * s);

    const outW = Math.max(1, Math.round(Math.min(props.outputWidth, sw)));
    const outH = Math.max(1, Math.round(ratio.value ? outW / ratio.value : outW * (sh / sw)));

    const canvas = document.createElement('canvas');
    canvas.width = outW;
    canvas.height = outH;
    const ctx = canvas.getContext('2d');
    ctx.imageSmoothingQuality = 'high';

    const mime = resolveMime();
    if (mime === 'image/jpeg') {
      ctx.fillStyle = '#ffffff';
      ctx.fillRect(0, 0, outW, outH);
    }

    const source = new Image();
    source.src = props.src;
    if (!source.complete) {
      await new Promise((res, rej) => { source.onload = res; source.onerror = rej; });
    }
    ctx.drawImage(source, sx, sy, sw, sh, 0, 0, outW, outH);

    const blob = await new Promise((res) => canvas.toBlob(res, mime, props.quality));
    if (!blob) return;
    const ext = mime === 'image/png' ? 'png' : mime === 'image/webp' ? 'webp' : 'jpg';
    const base = (props.fileName || 'image').replace(/\.[^.]+$/, '');
    emit('confirm', new File([blob], `${base}-cropped.${ext}`, { type: mime }));
  } finally {
    busy.value = false;
  }
};

const cancel = () => emit('cancel');
const onKey = (e) => { if (e.key === 'Escape') cancel(); };
const onResize = () => { measure(); clampCrop(); };

watch(
  () => props.open,
  async (isOpen) => {
    if (isOpen) {
      ready.value = false;
      document.body.style.overflow = 'hidden';
      window.addEventListener('keydown', onKey);
      window.addEventListener('resize', onResize);
      await nextTick();
      if (img.value?.complete && img.value.naturalWidth) onImgLoad();
    } else {
      document.body.style.overflow = '';
      window.removeEventListener('keydown', onKey);
      window.removeEventListener('resize', onResize);
    }
  },
  { immediate: true }
);

onBeforeUnmount(() => {
  document.body.style.overflow = '';
  window.removeEventListener('keydown', onKey);
  window.removeEventListener('resize', onResize);
});
</script>
