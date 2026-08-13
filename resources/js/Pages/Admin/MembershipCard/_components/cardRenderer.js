// Renders a single membership card from a card_templates row: the blank
// artwork (`card_empty`) plus a `layout` that says where every generated field
// lands on it, and `sample_data` holding the values that are fixed for the
// whole template (the contact lines).
//
// Layout units mirror App\Support\CardTemplateLayoutDefaults:
//   · x/y/width/height are fractions of the card (0..1)
//   · font_size is pixels on an EDITOR_WIDTH-wide canvas, rescaled here
//   · direction doubles as alignment — ltr | rtl | center
//
// With no template passed, FALLBACK_LAYOUT below keeps the renderer working
// standalone; it is a copy of the PHP defaults.

import QRCode from 'qrcode';
import { publicMembershipBaseUrl, withSlugQuery } from '@/composables/usePublicMembershipUrl.js';
import { drawBarcode } from './code128.js';

/** Export width in px. Height follows the artwork's own aspect ratio. */
export const CARD_W = 1579;
export const CARD_H = 996;

/** Must match CardTemplateLayoutDefaults::EDITOR_WIDTH. */
const EDITOR_WIDTH = 700;

const BG_VERSION = '20260810a';
const FALLBACK_TEMPLATE_IMAGE = `/images/cards/deilar-card-blank.png?v=${BG_VERSION}`;
const FONTS_HREF = 'https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap';

const INK = '#000000';
const QR_FRAME_COLOR = '#0a1128';
const QR_FRAME_BORDER = 5 / CARD_W; // as a fraction, so it scales with the card
const QR_QUIET_ZONE = 22 / 281; // fraction of the QR box left white around the modules
const QR_CORNER_RADIUS = 18 / 281;

const TEXT_FIELDS = ['slogan', 'membership_number', 'facebook', 'website', 'phone'];
const IMAGE_FIELDS = ['logo', 'partner_logo'];

/** Mirrors CardTemplateLayoutDefaults::layout() — used when no template is given. */
export const FALLBACK_LAYOUT = {
  logo: { x: 0.1102, y: 0.1275, width: 0.2299, height: 0.2339 },
  slogan: {
    x: 0.1096, y: 0.507, width: 0.2267, height: 0.0512,
    font_family: 'Tajawal', direction: 'center', color: '#0b1632', font_size: 22,
  },
  membership_number: {
    x: 0.5212, y: 0.4468, width: 0.2464, height: 0.0231,
    font_family: 'Tajawal', direction: 'center', color: INK, font_size: 10.6,
  },
  facebook: {
    x: 0.468, y: 0.6702, width: 0.494, height: 0.0382,
    font_family: 'Tajawal', direction: 'ltr', color: INK, font_size: 16.8,
  },
  website: {
    x: 0.468, y: 0.7626, width: 0.494, height: 0.0382,
    font_family: 'Tajawal', direction: 'ltr', color: INK, font_size: 16.8,
  },
  phone: {
    x: 0.468, y: 0.8544, width: 0.494, height: 0.0382,
    font_family: 'Tajawal', direction: 'ltr', color: INK, font_size: 16.8,
  },
  partner_logo: { x: 0.1393, y: 0.6175, width: 0.1875, height: 0.2359 },
  qrcode: { x: 0.5516, y: 0.0392, width: 0.171, height: 0.2741 },
  barcode: { x: 0.5212, y: 0.3394, width: 0.2464, height: 0.1054 },
};

export const FALLBACK_SAMPLE_DATA = {
  logo: '/images/logo/dielar.png',
  slogan: 'صحتك واكتر',
  facebook: 'www.facebook.com/IEasybusiness',
  website: 'www.deilar.com',
  phone: '01020709993',
};

/**
 * The per-batch override panel edits absolute pixels on the exported card, and
 * groups the three contact rows under one key. This maps its keys onto layout
 * field keys.
 */
export const OVERRIDE_TARGETS = {
  qr: ['qrcode'],
  barcode: ['barcode'],
  partner: ['partner_logo'],
  contact: ['facebook', 'website', 'phone'],
};

