<template>
  <div class="guest-card-flip-wrapper" @click="handleCardClick">
    <div class="card-flip-container" :style="{ aspectRatio: '1063 / 650' }">
      <div class="card-flip-inner" :class="{ flipped: isFlipped }">
        <div class="card-face card-front">
          <img
            v-if="generatedImageUrl"
            :src="generatedImageUrl"
            :alt="mode === 'full' ? 'Full Membership Card' : 'Minimal Membership Card'"
            class="guest-card-img"
          />
          <canvas
            v-else
            ref="canvasRef"
            width="1063"
            height="650"
            class="guest-card-canvas"
          ></canvas>
        </div>
        <div class="card-face card-back">
          <img
            :src="backSideSrc"
            alt="Card Back Side"
            class="guest-card-img"
          />
        </div>
      </div>
      <!-- Tap-to-expand icon (mobile only) -->
      <div class="card-expand-icon" @click.stop="handleCardClick">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="15 3 21 3 21 9"></polyline>
          <polyline points="9 21 3 21 3 15"></polyline>
          <line x1="21" y1="3" x2="14" y2="10"></line>
          <line x1="3" y1="21" x2="10" y2="14"></line>
        </svg>
      </div>
    </div>
    <button
      type="button"
      class="card-flip-btn"
      @click.stop="toggleFlip"
    >
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flip-icon" :class="{ 'rotated': isFlipped }">
        <polyline points="17 1 21 5 17 9"></polyline>
        <path d="M3 11V9a4 4 0 0 1 4-4h14"></path>
        <polyline points="7 23 3 19 7 15"></polyline>
        <path d="M21 13v2a4 4 0 0 1-4 4H3"></path>
      </svg>
      {{ isFlipped ? (tFront || 'Front') : (tBack || 'Back') }}
    </button>
    <button
      type="button"
      class="card-flip-btn"
      @click.stop="downloadCard"
    >
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
        <polyline points="7 10 12 15 17 10"/>
        <line x1="12" y1="15" x2="12" y2="3"/>
      </svg>
      {{ tDownload || 'Download' }}
    </button>

    <!-- Fullscreen overlay -->
    <Teleport to="body">
      <div v-if="showFullscreen" class="card-fullscreen-overlay" @click.self="closeFullscreen">
        <button class="card-fs-close" @click="closeFullscreen" :aria-label="t.close || 'Close'">
          <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 6L6 18"></path>
            <path d="M6 6l12 12"></path>
          </svg>
        </button>

        <div class="card-fs-container" :class="{ 'is-landscape': isLandscapeOrientation }">
          <div
            class="card-flip-container card-fs-flip"
            :class="{ 'fs-dragging': dragging }"
            :style="[{ aspectRatio: '1063 / 650' }, fsTransformStyle]"
            @mousedown="onDragStart"
            @touchstart.prevent="onDragStart"
          >
            <div class="card-flip-inner" :class="{ flipped: isFlipped }">
              <div class="card-face card-front">
                <img
                  v-if="generatedImageUrl"
                  :src="generatedImageUrl"
                  class="guest-card-img"
                  alt="Card"
                />
                <canvas
                  v-else
                  ref="fsCanvasRef"
                  width="1063"
                  height="650"
                  class="guest-card-canvas"
                ></canvas>
              </div>
              <div class="card-face card-back">
                <img
                  :src="backSideSrc"
                  alt="Card Back Side"
                  class="guest-card-img"
                />
              </div>
            </div>
          </div>
        </div>

        <div class="card-fs-controls">
          <button type="button" class="card-fs-flip-btn" @click="toggleFlip">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flip-icon" :class="{ 'rotated': isFlipped }">
              <polyline points="17 1 21 5 17 9"></polyline>
              <path d="M3 11V9a4 4 0 0 1 4-4h14"></path>
              <polyline points="7 23 3 19 7 15"></polyline>
              <path d="M21 13v2a4 4 0 0 1-4 4H3"></path>
            </svg>
            {{ isFlipped ? (tFront || 'Front') : (tBack || 'Back') }}
          </button>
          <div class="fs-zoom-wrapper">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="fs-zoom-icon"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <input type="range" v-model.number="fsZoom" min="0.5" max="2.5" step="0.05" class="fs-zoom-slider" />
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="fs-zoom-icon"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/><path d="M11 8v6"/><path d="M8 11h6"/></svg>
            <span class="fs-zoom-label">{{ Math.round(fsZoom * 100) }}%</span>
          </div>
          <div class="fs-zoom-wrapper">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="fs-zoom-icon"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
            <input type="range" v-model.number="fsRotate" min="-180" max="180" step="1" class="fs-zoom-slider" />
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="fs-zoom-icon"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
            <span class="fs-zoom-label">{{ fsRotate }}°</span>
          </div>
          <button type="button" class="card-fs-flip-btn" @click="fsResetTransform">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
            {{ tReset || 'Reset' }}
          </button>
          <button type="button" class="card-fs-flip-btn" @click="downloadCard">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
              <polyline points="7 10 12 15 17 10"/>
              <line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
            {{ tDownload || 'Download' }}
          </button>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch, nextTick } from "vue";
