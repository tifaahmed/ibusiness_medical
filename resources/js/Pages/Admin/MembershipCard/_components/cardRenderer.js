// Renders a single membership card to an offscreen canvas using the same
// minimal-mode layout as resources/js/Pages/Admin/CardGenerator/Index.vue,
// so batch-generated cards match what /admin/card-generator produces in
// minimal mode (white template, partner logo + policy + QR, no name/photo).
//
// Canvas size and coordinates are taken verbatim from the existing renderer.

import QRCode from 'qrcode';

const CW = 1063;
const CH = 650;
const BG_VERSION = '20260516b';
const BG_MINIMAL = `/card-template_white.jpg?v=${BG_VERSION}`;
const FONTS_HREF = 'https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap';

export const DEFAULT_LAYOUT_MINIMAL = {
  qr: { x: 704, y: 107, scale: 0.6 },
  fields: { x: 756, y: 368, scale: 1.2 },
  partner: { x: -165, y: 313, scale: 2.65 },
};

let _bgPromise = null;
let _fontPromise = null;
const _partnerImgCache = new Map();

function ensureFontLoaded() {
  if (_fontPromise) return _fontPromise;
  _fontPromise = new Promise((resolve) => {
    if (document.querySelector(`link[href="${FONTS_HREF}"]`)) {
      resolve();
      return;
    }
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = FONTS_HREF;
    link.onload = () => resolve();
    link.onerror = () => resolve();
    document.head.appendChild(link);
  }).then(() => {
    if (document.fonts && document.fonts.load) {
      return Promise.all([
        document.fonts.load('700 48px Tajawal'),
        document.fonts.load('400 48px Tajawal'),
      ]).catch(() => {});
    }
    return null;
  });
  return _fontPromise;
}

function loadImage(src) {
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = () => resolve(img);
    img.onerror = (e) => reject(e);
    img.src = src;
  });
}

function ensureBg() {
  if (!_bgPromise) {
    _bgPromise = loadImage(BG_MINIMAL).catch(() => null);
  }
  return _bgPromise;
}

function loadPartnerImage(url) {
  if (!url) return Promise.resolve(null);
  if (_partnerImgCache.has(url)) return _partnerImgCache.get(url);
  const p = loadImage(url).catch(() => null);
  _partnerImgCache.set(url, p);
  return p;
}

function partnerLayoutFor(partner, override) {
  if (override) {
    return {
      x: Number(override.x),
      y: Number(override.y),
      scale: Number(override.scale),
    };
  }
  if (partner && partner.card_x != null && partner.card_y != null) {
    return {
      x: Number(partner.card_x),
      y: Number(partner.card_y),
      scale: partner.card_scale != null ? Number(partner.card_scale) : DEFAULT_LAYOUT_MINIMAL.partner.scale,
    };
  }
  return { ...DEFAULT_LAYOUT_MINIMAL.partner };
}

function resolveLayout(defaults, override) {
  if (!override) return { ...defaults };
  return {
    x: Number(override.x),
    y: Number(override.y),
    scale: Number(override.scale),
  };
}

/**
 * Render a single card to a freshly-allocated canvas. The canvas is
 * 1063×650 (the same pixel size the existing card-generator uses) so a
 * direct toDataURL() snapshot will be print-ready at 9×5.6cm.
 *
 * @param {object} opts
 * @param {{ membership_number: string, slug: string }} opts.membership
 * @param {object|null} opts.partner partner with image + card_x/card_y/card_scale
 * @param {string} opts.publicBaseUrl base for the QR code URL
 * @returns {Promise<HTMLCanvasElement>}
 */
export async function renderCardCanvas({ membership, partner = null, publicBaseUrl = window.location.origin, overrides = null }) {
  await ensureFontLoaded();

  const canvas = document.createElement('canvas');
  canvas.width = CW;
  canvas.height = CH;
  const ctx = canvas.getContext('2d');

  // Background — fall back to a white fill if the template fails to load.
  const bg = await ensureBg();
  if (bg) {
    ctx.drawImage(bg, 0, 0, CW, CH);
  } else {
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, CW, CH);
  }

  // Partner logo. Matches paintMinimal(): maxW = maxH = 260 * scale,
  // aspect-ratio preserved.
  if (partner && partner.image) {
    const img = await loadPartnerImage(partner.image);
    if (img) {
      const prl = partnerLayoutFor(partner, overrides?.partner);
      const maxW = 260 * prl.scale;
      const maxH = 260 * prl.scale;
      const ar = img.width / img.height;
      let dw = maxW;
      let dh = maxH;
      if (ar > 1) dh = dw / ar; else dw = dh * ar;
      ctx.drawImage(img, prl.x, prl.y, dw, dh);
    }
  }

  // QR — minimal mode draws a white pad then the QR on top.
  const qrUrl = `${publicBaseUrl}/membership/${membership.slug}`;
  const qrSrc = document.createElement('canvas');
  qrSrc.width = 200;
  qrSrc.height = 200;
  await QRCode.toCanvas(qrSrc, qrUrl, {
    width: 200,
    margin: 0,
    color: { dark: '#000000', light: '#ffffff' },
    errorCorrectionLevel: 'M',
  });
  const ql = resolveLayout(DEFAULT_LAYOUT_MINIMAL.qr, overrides?.qr);
  const qs = 190 * ql.scale;
  const pad = 14 * ql.scale;
  ctx.fillStyle = '#ffffff';
  ctx.fillRect(ql.x - pad, ql.y - pad, qs + pad * 2, qs + pad * 2);
  ctx.drawImage(qrSrc, ql.x, ql.y, qs, qs);

  // Policy number — minimal mode draws just the policy, no label.
  const fl = resolveLayout(DEFAULT_LAYOUT_MINIMAL.fields, overrides?.fields);
  const fSize = 34 * fl.scale;
  ctx.font = `bold ${fSize}px Tajawal, Arial, sans-serif`;
  ctx.fillStyle = '#1a1a2e';
  ctx.textAlign = 'center';
  ctx.direction = 'ltr';
  ctx.fillText(String(membership.membership_number), fl.x, fl.y);

  return canvas;
}