/**
 * Where the per-batch override panel starts for a given design: each element's
 * anchor field taken from that design's own layout and resolved to pixels, in
 * the {x, y, scale} shape the panel and the stored `layout_overrides` use.
 *
 * A batch is cut from a design, so it has to start where the design puts
 * things. Seeding the panel from the bundled fallback instead is what made
 * batch cards print differently from every other card drawn from the same
 * template.
 */
export function overridePanelDefaults(template) {
  const layout = template?.layout || FALLBACK_LAYOUT;
  const defaults = {};

  for (const [key, targets] of Object.entries(OVERRIDE_TARGETS)) {
    const field = layout[targets[0]] || FALLBACK_LAYOUT[targets[0]];
    defaults[key] = {
      x: Math.round(Number(field.x) * CARD_W),
      y: Math.round(Number(field.y) * CARD_H),
      scale: 1,
    };
  }

  return defaults;
}

/** The contact rows a design prints, for the panel to start from. */
export function templateContactText(template) {
  const data = { ...FALLBACK_SAMPLE_DATA, ...(template?.sample_data || {}) };

  return {
    facebook: data.facebook ?? '',
    website: data.website ?? '',
    phone: data.phone ?? '',
  };
}

let _fontPromise = null;
const _imageCache = new Map();

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

/**
 * Stored image paths are host-less ('/images/logo/dielar.png'). Rows written
 * before the upload path added the leading slash hold a bare
 * 'storage/card-templates/…', which a browser would resolve against the current
 * admin URL and 404 — so root them here, mirroring the model's `*_url`
 * accessors. Absolute URLs, data: and blob: previews pass through untouched.
 */
export function assetUrl(src) {
  if (!src) return src;
  return /^([a-z]+:|\/\/|\/)/i.test(src) ? src : `/${src}`;
}

function loadImage(src) {
  const url = assetUrl(src);
  if (!url) return Promise.resolve(null);
  if (_imageCache.has(url)) return _imageCache.get(url);
  const p = new Promise((resolve) => {
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = () => resolve(img);
    img.onerror = () => resolve(null);
    img.src = url;
  });
  _imageCache.set(url, p);
  return p;
}

/**
 * Overrides saved before the card moved onto the Deilar artwork were measured
 * on a 1063 × 650 canvas and carried a `fields` element that no longer exists,
 * so their numbers mean nothing here — fall back to the template's own layout.
 */
function isLegacyOverrides(o) {
  if (!o || typeof o !== 'object') return false;
  return 'fields' in o || !('barcode' in o || 'contact' in o);
}

/**
 * Resolve a template's fractional layout into absolute pixels, drop whatever
 * the template's status hides, then apply the per-batch overrides on top.
 */
function resolveFields(template, overrides, width, height) {
  const layout = template?.layout || FALLBACK_LAYOUT;
  const hidden = new Set(template?.hidden_fields || []);

  const fields = {};
  for (const [key, f] of Object.entries(layout)) {
    if (hidden.has(key) || !f) continue;
    fields[key] = {
      left: Number(f.x) * width,
      top: Number(f.y) * height,
      width: Number(f.width) * width,
      height: Number(f.height) * height,
      fontSize: (Number(f.font_size) || 14) * (width / EDITOR_WIDTH),
      fontFamily: f.font_family || 'Tajawal',
      color: f.color || INK,
      direction: f.direction || 'ltr',
    };
  }

  if (!overrides) return fields;

  for (const [overrideKey, targets] of Object.entries(OVERRIDE_TARGETS)) {
    const ov = overrides[overrideKey];
    if (!ov) continue;

    const scale = Number.isFinite(Number(ov.scale)) ? Number(ov.scale) : 1;
    const x = Number(ov.x);
    const y = Number(ov.y);
    // The contact rows move as a group: the panel's y positions the first row
    // and the others keep their spacing from it.
    const anchor = fields[targets[0]];
    const dx = Number.isFinite(x) && anchor ? x - anchor.left : 0;
    const dy = Number.isFinite(y) && anchor ? y - anchor.top : 0;

    for (const key of targets) {
      const field = fields[key];
      if (!field) continue;
      fields[key] = {
        ...field,
        left: field.left + dx,
        top: field.top + dy,
        width: field.width * scale,
        height: field.height * scale,
        fontSize: field.fontSize * scale,
      };
    }
  }

  return fields;
}