import { usePage } from "@inertiajs/vue3";
import QRCode from "qrcode";

const CW = 1063;
const CH = 650;
const DPR = typeof window !== 'undefined' ? Math.min(window.devicePixelRatio || 1, 3) : 2;
const BG_VERSION = "20260516b";
const BG_FULL = `/card-template_pure.jpg?v=${BG_VERSION}`;
const BG_MINIMAL = `/card-template_white.jpg?v=${BG_VERSION}`;
const FONTS_HREF = "https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap";

const BACK_SIDE_SRC = "/card-template_back_side.png";

const DEFAULT_FULL = {
  name:    { x: 796, y: 337, scale: 1 },
  photo:   { x: 690, y: 94,  scale: 1 },
  fields:  { x: 572, y: 413, scale: 1 },
  qr:      { x: 376, y: 410, scale: 0.65 },
  partner: { x: -87, y: 361, scale: 2.65 },
};

const DEFAULT_FULL_EMPTY = {
  name:    { x: 796, y: 337, scale: 1 },
  photo:   { x: 690, y: 94,  scale: 1 },
  fields:  { x: 779, y: 442, scale: 1.1 },
  qr:      { x: 376, y: 410, scale: 0.65 },
  partner: { x: 95,  y: 405, scale: 1.16 },
};

const DEFAULT_MINIMAL = {
  qr:      { x: 704, y: 107, scale: 0.6 },
  fields:  { x: 756, y: 368, scale: 1.2 },
  partner: { x: -165, y: 313, scale: 2.65 },
};

const DEFAULT_COLORS = {
  full:    { name: "#000000", fields: "#000000" },
  minimal: { name: "#1a1a2e", fields: "#1a1a2e" },
};

