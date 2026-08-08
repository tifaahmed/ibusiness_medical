<template>
  <div class="ash-card-host">
    <canvas
      ref="canvasRef"
      class="ash-card-canvas"
      :width="1063"
      :height="650"
    ></canvas>
  </div>
</template>

<script setup>
import { ref, onMounted, watch, shallowRef } from 'vue';
import { renderCardCanvas } from './cardRenderer.js';

const props = defineProps({
  membership: { type: Object, required: true },
  partner: { type: Object, default: null },
  publicBaseUrl: { type: String, default: () => window.location.origin },
  overrides: { type: Object, default: null },
});

const emit = defineEmits(['rendered']);

const canvasRef = ref(null);
// shallowRef so Vue doesn't deep-watch the canvas pixel buffer.
const renderedCanvas = shallowRef(null);

async function paint() {
  if (!canvasRef.value) return;
  const off = await renderCardCanvas({
    membership: props.membership,
    partner: props.partner,
    publicBaseUrl: props.publicBaseUrl,
    overrides: props.overrides,
  });
  const ctx = canvasRef.value.getContext('2d');
  ctx.clearRect(0, 0, canvasRef.value.width, canvasRef.value.height);
  ctx.drawImage(off, 0, 0);
  renderedCanvas.value = off;
  emit('rendered', { membership: props.membership, canvas: off });
}

onMounted(paint);
watch(
  () => [
    props.membership?.slug,
    props.membership?.membership_number,
    props.partner?.id,
    props.partner?.image,
    props.overrides && JSON.stringify(props.overrides),
  ],
  paint,
);
</script>

<style scoped>
.ash-card-host {
  /* Print size: 9cm × 5.6cm. Canvas keeps native 1063×650 resolution so
     toDataURL() output is print-ready; we only scale visually here. */
  width: 9cm;
  height: 5.6cm;
  border-radius: 0.3cm;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  background: #fff;
}
.ash-card-canvas {
  width: 100%;
  height: 100%;
  display: block;
}
</style>