/**
 * The pixel box of every field a render would draw, at the given canvas size.
 * Same resolution the renderer itself uses, exposed so an editor can draw
 * handles over the canvas without re-deriving the maths.
 *
 * @returns {Record<string, {left:number, top:number, width:number, height:number}>}
 */
export function resolveFieldBoxes(template, overrides, width, height) {
  return resolveFields(template, isLegacyOverrides(overrides) ? null : overrides, width, height);
}

/**
 * The canvas size a render of this template lands on — the artwork's own
 * proportions at CARD_W wide. Lets an editor place boxes before the first paint.
 */
export async function measureCardSize(template) {
  const bg = await loadImage(template?.card_empty_url || FALLBACK_TEMPLATE_IMAGE);
  return {
    width: CARD_W,
    height: bg && bg.width && bg.height ? Math.round(CARD_W / (bg.width / bg.height)) : CARD_H,
  };
}

function roundedRectPath(ctx, x, y, w, h, r) {
  const radius = Math.min(r, w / 2, h / 2);
  ctx.beginPath();
  ctx.moveTo(x + radius, y);
  ctx.arcTo(x + w, y, x + w, y + h, radius);
  ctx.arcTo(x + w, y + h, x, y + h, radius);
  ctx.arcTo(x, y + h, x, y, radius);
  ctx.arcTo(x, y, x + w, y, radius);
  ctx.closePath();
}

/**
 * Draw a text field. `direction` sets both the writing direction and the
 * horizontal alignment inside the box; the text is centred vertically in the
 * box, matching the flex centring the server-side renderer uses.
 */
function drawTextField(ctx, field, text) {
  if (!text) return;

  const maxWidth = field.width;
  let size = field.fontSize;
  ctx.font = `bold ${size}px ${field.fontFamily}, Arial, sans-serif`;
  const measured = ctx.measureText(text).width;
  if (measured > maxWidth && measured > 0) {
    size *= maxWidth / measured;
    ctx.font = `bold ${size}px ${field.fontFamily}, Arial, sans-serif`;
  }

  ctx.fillStyle = field.color;
  ctx.textBaseline = 'middle';
  ctx.direction = field.direction === 'rtl' ? 'rtl' : 'ltr';

  let x = field.left;
  if (field.direction === 'center') {
    ctx.textAlign = 'center';
    x = field.left + field.width / 2;
  } else if (field.direction === 'rtl') {
    ctx.textAlign = 'right';
    x = field.left + field.width;
  } else {
    ctx.textAlign = 'left';
  }

  ctx.fillText(text, x, field.top + field.height / 2);
}

/** Contain-fit an image inside the field box, centred. */
function drawImageField(ctx, field, img) {
  if (!img || !img.width || !img.height) return;
  const fit = Math.min(field.width / img.width, field.height / img.height);
  const w = img.width * fit;
  const h = img.height * fit;
  ctx.drawImage(img, field.left + (field.width - w) / 2, field.top + (field.height - h) / 2, w, h);
}

/**
 * What this card's QR code encodes for a membership — the public page tagged
 * with `?slug=`. Exported so a screen can show the value without guessing at
 * it: what is displayed and what is drawn come from the same call.
 */
export function cardQrValue(membership, publicBaseUrl = publicMembershipBaseUrl()) {
  const slug = membership?.slug ?? '';

  return withSlugQuery(`${publicBaseUrl}/membership/${slug}`, slug);
}

/** What this card's bars encode: the membership number, exactly as printed. */
export function cardBarcodeValue(membership) {
  return String(membership?.membership_number ?? '');
}

/**
 * Render a card to a freshly-allocated canvas, sized to CARD_W with the height
 * taken from the artwork's own aspect ratio so an uploaded `card_empty` of any
 * proportion renders undistorted.
 *
 * @param {object} opts
 * @param {{ membership_number: string, slug: string }} opts.membership
 * @param {object|null} opts.template card_templates row (layout, sample_data, card_empty_url, hidden_fields)
 * @param {object|null} opts.partner partner with an `image` url
 * @param {string} opts.publicBaseUrl base for the QR code URL — defaults to the
 *   Deilar marketing site, which is where a scanned card should land
 * @param {object|null} opts.overrides per-batch { x, y, scale } overrides
 * @param {object|null} opts.contact { facebook, website, phone } text overrides
 * @param {boolean} opts.placeholderPartnerLogo draw the template's own
 *   partner_logo when there is no partner at all — for the layout editor, which
 *   previews the design rather than a real member's card
 * @param {object|null} opts.values explicit field values that win over
 *   everything, including the membership-derived number, barcode and QR — used
 *   by the single-card generator, where an admin types each field by hand
 * @returns {Promise<HTMLCanvasElement>}
 */