const props = defineProps({
  membership: { type: Object, required: true },
  partner: { type: Object, default: null },
  cardLayout: { type: Object, default: null },
  mode: { type: String, required: true, validator: (v) => ["full", "minimal"].includes(v) },
  translations: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["click"]);

const page = usePage();
const t = computed(() => {
  if (Object.keys(props.translations).length > 0) return props.translations;
  return page.props.translations?.home?.membership_page || {};
});
const tFront = computed(() => t.value.front || "Front");
const tBack = computed(() => t.value.back || "Back");
const tDownload = computed(() => t.value.download || "Download");
const tReset = computed(() => t.value.reset || "Reset");

const canvasRef = ref(null);
const fsCanvasRef = ref(null);
const generatedImageUrl = ref(null);
const isFlipped = ref(false);
const showFullscreen = ref(false);
const fsZoom = ref(1);
const fsRotate = ref(0);
const fsPanX = ref(0);
const fsPanY = ref(0);
const dragging = ref(false);
let dragStartX = 0;
let dragStartY = 0;
let panStartX = 0;
let panStartY = 0;

const fsTransformStyle = computed(() => ({
  transform: `translate(${fsPanX.value}px, ${fsPanY.value}px) scale(${fsZoom.value}) rotate(${fsRotate.value}deg)`,
  transformOrigin: 'center center',
  transition: dragging.value ? 'none' : 'transform 0.15s ease',
}));

function onDragStart(e) {
  const ev = e.touches ? e.touches[0] : e;
  dragging.value = true;
  dragStartX = ev.clientX;
  dragStartY = ev.clientY;
  panStartX = fsPanX.value;
  panStartY = fsPanY.value;
  document.addEventListener('mousemove', onDragMove);
  document.addEventListener('mouseup', onDragEnd);
  document.addEventListener('touchmove', onDragMove, { passive: false });
  document.addEventListener('touchend', onDragEnd);
}
function onDragMove(e) {
  if (!dragging.value) return;
  e.preventDefault();
  const ev = e.touches ? e.touches[0] : e;
  fsPanX.value = panStartX + (ev.clientX - dragStartX);
  fsPanY.value = panStartY + (ev.clientY - dragStartY);
}
function onDragEnd() {
  dragging.value = false;
  document.removeEventListener('mousemove', onDragMove);
  document.removeEventListener('mouseup', onDragEnd);
  document.removeEventListener('touchmove', onDragMove);
  document.removeEventListener('touchend', onDragEnd);
}
function fsResetTransform() {
  fsZoom.value = 1;
  fsRotate.value = 0;
  fsPanX.value = 0;
  fsPanY.value = 0;
}

const backSideSrc = BACK_SIDE_SRC;

const isLandscapeOrientation = computed(() => {
  if (typeof window === 'undefined') return false;
  return window.innerWidth > window.innerHeight;
});

function toggleFlip() {
  isFlipped.value = !isFlipped.value;
}

function handleCardClick() {
  openFullscreen();
}

function openFullscreen() {
  showFullscreen.value = true;
  document.body.style.overflow = 'hidden';
  nextTick(() => {
    if (!generatedImageUrl.value && fsCanvasRef.value) {
      renderFullscreenCard();
    }
  });
}

function closeFullscreen() {
  showFullscreen.value = false;
  document.body.style.overflow = '';
}

watch(showFullscreen, (val) => {
  if (!val) {
    document.body.style.overflow = '';
  }
});

let bgImg = null;
let partnerImg = null;
let qrCanvas = null;
let photoImg = null;

function getDefault(key) {
  if (props.mode === "minimal") {
    return DEFAULT_MINIMAL[key] ? { ...DEFAULT_MINIMAL[key] } : null;
  }
  const emptyMode = !props.membership.job_title && !props.membership.company_name;
  const d = emptyMode ? DEFAULT_FULL_EMPTY : DEFAULT_FULL;
  return d[key] ? { ...d[key] } : null;
}

function getLayout(key) {
  if (props.mode === "minimal") {
    const layout = props.cardLayout;
    if (layout) {
      const map = { partner: "partner", qr: "qr", fields: "fields" };
      const prefix = map[key];
      if (prefix && layout[prefix + "_x"] != null && layout[prefix + "_y"] != null) {
        return {
          x: layout[prefix + "_x"],
          y: layout[prefix + "_y"],
          scale: layout[prefix + "_scale"] ?? getDefault(key).scale,
        };
      }
    }
    return getDefault(key);
  }

  const layout = props.cardLayout;
  if (layout) {
    const map = { partner: "partner", photo: "photo", name: "name", fields: "fields", qr: "qr" };
    const prefix = map[key];
    if (prefix && layout[prefix + "_x"] != null && layout[prefix + "_y"] != null) {
      return {
        x: layout[prefix + "_x"],
        y: layout[prefix + "_y"],
        scale: layout[prefix + "_scale"] ?? getDefault(key).scale,
      };
    }
  }

  if (key === "partner") {
    if (props.partner && props.partner.card_x != null && props.partner.card_y != null) {
      return {
        x: Number(props.partner.card_x),
        y: Number(props.partner.card_y),
        scale: props.partner.card_scale ?? getDefault(key).scale,
      };
    }
  }

  return getDefault(key);
}

function getColor(key) {
  const layout = props.cardLayout;
  const mode = props.mode;
  if (layout && layout[key + "_color"]) {
    return layout[key + "_color"];
  }
  return DEFAULT_COLORS[mode][key] || "#000000";
}

function loadImage(src) {
  return new Promise((resolve) => {
    const img = new Image();
    img.crossOrigin = "anonymous";
    img.onload = () => resolve(img);
    img.onerror = () => resolve(null);
    img.src = src;
  });
}

function ensureFont() {
  return new Promise((resolve) => {
    if (document.querySelector(`link[href="${FONTS_HREF}"]`)) {
      resolve();
      return;
    }
    const link = document.createElement("link");
    link.rel = "stylesheet";
    link.href = FONTS_HREF;
    link.onload = () => resolve();
    link.onerror = () => resolve();
    document.head.appendChild(link);
  }).then(() => {
    if (document.fonts && document.fonts.load) {
      return Promise.all([
        document.fonts.load("700 48px Tajawal"),
        document.fonts.load("400 48px Tajawal"),
      ]).catch(() => {});
    }
    return null;
  });
}

function getCtx(refOverride) {
  return (refOverride || canvasRef.value)?.getContext("2d");
}

function paintMinimal(refOverride) {
  const ctx = getCtx(refOverride);
  if (!ctx) return;

  ctx.drawImage(bgImg, 0, 0, CW, CH);

  if (partnerImg) {
    const prl = getLayout("partner");
    const maxW = 260 * prl.scale;
    const maxH = 260 * prl.scale;
    const ar = partnerImg.width / partnerImg.height;
    let dw = maxW;
    let dh = maxH;
    if (ar > 1) dh = dw / ar;
    else dw = dh * ar;
    ctx.drawImage(partnerImg, prl.x, prl.y, dw, dh);
  }

  if (qrCanvas) {
    const ql = getLayout("qr");
    const qs = 190 * ql.scale;
    const pad = 14 * ql.scale;
    ctx.fillStyle = "#fff";
    ctx.fillRect(ql.x - pad, ql.y - pad, qs + pad * 2, qs + pad * 2);
    ctx.drawImage(qrCanvas, ql.x, ql.y, qs, qs);
  }

  const policy = props.membership.membership_number;
  if (policy) {
    const fl = getLayout("fields");
    const fSize = 34 * fl.scale;
    ctx.font = "bold " + fSize + "px Tajawal,Arial";
    ctx.fillStyle = getColor("fields");
    ctx.textAlign = "center";
    ctx.direction = "ltr";
    ctx.fillText(String(policy), fl.x, fl.y);
  }
}

function paintFull(refOverride) {
  const ctx = getCtx(refOverride);
  if (!ctx) return;

  ctx.drawImage(bgImg, 0, 0, CW, CH);
  const emptyMode = !props.membership.job_title && !props.membership.company_name;

  if (partnerImg) {
    const prl = getLayout("partner");
    const maxW = 170 * prl.scale;
    const maxH = 170 * prl.scale;
    const ar = partnerImg.width / partnerImg.height;
    let dw = maxW;
    let dh = maxH;
    if (ar > 1) dh = dw / ar;
    else dw = dh * ar;
    ctx.drawImage(partnerImg, prl.x, prl.y, dw, dh);
  }

  if (photoImg) {
    const pl = getLayout("photo");
    const pw = 178 * pl.scale;
    const ph = 178 * pl.scale;
    const px = pl.x;
    const py = pl.y;
    ctx.save();
    const r = 10 * pl.scale;
    ctx.beginPath();
    ctx.moveTo(px + r, py);
    ctx.lineTo(px + pw - r, py);
    ctx.quadraticCurveTo(px + pw, py, px + pw, py + r);
    ctx.lineTo(px + pw, py + ph - r);
    ctx.quadraticCurveTo(px + pw, py + ph, px + pw - r, py + ph);
    ctx.lineTo(px + r, py + ph);
    ctx.quadraticCurveTo(px, py + ph, px, py + ph - r);
    ctx.lineTo(px, py + r);
    ctx.quadraticCurveTo(px, py, px + r, py);
    ctx.closePath();
    ctx.clip();
    const ar2 = photoImg.width / photoImg.height;
    const tar = pw / ph;
    let sx = 0, sy = 0, sw = photoImg.width, sh = photoImg.height;
    if (ar2 > tar) {
      sw = sh * tar;
      sx = (photoImg.width - sw) / 2;
    } else {
      sh = sw / tar;
      sy = (photoImg.height - sh) / 2;
    }
    ctx.drawImage(photoImg, sx, sy, sw, sh, px, py, pw, ph);
    ctx.restore();
  }

  const nl = getLayout("name");
  const name = props.membership.user?.name;
  if (name) {
    const nameFontSize = (emptyMode ? 46 : 34) * nl.scale;
    const lineYOffset = (emptyMode ? 15 : 30) * nl.scale;
    ctx.textAlign = "center";
    ctx.direction = "rtl";
    ctx.font = "bold " + nameFontSize + "px Tajawal,Arial";
    ctx.fillStyle = getColor("name");
    ctx.fillText(name, nl.x, nl.y);
    ctx.strokeStyle = "rgba(196,160,88,0.4)";
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(nl.x - 175 * nl.scale, nl.y + lineYOffset);
    ctx.lineTo(nl.x + 175 * nl.scale, nl.y + lineYOffset);
    ctx.stroke();
  }

  const fl = getLayout("fields");
  const policy = props.membership.membership_number;
  const member = props.membership.job_title;
  const status = props.membership.company_name;
  const valid = props.membership.expiration_date_formatted;

  if (emptyMode) {
    const items = [
      { label: "Policy no / ", val: policy },
      { label: "Valid to / ", val: valid },
    ].filter((f) => !!f.val);
    const fSize = 34 * fl.scale;
    ctx.font = "bold " + fSize + "px Tajawal,Arial";
    ctx.fillStyle = getColor("fields");
    ctx.textAlign = "center";
    ctx.direction = "ltr";
    const lineH = 75 * fl.scale;
    items.forEach((f, i) => {
      const full = f.label + f.val;
      ctx.fillText(full, fl.x, fl.y + i * lineH);
    });
  } else {
    const items = [
      { label: "Policy no / ", val: policy },
      { label: "Member / ", val: member },
      { label: "Status / ", val: status },
      { label: "Valid to / ", val: valid },
    ].filter((f) => !!f.val);
    const fSize = 32 * fl.scale;
    ctx.font = fSize + "px Tajawal,Arial";
    ctx.fillStyle = getColor("fields");
    ctx.textAlign = "left";
    ctx.direction = "ltr";
    const lineH = 47 * fl.scale;
    items.forEach((f, i) => {
      const t = f.label + f.val;
      ctx.fillText(t, fl.x, fl.y + i * lineH);
    });
  }

  if (qrCanvas) {
    const ql = getLayout("qr");
    const qs = 190 * ql.scale;
    const pad = 14 * ql.scale;
    ctx.fillStyle = "#fff";
    ctx.fillRect(ql.x - pad, ql.y - pad, qs + pad * 2, qs + pad * 2);
    ctx.drawImage(qrCanvas, ql.x, ql.y, qs, qs);
  }
}

async function generateQR() {
  const qrUrl = window.location.origin + "/membership/" + props.membership.slug;
  const qrSize = 200 * DPR;
  const off = document.createElement("canvas");
  off.width = qrSize;
  off.height = qrSize;
  await QRCode.toCanvas(off, qrUrl, {
    width: qrSize,
    margin: 0,
    color: { dark: "#000000", light: "#ffffff" },
    errorCorrectionLevel: "M",
  });
  qrCanvas = off;
}

async function renderCard() {
  if (!canvasRef.value) return;

  await ensureFont();
  await generateQR();

  const canvas = canvasRef.value;
  canvas.width = CW * DPR;
  canvas.height = CH * DPR;
  const ctx = canvas.getContext("2d");
  ctx.scale(DPR, DPR);

  bgImg = await loadImage(props.mode === "minimal" ? BG_MINIMAL : BG_FULL);
  if (!bgImg) {
    ctx.fillStyle = "#ffffff";
    ctx.fillRect(0, 0, CW, CH);
  }

  if (props.partner?.image) {
    partnerImg = await loadImage(props.partner.image);
  }

  if (props.mode === "full" && props.membership.user?.avatar_url) {
    photoImg = await loadImage(props.membership.user.avatar_url);
  }

  if (props.mode === "minimal") {
    paintMinimal();
  } else {
    paintFull();
  }
}

onMounted(() => {
  const layout = props.cardLayout;
  if (layout && layout.generated_image_url) {
    generatedImageUrl.value = layout.generated_image_url;
    return;
  }
  renderCard();
});

async function renderFullscreenCard() {
  const canvas = fsCanvasRef.value;
  if (!canvas) return;

  await ensureFont();
  await generateQR();

  canvas.width = CW * DPR;
  canvas.height = CH * DPR;

  const bg = await loadImage(props.mode === "minimal" ? BG_MINIMAL : BG_FULL);
  const ctx = canvas.getContext("2d");
  ctx.scale(DPR, DPR);
  if (!bg) {
    ctx.fillStyle = "#ffffff";
    ctx.fillRect(0, 0, CW, CH);
  }

  const partner = props.partner?.image ? await loadImage(props.partner.image) : null;
  const photo = props.mode === "full" && props.membership.user?.avatar_url
    ? await loadImage(props.membership.user.avatar_url)
    : null;

  const tempBgImg = bgImg;
  const tempPartnerImg = partnerImg;
  const tempPhotoImg = photoImg;
  const tempQrCanvas = qrCanvas;

  bgImg = bg;
  partnerImg = partner;
  photoImg = photo;

  if (props.mode === "minimal") {
    paintMinimal(canvas);
  } else {
    paintFull(canvas);
  }

  bgImg = tempBgImg;
  partnerImg = tempPartnerImg;
  photoImg = tempPhotoImg;
  qrCanvas = tempQrCanvas;
}

function downloadCard() {
  const prefix = props.membership.membership_number || 'membership-card';
  const modeLabel = props.mode === 'full' ? 'full' : 'minimal';

  if (generatedImageUrl.value) {
    const a = document.createElement('a');
    a.href = generatedImageUrl.value;
    a.download = `${prefix}-${modeLabel}.png`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    return;
  }

  const canvas = canvasRef.value;
  if (!canvas) return;
  const link = document.createElement('a');
  link.download = `${prefix}-${modeLabel}.png`;
  link.href = canvas.toDataURL('image/png');
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}
</script>

<style scoped>
.guest-card-flip-wrapper {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  width: 100%;
  cursor: pointer;
}

.card-flip-container {
  width: 100%;
  max-width: 500px;
  perspective: 1200px;
}

.card-flip-inner {
  position: relative;
  width: 100%;
  height: 100%;
  transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
  transform-style: preserve-3d;
}

.card-flip-inner.flipped {
  transform: rotateY(180deg);
}

.card-face {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  backface-visibility: hidden;
  -webkit-backface-visibility: hidden;
  -webkit-transform: translate3d(0, 0, 0);
  transform: translate3d(0, 0, 0);
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 8px 40px rgba(0, 0, 0, 0.6);
}

.card-front {
  z-index: 2;
}

.card-back {
  -webkit-transform: rotateY(180deg) translate3d(0, 0, 0);
  transform: rotateY(180deg) translate3d(0, 0, 0);
}

.guest-card-img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  display: block;
}

