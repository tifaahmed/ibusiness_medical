import { jsPDF } from 'jspdf';
import JSZip from 'jszip';
import { renderCardCanvas } from './cardRenderer.js';

// A4 portrait dimensions in cm.
const PAGE_W = 21.0;
const PAGE_H = 29.7;
const CARD_W = 9.0;
const CARD_H = 5.6;
const COLS = 2;
const ROWS = 5;
const CARDS_PER_PAGE = COLS * ROWS;
// White-space gutters between cards. Vertical room on A4 is tight
// (5 × 5.6 = 28cm of 29.7cm), so the vertical gap stays small.
const GAP_X = 0.5; // gap between the two columns
const GAP_Y = 0.3; // gap between rows
const MARGIN_X = (PAGE_W - COLS * CARD_W - (COLS - 1) * GAP_X) / 2; // 1.25cm
const MARGIN_Y = (PAGE_H - ROWS * CARD_H - (ROWS - 1) * GAP_Y) / 2; // 0.25cm

/**
 * Render every membership once and produce BOTH:
 *  - a multi-page A4 PDF with 10 cards per page (with gutters), and
 *  - a zip of single-card PDFs (one PDF per card, sized 9×5.6 cm).
 *
 * Each card is rasterised exactly once and shared between the two outputs.
 *
 * @param {Array<{ membership_number: string, slug: string }>} memberships
 * @param {object|null} partner
 * @param {(progress: number) => void} [onProgress]
 * @param {(idx0: number) => object|null} [overridesFor]
 * @returns {Promise<{ batchPdf: Blob, zip: Blob }>}
 */
export async function buildBatchAndZip(memberships, partner, onProgress, overridesFor = null) {
  if (!memberships.length) {
    throw new Error('No memberships to render');
  }

  const batchPdf = new jsPDF({ unit: 'cm', format: 'a4', orientation: 'portrait' });
  const zip = new JSZip();

  for (let i = 0; i < memberships.length; i++) {
    const canvas = await renderCardCanvas({
      membership: memberships[i],
      partner,
      overrides: overridesFor ? overridesFor(i) : null,
    });
    // JPEG at 0.92 keeps file size reasonable while staying visually crisp
    // at the print size we land on.
    const dataUrl = canvas.toDataURL('image/jpeg', 0.92);

    // ---- Batch PDF placement (10 per A4 page) ----
    const posOnPage = i % CARDS_PER_PAGE;
    if (i > 0 && posOnPage === 0) batchPdf.addPage();
    const col = posOnPage % COLS;
    const row = Math.floor(posOnPage / COLS);
    const x = MARGIN_X + col * (CARD_W + GAP_X);
    const y = MARGIN_Y + row * (CARD_H + GAP_Y);
    batchPdf.addImage(dataUrl, 'JPEG', x, y, CARD_W, CARD_H, undefined, 'FAST');

    // ---- Single-card PDF for the zip ----
    const single = new jsPDF({ unit: 'cm', format: [CARD_W, CARD_H], orientation: 'landscape' });
    single.addImage(dataUrl, 'JPEG', 0, 0, CARD_W, CARD_H, undefined, 'FAST');
    const rawName = String(memberships[i].membership_number ?? `card-${i + 1}`);
    const safeName = rawName.replace(/[^A-Za-z0-9_-]+/g, '_') || `card-${i + 1}`;
    zip.file(`${safeName}.pdf`, single.output('blob'));

    if (onProgress) onProgress((i + 1) / memberships.length);
  }

  const zipBlob = await zip.generateAsync({ type: 'blob' });
  return { batchPdf: batchPdf.output('blob'), zip: zipBlob };
}
