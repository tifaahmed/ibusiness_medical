<template>
  <div class="ash-card-host" :style="hostStyle">
    <canvas ref="canvasRef" class="ash-card-canvas" :width="CARD_W" :height="CARD_H"></canvas>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, shallowRef } from 'vue';
import { renderCardCanvas, CARD_W, CARD_H } from './cardRenderer.js';
import { publicMembershipBaseUrl } from '@/composables/usePublicMembershipUrl.js';

const props = defineProps({
  membership: { type: Object, required: true },
  template: { type: Object, default: null },
  partner: { type: Object, default: null },
  publicBaseUrl: { type: String, default: () => publicMembershipBaseUrl() },
  overrides: { type: Object, default: null },
  contact: { type: Object, default: null },
  /**
   * Draw the template's own partner_logo when no partner is given — for the
   * layout editor, which previews the design itself rather than a real card.
   */
  placeholderPartnerLogo: { type: Boolean, default: false },
  /**
   * Explicit field values that win over the template's own — the single-card
   * generator, where an admin types each field by hand.
   */
  values: { type: Object, default: null },
  /**
   * Any CSS width. Defaults to the 9cm print width so batch previews stay
   * true-to-print; pass '100%' (or a px value) to show the card larger, e.g.
   * in the template layout editor. Height always follows the rendered card's
   * own aspect ratio, so the preview is never stretched.
   */
  displayWidth: { type: String, default: '9cm' },
});

const emit = defineEmits(['rendered']);

const canvasRef = ref(null);
// shallowRef so Vue doesn't deep-watch the canvas pixel buffer.
const renderedCanvas = shallowRef(null);
const aspect = ref(CARD_W / CARD_H);

const hostStyle = computed(() => ({
  width: props.displayWidth,
  aspectRatio: String(aspect.value),
}));

async function paint() {
  if (!canvasRef.value) return;
  const off = await renderCardCanvas({
    membership: props.membership,
    template: props.template,
    partner: props.partner,
    publicBaseUrl: props.publicBaseUrl,
    overrides: props.overrides,
    contact: props.contact,
    placeholderPartnerLogo: props.placeholderPartnerLogo,
    values: props.values,
  });
  // The card's height follows the template artwork's aspect ratio, so match the
  // visible canvas to whatever was rendered rather than assuming CARD_H.
  canvasRef.value.width = off.width;
  canvasRef.value.height = off.height;
  aspect.value = off.width / off.height;
  const ctx = canvasRef.value.getContext('2d');
  ctx.clearRect(0, 0, off.width, off.height);
  ctx.drawImage(off, 0, 0);
  renderedCanvas.value = off;
  emit('rendered', { membership: props.membership, canvas: off });
}

onMounted(paint);
watch(
  () => [
    props.membership?.slug,
    props.membership?.membership_number,
    props.template?.id,
    props.template && JSON.stringify(props.template.layout),
    props.template && JSON.stringify(props.template.sample_data),
    props.partner?.id,
    props.partner?.image,
    props.overrides && JSON.stringify(props.overrides),
    props.contact && JSON.stringify(props.contact),
    props.placeholderPartnerLogo,
    props.values && JSON.stringify(props.values),
  ],
  paint,
);
</script>

<style scoped>
.ash-card-host {
  /* Canvas keeps the artwork's native resolution (so toDataURL() stays
     print-ready); only the on-screen box is scaled here. */
  max-width: 100%;
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