.guest-card-canvas {
  width: 100%;
  height: 100%;
  object-fit: contain;
  display: block;
}

.card-flip-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 8px;
  border: 1px solid rgba(184, 148, 90, 0.4);
  background: rgba(184, 148, 90, 0.1);
  color: #D4AF6E;
  font-size: 13px;
  font-family: inherit;
  cursor: pointer;
  transition: background 0.2s, color 0.2s;
}

.card-flip-btn:hover {
  background: rgba(184, 148, 90, 0.25);
  color: #fff;
}

.flip-icon {
  transition: transform 0.3s;
}

.flip-icon.rotated {
  transform: rotate(180deg);
}

/* Tap-to-expand icon — visible on mobile only */
.card-expand-icon {
  display: none;
  position: absolute;
  top: 8px;
  right: 8px;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: rgba(0, 0, 0, 0.55);
  color: #fff;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  z-index: 10;
  backdrop-filter: blur(4px);
  transition: opacity 0.2s;
  opacity: 0.85;
}

.card-expand-icon:hover {
  opacity: 1;
}

.card-flip-container:hover .card-expand-icon {
  opacity: 1;
}

.card-expand-icon {
  display: flex;
}

/* Fullscreen overlay */
.card-fullscreen-overlay {
  position: fixed;
  inset: 0;
  z-index: 9999;
  background: rgba(10, 10, 10, 0.95);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 16px;
  animation: fsFadeIn 0.25s ease;
}

