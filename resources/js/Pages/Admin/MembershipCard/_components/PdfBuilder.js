import { jsPDF } from 'jspdf';
import JSZip from 'jszip';
import { loadImage, renderCardCanvas } from './cardRenderer.js';

// A4 portrait dimensions in cm.
const PAGE_W = 21.0;
const PAGE_H = 29.7;
// White-space gutters between cards.
const GAP_X = 0.5; // gap between columns
const GAP_Y = 0.3; // gap between rows
const DEFAULT_PRINT = { width: 8.5, height: 5.5, back_width: 8.0, back_height: 5.0 };
const BACK_FALLBACK = '/images/cards/card-backside.png';

/**
 * Sheet geometry for a card size taken from the project settings (CardPrint):
 * as many columns/rows as fit on A4, centred.
 */
export function sheetLayout(print) {
  const w = Number(print?.width) > 0 ? Number(print.width) : DEFAULT_PRINT.width;
  const h = Number(print?.height) > 0 ? Number(print.height) : DEFAULT_PRINT.height;
  const cols = Math.max(1, Math.floor((PAGE_W + GAP_X) / (w + GAP_X)));
  const rows = Math.max(1, Math.floor((PAGE_H + GAP_Y) / (h + GAP_Y)));
  return {
    w,
    h,
    cols,
    rows,
    perPage: cols * rows,
    marginX: (PAGE_W - cols * w - (cols - 1) * GAP_X) / 2,
    marginY: (PAGE_H - rows * h - (rows - 1) * GAP_Y) / 2,
  };
}

function slotXY(L, pos, mirror) {
  let col = pos % L.cols;
  const row = Math.floor(pos / L.cols);
  // Flipped: the columns run right-to-left, so a sheet printed on its back
  // lines every back up behind its own front.
  if (mirror) col = L.cols - 1 - col;
  return { x: L.marginX + col * (L.w + GAP_X), y: L.marginY + row * (L.h + GAP_Y) };
}

// The same picture turned left-to-right, as a JPEG data URL.
function flippedDataUrl(source, w, h) {
  const c = document.createElement('canvas');
  c.width = w;
  c.height = h;
  const ctx = c.getContext('2d');
  ctx.translate(w, 0);
  ctx.scale(-1, 1);
  ctx.drawImage(source, 0, 0, w, h);
  return c.toDataURL('image/jpeg', 0.92);
}

async function backDataUrl(template, mirrorImage = false) {
  const img = await loadImage(template?.back_url || BACK_FALLBACK);
  if (!img) throw new Error('The card back image could not be loaded.');
  const c = document.createElement('canvas');
  c.width = img.naturalWidth;
  c.height = img.naturalHeight;
  if (mirrorImage) return flippedDataUrl(img, c.width, c.height);
  c.getContext('2d').drawImage(img, 0, 0);
  return c.toDataURL('image/jpeg', 0.92);
}

/**
 * Backs only, or fronts + backs, for a whole batch.
 *  - mode 'flipped': one sheet of backs per sheet of fronts, columns mirrored.
 *  - mode 'mirror':  like 'both', but every page is the true mirror image of the
 *                    sheet (artwork turned left-to-right too, columns reversed).
 *  - mode 'both':    sheet of fronts, then the matching mirrored sheet of backs
 *                    (print double-sided, flip on the long edge).
 * The back is drawn at its own width/height (settings), centred in
 * the card's slot.
 *
 * @returns {Promise<Blob>}
 */
