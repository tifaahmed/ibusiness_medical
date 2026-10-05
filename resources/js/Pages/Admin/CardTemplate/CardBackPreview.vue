<template>
  <!-- Mirrors CardBackRenderer.php box for box (same fractions of the card, same
       px-on-700 font sizes), so the stage reads as the finished back. With
       `editable`, elements can be dragged and resized like the front editor. -->
  <div
    ref="stageRef"
    class="back-preview relative w-full select-none overflow-hidden bg-white"
    :style="{ aspectRatio: '1579 / 996' }"
  >
    <img v-if="background" :src="background" alt="" class="pointer-events-none absolute inset-0 h-full w-full" style="object-fit: fill" />

    <template v-for="key in ELEMENTS" :key="key">
      <div
        v-if="config[key]?.visible"
        class="absolute"
        :class="editable ? (key === selected ? 'z-10 cursor-move border-2 border-primary bg-primary/10' : 'cursor-move border-2 border-sky-400/70 bg-sky-400/5 hover:bg-sky-400/15') : ''"
        :style="boxStyle(key)"
        @pointerdown="editable && startDrag($event, key, 'move')"
      >
        <span v-if="editable" class="absolute -top-4 left-0 whitespace-nowrap text-[9px] font-medium text-sky-700">{{ LABELS[key] }}</span>

        <img v-if="key === 'logo' && logo" :src="logo" alt="" class="pointer-events-none h-full w-full object-contain" />

        <div
          v-else-if="key === 'qrcode'"
          class="pointer-events-none flex h-full w-full items-center justify-center bg-white"
        >
          <img v-if="qrShown" :src="qrShown" alt="" style="width: 84%; height: 84%" />
        </div>

        <div
          v-else-if="TEXTS.includes(key) && (config[key].text || '').trim()"
          class="pointer-events-none flex h-full w-full items-center overflow-hidden whitespace-pre font-bold"
          :style="textStyle(key)"
        >{{ config[key].text }}</div>

        <span
          v-if="editable"
          class="absolute -bottom-1 -right-1 h-3 w-3 cursor-nwse-resize rounded-sm bg-primary"
          @pointerdown.stop="startDrag($event, key, 'resize')"
        ></span>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import QRCode from 'qrcode';

const ELEMENTS = ['logo', 'slogan', 'title', 'website', 'qrcode'];
const TEXTS = ['slogan', 'title', 'website'];
const LABELS = { logo: 'Logo', slogan: 'Slogan', title: 'Title', website: 'Website', qrcode: 'QR code' };
const EDITOR_WIDTH = 700;

const props = defineProps({
  config: { type: Object, required: true },
  background: { type: String, default: '' },
  logo: { type: String, default: '' },
  qrUpload: { type: String, default: '' },
  editable: { type: Boolean, default: false },
  selected: { type: String, default: '' },
});
const emit = defineEmits(['update:selected']);

const stageRef = ref(null);

const boxStyle = (key) => {
  const f = props.config[key];
  return { left: `${f.x * 100}%`, top: `${f.y * 100}%`, width: `${f.width * 100}%`, height: `${f.height * 100}%` };
};

const textStyle = (key) => {
  const f = props.config[key];
  const dir = f.direction || 'center';
  return {
    // font_size is px on a 700px card; cqw keeps that ratio at any preview width.
    fontSize: `${((f.font_size || 20) / EDITOR_WIDTH) * 100}cqw`,
    fontFamily: 'Tajawal, Arial, sans-serif',
    color: f.color || '#000000',
    direction: dir === 'rtl' ? 'rtl' : 'ltr',
    justifyContent: dir === 'rtl' ? 'flex-end' : (dir === 'center' ? 'center' : 'flex-start'),
  };
};

const clamp = (value, min = -1) => Math.min(2, Math.max(min, Number(value.toFixed(4))));

function startDrag(event, key, mode) {
  emit('update:selected', key);
  const stage = stageRef.value;
  if (!stage) return;
  event.preventDefault();
  const rect = stage.getBoundingClientRect();
  const f = props.config[key];
  // Measured from where the drag began, so moves never accumulate error.
  const origin = { px: event.clientX, py: event.clientY, x: f.x, y: f.y, width: f.width, height: f.height };

  const onMove = (e) => {
    const dx = (e.clientX - origin.px) / rect.width;
    const dy = (e.clientY - origin.py) / rect.height;
    const target = props.config[key];
    if (mode === 'move') {
      target.x = clamp(origin.x + dx);
      target.y = clamp(origin.y + dy);
    } else {
      target.width = clamp(origin.width + dx, 0.01);
      target.height = clamp(origin.height + dy, 0.01);
    }
  };
  const onUp = () => {
    window.removeEventListener('pointermove', onMove);
    window.removeEventListener('pointerup', onUp);
  };
  window.addEventListener('pointermove', onMove);
  window.addEventListener('pointerup', onUp);
}

const qrImage = ref('');
const qrShown = computed(() => (props.config.qrcode?.mode === 'image' ? props.qrUpload : qrImage.value));
watch(
  () => [props.config.qrcode?.visible, props.config.qrcode?.value],
  async ([visible, value]) => {
    const text = String(value || '').trim();
    if (!visible || !text) { qrImage.value = ''; return; }
    try {
      qrImage.value = await QRCode.toDataURL(text, { margin: 0, width: 300, errorCorrectionLevel: 'M' });
    } catch {
      qrImage.value = '';
    }
  },
  { immediate: true },
);
</script>

<style scoped>
.back-preview { container-type: inline-size; }
</style>