export async function renderCardCanvas({
  membership,
  template = null,
  partner = null,
  publicBaseUrl = publicMembershipBaseUrl(),
  overrides = null,
  contact = null,
  placeholderPartnerLogo = false,
  values: valueOverrides = null,
}) {
  await ensureFontLoaded();

  const bg = await loadImage(template?.card_empty_url || FALLBACK_TEMPLATE_IMAGE);

  const width = CARD_W;
  const height = bg && bg.width && bg.height
    ? Math.round(width / (bg.width / bg.height))
    : CARD_H;

  const canvas = document.createElement('canvas');
  canvas.width = width;
  canvas.height = height;
  const ctx = canvas.getContext('2d');

  if (bg) {
    ctx.drawImage(bg, 0, 0, width, height);
  } else {
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, width, height);
  }

  const ov = isLegacyOverrides(overrides) ? null : overrides;
  const fields = resolveFields(template, ov, width, height);

  // Values: per-member fields come from the membership, the rest are the
  // template's own sample_data (overridable per batch).
  const number = String(membership?.membership_number ?? '');
  const values = {
    ...FALLBACK_SAMPLE_DATA,
    ...(template?.sample_data || {}),
    ...(contact || {}),
    membership_number: number,
    barcode: cardBarcodeValue(membership),
    qrcode: cardQrValue(membership, publicBaseUrl),
    // Only real values win — a blank one falls through to what the template
    // already carries, so an empty box never blanks a field it did not mean to.
    ...Object.fromEntries(Object.entries(valueOverrides || {}).filter(([, v]) => v !== '' && v != null)),
  };

  // The partner's own logo wins. The template's sample_data placeholder stands
  // in for a partner that has none, and for the layout editor's preview — but
  // never for a card with no partner at all, which prints no logo.
  values.partner_logo = partner?.image
    || ((partner || placeholderPartnerLogo) ? values.partner_logo : null);

  // --- Images (partner logo) — drawn first so codes and text sit above.
  for (const key of IMAGE_FIELDS) {
    const field = fields[key];
    if (!field || !values[key]) continue;
    drawImageField(ctx, field, await loadImage(values[key]));
  }

  // --- QR code, inside the white rounded frame the reference card uses.
  const qr = fields.qrcode;
  if (qr && values.qrcode) {
    const qrSrc = document.createElement('canvas');
    await QRCode.toCanvas(qrSrc, values.qrcode, {
      width: 512,
      margin: 0,
      color: { dark: template?.sample_data?.qrcode_color || INK, light: '#ffffff' },
      errorCorrectionLevel: 'M',
    });

    const box = Math.min(qr.width, qr.height);
    const inset = box * QR_QUIET_ZONE;

    ctx.save();
    roundedRectPath(ctx, qr.left, qr.top, qr.width, qr.height, box * QR_CORNER_RADIUS);
    ctx.fillStyle = '#ffffff';
    ctx.fill();
    ctx.lineWidth = QR_FRAME_BORDER * width;
    ctx.strokeStyle = QR_FRAME_COLOR;
    ctx.stroke();
    ctx.restore();

    ctx.drawImage(qrSrc, qr.left + inset, qr.top + inset, qr.width - inset * 2, qr.height - inset * 2);
  }

  // --- Barcode: the bars fill their box; the readable number is its own field.
  const bars = fields.barcode;
  if (bars && values.barcode) {
    drawBarcode(ctx, values.barcode, {
      x: bars.left,
      y: bars.top,
      width: bars.width,
      height: bars.height,
      color: INK,
    });
  }

  // --- Text fields.
  for (const key of TEXT_FIELDS) {
    const field = fields[key];
    if (!field) continue;
    drawTextField(ctx, field, values[key]);
  }

  return canvas;
}
