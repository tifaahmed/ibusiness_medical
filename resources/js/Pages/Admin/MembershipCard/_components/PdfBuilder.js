import { jsPDF } from 'jspdf';
import JSZip from 'jszip';
import { loadImage, renderCardCanvas } from './cardRenderer.js';

import { PAGE_W, PAGE_H, sheetLayout, slotRect, backRect, guideShapes } from './sheetGeometry.js';

// The sheet's geometry (10 cards = 2 × 5, columns at the paper's edges, per-card position and size) lives in sheetGeometry.js,
// shared with the sheet editor page so what is edited there is exactly what is printed here.
export { sheetLayout };

// The back's spot when the sheet itself is not mirrored: the card's own footprint, back centred in it.
function backRectUnmirrored(L, pos, print) {
  const f = slotRect(L, pos, false);
  const mirrored = backRect(L, pos, print);
  return { x: f.x + (f.w - mirrored.w) / 2, y: f.y + (f.h - mirrored.h) / 2, w: mirrored.w, h: mirrored.h };
}

const BACK_FALLBACK = '/images/cards/card-backside.png';

/**
 * The pin/staple mark and the cutting guides. Drawn at the SAME spot on every page — fronts and backs — so a stack of
 * printed sheets can be pinned through one point and cut alike. The guides never touch a card: row borders edge to edge,
 * and the column divider(s) as ONE unbroken line from the top edge to the bottom edge.
 */
function drawPin(pdf, L, mirror = false) {
  const g = guideShapes(L, mirror);
  pdf.setDrawColor(150);
  pdf.setLineWidth(0.02);
  pdf.circle(g.pin.x, g.pin.y, g.pin.r, 'S');
  pdf.line(g.pin.x - g.pin.r * 0.6, g.pin.y, g.pin.x + g.pin.r * 0.6, g.pin.y);
  pdf.line(g.pin.x, g.pin.y - g.pin.r * 0.6, g.pin.x, g.pin.y + g.pin.r * 0.6);

  if (!L.guides) return;
  pdf.setDrawColor(200);
  pdf.setLineWidth(0.012);
  g.hLines.forEach((y) => pdf.line(0, y, PAGE_W, y));
  g.vLines.forEach((x) => pdf.line(x, 0, x, PAGE_H));
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
  const mirror = mode === 'mirror';
  // 'fronts': only the fronts of the chosen cards, no back sheets.
  const frontsOnly = mode === 'fronts';
  const withFronts = mode === 'both' || mirror || frontsOnly;
  const back = frontsOnly ? null : await backDataUrl(template, mirror);
  const pdf = new jsPDF({ unit: 'cm', format: 'a4', orientation: 'portrait' });
  const total = memberships.length;
  const steps = frontsOnly ? total : total * 2;
  const pages = Math.ceil(total / L.perPage);
  let first = true;
  // Every sheet gets the pin mark in the same place; the first one exists already, later ones are added below.
  // `flipped`: this page is turned over (a back sheet, or the front page of the 'mirror' mode) so its pin and column lines are mirrored too.
  const newPage = (flipped = false) => { if (!first) pdf.addPage(); first = false; drawPin(pdf, L, flipped); };

  for (let page = 0; page < pages; page++) {
    const from = page * L.perPage;
    const count = Math.min(L.perPage, total - from);

    if (withFronts) {
      newPage(mirror);
      for (let k = 0; k < count; k++) {
        const canvas = await renderCardCanvas({
          membership: memberships[from + k],
          template,
          partner,
          overrides: overridesFor ? overridesFor(from + k) : null,
          contact,
        });
        const r = slotRect(L, k, mirror);
        const front = mirror ? flippedDataUrl(canvas, canvas.width, canvas.height) : canvas.toDataURL('image/jpeg', 0.92);
        pdf.addImage(front, 'JPEG', r.x, r.y, r.w, r.h, undefined, 'FAST');
        if (onProgress) onProgress((from + k + 1) / steps);
      }
    }

    if (frontsOnly) continue;
    newPage(!mirror);
    for (let k = 0; k < count; k++) {
      // The back sits exactly behind its front, at its own (smaller) printed size, centred in the card's footprint.
      // (In 'mirror' mode the page is the true mirror image, so the back is placed where the unmirrored sheet would have it.)
      const b = mirror ? backRectUnmirrored(L, k, print) : backRect(L, k, print);
      pdf.addImage(back, 'JPEG', b.x, b.y, b.w, b.h, undefined, 'FAST');
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
    if (posOnPage === 0) drawPin(batchPdf, L);
    const r = slotRect(L, posOnPage, false);
    batchPdf.addImage(dataUrl, 'JPEG', r.x, r.y, r.w, r.h, undefined, 'FAST');

    // ---- Single-card PDF for the zip ----
    // The single-card PDF has the card's own printed size (its slot's, when the sheet editor gave that card another one).
    const single = new jsPDF({ unit: 'cm', format: [r.w, r.h], orientation: 'landscape' });
    single.addImage(dataUrl, 'JPEG', 0, 0, r.w, r.h, undefined, 'FAST');
    const rawName = String(memberships[i].membership_number ?? `card-${i + 1}`);
    const safeName = rawName.replace(/[^A-Za-z0-9_-]+/g, '_') || `card-${i + 1}`;
    zip.file(`${safeName}.pdf`, single.output('blob'));

    if (onProgress) onProgress((i + 1) / memberships.length);
  }

  const zipBlob = await zip.generateAsync({ type: 'blob' });
  return { batchPdf: batchPdf.output('blob'), zip: zipBlob };
}