@keyframes fsFadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.card-fs-close {
  position: absolute;
  top: 12px;
  right: 12px;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  border: none;
  background: rgba(255, 255, 255, 0.15);
  color: #fff;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 10;
  transition: background 0.2s;
}

.card-fs-close:hover {
  background: rgba(255, 255, 255, 0.3);
}

/* Fullscreen zoom slider */
.fs-zoom-wrapper {
  display: flex;
  align-items: center;
  gap: 8px;
}
.fs-zoom-icon {
  color: rgba(255, 255, 255, 0.6);
  flex-shrink: 0;
}
.fs-zoom-slider {
  -webkit-appearance: none;
  appearance: none;
  width: 120px;
  max-width: 30vw;
  height: 5px;
  border-radius: 3px;
  background: linear-gradient(to right, rgba(255,255,255,0.2), #D4AF6E);
  outline: none;
  cursor: pointer;
}
.fs-zoom-slider::-webkit-slider-thumb {
  -webkit-appearance: none;
  appearance: none;
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: #D4AF6E;
  border: 2px solid #fff;
  box-shadow: 0 2px 6px rgba(0,0,0,0.3);
  cursor: pointer;
  transition: transform 0.15s ease;
}
.fs-zoom-slider::-webkit-slider-thumb:hover {
  transform: scale(1.2);
}
.fs-zoom-slider::-moz-range-thumb {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: #D4AF6E;
  border: 2px solid #fff;
  box-shadow: 0 2px 6px rgba(0,0,0,0.3);
  cursor: pointer;
}
.fs-zoom-label {
  color: rgba(255, 255, 255, 0.7);
  font-size: 12px;
  min-width: 36px;
  text-align: center;
  font-variant-numeric: tabular-nums;
}

.card-fs-container {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  max-height: calc(100vh - 100px);
}

/* Portrait mobile: rotate card to landscape to fill screen width */
.card-fs-container:not(.is-landscape) {
  max-width: 100vw;
}

.card-fs-container:not(.is-landscape) .card-fs-flip {
  transform: rotate(90deg);
  width: 100vh;
  max-width: none;
}

.card-fs-container.is-landscape .card-fs-flip {
  max-width: 95vw;
  max-height: 70vh;
}

.card-fs-flip {
  width: 100%;
  max-width: 95vw;
  max-height: 80vh;
  transition: transform 0.3s ease;
}

.card-fs-flip .card-flip-container {
  max-width: none;
  cursor: grab;
  user-select: none;
  -webkit-user-select: none;
}
.card-fs-flip.fs-dragging .card-flip-container {
  cursor: grabbing;
}

.card-fs-controls {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding-bottom: 8px;
  flex-wrap: wrap;
}

.card-fs-flip-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 22px;
  border-radius: 10px;
  border: 1px solid rgba(255, 255, 255, 0.3);
  background: rgba(255, 255, 255, 0.1);
  color: #D4AF6E;
  font-size: 15px;
  font-family: inherit;
  cursor: pointer;
  transition: background 0.2s, color 0.2s;
}

.card-fs-flip-btn:hover {
  background: rgba(255, 255, 255, 0.2);
  color: #fff;
}
</style>