export async function buildBackPdf(memberships, partner, template, print, mode, onProgress, overridesFor = null, contact = null) {
  if (!memberships.length) throw new Error('No memberships to render');
  const L = sheetLayout(print);
  const bw = Math.min(L.w, Number(print?.back_width) > 0 ? Number(print.back_width) : DEFAULT_PRINT.back_width);
  const bh = Math.min(L.h, Number(print?.back_height) > 0 ? Number(print.back_height) : DEFAULT_PRINT.back_height);
  const mirror = mode === 'mirror';
  const withFronts = mode === 'both' || mirror;
  const back = await backDataUrl(template, mirror);
  const pdf = new jsPDF({ unit: 'cm', format: 'a4', orientation: 'portrait' });
  const total = memberships.length;
  const pages = Math.ceil(total / L.perPage);
  let first = true;

  for (let page = 0; page < pages; page++) {
    const from = page * L.perPage;
    const count = Math.min(L.perPage, total - from);

    if (withFronts) {
      if (!first) pdf.addPage();
      first = false;
      for (let k = 0; k < count; k++) {
        const canvas = await renderCardCanvas({
          membership: memberships[from + k],
          template,
          partner,
          overrides: overridesFor ? overridesFor(from + k) : null,
          contact,
        });
        const { x, y } = slotXY(L, k, mirror);
        const front = mirror ? flippedDataUrl(canvas, canvas.width, canvas.height) : canvas.toDataURL('image/jpeg', 0.92);
        pdf.addImage(front, 'JPEG', x, y, L.w, L.h, undefined, 'FAST');
        if (onProgress) onProgress((from + k + 1) / (total * 2));
      }
    }

    if (!first) pdf.addPage();
    first = false;
    for (let k = 0; k < count; k++) {
      const { x, y } = slotXY(L, k, !mirror);
      pdf.addImage(back, 'JPEG', x + (L.w - bw) / 2, y + (L.h - bh) / 2, bw, bh, undefined, 'FAST');
    }
    if (onProgress) onProgress(withFronts ? (from + count + total) / (total * 2) : (from + count) / total);
  }

  return pdf.output('blob');
}

/**
 * Render every membership once and produce BOTH:
 *  - a multi-page A4 PDF with as many cards per page as the card size allows (10 at 8.5×5.5 cm), and
 *  - a zip of single-card PDFs (one PDF per card, sized 8.5×5.5 cm).
 *
 * Each card is rasterised exactly once and shared between the two outputs.
 *
 * @param {Array<{ membership_number: string, slug: string }>} memberships
 * @param {object|null} partner
 * @param {object|null} template card_templates row driving the layout
 * @param {(progress: number) => void} [onProgress]
 * @param {(idx0: number) => object|null} [overridesFor]
 * @param {object|null} [contact] { facebook, website, phone } text overrides
 * @returns {Promise<{ batchPdf: Blob, zip: Blob }>}
 */
export async function buildBatchAndZip(memberships, partner, template, onProgress, overridesFor = null, contact = null, print = null) {
  if (!memberships.length) {
    throw new Error('No memberships to render');
  }

  const L = sheetLayout(print);
  const batchPdf = new jsPDF({ unit: 'cm', format: 'a4', orientation: 'portrait' });
  const zip = new JSZip();

  for (let i = 0; i < memberships.length; i++) {
    const canvas = await renderCardCanvas({
      membership: memberships[i],
      template,
      partner,
      overrides: overridesFor ? overridesFor(i) : null,
      contact,
    });
    // JPEG at 0.92 keeps file size reasonable while staying visually crisp
    // at the print size we land on.
    const dataUrl = canvas.toDataURL('image/jpeg', 0.92);

    // ---- Batch PDF placement (10 per A4 page) ----
    const posOnPage = i % L.perPage;
    if (i > 0 && posOnPage === 0) batchPdf.addPage();
    const { x, y } = slotXY(L, posOnPage, false);
    batchPdf.addImage(dataUrl, 'JPEG', x, y, L.w, L.h, undefined, 'FAST');

    // ---- Single-card PDF for the zip ----
    const single = new jsPDF({ unit: 'cm', format: [L.w, L.h], orientation: 'landscape' });
    single.addImage(dataUrl, 'JPEG', 0, 0, L.w, L.h, undefined, 'FAST');
    const rawName = String(memberships[i].membership_number ?? `card-${i + 1}`);
    const safeName = rawName.replace(/[^A-Za-z0-9_-]+/g, '_') || `card-${i + 1}`;
    zip.file(`${safeName}.pdf`, single.output('blob'));

    if (onProgress) onProgress((i + 1) / memberships.length);
  }

  const zipBlob = await zip.generateAsync({ type: 'blob' });
  return { batchPdf: batchPdf.output('blob'), zip: zipBlob };
}
